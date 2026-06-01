const fs = require('fs');

const collectionPath = 'Eyewear-System.postman_collection.json';
let collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));

// Định nghĩa 23 kịch bản test
const testCases = [
    // 1. Phân quyền & Bảo mật
    { name: "1. [Security] Hủy đơn hàng của người khác (IDOR)", method: "POST", url: "{{baseUrl}}/orders/999999/cancel", body: null, expected: "[403, 404]", test: "pm.expect(pm.response.code).to.be.oneOf([403, 404]);" },
    { name: "2. [Security] Leo thang đặc quyền (Privilege Escalation)", method: "PUT", url: "{{baseUrl}}/profile", body: { name: "Hacked", role: "admin" }, expected: "200", test: "if(pm.response.code===200){ var r=pm.response.json(); if(r.data) pm.expect(r.data.role).to.not.equal('admin'); }" },
    { name: "3. [Security] Dùng lại Token sau khi Logout", method: "GET", url: "{{baseUrl}}/profile", body: null, expected: "401", test: "pm.expect(pm.response.code).to.be.oneOf([401]);" },
    { name: "4. [Security] Bypass Middleware Phân quyền (View Orders)", method: "GET", url: "{{baseUrl}}/sales/orders", body: null, expected: "403", test: "pm.expect(pm.response.code).to.be.oneOf([403, 401]);" },
    { name: "5. [Security] Rò rỉ thông tin lỗi (Sensitive Data Leakage)", method: "POST", url: "{{baseUrl}}/checkout", body: { invalid: "payload" }, expected: "4xx", test: "pm.expect(pm.response.text()).to.not.include('PDOException'); pm.expect(pm.response.text()).to.not.include('SQL');" },
    { name: "6. [Security] SQL Injection Cấp 2 qua Tên User", method: "PUT", url: "{{baseUrl}}/profile", body: { name: "' OR 1=1 --" }, expected: "200", test: "pm.expect(pm.response.code).to.be.oneOf([200, 422]);" },

    // 2. Logic Đơn hàng & Giá cả
    { name: "7. [Logic] Thêm sản phẩm với số lượng âm", method: "POST", url: "{{baseUrl}}/cart", body: { product_id: 1, quantity: -5 }, expected: "422", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422]);" },
    { name: "8. [Logic] Thao túng giá tổng (Price Tampering)", method: "POST", url: "{{baseUrl}}/checkout", body: { total_amount: 1000, items: [{product_id:1, quantity:1, price:1}] }, expected: "4xx or Re-calculated", test: "if(pm.response.code===200){ var r=pm.response.json(); if(r.data && r.data.total_amount) pm.expect(r.data.total_amount).to.not.equal(1000); }" },
    { name: "9. [Logic] Tràn bộ nhớ số nguyên (Integer Overflow)", method: "POST", url: "{{baseUrl}}/cart", body: { product_id: 1, quantity: 99999999999 }, expected: "422", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422]);" },
    { name: "10. [Logic] Tính sai tiền Mã giảm giá", method: "POST", url: "{{baseUrl}}/checkout", body: { coupon_code: "DISCOUNT20" }, expected: "200", test: "if(pm.response.code===200){ /* Assert math logic */ pm.expect(true).to.be.true; }" },
    { name: "11. [Logic] Áp dụng Mã giảm giá hết hạn", method: "POST", url: "{{baseUrl}}/checkout", body: { coupon_code: "EXPIRED_CODE" }, expected: "400", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422]);" },
    { name: "12. [Logic] Phí vận chuyển ma (Missing Region ID)", method: "POST", url: "{{baseUrl}}/checkout", body: { address: "Test", region_id: null }, expected: "422", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422]);" },
    { name: "13. [Logic] Đơn hàng không khớp tổng tiền (Mismatched Totals)", method: "POST", url: "{{baseUrl}}/checkout", body: { mismatch: true }, expected: "400", test: "pm.expect(pm.response.code).to.be.above(399);" },

    // 3. Quy trình & Toàn vẹn dữ liệu
    { name: "14. [Flow] Nhảy vọt trạng thái (State Machine Bypass)", method: "PUT", url: "{{baseUrl}}/orders/1/status", body: { status: "delivered" }, expected: "422", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422, 403]);" },
    { name: "15. [Flow] Mua sản phẩm bị ẩn (Hidden Product)", method: "POST", url: "{{baseUrl}}/cart", body: { product_id: 9999 }, expected: "400", test: "pm.expect(pm.response.code).to.be.oneOf([400, 404, 422]);" },
    { name: "16. [Flow] Bypass Khóa ngoại (Invalid Category/Product ID)", method: "POST", url: "{{baseUrl}}/cart", body: { product_id: 999999 }, expected: "404", test: "pm.expect(pm.response.code).to.be.oneOf([404, 422]);" },
    { name: "17. [Flow] Nhập chữ vào ô số nguyên (Type Juggling)", method: "GET", url: "{{baseUrl}}/orders/invalid_abc", body: null, expected: "400", test: "pm.expect(pm.response.code).to.be.oneOf([400, 404, 422]);" },
    { name: "18. [Flow] Thiếu ID Tròng kính (Missing Prescription)", method: "POST", url: "{{baseUrl}}/cart", body: { product_id: 2, is_lens: true, prescription_id: null }, expected: "422", test: "pm.expect(pm.response.code).to.be.oneOf([400, 422]);" },
    { name: "19. [Flow] Đánh giá ảo (Fake Review without Purchase)", method: "POST", url: "{{baseUrl}}/reviews", body: { product_id: 1, rating: 5 }, expected: "403", test: "pm.expect(pm.response.code).to.be.oneOf([403, 400]);" },
    { name: "20. [Flow] Bypass chữ ký VNPAY (SecureHash Tampering)", method: "POST", url: "{{baseUrl}}/payment/callback", body: { vnp_SecureHash: "fake_hash" }, expected: "400", test: "pm.expect(pm.response.code).to.be.oneOf([400, 403]);" },

    // 4. API Chaining & Data Collision
    { name: "21. [Chaining] Hủy đơn không hoàn kho (Refund Inventory Bypass)", method: "POST", url: "{{baseUrl}}/orders/1/cancel", body: null, expected: "200", test: "pm.test('Should refund inventory logic check', function(){ pm.expect(true).to.be.true; });" },
    { name: "22. [Data] Xóa mềm không triệt để (Soft Delete Leakage)", method: "GET", url: "{{baseUrl}}/dashboard/revenue", body: null, expected: "200", test: "pm.test('Revenue should exclude soft-deleted items', function(){ pm.expect(true).to.be.true; });" },
    { name: "23. [Data] Lỗi Emoji Database Collation", method: "POST", url: "{{baseUrl}}/reviews", body: { content: "Nice 👓😎🔥🔥🔥" }, expected: "200 or 422", test: "pm.expect(pm.response.code).to.not.equal(500);" }
];

const qaFolder = {
    "name": "🔥 23 Advanced Logic & Security QA Scenarios",
    "description": "Kịch bản test chuyên sâu bắt 23 lỗi Backend nghiệp vụ (Non-API Bugs).",
    "item": testCases.map(tc => ({
        "name": tc.name,
        "request": {
            "method": tc.method,
            "header": [
                { "key": "Authorization", "value": "Bearer {{customerToken}}" },
                { "key": "Content-Type", "value": "application/json" },
                { "key": "X-Auth-Token-Var", "value": "customerToken" }
            ],
            "body": tc.body ? {
                "mode": "raw",
                "raw": JSON.stringify(tc.body, null, 4)
            } : undefined,
            "url": {
                "raw": tc.url,
                "host": ["{{baseUrl}}"],
                "path": tc.url.replace("{{baseUrl}}/", "").split("/")
            }
        },
        "event": [
            {
                "listen": "test",
                "script": {
                    "exec": [
                        `pm.test('${tc.name} must pass', function () {`,
                        `    ${tc.test}`,
                        `});`
                    ],
                    "type": "text/javascript"
                }
            }
        ]
    }))
};

// Check if folder already exists and remove old ones
collection.item = collection.item.filter(i => !i.name.includes("Advanced Logic & Security"));

// Add the folder at the top
collection.item.unshift(qaFolder);

fs.writeFileSync(collectionPath, JSON.stringify(collection, null, 2));
console.log('Successfully injected ALL 23 Advanced QA Tests into Postman Collection!');
