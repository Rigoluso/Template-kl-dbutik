"""Validate and hash backup archives, then extract only regular files/directories."""
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import posixpath
import shutil
import sys
import tarfile

MAX_BYTES = 100 * 1024**3
ARCHIVES = {"uploads": "uploads", "private": ".", "certificates": "letsencrypt"}


def checked_members(archive, prefix=None):
    members = archive.getmembers()
    if len(members) > 1_000_000 or sum(max(0, item.size) for item in members) > MAX_BYTES:
        raise ValueError("Backup exceeds the 100 GiB / 1,000,000 file validation limit.")
    seen = set()
    entries = {str(PurePosixPath(item.name)): item for item in members}
    for item in members:
        path = PurePosixPath(item.name)
        if path.is_absolute() or ".." in path.parts or "\\" in item.name or ":" in item.name:
            raise ValueError("Unsafe backup member path.")
        if item.issym() and prefix == "letsencrypt":
            # Certbot live/ symlinks are required for future renewals. Accept only
            # links to a real file inside the same certificate volume.
            link = item.linkname
            target = posixpath.normpath(posixpath.join(str(path.parent), link))
            target_item = entries.get(target)
            if link.startswith("/") or "\\" in link or ":" in link or not target.startswith("letsencrypt/") or not target_item or not target_item.isfile():
                raise ValueError("Unsafe certificate symlink.")
        elif not item.isfile() and not item.isdir():
            raise ValueError("Backup links and special files are not accepted.")
        if item.name in seen:
            raise ValueError("Duplicate backup members are not accepted.")
        seen.add(item.name)
        if prefix and prefix != "." and path.parts and path.parts[0] != prefix:
            raise ValueError("Archive contains a file outside its expected volume.")
    return members


def safe_extract(source, destination):
    with tarfile.open(source, "r:gz") as archive:
        members = checked_members(archive)
        # Validate every member before touching any destination path.
        for item in members:
            target = Path(destination).joinpath(*PurePosixPath(item.name).parts)
            if item.isdir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                with archive.extractfile(item) as stream, target.open("wb") as output:
                    shutil.copyfileobj(stream, output)
                target.chmod(0o600)


def archive_hashes(path, prefix):
    result = {}
    with tarfile.open(path, "r:gz") as archive:
        for item in checked_members(archive, prefix):
            if item.issym():
                result[str(PurePosixPath(item.name))] = "symlink:" + item.linkname
            elif item.isfile():
                digest = hashlib.sha256()
                with archive.extractfile(item) as stream:
                    for block in iter(lambda: stream.read(1024 * 1024), b""):
                        digest.update(block)
                result[str(PurePosixPath(item.name))] = digest.hexdigest()
    return result


def hashes(directory):
    return {name: archive_hashes(directory / (name + ".tar.gz"), prefix) for name, prefix in ARCHIVES.items()}


def file_hash(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def capture_config(directory, root):
    config = json.loads((directory / "resolved-compose.json").read_text())
    target = directory / "config"
    target.mkdir()
    for name in (".env", "compose.yaml", "compose.dev.yaml"):
        if (root / name).is_file():
            shutil.copy2(root / name, target / name)
    shutil.copytree(root / "deploy/nginx", target / "deploy/nginx")
    shutil.copytree(root / "deploy/certbot", target / "deploy/certbot")
    secrets = target / "secrets"
    secrets.mkdir()
    for name, secret in config.get("secrets", {}).items():
        if "file" not in secret:
            raise ValueError("External Docker secrets require a separate backup procedure.")
        shutil.copyfile(secret["file"], secrets / name)
        (secrets / name).chmod(0o600)


def main():
    operation, folder = sys.argv[1:3]
    directory = Path(folder)
    if operation == "capture":
        capture_config(directory, Path(sys.argv[3]))
    elif operation == "manifest":
        config = json.loads((directory / "resolved-compose.json").read_text())
        result = {"format": 1, "app_image": config["services"]["app"]["image"], "volumes": hashes(directory), "database_sha256": file_hash(directory / "database.sql")}
        (directory / "manifest.json").write_text(json.dumps(result, indent=2) + "\n")
    elif operation == "extract":
        safe_extract(Path(sys.argv[3]), directory)
        manifest = json.loads((directory / "manifest.json").read_text())
        if manifest.get("format") != 1 or manifest["volumes"] != hashes(directory):
            raise ValueError("Backup volume hashes do not match the manifest.")
        if manifest["database_sha256"] != file_hash(directory / "database.sql"):
            raise ValueError("Backup database hash does not match the manifest.")
    elif operation == "check-image":
        manifest = json.loads((directory / "manifest.json").read_text())
        current = json.loads(Path(sys.argv[3]).read_text())
        if current["services"]["app"]["image"] != manifest["app_image"]:
            raise ValueError("Restore requires the backup's application image reference. Restore matching configuration first.")
    elif operation == "compare":
        manifest = json.loads((directory / "manifest.json").read_text())
        if manifest["volumes"] != hashes(Path(sys.argv[3])):
            raise ValueError("Restored volume contents differ from the backup manifest.")
    else:
        raise ValueError("Unknown archive operation.")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, KeyError, tarfile.TarError, json.JSONDecodeError) as error:
        print("Backup validation failed: " + str(error), file=sys.stderr)
        sys.exit(1)
