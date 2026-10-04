"""Check actual container mounts and image content, including after recreation."""
import hashlib
import json
import os
from pathlib import Path
import shlex
import subprocess

docker=shlex.split(os.environ.get('DOCKER_COMMAND','docker'))
project=os.environ.get('SHOP_TEST_PROJECT','shopverify')
compose=[*docker,'compose','--project-name',project,'-f','compose.yaml','-f','compose.dev.yaml']

def run(args):
    return subprocess.run(args,text=True,capture_output=True,check=True).stdout.strip()

for service in ('app','worker'):
    identity=run([*compose,'ps','-q',service])
    state=json.loads(run([*docker,'inspect',identity]))[0]
    assert state['HostConfig']['ReadonlyRootfs'], f'{service}: application filesystem is writable'
    for mount in state['Mounts']:
        destination=mount['Destination'].rstrip('/')
        assert destination != '/var/www/shop' and not '/var/www/shop'.startswith(destination+'/'), f'{service}: image code is shadowed by a mount'
        if destination.startswith('/var/www/shop/'):
            assert destination == '/var/www/shop/wp-content/uploads', f'{service}: unexpected code volume'

files={
    'app/bootstrap.php':'/opt/shop/bootstrap.php',
    'app/theme/functions.php':'/var/www/shop/wp-content/themes/kladbutik/functions.php',
    'app/plugin/shop-template.php':'/var/www/shop/wp-content/mu-plugins/shop-template.php',
    'docker/health.php':'/var/www/shop/health.php',
}
for source,destination in files.items():
    expected=hashlib.sha256(Path(source).read_bytes()).hexdigest()
    actual=run([*compose,'exec','-T','app','sha256sum',destination]).split()[0]
    assert actual == expected, f'Running container retains stale code: {source}'
run([*compose,'exec','-T','--user','www-data','app','php','-r',
     'if (!is_readable("/var/www/shop/wp-config.php") || !file_exists("/var/www/shop/wp-config.php")) exit(1);'])
print('Image code is read-only, unshadowed, current, and its runtime configuration is readable by Apache.')
