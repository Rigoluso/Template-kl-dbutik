"""Real Docker/NGINX HTTP tests. Requires Docker, openssl and Python 3 on Linux.

The fixture replaces only the app server, so proxy and TLS behavior execute in
the same official NGINX image and scripts used by compose.yaml. This does not
claim external ACME issuance or checkout integration.
"""
import http.client
import json
import os
from pathlib import Path
import shlex
import socket
import ssl
import subprocess
import tempfile
import time
import unittest
import uuid

ROOT = Path(__file__).resolve().parents[2]
WORK = Path(os.environ.get("SHOP_TEST_WORK_DIR", ROOT.parents[1] / "work" if ROOT.parent.name == "outputs" else ROOT / "work"))
IMAGE = "nginx:1.30.5-alpine@sha256:0985e772fb9f729e6fa0980da05fca5d9c468e870eed43071545afa9d2e27d94"
DOCKER = shlex.split(os.environ.get("DOCKER_COMMAND", "docker"))


class NginxTests(unittest.TestCase):
    @classmethod
    def docker(cls, *arguments):
        return subprocess.check_output([*DOCKER, *arguments], text=True).strip()

    @classmethod
    def setUpClass(cls):
        cls.prefix = "shop-nginx-test-" + uuid.uuid4().hex[:8]
        WORK.mkdir(parents=True, exist_ok=True)
        cls.directory = tempfile.TemporaryDirectory(dir=WORK)
        cls.work = Path(cls.directory.name)
        (cls.work / "acme/.well-known/acme-challenge").mkdir(parents=True)
        (cls.work / "certificates").mkdir()
        cls.containers = []
        try:
            cls.docker("network", "create", cls.prefix)
            cls.fixture = cls.prefix + "-app"
            cls.docker("run", "-d", "--name", cls.fixture, "--network", cls.prefix, "--network-alias", "app", "--mount", f"type=bind,src={ROOT / 'deploy/tests/fixture-nginx.conf'},dst=/fixture.conf,readonly", "--entrypoint", "nginx", IMAGE, "-c", "/fixture.conf", "-g", "daemon off;")
            cls.containers.append(cls.fixture)
            cls.demo, cls.demo_port, _ = cls.start_proxy("demo")
            cls.production, cls.http_port, cls.tls_port = cls.start_proxy("production")
        except Exception:
            cls.tearDownClass()
            raise

    @classmethod
    def start_proxy(cls, mode):
        name = cls.prefix + "-" + mode
        cls.docker("run", "-d", "--name", name, "--network", cls.prefix, "--read-only", "--tmpfs", "/tmp", "--tmpfs", "/var/cache/nginx", "-p", "127.0.0.1::80", "-p", "127.0.0.1::443", "-e", "SHOP_MODE=" + mode, "-e", "SHOP_DOMAIN=" + ("localhost" if mode == "demo" else "shop.test"), "-e", "SHOP_WWW_DOMAIN=" + ("" if mode == "demo" else "www.shop.test"), "--mount", f"type=bind,src={ROOT / 'deploy/nginx'},dst=/opt/shop-nginx,readonly", "--mount", f"type=bind,src={cls.work / 'acme'},dst=/var/www/acme,readonly", "--mount", f"type=bind,src={cls.work / 'certificates'},dst=/etc/letsencrypt,readonly", "--entrypoint", "/bin/sh", IMAGE, "/opt/shop-nginx/entrypoint.sh")
        cls.containers.append(name)
        http_port = int(cls.docker("port", name, "80/tcp").split(":")[-1])
        tls_port = int(cls.docker("port", name, "443/tcp").split(":")[-1])
        for _ in range(100):
            try:
                status, _, _ = cls.request(http_port, "/", "localhost" if mode == "demo" else "shop.test")
                if status in (200, 503):
                    return name, http_port, tls_port
            except OSError:
                pass
            if cls.docker("inspect", "--format", "{{.State.Running}}", name) != "true":
                raise AssertionError(cls.docker("logs", name))
            time.sleep(0.1)
        raise AssertionError("NGINX did not become ready: " + cls.docker("logs", name))

    @classmethod
    def tearDownClass(cls):
        for name in getattr(cls, "containers", []):
            subprocess.run([*DOCKER, "rm", "-f", name], capture_output=True)
        subprocess.run([*DOCKER, "network", "rm", cls.prefix], capture_output=True)
        if hasattr(cls, "directory"):
            cls.directory.cleanup()

    @staticmethod
    def request(port, path, host, headers=None, tls=False, sni=None):
        connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
        if tls:
            connection.sock = ssl._create_unverified_context().wrap_socket(socket.create_connection(("127.0.0.1", port), timeout=5), server_hostname=sni or host)
        connection.request("GET", path, headers={"Host": host, **(headers or {})})
        response = connection.getresponse()
        result = response.status, dict(response.getheaders()), response.read().decode()
        connection.close()
        return result

    def test_demo_proxies_and_overwrites_untrusted_headers(self):
        status, headers, body = self.request(self.demo_port, "/", "localhost:18080", {"X-Forwarded-For": "1.2.3.4", "X-Forwarded-Proto": "https", "X-Forwarded-Host": "evil.test", "X-Real-IP": "1.2.3.4", "Forwarded": "for=1.2.3.4;proto=https;host=evil.test", "Proxy": "http://evil.test"})
        self.assertEqual(status, 200)
        data = json.loads(body)
        self.assertEqual(data["host"], "localhost:18080")
        self.assertEqual(data["forwarded_host"], "localhost:18080")
        self.assertEqual(data["forwarded_proto"], "http")
        self.assertNotEqual(data["forwarded_for"], "1.2.3.4")
        self.assertEqual(data["forwarded_for"], data["real_ip"])
        self.assertEqual(data["forwarded"], "")
        self.assertEqual(data["proxy"], "")
        self.assertEqual(headers["X-Content-Type-Options"], "nosniff")
        self.assertEqual(headers["Referrer-Policy"], "no-referrer")
        self.assertNotIn("Strict-Transport-Security", headers)

    def test_unknown_hosts_are_rejected(self):
        self.assertEqual(self.request(self.demo_port, "/", "evil.test")[0], 421)
        self.assertEqual(self.request(self.http_port, "/", "evil.test")[0], 421)

    def test_health_path_and_upload_execution(self):
        self.assertEqual(self.request(self.demo_port, "/healthz", "localhost")[0], 200)
        for path in ("/health.php", "/wp-content/uploads/evil.php", "/wp-content/uploads/evil.phtml/path", "/wp-content/uploads/evil.svg", "/.env", "/wp-config.php", "/wp-admin/install.php", "/server-status"):
            with self.subTest(path=path):
                self.assertEqual(self.request(self.demo_port, path, "localhost")[0], 404)
        self.assertEqual(self.request(self.demo_port, "/wp-cron.php", "localhost")[0], 403)

    def test_login_is_rate_limited(self):
        statuses = [self.request(self.demo_port, "/wp-login.php", "localhost")[0] for _ in range(12)]
        self.assertIn(200, statuses)
        self.assertIn(429, statuses)

    def test_private_query_values_are_not_logged(self):
        self.request(self.demo_port, "/?guest_token=must-not-appear-in-logs", "localhost")
        self.request(self.demo_port, "/wp-login.php?guest_token=must-not-appear-in-logs", "localhost")
        self.assertNotIn("must-not-appear-in-logs", self.docker("logs", self.demo))

    def test_production_first_start_only_exposes_acme(self):
        for path in ("/", "/wp-login.php", "/wp-admin/", "/checkout"):
            self.assertEqual(self.request(self.http_port, path, "shop.test")[0], 503)
        token = self.work / "acme/.well-known/acme-challenge/probe"
        token.write_text("challenge-proof", encoding="ascii")
        status, _, body = self.request(self.http_port, "/.well-known/acme-challenge/probe", "shop.test")
        self.assertEqual(status, 200)
        self.assertEqual(body, "challenge-proof")

    def test_z_certificate_install_and_renewal_reload(self):
        # A self-signed fixture verifies NGINX promotion/reload, not an ACME issuer.
        destination = self.work / "certificates/live/shop.test"
        destination.mkdir(parents=True)

        def certificate(serial):
            subprocess.run(["openssl", "req", "-x509", "-nodes", "-newkey", "rsa:2048", "-days", "2", "-set_serial", str(serial), "-subj", "/CN=shop.test", "-addext", "subjectAltName=DNS:shop.test,DNS:www.shop.test", "-keyout", str(destination / "privkey.pem"), "-out", str(destination / "fullchain.pem")], check=True, capture_output=True)

        def wait_tls():
            deadline = time.monotonic() + 45
            while time.monotonic() < deadline:
                try:
                    if self.request(self.tls_port, "/", "shop.test", tls=True)[0] == 200:
                        return
                except OSError:
                    pass
                time.sleep(0.25)
            self.fail("TLS promotion did not occur: " + self.docker("logs", self.production))

        def peer_certificate():
            with socket.create_connection(("127.0.0.1", self.tls_port), timeout=5) as raw:
                with ssl._create_unverified_context().wrap_socket(raw, server_hostname="shop.test") as connection:
                    return connection.getpeercert(binary_form=True)

        certificate(101)
        wait_tls()
        first = peer_certificate()
        status, headers, body = self.request(self.tls_port, "/", "shop.test", {"X-Forwarded-Proto": "http"}, tls=True)
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(body)["forwarded_proto"], "https")
        self.assertEqual(headers["Strict-Transport-Security"], "max-age=86400")
        for port, tls, host in [(self.http_port, False, "shop.test"), (self.tls_port, True, "www.shop.test")]:
            status, headers, _ = self.request(port, "/product?size=M", host, tls=tls)
            self.assertEqual(status, 308)
            self.assertEqual(headers["Location"], "https://shop.test/product?size=M")
        with self.assertRaises(ssl.SSLError):
            self.request(self.tls_port, "/", "evil.test", tls=True)
        self.assertEqual(self.request(self.tls_port, "/", "evil.test", tls=True, sni="shop.test")[0], 421)
        certificate(202)
        deadline = time.monotonic() + 45
        while time.monotonic() < deadline and peer_certificate() == first:
            time.sleep(0.25)
        self.assertNotEqual(peer_certificate(), first, "A renewed certificate was not reloaded.")


if __name__ == "__main__":
    unittest.main(verbosity=2)
