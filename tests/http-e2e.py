#!/usr/bin/env python3
"""Exercise the wizard over real HTTP against a disposable MySQL database.

Run through a local database service or the scratch run-with-mysql.sh wrapper.
The test reads WIZARD_TEST_DB_* environment variables and never prints the
generated setup token, database password, or raw HTTP response bodies.
"""

from __future__ import annotations

import argparse
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


class TestFailure(Exception):
    pass


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


def ensure(condition: bool, message: str) -> None:
    if not condition:
        raise TestFailure(message)


def available_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


class Client:
    def __init__(self, url: str) -> None:
        self.url = url
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            urllib.request.ProxyHandler({}),
            NoRedirect(),
        )
        self.csrf = ""
        self.last_headers: dict[str, str] = {}

    def get(self) -> tuple[int, str]:
        return self.request(urllib.request.Request(self.url))

    def post(self, action: str, *, csrf: str | None = None, **data: object) -> tuple[int, dict]:
        payload = {"action": action, "csrf": self.csrf if csrf is None else csrf, **data}
        status, body = self.request(urllib.request.Request(
            self.url, data=json.dumps(payload).encode(),
            headers={"Content-Type": "application/json", "Accept": "application/json"},
            method="POST",
        ))
        try:
            decoded = json.loads(body)
        except json.JSONDecodeError:
            raise TestFailure(f"Action {action} returned non-JSON HTTP {status}") from None
        ensure(isinstance(decoded, dict), f"Action {action} returned an invalid response")
        return status, decoded

    def request(self, request: urllib.request.Request) -> tuple[int, str]:
        try:
            with self.opener.open(request, timeout=15) as response:
                self.last_headers = dict(response.headers)
                return response.status, response.read().decode()
        except urllib.error.HTTPError as response:
            self.last_headers = dict(response.headers)
            return response.code, response.read().decode()

    def load_csrf(self) -> str:
        status, page = self.get()
        ensure(status == 200, f"The installer page returned HTTP {status}")
        match = re.search(r'<meta\s+name=[\"\']csrf-token[\"\']\s+content=[\"\']([^\"\']+)', page)
        ensure(match is not None, "The installer page did not contain its CSRF token")
        self.csrf = html.unescape(match.group(1))
        ensure(re.fullmatch(r"[0-9a-f]{48}", self.csrf) is not None, "Invalid CSRF token format")
        ensure("Solomon Gbeta" in page, "Developer attribution is missing")
        return page


def expect_error(response: tuple[int, dict], status: int, code: str) -> None:
    actual, body = response
    ensure(actual == status, f"Expected HTTP {status}, received {actual} for {code}")
    ensure(body.get("ok") is False and body.get("code") == code, f"Expected error code {code}")


def expect_success(response: tuple[int, dict], action: str) -> dict:
    status, body = response
    ensure(status == 200 and body.get("ok") is True, f"Action {action} failed (HTTP {status}, code {body.get('code', 'unknown')})")
    ensure(isinstance(body.get("data"), dict), f"Action {action} is missing status data")
    return body["data"]


def database_query(php: str, values: dict, sql: str) -> list:
    query = subprocess.run([php, "-r", r'''
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO('mysql:host='.$input['db_host'].';port='.$input['db_port'].';dbname='.$input['db_database'], $input['db_username'], $input['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$statement = $db->query($input['sql']);
echo json_encode($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : [], JSON_THROW_ON_ERROR);
'''], input=json.dumps({**values, "sql": sql}), capture_output=True, text=True)
    ensure(query.returncode == 0, "The disposable test database could not be queried")
    try:
        return json.loads(query.stdout)
    except json.JSONDecodeError:
        raise TestFailure("The test database returned invalid output") from None


def run(php: str, fixture_script: Path) -> None:
    values = {
        "name": "Wizard HTTP test",
        "timezone": "Africa/Lagos",
        "db_host": os.environ.get("WIZARD_TEST_DB_HOST", "127.0.0.1"),
        "db_port": os.environ.get("WIZARD_TEST_DB_PORT", "3306"),
        "db_database": os.environ.get("WIZARD_TEST_DB_DATABASE", "wizard_e2e"),
        "db_username": os.environ.get("WIZARD_TEST_DB_USERNAME", "wizard_test"),
        "db_password": os.environ.get("WIZARD_TEST_DB_PASSWORD", ""),
        "confirm_empty": True,
    }
    ensure(values["db_database"].startswith("wizard_"), "Use a disposable database name beginning with wizard_")
    ensure(fixture_script.is_file(), "The fixture-generation script is missing")
    with tempfile.TemporaryDirectory(prefix="laravel-wizard-http-") as temporary:
        app = Path(temporary) / "app"
        created = subprocess.run([php, str(fixture_script), str(app)], capture_output=True, text=True)
        ensure(created.returncode == 0, "The Laravel HTTP fixture could not be created")
        try:
            fixture = json.loads(created.stdout)
        except json.JSONDecodeError:
            raise TestFailure("The fixture script must output JSON containing token") from None
        ensure(isinstance(fixture.get("token"), str) and len(fixture["token"]) >= 32, "The fixture did not provide a private setup token")
        port = available_port()
        base = f"http://127.0.0.1:{port}"
        values["url"] = base
        with (Path(temporary) / "server.log").open("w") as log:
            server = subprocess.Popen([php, "-S", f"127.0.0.1:{port}", "-t", str(app / "public")], stdout=log, stderr=log)
            try:
                client = Client(base + "/install.php")
                for _ in range(100):
                    ensure(server.poll() is None, "The PHP HTTP server stopped before setup")
                    try:
                        client.get()
                        break
                    except urllib.error.URLError:
                        time.sleep(0.05)
                else:
                    raise TestFailure("The PHP HTTP server did not become ready")
                page = client.load_csrf()
                ensure(fixture["token"] not in page, "The private setup token leaked into the page")
                if (app / "public/index.php").is_file():
                    guarded = Client(base + "/")
                    ensure(guarded.get()[0] == 302, "The uninstalled website did not redirect to setup")
                    ensure(urllib.parse.urljoin(base, guarded.last_headers.get("Location", "")) == base + "/install.php", "The setup redirect was incorrect")
                    ensure(Client(base + "/api/probe").get()[0] == 503, "The uninstalled API remained accessible")
                expect_error(client.post("check", csrf="invalid"), 419, "csrf")
                expect_error(client.post("check"), 401, "unauthorized")
                expect_error(client.post("login", token="invalid-token"), 401, "invalid_token")
                expect_success(client.post("login", token=fixture["token"]), "login")
                profile = expect_success(client.post("profile", profile="20i"), "profile")
                ensure(profile["profile"] == "20i", "The hosting choice was not saved")
                status = expect_success(client.post("check"), "check")
                failures = [check["id"] for check in status["checks"] if check["status"] == "fail"]
                ensure(status["ready"] is True, "Hosting readiness failed: " + ", ".join(failures))
                expect_error(client.post("configure", **{**values, "confirm_empty": False}), 422, "confirmation")
                original_settings = (app / ".env").read_text()
                expect_error(client.post("configure", **{**values, "db_password": values["db_password"] + "-incorrect"}), 422, "database_connection")
                ensure((app / ".env").read_text() == original_settings, "A failed connection changed the website settings")
                database_query(php, values, "CREATE TABLE wizard_existing_sentinel (id INTEGER)")
                expect_error(client.post("configure", **values), 422, "database_not_empty")
                ensure(database_query(php, values, "SHOW TABLES LIKE 'wizard_existing_sentinel'"), "The rejected database's existing table was removed")
                database_query(php, values, "DROP TABLE wizard_existing_sentinel")
                ensure(database_query(php, values, "SHOW TABLES") == [], "The disposable database became nonempty unexpectedly before installation")
                configured = expect_success(client.post("configure", **values), "configure")
                ensure(configured["phase"] == "configured", "Website settings were not saved")
                serialized = json.dumps(configured)
                ensure(values["db_password"] not in serialized if values["db_password"] else True, "Database password leaked into the status response")
                for _ in range(40):
                    progress = expect_success(client.post("run"), "run")
                    if progress["phase"] == "complete":
                        break
                else:
                    raise TestFailure("Setup did not finish within forty separate requests")
                ensure(progress["progress"]["percent"] == 100, "Completed setup did not report 100%")
                ensure(all(step["status"] == "done" for step in progress["steps"]), "A setup step remains unfinished")
                ensure(progress["success_url"] == base + "/", "The final website URL is incorrect")
                ensure((app / "storage/app/laravel-wizard/installed.json").is_file(), "The permanent installation lock is missing")
                settings = (app / ".env").read_text()
                ensure("APP_ENV=production" in settings or 'APP_ENV="production"' in settings, "Production mode is missing")
                ensure("APP_DEBUG=false" in settings or 'APP_DEBUG="false"' in settings, "Debug mode remains enabled")
                ensure("APP_KEY=" in settings and "base64:" in settings, "The Laravel encryption key is missing")
                verification = subprocess.run([php, "-r", r'''
require $argv[1].'/vendor/autoload.php';
$env = \Dotenv\Dotenv::parse(file_get_contents($argv[1].'/.env'));
$database = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';dbname='.$env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD']);
$table = $database->query("SHOW TABLES LIKE 'wizard_test_records'")->fetchColumn();
$migrations = $database->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
echo json_encode(['password'=>$env['DB_PASSWORD'], 'table'=>$table, 'migrations'=>(int)$migrations], JSON_THROW_ON_ERROR);
''', str(app)], capture_output=True, text=True)
                ensure(verification.returncode == 0, "The saved database connection could not be verified")
                try:
                    evidence = json.loads(verification.stdout)
                except json.JSONDecodeError:
                    raise TestFailure("The database verification returned invalid output") from None
                ensure(evidence["password"] == values["db_password"], "The database password changed during dotenv encoding")
                ensure(evidence["table"] == "wizard_test_records", "The application migration did not create its table")
                ensure(evidence["migrations"] >= 1, "The migration repository did not record the application migration")
                if (app / "public/index.php").is_file():
                    ensure(Client(base + "/").get()[0] == 200, "The installed website did not become available")
                    ensure(Client(base + "/api/probe").get()[0] == 200, "The installed API did not become available")
                ensure(client.get()[0] == 410, "The completed installer remained accessible")
                expect_error(client.post("run"), 410, "installed")
                print("PASS: real HTTP sessions, CSRF, private setup code, hosting checks, MySQL configuration, Laravel migrations, progress, production settings, and permanent setup lock")
            finally:
                server.terminate()
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", default="php", help="PHP CLI executable")
    parser.add_argument("--fixture-script", type=Path, default=Path(__file__).with_name("create-fixture.php"))
    args = parser.parse_args()
    try:
        run(args.php, args.fixture_script.resolve())
    except (TestFailure, OSError) as error:
        print("FAIL:", error)
        raise SystemExit(1) from None
