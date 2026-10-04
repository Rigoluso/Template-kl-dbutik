"""Exercise deployment input validation; full HTTP behavior is in test_nginx.py."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORK = Path(os.environ.get("SHOP_TEST_WORK_DIR", ROOT.parents[1] / "work" if ROOT.parent.name == "outputs" else ROOT / "work"))
WORK.mkdir(parents=True, exist_ok=True)
BASH = shutil.which("bash") or "C:/msys64/usr/bin/bash.exe"


def command(path):
    # MSYS bash inherits Windows PATH without /usr/bin; Linux already includes it.
    return [BASH, "-c", 'PATH=/usr/bin:/bin:$PATH; export PATH; exec sh "$@"', "--", "deploy/nginx/render.sh", os.path.relpath(path, ROOT).replace("\\", "/"), "http"]


class RenderTests(unittest.TestCase):
    def render(self, **settings):
        directory = tempfile.TemporaryDirectory(dir=WORK)
        self.addCleanup(directory.cleanup)
        destination = Path(directory.name) / "shop.conf"
        env = {**os.environ, "SHOP_MODE": "demo", "SHOP_DOMAIN": "localhost", "SHOP_WWW_DOMAIN": "", **settings}
        # Relative output paths work with Linux bash and Windows MSYS bash.
        result = subprocess.run(command(destination), cwd=ROOT, env=env, capture_output=True, text=True)
        return result, destination

    def test_demo_can_render_without_certificate(self):
        result, path = self.render()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(path.is_file())

    def test_invalid_domains_do_not_emit_configuration(self):
        # Without validation, these could inject directives or broaden trusted hosts.
        for domain in ["shop.test;", "shop.test\nserver{}", "*.shop.test", "http://shop.test", "-shop.test", "shop..test", "shop.test/path", "", "a" * 64 + ".test"]:
            with self.subTest(domain=domain):
                result, path = self.render(SHOP_MODE="production", SHOP_DOMAIN=domain)
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse(path.exists())

    def test_production_requires_public_domain(self):
        result, path = self.render(SHOP_MODE="production", SHOP_DOMAIN="localhost")
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(path.exists())

    def test_unknown_mode_is_rejected(self):
        result, path = self.render(SHOP_MODE="live")
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(path.exists())

    def test_alias_is_validated_before_writing(self):
        result, path = self.render(SHOP_MODE="production", SHOP_DOMAIN="shop.test", SHOP_WWW_DOMAIN="evil.test;}")
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(path.exists())

    def test_existing_file_survives_rejected_configuration(self):
        directory = tempfile.TemporaryDirectory(dir=WORK)
        self.addCleanup(directory.cleanup)
        path = Path(directory.name) / "shop.conf"
        path.write_text("active configuration", encoding="utf-8")
        env = {**os.environ, "SHOP_MODE": "production", "SHOP_DOMAIN": "bad;host", "SHOP_WWW_DOMAIN": ""}
        result = subprocess.run(command(path), cwd=ROOT, env=env, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(path.read_text(), "active configuration")


if __name__ == "__main__":
    unittest.main()
