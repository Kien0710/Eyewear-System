const https = require('https');

// ==========================================
// THAY 3 THÔNG TIN NÀY BẰNG CỦA BẠN:
// ==========================================
const JIRA_DOMAIN = "kien7708.atlassian.net"; // Domain Jira của bạn (lấy trên thanh URL)
const JIRA_EMAIL = "nhutp2945@gmail.com"; // Email đăng nhập Jira của bạn
const JIRA_API_TOKEN = "HIDDEN_SECRET_TOKEN_VUI_LONG_NHAP_LAI_KHI_CHAY_LOCAL"; // Token Jira (Lấy từ Jira, không phải OpenAI)

const PROJECT_KEY = "ESQ";
const auth = Buffer.from(`${JIRA_EMAIL}:${JIRA_API_TOKEN}`).toString("base64");

function fetchJson(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: JIRA_DOMAIN,
      path: path,
      method: method,
      headers: {
        "Authorization": `Basic ${auth}`,
        "Content-Type": "application/json",
        "Accept": "application/json"
      }
    };
    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try { resolve(data ? JSON.parse(data) : {}); } catch (e) { resolve({}); }
      });
    });
    req.on('error', reject);
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

async function run() {
  const fieldId = "customfield_10016"; // Story point estimate default ID
  console.log(`✅ Sử dụng Story Point mang mã: ${fieldId}`);

  console.log("🔎 Đang lấy danh sách các thẻ Bug trong dự án...");
  const searchBody = {
    jql: `project=${PROJECT_KEY} AND issuetype=Bug`,
    maxResults: 200,
    fields: ["summary", "issuetype", "customfield_10016"]
  };
  const search = await fetchJson("POST", `/rest/api/3/search/jql`, searchBody);
  const issues = search.issues || [];
  
  console.log(`✅ Tìm thấy ${issues.length} thẻ Bug. Tiến hành chấm điểm tự động...`);

  for (const issue of issues) {
    const summary = (issue.fields.summary || "").toLowerCase();
    
    // Thuật toán tính điểm (tuỳ biến theo tên tính năng)
    let point = 2; // Mặc định
    if (summary.includes("login") || summary.includes("checkout") || summary.includes("order")) point = 5;
    else if (summary.includes("cart") || summary.includes("ticket") || summary.includes("address") || summary.includes("voucher")) point = 3;

    // Gửi lệnh update điểm lên Jira
    const updateBody = { fields: {} };
    updateBody.fields[fieldId] = point; // Gán điểm vào Custom Field
    
    await fetchJson("PUT", `/rest/api/3/issue/${issue.key}`, updateBody);
    console.log(`🎯 Đã cập nhật thẻ [${issue.key}] thành ${point} điểm.`);
  }
  
  console.log("🎉 Hoàn tất! Lên F5 lại trang Jira kiểm tra nhé.");
}

run();
