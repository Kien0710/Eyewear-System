"""
Script tự động tạo Jira bug tickets từ Newman test results.
Được gọi từ GitHub Actions workflow.
"""
import json, subprocess, os, sys

def main():
    result_file = "reports/test-results.json"
    if not os.path.exists(result_file):
        print("No test-results.json found, skipping Jira ticket creation.")
        return

    with open(result_file) as f:
        data = json.load(f)

    # Thống kê tổng
    stats   = data["run"]["stats"]
    total   = stats["assertions"]["total"]
    failed  = stats["assertions"]["failed"]
    passed  = total - failed
    failures = data["run"].get("failures", [])

    JIRA_URL  = os.environ.get("JIRA_URL", "")
    JIRA_AUTH = os.environ.get("JIRA_AUTH", "")
    RUN_ID    = os.environ.get("RUN_ID", "")
    COMMIT    = os.environ.get("COMMIT_SHA", "")[:7]
    REPO      = os.environ.get("GITHUB_REPOSITORY", "")
    REPORT_URL = f"https://github.com/{REPO}/actions/runs/{RUN_ID}"

    print(f"Test results: {passed}/{total} passed, {failed} failed")

    if not failures:
        print("All tests passed — no new Jira bug tickets needed.")
        return

    created = 0
    for failure in failures:
        name  = failure.get("source", {}).get("name", "Unknown Test")
        error = failure.get("error", {}).get("message", "No details")

        summary = f"[CI FAIL] {name} - Build #{RUN_ID}"
        desc_text = (
            f"Test '{name}' failed in CI pipeline. "
            f"Commit: {COMMIT}. "
            f"Error: {error}. "
            f"Report: {REPORT_URL}"
        )

        payload = json.dumps({
            "fields": {
                "project": {"key": "ESQ"},
                "summary": summary,
                "description": {
                    "type": "doc",
                    "version": 1,
                    "content": [{
                        "type": "paragraph",
                        "content": [{"type": "text", "text": desc_text}]
                    }]
                },
                "issuetype": {"name": "Bug"},
                "priority": {"name": "Medium"},
                "labels": ["CI-auto", "postman-test"]
            }
        })

        result = subprocess.run(
            ["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
             "-X", "POST",
             f"{JIRA_URL}/rest/api/3/issue",
             "-u", JIRA_AUTH,
             "-H", "Content-Type: application/json",
             "-d", payload],
            capture_output=True, text=True
        )
        code = result.stdout.strip()
        if code in ("200", "201"):
            print(f"Created Jira bug: {name}")
            created += 1
        else:
            print(f"Could not create ticket for '{name}' (HTTP {code})")

    print(f"Done. Created {created}/{len(failures)} Jira bug tickets.")

if __name__ == "__main__":
    main()
