"""Ensure private query values never reach either proxy or app access logs."""
import os
import shlex
import subprocess
import time
from urllib.request import urlopen

command = shlex.split(os.environ.get('DOCKER_COMMAND', 'docker'))
project = os.environ.get('SHOP_TEST_PROJECT', 'shopverify')
base = os.environ.get('SHOP_TEST_URL', 'http://localhost:18080')
canary = 'private-query-canary-6cf5a11d'
with urlopen(base + '/?guest_token=' + canary, timeout=20) as response:
    if response.status != 200:
        raise AssertionError('Cannot exercise the real application access log')
time.sleep(0.5)
for service in ('app', 'nginx'):
    result = subprocess.run([*command, 'compose', '--project-name', project, '-f', 'compose.yaml', '-f', 'compose.dev.yaml', 'logs', '--no-color', '--tail', '100', service], text=True, capture_output=True, check=True)
    if canary in result.stdout or canary in result.stderr:
        raise AssertionError(f'{service} logs private query parameters')
print('App and NGINX access logs exclude private query values.')
