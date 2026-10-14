# Customer diagnostics

Use the check's observed result before suggesting a fix. Keep the response simple: what failed, where to click or which file/path to inspect, and how to confirm recovery. Do not ask for passwords, setup codes, `.env`, or private archives in chat.

| Symptom | Check |
|---|---|
| Installer missing | Prepared website release, generated entry point, correct public root |
| Wrong PHP / missing driver | Domain-specific web PHP and extensions; CLI PHP can differ |
| Missing dependency file | Complete production `vendor/`, not only package source |
| 403 after root change | Existing target folder, intended root, public index file |
| 500 immediately | Host error log, unsupported `.htaccess` directive, dependency runtime mismatch |
| Unwritable storage/cache | Owner-compatible write permissions; no blanket `777` |
| Database authentication failed | Exact host, prefixed names, assigned user, privileges, password |
| Populated database refused | Dedicated new empty database; no table deletion |
| Redirect loop | Panel/app/CDN redirect agreement and trusted proxy configuration |
| Setup code expired | Fresh pre-install preparation; expiry begins at preparation |
| Migration timeout / table exists | Private logs and actual DB state; do not blindly retry |
| Completed/locked | Use ordinary update deployment; do not delete lock |

One migration per request reduces aggregate request work but does not split an individual long migration. MySQL DDL may leave partial schema after failure. Review recorded migrations and tables before deciding whether a resume is safe.

`wizard:status` and `wizard:doctor` are developer terminal tools; the customer browser flow does not require them. After successful setup, verify ordinary pages, sign-in, mail, uploads, storage paths, and any project external services. Remove `install.php` and preserve private completion state.
