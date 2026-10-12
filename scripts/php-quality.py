#!/usr/bin/env python3
"""Format and check selected project PHP files without running tests."""

import argparse
import os
from pathlib import Path
import shutil
import subprocess
import sys


ROOT = Path(__file__).resolve().parent.parent
EXCLUDED = {".agents", ".codex", ".git", "assets", "build", "graphify-out", "node_modules", "vendor"}


def changed_files():
    """Include staged, unstaged, and untracked PHP paths, excluding deletions."""
    paths = set()
    for args in (
        ["diff", "--name-only", "--diff-filter=ACMRT", "-z", "HEAD", "--", "*.php"],
        ["ls-files", "--others", "--exclude-standard", "-z", "--", "*.php"],
    ):
        result = subprocess.run(["git", *args], cwd=ROOT, check=True, capture_output=True)
        paths.update(os.fsdecode(path) for path in result.stdout.split(b"\0") if path)
    return sorted(paths)


def php_files(paths, explicit):
    """Reject explicit paths outside editable project PHP sources."""
    selected = set()
    for name in paths:
        path = (ROOT / name).resolve()
        try:
            relative = path.relative_to(ROOT)
        except ValueError as error:
            raise ValueError(f"Path is outside the plugin: {name}") from error
        if not path.is_file() or path.suffix != ".php" or EXCLUDED.intersection(relative.parts):
            if explicit:
                raise ValueError(f"Not an editable project PHP file: {name}")
            continue
        selected.add(str(relative))
    return sorted(selected)


def tool_path(name):
    """Find project, PATH, or user Composer tools; respect explicit overrides."""
    override = os.environ.get(f"DKBCE_{name.upper()}_BIN")
    if override:
        candidates = [override]
    else:
        config = Path(os.environ.get("XDG_CONFIG_HOME") or Path.home() / ".config")
        candidates = [
            ROOT / "vendor" / "bin" / name,
            shutil.which(name),
            config / "composer" / "vendor" / "bin" / name,
            Path.home() / ".composer" / "vendor" / "bin" / name,
        ]
    for candidate in candidates:
        if candidate and Path(candidate).is_file() and os.access(candidate, os.X_OK):
            return str(Path(candidate).resolve())
    raise ValueError(f"Cannot find {name}; install the project coding standards or set DKBCE_{name.upper()}_BIN.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("mode", choices=("fix", "check", "all"))
    parser.add_argument("files", nargs="*", help="PHP files relative to the plugin root; default: changed files")
    args = parser.parse_args()
    files = php_files(args.files or changed_files(), explicit=bool(args.files))
    if not files:
        print("No changed PHP files to check.")
        return 0

    fixer = tool_path("phpcbf") if args.mode in ("fix", "all") else None
    checker = tool_path("phpcs") if args.mode in ("check", "all") else None
    php = shutil.which("php") if checker else None
    if checker and not php:
        raise ValueError("Cannot find PHP for syntax checks.")
    options = [f"--standard={ROOT / 'phpcs.xml'}", "--parallel=1", *[str(ROOT / path) for path in files]]
    print(f"PHP quality: {len(files)} file(s), mode={args.mode}", flush=True)
    fix_status = 0
    if fixer:
        fix_status = subprocess.run([fixer, *options], cwd=ROOT, check=False).returncode
        # PHPCBF returns 1 when it successfully fixes all fixable findings.
        if fix_status not in (0, 1) and not checker:
            return fix_status
    if checker:
        for path in files:
            result = subprocess.run([php, "-l", str(ROOT / path)], cwd=ROOT, check=False)
            if result.returncode:
                return result.returncode
        check_status = subprocess.run([checker, *options], cwd=ROOT, check=False).returncode
        if check_status:
            return check_status
    return fix_status if fix_status not in (0, 1) else 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f"PHP quality: {error}", file=sys.stderr)
        sys.exit(2)
