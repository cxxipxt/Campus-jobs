"""HTTP and InnoDB regression checks. Use a disposable local MySQL 8 test instance.
TEST_MYSQL_PORT is required. PHP_BIN and MYSQL_BIN default to php and mysql on PATH.
The PHP CLI needs mbstring and pdo_mysql. The local test user needs CREATE DATABASE
and read access to performance_schema.data_locks. No live OAuth credentials needed.
"""
import concurrent.futures
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
DB = "campus_review_test_" + secrets.token_hex(10)
PORT = str(int(os.environ["TEST_MYSQL_PORT"]))
USER = os.environ.get("TEST_MYSQL_USER", "root")
MYSQL = [os.environ.get("MYSQL_BIN", "mysql"), "--no-defaults", "--protocol=TCP",
         "-h127.0.0.1", "-P" + PORT, "-u" + USER, "--default-character-set=utf8mb4",
         "--batch", "--skip-column-names", "--unbuffered"]
PHP = [os.environ.get("PHP_BIN", "php")]
if os.environ.get("TEST_PHP_EXT"):
    PHP += ["-d", "extension_dir=" + os.environ["TEST_PHP_EXT"],
            "-d", "extension=pdo_mysql", "-d", "extension=mbstring"]
PASSWORD = secrets.token_urlsafe(24)
DB_ENV = dict(os.environ, MYSQL_PWD=os.environ.get("TEST_MYSQL_PASSWORD", ""))


def sql(statement, database=True):
    result = subprocess.run(MYSQL + ([DB] if database else []), input=statement,
                            capture_output=True, check=True, encoding="utf-8", env=DB_ENV)
    return result.stdout.strip()


def browser():
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def page(client, path, fields=None):
    body = None if fields is None else urllib.parse.urlencode(fields).encode()
    with client.open(BASE + path, data=body, timeout=12) as response:
        return response.read().decode()


def hidden(html, name):
    match = re.search(r'name="' + name + r'"[^>]*value="([^"]*)"', html)
    assert match, f"Missing form field {name}"
    return match.group(1)


def check_site():
    employer, admin = browser(), browser()
    profile = "/employer/profile.php"
    queue = "/admin/verification.php"
    job = "/employer/job-form.php"
    login = "/employer/login.php"
    admin_login = "/admin/login.php"
    sql("INSERT INTO EMPLOYER(email,google_subject) VALUES ('google@example.test','fixture-subject')")
    assert sql("SELECT COUNT(*) FROM EMPLOYER WHERE google_subject='fixture-subject' "
               "AND verification_status='Draft' AND company_name IS NULL") == "1"
    signup = browser()
    register = "/employer/register.php"
    registration = page(signup, register, dict(csrf=hidden(page(signup, register), "csrf"),
                            username="employee", email="test@example.test",
                            password=PASSWORD, password_confirmation=PASSWORD))
    assert re.search(r'name="company_name"[^>]*value=""', registration)
    assert sql("SELECT verification_status FROM EMPLOYER WHERE username='employee'") == "Draft"
    screen = page(employer, login, dict(csrf=hidden(page(employer, login), "csrf"),
                                      username="employee", password=PASSWORD))
    assert re.search(r'name="company_name"[^>]*value=""', screen), "NULL company name must render an empty input"
    print("PASS nullable company profile via HTTP", flush=True)
    assert '/admin/login.php' in urllib.parse.urlparse(employer.open(BASE + queue).geturl()).path
    data = dict(company_name="Example A", business_type="บริการ", phone="020000000",
                contact_email="contact@example.test", address="Example address")
    csrf = hidden(screen, "csrf")
    page(employer, profile, dict(data, csrf=csrf))
    em_id = sql("SELECT employer_id FROM EMPLOYER WHERE username='employee'")
    status = "SELECT verification_status FROM EMPLOYER WHERE employer_id=" + em_id
    assert sql(status) == "Pending"
    initial = page(admin, admin_login, dict(csrf=hidden(page(admin, admin_login), "csrf"),
                                            username="reviewer", password=PASSWORD))
    def decision(screen, action):
        return dict(csrf=hidden(screen, "csrf"), employer_id=em_id, action=action,
                    review_token=hidden(screen, "review_token"), rejection_reason="Update contact")
    assert "google@example.test" not in initial
    old = decision(initial, "approve")
    page(employer, profile, dict(data, company_name="Example B", csrf=csrf))
    for action in ("approve", "reject"):
        result = page(admin, queue, dict(old, action=action))
        assert "ตรวจสอบข้อมูลล่าสุด" in result and sql(status) == "Pending", "Outdated review must be declined"
    fresh = page(admin, queue)
    assert "Example B" in fresh
    page(admin, queue, decision(fresh, "reject"))
    assert sql(status) == "Rejected"
    page(employer, profile, dict(data, company_name="Example B", csrf=csrf))
    page(admin, queue, decision(fresh, "approve"))
    assert sql(status) == "Pending", "A resubmission needs a new decision even with identical fields"
    page(admin, queue, decision(page(admin, queue), "approve"))
    assert sql(status) == "Verified"
    print("PASS stale decisions, reject, identical resubmission and fresh approval", flush=True)
    fields = dict(csrf=hidden(page(employer, job), "csrf"), job_title="Fixture job",
                  category_id=sql("SELECT MIN(category_id) FROM JOB_CATEGORY"),
                  description="Test job", hourly_wage="100", location="Campus", work_schedule="Monday")
    page(employer, job, fields)
    job_id = sql("SELECT job_id FROM JOB WHERE employer_id=" + em_id)
    assert job_id.isdigit(), "Verified account can create a job"
    page(employer, job + "?job_id=" + job_id, dict(fields, job_title="Edited job"))
    assert sql("SELECT job_title FROM JOB WHERE job_id=" + job_id) == "Edited job"

    # Hold the employer row on an independent connection until the web request
    # reaches its locking read. A foreign-key shared lock is not this guard.
    with subprocess.Popen(MYSQL + [DB], stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                          encoding="utf-8", env=DB_ENV) as writer:
        writer.stdin.write("START TRANSACTION; UPDATE EMPLOYER SET verification_status='Pending',"
                           "verified_at=NULL WHERE employer_id=" + em_id + "; SELECT 'ready';\n")
        writer.stdin.flush()
        assert writer.stdout.readline().strip() == "ready"
        with concurrent.futures.ThreadPoolExecutor(1) as pool:
            request = pool.submit(page, employer, job, fields)
            try:
                deadline = time.monotonic() + 6
                while time.monotonic() < deadline:
                    waiting = sql("SELECT COUNT(*) FROM performance_schema.data_locks WHERE "
                                  "OBJECT_SCHEMA=DATABASE() AND UPPER(OBJECT_NAME)='EMPLOYER' AND "
                                  "LOCK_STATUS='WAITING' AND LOCK_MODE LIKE 'X%'")
                    if int(waiting):
                        break
                    time.sleep(.05)
                else:
                    raise AssertionError("Job transaction must lock the employer before writing")
            finally:
                writer.stdin.write("COMMIT;\n")
                writer.stdin.close()
            request.result()
    assert sql("SELECT COUNT(*) FROM JOB WHERE employer_id=" + em_id) == "1"
    assert sql(status) == "Pending"
    print("PASS job create/edit, two-connection lock and post-wait status check", flush=True)


if __name__ == "__main__":
    password_hash = subprocess.check_output(PHP + ["-r", "echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);"],
                                            input=PASSWORD, encoding="utf-8")
    sql("CREATE DATABASE " + DB + " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", False)
    try:
        for filename in ("schema.sql", "seed.sql"):
            sql((ROOT / "database" / filename).read_text(encoding="utf-8-sig"))
        with tempfile.TemporaryDirectory() as sessions, socket.socket() as port_probe:
            port_probe.bind(("127.0.0.1", 0))
            port = port_probe.getsockname()[1]
            port_probe.close()
            BASE = f"http://127.0.0.1:{port}"
            env = dict(os.environ, DB_HOST="127.0.0.1", DB_PORT=PORT, DB_NAME=DB,
                       DB_USER=USER, DB_PASSWORD=DB_ENV["MYSQL_PWD"], APP_BASE_PATH="",
                       TMPDIR=sessions, TMP=sessions, TEMP=sessions,
                       ADMIN_USERNAME="reviewer", ADMIN_PASSWORD_HASH=password_hash)
            server = subprocess.Popen(PHP + ["-d", "session.save_path=" + sessions, "-S",
                                     f"127.0.0.1:{port}", "-t", "frontend", "frontend/router.php"],
                                     cwd=ROOT, env=env, stderr=subprocess.DEVNULL)
            try:
                for attempt in range(100):
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=.2):
                            break
                    except OSError:
                        if server.poll() is not None:
                            raise RuntimeError("PHP server exited")
                        time.sleep(.05)
                check_site()
            finally:
                server.terminate()
                server.wait(timeout=10)
    finally:
        sql("DROP DATABASE " + DB, False)
