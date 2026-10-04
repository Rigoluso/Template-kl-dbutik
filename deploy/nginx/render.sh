#!/bin/sh
# Emit a complete nginx configuration. Validate inputs before replacing a file.
set -eu
export LC_ALL=C
destination=${1:?Usage: render.sh OUTPUT http|tls}
phase=${2:?Usage: render.sh OUTPUT http|tls}
mode=${SHOP_MODE:-production}
domain=${SHOP_DOMAIN:-}
alias=${SHOP_WWW_DOMAIN:-}
fail() { printf '%s\n' "$*" >&2; exit 1; }
valid_domain() {
    printf '%s\n' "$1" | awk '
    length($0) < 1 || length($0) > 253 || $0 !~ /^[a-z0-9.-]+$/ { exit 1 }
    { count = split($0, labels, "."); for (i = 1; i <= count; i++) {
        if (length(labels[i]) < 1 || length(labels[i]) > 63 || labels[i] !~ /^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/) exit 1
    }}'
}
case "$mode" in demo|production) ;; *) fail 'SHOP_MODE must be demo or production.' ;; esac
case "$phase" in http|tls) ;; *) fail 'Configuration phase must be http or tls.' ;; esac
valid_domain "$domain" || fail 'SHOP_DOMAIN must be a lowercase ASCII hostname.'
if [ -n "$alias" ]; then
    valid_domain "$alias" || fail 'SHOP_WWW_DOMAIN must be a lowercase ASCII hostname.'
    [ "$alias" != "$domain" ] || fail 'The alias must differ from the canonical domain.'
fi
if [ "$mode" = production ]; then
    case "$domain" in *.*) ;; *) fail 'Production requires a public domain with at least two labels.' ;; esac
    case "$domain" in localhost|127.*|0.0.0.0) fail 'Production requires a public domain.' ;; esac
else
    [ "$domain" = localhost ] || fail 'The local demo uses SHOP_DOMAIN=localhost.'
    [ -z "$alias" ] || fail 'The local demo does not use a domain alias.'
    [ "$phase" = http ] || fail 'TLS is configured only for production.'
fi
temporary="${destination}.new.$$"
trap 'rm -f "$temporary"' EXIT HUP INT TERM
headers() {
cat <<'EOF'
        add_header X-Content-Type-Options nosniff always;
        add_header X-Frame-Options SAMEORIGIN always;
        add_header Referrer-Policy no-referrer always;
        add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
        add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://js.stripe.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://*.stripe.com; font-src 'self' data:; connect-src 'self' https://api.stripe.com; frame-src https://js.stripe.com https://hooks.stripe.com; form-action 'self' https://checkout.stripe.com; frame-ancestors 'self'; base-uri 'self'; object-src 'none'" always;
EOF
}
proxy() {
    if [ "$mode" = demo ]; then canonical='$http_host'; else canonical="$domain"; fi
    # Single-quoted fragments below preserve nginx variables, not shell expansions.
    cat <<EOF
        set \$shop_upstream app:80;
        proxy_http_version 1.1;
        proxy_set_header Host $canonical;
        proxy_set_header X-Forwarded-Host $canonical;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$remote_addr;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Forwarded "";
        proxy_set_header Proxy "";
        proxy_set_header Connection "";
        proxy_hide_header X-Powered-By;
        proxy_hide_header Server;
        proxy_connect_timeout 10s;
        proxy_read_timeout 120s;
        proxy_send_timeout 120s;
        proxy_buffer_size 16k;
        proxy_buffers 8 16k;
        proxy_cache off;
        location = /healthz { proxy_pass http://\$shop_upstream/health.php; access_log off; }
        location = /health.php { return 404; }
        location = /xmlrpc.php { return 403; }
        location = /wp-cron.php { return 403; }
        location = /server-status { return 404; }
        location = /wp-config.php { return 404; }
        location = /wp-admin/install.php { return 404; }
        location ~ /\.(?!well-known/) { return 404; }
        location ~* ^/wp-content/uploads/.*\.(php[0-9]?|phtml|phar|cgi|pl|sh|html?|svg)(/|\$) { return 404; }
        location = /wp-login.php {
            limit_req zone=login burst=5 nodelay;
            limit_req_status 429;
            proxy_pass http://\$shop_upstream;
        }
        location / { proxy_pass http://\$shop_upstream; }
EOF
}
acme() {
cat <<'EOF'
        location ^~ /.well-known/acme-challenge/ {
            root /var/www/acme;
            default_type text/plain;
            try_files $uri =404;
        }
EOF
}
{
cat <<'EOF'
user nginx;
worker_processes auto;
pid /tmp/nginx.pid;
# Routine upstream/request errors can contain full private query strings.
# Readiness checks and application metrics carry operational diagnostics instead.
error_log /dev/stderr crit;
events { worker_connections 1024; }
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    server_tokens off;
    # Do not record query strings, referrers, cookies, credentials, or client IPs.
    log_format minimal '$request_method $uri $status $body_bytes_sent $request_time';
    access_log /dev/stdout minimal;
    resolver 127.0.0.11 valid=10s ipv6=off;
    client_max_body_size 100m;
    client_body_timeout 30s;
    send_timeout 30s;
    keepalive_timeout 30s;
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    server { listen 127.0.0.1:8081; location = /nginx-health { access_log off; return 200 'ready\n'; } }
    server { listen 80 default_server; server_name _; return 421; }
EOF
if [ "$mode" = demo ]; then
    printf '    server {\n        listen 80;\n        server_name localhost 127.0.0.1;\n'
    headers
    proxy
    printf '    }\n'
else
    printf '    server {\n        listen 80;\n        server_name %s %s;\n' "$domain" "$alias"
    headers
    acme
    if [ "$phase" = tls ]; then
        printf '        location / { return 308 https://%s$request_uri; }\n' "$domain"
    else
        printf '        location / { default_type text/plain; return 503 "HTTPS setup is pending. Check certbot logs and DNS.\\n"; }\n'
    fi
    printf '    }\n'
    if [ "$phase" = tls ]; then
        printf '    server { listen 443 ssl default_server; ssl_reject_handshake on; return 421; }\n'
        printf '    server {\n        listen 443 ssl;\n        http2 on;\n        server_name %s %s;\n' "$domain" "$alias"
        printf '        ssl_certificate /etc/letsencrypt/live/%s/fullchain.pem;\n        ssl_certificate_key /etc/letsencrypt/live/%s/privkey.pem;\n' "$domain" "$domain"
        printf '        ssl_protocols TLSv1.2 TLSv1.3;\n        ssl_session_cache shared:TLS:10m;\n        ssl_session_timeout 1d;\n        ssl_session_tickets off;\n'
        printf '        if ($host != "%s") { return 308 https://%s$request_uri; }\n' "$domain" "$domain"
        headers
        # Enabled only after nginx can load a certificate. Avoid includeSubDomains/preload.
        printf '        add_header Strict-Transport-Security "max-age=86400" always;\n'
        proxy
        printf '    }\n'
    fi
fi
printf '}\n'
} > "$temporary"
mv "$temporary" "$destination"
