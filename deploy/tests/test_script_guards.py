"""Safety failures execute the real scripts before any Docker mutation."""
import os
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
BASH = shutil.which("bash") or "C:/msys64/usr/bin/bash.exe"


class ScriptGuardTests(unittest.TestCase):
    def run_script(self, script, *args):
        env = {**os.environ, "BACKUP_GPG_RECIPIENT": "", "DOCKER": "/nonexistent-docker"}
        return subprocess.run([BASH, "-c", 'PATH=/usr/bin:/bin:$PATH; export PATH; exec bash "$@"', "--", f"scripts/{script}", *args], cwd=ROOT, env=env, capture_output=True, text=True)

    def test_backup_refuses_unencrypted_output_without_recipient(self):
        result = self.run_script("backup.sh")
        self.assertEqual(result.returncode, 2, result.stderr)

    def test_restore_requires_explicit_replace_flag(self):
        result = self.run_script("restore.sh", "missing.tar.gz.gpg")
        self.assertEqual(result.returncode, 2, result.stderr)


if __name__ == "__main__":
    unittest.main()
