#!/bin/sh
# Synthetic key for the isolated recovery exercise, never a production key.
set -eu
key_home=${1:?Provide an unused temporary directory for the synthetic GPG key.}
case "$key_home" in /tmp/*/backup-verification-gpg) ;; *) echo 'Use a temporary /tmp/.../backup-verification-gpg directory.' >&2; exit 2;; esac
[ ! -e "$key_home" ] || { echo 'Temporary key directory already exists; do not overwrite it.' >&2; exit 2; }
mkdir -m 700 "$key_home"
gpg --homedir "$key_home" --batch --pinentry-mode loopback --passphrase '' --quick-generate-key 'Temporary shop recovery test <restore-check@example.test>' rsa2048 encrypt 1d >/dev/null
gpg --homedir "$key_home" --batch --with-colons --list-keys
