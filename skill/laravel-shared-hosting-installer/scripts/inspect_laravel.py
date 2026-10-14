#!/usr/bin/env python3
"""Read Laravel deployment structure without reading or printing .env secrets."""

from __future__ import annotations

import argparse
import json
from pathlib import Path


def read_json(path: Path) -> dict:
    if not path.is_file():
        return {}
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        raise ValueError(f"Cannot parse {path.name}: {type(error).__name__}") from error
    if not isinstance(value, dict):
        raise ValueError(f"{path.name} must contain a JSON object")
    return value


def inspect(root: Path) -> dict:
    root = root.resolve(strict=True)
    if not root.is_dir():
        raise ValueError("Project root must be a directory")
    composer = read_json(root / "composer.json")
    if not composer:
        raise ValueError("No readable composer.json in the supplied project root")
    lock = read_json(root / "composer.lock")
    requires = composer.get("require", {})
    requires = requires if isinstance(requires, dict) else {}
    locked = {}
    packages = lock.get("packages", [])
    for package in packages if isinstance(packages, list) else []:
        if isinstance(package, dict) and isinstance(package.get("name"), str):
            locked[package["name"]] = package.get("version")
    structural = [
        "artisan", "bootstrap/app.php", "public/index.php", "public/.htaccess",
        "vendor/autoload.php", "storage", "bootstrap/cache", ".env.example",
    ]
    extra = composer.get("extra", {})
    laravel_extra = extra.get("laravel", {}) if isinstance(extra, dict) else {}
    package_json = read_json(root / "package.json")
    scripts = package_json.get("scripts", {})
    return {
        "project_root": str(root),
        "php_constraint": requires.get("php"),
        "framework_constraint": requires.get("laravel/framework"),
        "locked_framework_version": locked.get("laravel/framework"),
        "composer_lock_present": bool(lock),
        "required_extensions": sorted(key[4:] for key in requires if key.startswith("ext-")),
        "installer_constraint": requires.get("solomongs/laravel-wizard-installer"),
        "locked_installer_version": locked.get("solomongs/laravel-wizard-installer"),
        "discovery_exclusions": laravel_extra.get("dont-discover", []) if isinstance(laravel_extra, dict) else [],
        "paths": {relative: (root / relative).exists() for relative in structural},
        "frontend_script_names": sorted(scripts) if isinstance(scripts, dict) else [],
        "migration_count": len(list((root / "database/migrations").glob("*.php"))),
        "existing_env_present": (root / ".env").is_file(),
        "notes": [
            "No .env contents or setup state were read.",
            "Host PHP, extensions, public-root separation, and DB privileges need independent checks.",
            "The package source alone is not a prepared Laravel website release.",
        ],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("project_root", type=Path)
    args = parser.parse_args()
    try:
        result = inspect(args.project_root)
    except (OSError, ValueError) as error:
        parser.exit(1, f"Inspection failed: {error}\n")
    print(json.dumps(result, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
