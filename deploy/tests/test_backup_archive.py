import importlib.util
import io
from pathlib import Path
import tarfile
import tempfile
import unittest

HELPER = Path(__file__).resolve().parents[1] / "backup_archive.py"


class BackupArchiveTests(unittest.TestCase):
    def load_helper(self):
        self.assertTrue(HELPER.exists(), "Backup archive validation is not implemented.")
        spec = importlib.util.spec_from_file_location("backup_archive", HELPER)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def test_regular_files_are_extracted(self):
        module = self.load_helper()
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            archive = root / "backup.tar.gz"
            with tarfile.open(archive, "w:gz") as tar:
                entry = tarfile.TarInfo("config/.env")
                entry.size = 5
                tar.addfile(entry, io.BytesIO(b"hello"))
            module.safe_extract(archive, root / "restored")
            self.assertEqual((root / "restored/config/.env").read_bytes(), b"hello")

    def test_traversal_links_and_special_files_are_rejected_before_extraction(self):
        module = self.load_helper()
        for name, kind in [("../escape", tarfile.REGTYPE), ("/escape", tarfile.REGTYPE), ("config/link", tarfile.SYMTYPE), ("config/hardlink", tarfile.LNKTYPE), ("config/device", tarfile.CHRTYPE), ("C:/escape", tarfile.REGTYPE)]:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as folder:
                root = Path(folder)
                archive = root / "backup.tar.gz"
                with tarfile.open(archive, "w:gz") as tar:
                    safe = tarfile.TarInfo("config/valid")
                    safe.size = 2
                    tar.addfile(safe, io.BytesIO(b"ok"))
                    entry = tarfile.TarInfo(name)
                    entry.type = kind
                    entry.linkname = "../escape"
                    tar.addfile(entry)
                with self.assertRaises(ValueError):
                    module.safe_extract(archive, root / "restored")
                self.assertFalse((root / "restored/config/valid").exists())

    def test_certificate_volume_keeps_only_contained_certbot_symlinks(self):
        module = self.load_helper()
        with tempfile.TemporaryDirectory() as folder:
            archive = Path(folder) / "certificates.tar.gz"
            with tarfile.open(archive, "w:gz") as tar:
                certificate = tarfile.TarInfo("letsencrypt/archive/shop.test/fullchain1.pem")
                certificate.size = 4
                tar.addfile(certificate, io.BytesIO(b"cert"))
                link = tarfile.TarInfo("letsencrypt/live/shop.test/fullchain.pem")
                link.type = tarfile.SYMTYPE
                link.linkname = "../../archive/shop.test/fullchain1.pem"
                tar.addfile(link)
            try:
                entries = module.archive_hashes(archive, "letsencrypt")
            except ValueError as error:
                self.fail("A legitimate contained Certbot symlink was rejected: " + str(error))
            self.assertEqual(entries["letsencrypt/live/shop.test/fullchain.pem"], "symlink:../../archive/shop.test/fullchain1.pem")

    def test_certificate_symlink_cannot_escape_volume(self):
        module = self.load_helper()
        with tempfile.TemporaryDirectory() as folder:
            archive = Path(folder) / "certificates.tar.gz"
            with tarfile.open(archive, "w:gz") as tar:
                link = tarfile.TarInfo("letsencrypt/live/shop.test/fullchain.pem")
                link.type = tarfile.SYMTYPE
                link.linkname = "../../../../etc/passwd"
                tar.addfile(link)
            with self.assertRaises(ValueError):
                module.archive_hashes(archive, "letsencrypt")


if __name__ == "__main__":
    unittest.main()
