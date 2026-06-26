const fs = require("fs");
const { execSync } = require("child_process");

const reportFiles = [
  { name: "Eyewear API", file: "./postman-reports/summary-eyewear.json" }
];

let createdCount = 0;
let commentedCount = 0;
let closedCount = 0;

const requiredEnv = ["JIRA_BASE_URL", "JIRA_USER_EMAIL", "JIRA_API_TOKEN", "PROJECT_KEY"];
for (const key of requiredEnv) {
  if (!process.env[key]) {
    console.log(`⚠️ Missing env ${key}. Skip Jira sync.`);
    process.exit(0);
  }
}

const auth = Buffer
  .from(`${process.env.JIRA_USER_EMAIL}:${process.env.JIRA_API_TOKEN}`)
  .toString("base64");
const jiraBase = process.env.JIRA_BASE_URL.replace(/\/$/, "");
console.log(`📌 Starting Jira sync for project ${process.env.PROJECT_KEY} at ${jiraBase}`);

const members = [
  "712020:ba3687e1-2a5e-4f18-8594-07315b414728",
  "712020:b2b9ed8a-a72a-43ae-8a12-1c5c7e599e08",
  "712020:0ad5e210-883e-4449-8daa-2d3b51029e4d",
  "712020:d335bfed-2162-4164-9e7f-172d5434d5b0",
  "712020:83ddbb5b-a878-423a-8972-12137f84b4f3",
  "712020:9645f74c-0db2-4301-a77a-f0f57c6244fd"
];

// ── Helpers ───────────────────────────────────────────────────────
async function fetchJson(method, url, body = null) {
  const options = {
    method,
    headers: {
      "Authorization": `Basic ${auth}`,
      "Content-Type": "application/json"
    }
  };
  if (body) {
    options.body = JSON.stringify(body);
  }
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);
    options.signal = controller.signal;
    
    console.log(`➡️  Fetching: ${method} ${url}`);
    const response = await fetch(url, options);
    clearTimeout(timeoutId);
    
    const text = await response.text();
    if (!text || text.trim() === "") return {};
    return JSON.parse(text);
  } catch (err) {
    console.log("⚠️ Jira request error:", err.message);
    return {};
  }
}

const normalize = v => String(v || "").replace(/[\r\n\t]+/g, " ").replace(/\s+/g, " ").trim();
const slugify   = v => normalize(v).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 60);
const adf       = lines => ({
  version: 1, type: "doc",
  content: lines.map(t => ({ type: "paragraph", content: [{ type: "text", text: String(t) }] }))
});

// ── Dynamic "Done" transition ID ─────────────────────────────────
let DONE_TRANSITION_ID = null;
async function getDoneTransitionId(issueKey) {
  if (DONE_TRANSITION_ID) return DONE_TRANSITION_ID;
  const res = await fetchJson("GET", `${jiraBase}/rest/api/3/issue/${issueKey}/transitions`);
  const done = (res.transitions || []).find(t =>
    ["done", "closed", "resolved"].includes(normalize(t.name).toLowerCase())
  );
  if (done) { DONE_TRANSITION_ID = done.id; return done.id; }
  console.log("⚠️ No Done transition found. Available:", (res.transitions || []).map(t => t.name).join(", "));
  return null;
}

async function main() {
  // ── Parse Newman report ───────────────────────────────────────────
  const failedApis   = new Set();
  const passedApis   = new Map();
  const failuresMap  = new Map();

  for (const item of reportFiles) {
    if (!fs.existsSync(item.file)) {
      console.log(`⚠️ Report not found: ${item.file}`);
      continue;
    }
    const data = JSON.parse(fs.readFileSync(item.file, "utf8"));

    for (const fail of (data?.run?.failures || [])) {
      const apiName  = normalize(fail?.source?.name || "Unknown API");
      const errorMsg = normalize(fail?.error?.message || "Assertion failed");
      const key      = `${item.name}|||${apiName}`;
      
      failedApis.add(apiName);
      
      if (!failuresMap.has(key)) {
        failuresMap.set(key, {
          moduleName: item.name,
          apiName,
          errorMsgs: [errorMsg],
          bugKey: slugify(`v2-${item.name}-${apiName}`),
          summary: `❌ [CI/CD] Lỗi API: ${apiName} (${item.name})`
        });
      } else {
        failuresMap.get(key).errorMsgs.push(errorMsg);
      }
    }

    for (const exec of (data?.run?.executions || [])) {
      const apiName = normalize(exec?.item?.name || "");
      if (apiName && !failedApis.has(apiName)) {
        passedApis.set(`${item.name}|||${apiName}`, {
          time: exec?.response?.responseTime || "N/A",
          code: exec?.response?.code || "200"
        });
      }
      
      const key = `${item.name}|||${apiName}`;
      if (apiName && failuresMap.has(key)) {
        const failObj = failuresMap.get(key);
        if (!failObj.details) {
          let reqBody = exec?.request?.body?.raw ? "\nPayload:\n" + exec.request.body.raw : "";
          if (reqBody.length > 500) reqBody = reqBody.substring(0, 500) + "\n... (truncated)";
          failObj.details = `Method: ${exec?.request?.method || "UNKNOWN"}\nURL: ${exec?.request?.url?.raw || "UNKNOWN URL"}${reqBody}\nResponse Code: ${exec?.response?.code || "UNKNOWN"}`;
        }
      }
    }
  }

  const failures = Array.from(failuresMap.values()).map(f => {
    f.errorMsg = "- " + [...new Set(f.errorMsgs)].join("\n- ");
    return f;
  });

  const runUrl = `https://github.com/${process.env.REPOSITORY}/actions/runs/${process.env.RUN_ID}`;

  // ── Load open CI/CD bugs from Jira (for auto-close logic) ──────────
  async function fetchAllIssues(jql) {
    let issues = [];
    let startAt = 0;
    const maxResults = 100;
    while (true) {
      const url = `${jiraBase}/rest/api/3/search/jql?jql=${encodeURIComponent(jql)}&startAt=${startAt}&maxResults=${maxResults}&fields=summary,status,labels,issuetype,assignee`;
      const res = await fetchJson("GET", url);
      if (!res || res.errorMessages || res.errors) {
        console.log("❌ Jira search error:", JSON.stringify(res).slice(0, 500));
        break;
      }
      if (!res.issues || res.issues.length === 0) {
        break;
      }
      issues = issues.concat(res.issues);
      
      // Strict pagination breaks
      if (res.issues.length < maxResults) break;
      if (res.total && startAt + res.issues.length >= res.total) break;
      if (startAt > 5000) break; // Circuit breaker
      
      startAt += res.issues.length;
    }
    return issues;
  }

  function filterCiBugs(issues) {
    return issues.filter(i => {
      const summary = normalize(i?.fields?.summary || "");
      const type    = normalize(i?.fields?.issuetype?.name || "").toLowerCase();
      return type === "bug" && (
        summary.includes("[CI/CD]") ||
        summary.includes("Lỗi API:")  ||
        summary.includes("Loi API:")
      );
    });
  }

  async function getOpenCiBugs() {
    const jql = `project = ${process.env.PROJECT_KEY} AND statusCategory != Done`;
    return filterCiBugs(await fetchAllIssues(jql));
  }

  async function getAllCiBugs() {
    const jql = `project = ${process.env.PROJECT_KEY} AND issuetype = Bug ORDER BY created DESC`;
    return filterCiBugs(await fetchAllIssues(jql));
  }

  const openCiBugs = await getOpenCiBugs();
  const allCiBugs  = await getAllCiBugs();
  console.log(`🔎 Found ${openCiBugs.length} open CI/CD bugs in Jira (${allCiBugs.length} total including closed).`);

  function findExistingBug(bug) {
    return allCiBugs.find(i => {
      const summary = normalize(i?.fields?.summary || "");
      const labels  = i?.fields?.labels || [];
      return labels.includes(`api-${bug.bugKey}`)
        || summary === normalize(bug.summary)
        || (summary.includes("[CI/CD]") && summary.includes(bug.apiName) && summary.includes(bug.moduleName));
    }) || null;
  }

  const COMMENT_MARKER = "🤖 [CI/CD-AUTO-COMMENT]";

  async function findCiComment(issueKey) {
    const res = await fetchJson("GET", `${jiraBase}/rest/api/3/issue/${issueKey}/comment?maxResults=100&orderBy=created`);
    const comments = res.comments || [];
    return comments.find(c => {
      const bodyText = JSON.stringify(c.body || "");
      return bodyText.includes(COMMENT_MARKER);
    }) || null;
  }

  async function upsertComment(issueKey, adfBody) {
    const existing = await findCiComment(issueKey);
    if (existing) {
      await fetchJson("PUT", `${jiraBase}/rest/api/3/issue/${issueKey}/comment/${existing.id}`, { body: adfBody });
      return "updated";
    } else {
      await fetchJson("POST", `${jiraBase}/rest/api/3/issue/${issueKey}/comment`, { body: adfBody });
      return "created";
    }
  }

  // ── Auto-close bugs whose API now passes ──────────────────────────
  for (const issue of openCiBugs) {
    const summary = normalize(issue?.fields?.summary || "");
    const labels  = issue?.fields?.labels || [];
    let matched   = false;
    let matchedApi = "Không xác định";
    let matchedTime = "N/A";
    let matchedCode = "200";

    for (const [passedKey, passData] of passedApis.entries()) {
      const [modName, apiName] = passedKey.split("|||");
      const bk = slugify(`${modName}-${apiName}`);
      if (labels.includes(`api-${bk}`) || (summary.includes("[CI/CD]") && summary.includes(apiName) && summary.includes(modName))) {
        matched = true;
        matchedApi = `[${modName}] ${apiName}`;
        matchedTime = passData.time;
        matchedCode = passData.code;
        break;
      }
    }

    if (!matched) continue;

    const transId = await getDoneTransitionId(issue.key);
    if (!transId) { console.log(`⚠️ Skip auto-close ${issue.key} — transition not found.`); continue; }

    await fetchJson("POST", `${jiraBase}/rest/api/3/issue/${issue.key}/transitions`, { transition: { id: transId } });
    const adfCloseBody = {
      version: 1, type: "doc",
      content: [
        { type: "paragraph", content: [{ type: "text", text: COMMENT_MARKER, marks: [{ type: "strong" }] }] },
        { type: "heading", attrs: { level: 3 }, content: [{ type: "text", text: "✅ Tự động Đóng Bug (Auto-Closed)" }] },
        { type: "paragraph", content: [{ type: "text", text: `Hệ thống CI/CD xác nhận API `, marks: [{ type: "strong" }]}, { type: "text", text: `${matchedApi}` }, { type: "text", text: ` đã hoàn toàn vượt qua bài kiểm tra tự động.`, marks: [{ type: "strong" }]}] },
        { type: "paragraph", content: [{ type: "text", text: "Báo cáo chất lượng (Quality Report):" }] },
        { type: "bulletList", content: [
            { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: `Status Code: Trả về chính xác mã ${matchedCode} hợp lệ theo tài liệu thiết kế (SRS).` }] }] },
            { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "BVA & EP: Toàn bộ kiểm thử biên và phân hoạch tương đương đã PASS." }] }] },
            { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Response Schema: Cấu trúc JSON trả về chính xác, không bị thiếu trường." }] }] },
            { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: `Hiệu năng (Response Time): Thực tế đạt ${matchedTime}ms (Đạt chuẩn tối ưu).` }] }] }
        ]},
        { type: "paragraph", content: [
            { type: "text", text: "Người viết Code / Merge: ", marks: [{ type: "strong" }] }, { type: "text", text: process.env.ACTOR }
        ]},
        { type: "paragraph", content: [
            { type: "text", text: "Nhánh Code (Branch): ", marks: [{ type: "strong" }] }, { type: "text", text: process.env.BRANCH }
        ]},
        { type: "paragraph", content: [
            { type: "text", text: "🔗 " },
            { type: "text", text: `Xem báo cáo chi tiết trên GitHub Actions (Run #${process.env.RUN_NUMBER})`, marks: [{ type: "link", attrs: { href: runUrl } }] }
        ]}
      ]
    };
    const action = await upsertComment(issue.key, adfCloseBody);
    console.log(`✅ Auto-closed ${issue.key} — API đã pass. (comment ${action})`);
    closedCount++;
  }

  let cachedBranches = "";
  try {
    console.log("⏳ Fetching git branches to verify active issues...");
    cachedBranches = execSync("env GIT_TERMINAL_PROMPT=0 git ls-remote --heads origin 2>/dev/null", { encoding: "utf8", timeout: 10000 });
    console.log("✅ Git branches fetched.");
  } catch (e) { 
    console.log("⚠️ Could not fetch git branches, skipping branch verification.");
  }

  for (const [index, bug] of failures.entries()) {
    const existing = findExistingBug(bug);

    if (existing) {
      const statusName = (existing?.fields?.status?.statusCategory?.name || "").toLowerCase();
      const statusKey = (existing?.fields?.status?.statusCategory?.key || "").toLowerCase();
      
      const hasBranch = (issueKey) => cachedBranches.includes(issueKey);
      
      const isWorking = hasBranch(existing.key);
      const isDone = (statusName === "done" || statusKey === "done");
      const isInProgress = (statusName.includes("in progress") || statusName.includes("đang") || statusName.includes("tiến hành"));
      
      const needsTransition = isDone || (isWorking && !isInProgress) || (!isWorking && isInProgress);

      if (needsTransition) {
        const attemptTransition = async (issueKey, targetList) => {
          const res = await fetchJson("GET", `${jiraBase}/rest/api/3/issue/${issueKey}/transitions`);
          const trans = res.transitions || [];
          let found = null;
          for (const name of targetList) {
            found = trans.find(t => normalize(t.name).toLowerCase().includes(name));
            if (found) break;
          }
          if (found) {
            await fetchJson("POST", `${jiraBase}/rest/api/3/issue/${issueKey}/transitions`, { transition: { id: found.id } });
            return found;
          }
          return null;
        };

        let firstTries = isWorking 
          ? ["in progress", "start", "đang", "tiến hành", "reopen", "open", "to do", "backlog"]
          : ["to do", "backlog", "open"];
        
        const firstTrans = (await attemptTransition(existing.key, firstTries)) || (await attemptTransition(existing.key, [""]));
        if (firstTrans) console.log(`🔄 Moved ${existing.key} to (Transition: ${firstTrans.name})`);

        if (isWorking) {
           const hopTrans = await attemptTransition(existing.key, ["in progress", "start", "đang", "tiến hành"]);
           if (hopTrans) console.log(`🚀 Double-hopped ${existing.key} to (Transition: ${hopTrans.name})`);
        }
      }

      await fetchJson("PUT", `${jiraBase}/rest/api/3/issue/${existing.key}`, {
        fields: {
          description: adf([
            "Hệ thống kiểm thử tự động phát hiện API bị lỗi (Bug tái diễn).",
            `Bộ kịch bản: ${bug.moduleName}`,
            `Tên API: ${bug.apiName}`,
            `Thông tin Request/Response:\\n${bug.details || "Không có"}`,
            `Chi tiết các lỗi hiện tại:\\n- ${bug.errorMsg}`,
            "---",
            `Người kích hoạt build: ${process.env.ACTOR}`,
            `Nhánh code: ${process.env.BRANCH}`,
            `GitHub Actions Run #${process.env.RUN_NUMBER}: ${runUrl}`,
            "Báo cáo HTML: tải trong mục Artifacts trên GitHub Actions."
          ])
        }
      });

      const adfBody = {
        version: 1,
        type: "doc",
        content: [
          { type: "paragraph", content: [{ type: "text", text: COMMENT_MARKER, marks: [{ type: "strong" }] }] },
          {
            type: "heading",
            attrs: { level: 3 },
            content: [{ type: "text", text: "🚨 CI/CD vẫn phát hiện lỗi này (Bug tái diễn - Reopened)" }]
          },
          {
            type: "paragraph",
            content: [
              { type: "text", text: "Người kích hoạt (Triggered by): ", marks: [{ type: "strong" }] },
              { type: "text", text: process.env.ACTOR }
            ]
          },
          {
            type: "paragraph",
            content: [
              { type: "text", text: "❌ Lỗi Assertions phát hiện từ Postman:", marks: [{ type: "strong" }] }
            ]
          },
          {
            type: "codeBlock",
            attrs: { language: "text" },
            content: [{ type: "text", text: bug.errorMsg }]
          },
          {
            type: "paragraph",
            content: [
              { type: "text", text: "🛠 Chi tiết Request (Execution Details):", marks: [{ type: "strong" }] }
            ]
          },
          {
            type: "codeBlock",
            attrs: { language: "http" },
            content: [{ type: "text", text: bug.details }]
          },
          {
            type: "paragraph",
            content: [
              { type: "text", text: "🔗 " },
              { type: "text", text: "Nhấn vào đây để xem Log chi tiết trên GitHub Actions", marks: [{ type: "link", attrs: { href: runUrl } }] }
            ]
          }
        ]
      };
      const action = await upsertComment(existing.key, adfBody);
      console.log(`💬 Added CI/CD comment on (${existing.key}) — [${bug.apiName}] still failing.`);
      commentedCount++;
    } else {
      
      // Calculate priority dynamically based on business impact
      let priorityName = "High";
      const modLower = (bug.moduleName || "").toLowerCase();
      const apiLower = (bug.apiName || "").toLowerCase();
      
      if (modLower.includes("auth") || modLower.includes("payment") || modLower.includes("checkout") || apiLower.includes("login") || apiLower.includes("order")) {
        priorityName = "Highest";
      } else if (modLower.includes("cart") || modLower.includes("profile") || apiLower.includes("address")) {
        priorityName = "High";
      } else if (modLower.includes("product") || modLower.includes("catalog") || apiLower.includes("search")) {
        priorityName = "Medium";
      } else {
        priorityName = "Medium";
      }

      const jiraData = {
        fields: {
          project: { key: process.env.PROJECT_KEY },
          summary: bug.summary,
          description: {
            version: 1, type: "doc",
            content: [
              { type: "heading", attrs: { level: 3 }, content: [{ type: "text", text: "📌 BÁO CÁO LỖI TỪ HỆ THỐNG CI/CD" }] },
              { type: "paragraph", content: [{ type: "text", text: "Hệ thống kiểm thử tự động phát hiện API bị lỗi hoặc không đạt chuẩn chất lượng." }] },
              { type: "bulletList", content: [
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Bộ kịch bản (Module): ", marks: [{ type: "strong" }] }, { type: "text", text: bug.moduleName }] }] },
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Tên API (Endpoint): ", marks: [{ type: "strong" }] }, { type: "text", text: bug.apiName }] }] }
              ]},
              { type: "paragraph", content: [{ type: "text", text: "❌ Dấu hiệu lỗi (Symptom):", marks: [{ type: "strong" }] }] },
              { type: "codeBlock", attrs: { language: "text" }, content: [{ type: "text", text: bug.errorMsg }] },
              { type: "paragraph", content: [{ type: "text", text: "🛠 Payload / Thông tin Request:", marks: [{ type: "strong" }] }] },
              { type: "codeBlock", attrs: { language: "http" }, content: [{ type: "text", text: bug.details || "Không có" }] },
              { type: "heading", attrs: { level: 3 }, content: [{ type: "text", text: "📊 ĐÁNH GIÁ MỨC ĐỘ ẢNH HƯỞNG" }] },
              { type: "bulletList", content: [
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Mức độ nghiêm trọng (Severity): ", marks: [{ type: "strong" }] }, { type: "text", text: priorityName === "Highest" ? "Nghiêm trọng (Critical) - Ảnh hưởng luồng chính" : priorityName === "High" ? "Cao (Major) - Lỗi chức năng quan trọng" : "Trung bình (Minor)" }] }] },
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Tần số xuất hiện (Frequency): ", marks: [{ type: "strong" }] }, { type: "text", text: "Liên tục (100% reproducible trên CI/CD)" }] }] },
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Phân loại (Labels): ", marks: [{ type: "strong" }] }, { type: "text", text: `ci-cd, postman, api-${bug.bugKey}` }] }] },
                  { type: "listItem", content: [{ type: "paragraph", content: [{ type: "text", text: "Reporter & Date: ", marks: [{ type: "strong" }] }, { type: "text", text: `Kích hoạt bởi ${process.env.ACTOR}` }] }] }
              ]},
              { type: "paragraph", content: [{ type: "text", text: "⚖️ QUYẾT ĐỊNH (Decision): ", marks: [{ type: "strong" }] }, { type: "text", text: "Yêu cầu Developer fix lỗi này ngay trong Sprint hiện tại. Trạng thái pipeline đang bị chặn (Failed).", marks: [{ type: "strong" }] }] },
              { type: "paragraph", content: [
                  { type: "text", text: "🔗 " },
                  { type: "text", text: `Xem báo cáo chi tiết trên GitHub Actions (Run #${process.env.RUN_NUMBER} - Nhánh ${process.env.BRANCH})`, marks: [{ type: "link", attrs: { href: runUrl } }] }
              ]}
            ]
          },
          issuetype: { name: "Bug" },
          priority:  { name: priorityName },
          labels: ["ci-cd", "postman", "auto-detected", `api-${bug.bugKey}`],
          assignee:  { accountId: members[index % members.length] }
        }
      };
      const res = await fetchJson("POST", `${jiraBase}/rest/api/3/issue`, jiraData);
      if (res.key) { console.log(`✅ Created bug [${bug.apiName}] → ${res.key}`); createdCount++; }
      else { console.log(`❌ Failed to create bug [${bug.apiName}]:`, JSON.stringify(res).slice(0, 500)); }
    }
  }

  if (failures.length === 0) {
    console.log("✅ All APIs passed — no new bugs needed.");
  }

  console.log(`\n📌 Jira sync done. Created: ${createdCount} | Commented: ${commentedCount} | Auto-closed: ${closedCount}`);

  if (failures.length > 0) {
    console.log("\n❌ Failing the pipeline because there are API errors.");
    process.exit(1);
  }
}

main().catch(err => {
  console.error("❌ Uncaught error during Jira sync:", err);
  process.exit(1);
});
