# Verification record — 14 October 2026

Developed by Solomon Gbeta. These are checks actually executed during the first release, rather than claims based only on Composer constraints.

| Framework | Local runtime | Standalone | Laravel integration | Real HTTP + MariaDB 10.11 |
|---|---|---|---|---|
| Laravel 10.50.3 | PHP 8.3.6 | 23 passed | 8 passed; 1 feature not available | Passed |
| Laravel 11.57.0 | PHP 8.3.6 | 23 passed | 8 passed; 1 feature not available | Passed |
| Laravel 12.70.0 | PHP 8.3.6 | 23 passed | 9 passed | Passed |
| Laravel 13.36.0 | PHP 8.3.6 | 23 passed | 9 passed | Passed |

The conditional integration test covers a framework-provided migration-skipping feature. Older frameworks lack that feature; their other integration checks passed. Laravel 10/11 are legacy compatibility targets. New projects should use maintained framework versions. The CI matrix separately runs the appropriate minimum PHP versions and MySQL 8; read its returned results before claiming those exact runtime combinations have passed.

The HTTP suite starts a genuine Laravel application and its standalone installer. It verifies unauthorised requests, setup-code checks, CSRF rejection, public/API application guards, requirement checks, wrong credentials without configuration changes, populated database rejection without deletion, production environment writing, stale database URL/socket override removal, password round trips, real migration tables and records, progress, and permanent completion locking. The database is disposable.

The integration suite verifies command discovery, dotenv escaping, migration execution, permanent locks, interrupted request recovery, refusal to repeat partial SQL, effective database mismatch rejection before repository creation, corrupted-state protection, and supported intentional migration skipping.

The wizard UI was exercised through the complete flow at 1365, 390 and 320 pixels, including password privacy and responsive overflow checks. The documentation website and content editor have separate browser checks. Syntax checks cover PHP, JavaScript and the Python harness.

These automated checks use real framework/database behavior. They do not change a customer's Multi8 Hosting or cPanel account. The reusable skill calls for a disposable release-clone test on the intended hosting account before shipping a particular project.
