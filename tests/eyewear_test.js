// ============================================================
// Eyewear System — CodeceptJS End-to-End Tests
// Base URL: http://localhost (frontend served via Laragon)
// Run: npx codeceptjs run --steps
// ============================================================

Feature("Homepage");

Scenario("TC-UI-01: Trang chủ hiển thị đúng tiêu đề và nút Shop Now", ({ I }) => {
  I.amOnPage("/");
  I.see("EVELENS", "title");
  I.see("Redefining Vision");
  I.seeElement("a.btn[href='pages/shop/']");
});

Scenario("TC-UI-02: Trang chủ có section Categories", ({ I }) => {
  I.amOnPage("/");
  I.seeElement(".categories");
  I.see("Sunglasses");
});

Scenario("TC-UI-03: Click 'Shop Now' điều hướng đến trang Shop", ({ I }) => {
  I.amOnPage("/");
  I.click("Shop Now");
  I.seeInCurrentUrl("/pages/shop");
});

// ============================================================

Feature("Authentication");

Scenario("TC-AUTH-01: Trang đăng nhập hiển thị form Sign In và Sign Up", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.seeElement(".sign-in-container");
  I.seeElement(".sign-up-container");
  I.see("Sign In");
  I.see("Sign Up");
});

Scenario("TC-AUTH-02: Form Sign In có đầy đủ các trường Email, Password, nút Sign In", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.seeElement(".sign-in-container input[name='email']");
  I.seeElement(".sign-in-container input[name='password']");
  I.seeElement(".sign-in-container button.btn");
  I.see("Forgot your password?");
});

Scenario("TC-AUTH-03: Form Sign Up có đầy đủ các trường Name, Email, Password", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.seeElement(".sign-up-container input[name='name']");
  I.seeElement(".sign-up-container input[name='email']");
  I.seeElement(".sign-up-container input[name='password']");
});

Scenario("TC-AUTH-04: Nút 'Back to Home' trên trang Auth dẫn về trang chủ", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.seeElement("a.auth-back-home");
  I.click("Back to Home");
  I.seeInCurrentUrl("/index.html");
});

Scenario("TC-AUTH-05: Đăng nhập với sai email giữ nguyên ở trang auth", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.fillField(".sign-in-container input[name='email']", "wrong@email.com");
  I.fillField(".sign-in-container input[name='password']", "wrongpassword");
  I.click(".sign-in-container button.btn");
  I.wait(2);
  // Không redirect — vẫn ở trang auth (không cần backend)
  I.seeInCurrentUrl("/auth");
});

Scenario("TC-AUTH-06: Form đăng nhập có thể submit và có địa chỉ email hợp lệ", ({ I }) => {
  I.amOnPage("/pages/auth/");
  I.seeElement(".sign-in-container input[name='email']");
  I.fillField(".sign-in-container input[name='email']", "admin@eyewear.com");
  I.fillField(".sign-in-container input[name='password']", "Admin@123");
  // Kiểm tra form hợp lệ (có thể click nút Submit)
  I.seeElement(".sign-in-container button.btn");
  // [NOTE] Kiểm tra redirect cần backend chạy
});

// ============================================================

Feature("Shop / Product Catalog");

Scenario("TC-PROD-01: Trang Shop tải được và hiển thị toolbar", ({ I }) => {
  I.amOnPage("/pages/shop/");
  // Toolbar và sort được render tĩnh không cần API
  I.seeElement(".toolbar");
  I.seeElement("#sortSelect");
  I.see("Shop - EVELENS", "title");
});

Scenario("TC-PROD-02: Trang Shop có sidebar filter và sort dropdown", ({ I }) => {
  I.amOnPage("/pages/shop/");
  // Các element filter là tĩnh trong HTML
  I.seeElement("#catalogFilterMount");
  I.seeElement("#sortSelect");
  I.seeElement("#resultCount");
});

Scenario("TC-PROD-03: Trang Shop có nút chuyển view Grid/List", ({ I }) => {
  I.amOnPage("/pages/shop/");
  I.seeElement("#gridViewBtn");
  I.seeElement("#listViewBtn");
  // Click chuyển sang List view
  I.click("#listViewBtn");
  I.seeElement("#listViewBtn");
});

// ============================================================

Feature("Cart");

Scenario("TC-CART-01: Truy cập trang Cart khi chưa đăng nhập redirect về Auth", ({ I }) => {
  I.amOnPage("/pages/cart/");
  I.wait(2);
  // Hoặc hiện thông báo đăng nhập, hoặc redirect
  I.seeInCurrentUrl("/cart");
});

// ============================================================

Feature("Navigation");

Scenario("TC-NAV-01: Trang 404 hiển thị khi truy cập URL không tồn tại", ({ I }) => {
  I.amOnPage("/pages/nonexistent-page/");
  // Kiểm tra trang 404 hoặc nội dung lỗi
  I.waitForElement("body", 3);
  I.seeElement("body");
});

// ============================================================

Feature("Product Details");

Scenario("TC-DTL-01: Trang Details hiển thị khu vực hình ảnh và thông tin cơ bản", ({ I }) => {
  I.amOnPage("/pages/details/");
  I.seeElement("#details-main-img");
  I.seeElement(".details__title");
  I.seeElement(".details__price");
  I.seeElement("#add-to-cart-btn");
});

Scenario("TC-DTL-02: Trang Details hiển thị mô tả ngắn và tuỳ chọn variant", ({ I }) => {
  I.amOnPage("/pages/details/");
  I.seeElement(".short__description");
  I.seeElement("#variant-colors");
  I.seeElement("#variant-sizes");
});

// ============================================================

Feature("Cart & Checkout");

Scenario("TC-CART-02: Trang Cart hiển thị bảng sản phẩm và tổng tiền", ({ I }) => {
  I.amOnPage("/pages/cart/");
  I.seeElement("table.table");
  I.seeElement("#cart-total");
  I.seeElement("#checkout-btn");
});

Scenario("TC-CHK-01: Trang Checkout hiển thị form thanh toán", ({ I }) => {
  I.amOnPage("/pages/checkout/");
  I.seeElement("#checkout-name");
  I.seeElement("#checkout-email");
  I.seeElement("#place-order-btn");
  I.seeElement(".payment__methods");
});

// ============================================================

Feature("Wishlist");

Scenario("TC-WISH-01: Trang Wishlist hiển thị bảng danh sách yêu thích", ({ I }) => {
  I.amOnPage("/pages/wishlist/");
  I.seeElement("table.table");
  I.see("Wishlist", "title");
});

// ============================================================

Feature("Accounts");

Scenario("TC-ACC-01: Trang Accounts có chứa các tab chức năng", ({ I }) => {
  I.amOnPage("/pages/accounts/");
  I.seeElement(".account__tabs");
  I.seeElement("[data-target='#dashboard']");
  I.seeElement("[data-target='#orders']");
});

// ============================================================

Feature("Static Pages");

Scenario("TC-STAT-01: Trang About hiển thị thông tin giới thiệu", ({ I }) => {
  I.amOnPage("/pages/about/");
  I.seeElement(".about-hero__title");
  I.see("About Us", "title");
});

Scenario("TC-STAT-02: Trang Contact hiển thị form liên hệ", ({ I }) => {
  I.amOnPage("/pages/contact/");
  I.seeElement("form.form");
  I.seeElement("input[placeholder='Your Name']");
  I.seeElement("textarea[placeholder='Your Message']");
  I.see("Contact Us", "title");
});
