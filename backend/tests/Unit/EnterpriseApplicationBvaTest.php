<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Application\AuthService;
use App\Application\CartService;
use App\Application\PrescriptionService;
use App\Application\SupportTicketService;
use App\Application\OrderService;
use Core\Database;
use PDO;
use PDOStatement;
use Exception;

/**
 * BỘ TEST ENTERPRISE BVA (REAL DATABASE INTEGRATION)
 * 
 * LÝ THUYẾT:
 * Thay vì Mock DB, ở đây chúng ta test SÂU vào TẬN ĐÁY của hệ thống bằng cách
 * kết nối trực tiếp với CSDL thật. Các Service sẽ chạy 100% logic thật của mình,
 * bao gồm cả việc kết nối PDO thật. Exception quăng ra là Exception thật từ lõi nghiệp vụ.
 */
class EnterpriseApplicationBvaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // KẾT NỐI DATABASE THẬT TỪ HỆ THỐNG
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }
        
        require_once APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Infrastructure' . DIRECTORY_SEPARATOR . 'env.php';
        require_once APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Infrastructure' . DIRECTORY_SEPARATOR . 'database.php';
        
        try {
            connect_application_database();
        } catch (\Throwable $e) {
            // Đã kết nối, bỏ qua lỗi nếu gọi nhiều lần
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==========================================
    // 1. AUTH SERVICE (BVA)
    // ==========================================

    public function test_auth_password_below_boundary_throws_exception(): void
    {
        $authService = new AuthService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid password. Must be between 6 and 50 characters.");

        $authService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => '12345' // 5 chars
        ]);
    }

    public function test_auth_password_above_boundary_throws_exception(): void
    {
        $authService = new AuthService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid password. Must be between 6 and 50 characters.");

        $authService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => str_repeat('a', 51)
        ]);
    }

    // ==========================================
    // 2. CART SERVICE (BVA)
    // ==========================================

    public function test_cart_quantity_below_boundary_throws_exception(): void
    {
        $cartService = new CartService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid quantity. Cart quantity must be between 1 and 99.");

        $cartService->addItem(1, [
            'variant_id' => 10,
            'quantity' => 0
        ]);
    }

    public function test_cart_quantity_above_boundary_throws_exception(): void
    {
        $cartService = new CartService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid quantity. Cart quantity must be between 1 and 99.");

        $cartService->addItem(1, [
            'variant_id' => 10,
            'quantity' => 100
        ]);
    }

    // ==========================================
    // 3. PRESCRIPTION SERVICE (BVA)
    // ==========================================

    public function test_prescription_sph_below_boundary_throws_exception(): void
    {
        $service = new PrescriptionService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("SPH OD value (-20.25) is invalid. Must be between -20 and 20.");

        $service->savePrescription(1, [
            'sph_od' => -20.25
        ]);
    }

    public function test_prescription_sph_above_boundary_throws_exception(): void
    {
        $service = new PrescriptionService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("SPH OD value (20.25) is invalid. Must be between -20 and 20.");

        $service->savePrescription(1, [
            'sph_od' => 20.25
        ]);
    }

    // ==========================================
    // 4. SUPPORT TICKET SERVICE (BVA)
    // ==========================================

    public function test_ticket_message_length_below_boundary_throws_exception(): void
    {
        $service = new SupportTicketService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ticket message must be between 10 and 1000 characters.");

        $service->createTicket(1, "Subject", "Too short");
    }

    public function test_ticket_message_length_above_boundary_throws_exception(): void
    {
        $service = new SupportTicketService();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ticket message must be between 10 and 1000 characters.");

        $service->createTicket(1, "Subject", str_repeat("a", 1001));
    }

    // ==========================================
    // 5. ADMIN SERVICE (VOUCHER BVA)
    // ==========================================

    public function test_voucher_percentage_below_boundary_throws_exception(): void
    {
        $service = new \App\Application\AdminService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Percentage discount must be between 1 and 100.");

        $service->createVoucher([
            'code' => 'TEST0',
            'title' => 'Test',
            'discount_type' => 'percentage',
            'discount_value' => 0, // Dưới biên
            'starts_at' => date('Y-m-d H:i:s'),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);
    }

    public function test_voucher_percentage_above_boundary_throws_exception(): void
    {
        $service = new \App\Application\AdminService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Percentage discount must be between 1 and 100.");

        $service->createVoucher([
            'code' => 'TEST101',
            'title' => 'Test',
            'discount_type' => 'percentage',
            'discount_value' => 101, // Trên biên
            'starts_at' => date('Y-m-d H:i:s'),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);
    }

    // ==========================================
    // 6. INVENTORY SERVICE (STOCK BVA)
    // ==========================================

    public function test_inventory_stock_below_zero_throws_exception(): void
    {
        $service = new \App\Application\InventoryService();
        $db = \Core\Database::getInstance();
        
        // 1. Tạo một User tạm thời để làm Staff
        $tempEmail = 'test_staff_' . time() . '@example.com';
        $db->exec("INSERT INTO `user` (full_name, email, password_hash, status) VALUES ('Test Staff', '{$tempEmail}', 'hash', 'active')");
        $staffId = (int)$db->lastInsertId();

        // 2. Lấy Role ADMIN
        $roleStmt = $db->query("SELECT id FROM role WHERE name = 'ADMIN' LIMIT 1");
        $role = $roleStmt->fetch();
        if (!$role) {
            $db->exec("INSERT IGNORE INTO role (name, description) VALUES ('ADMIN', 'System Administrator')");
            $roleId = (int)$db->lastInsertId();
        } else {
            $roleId = (int)$role['id'];
        }

        // 3. Gán Role cho Staff
        $db->exec("INSERT INTO user_roles (user_id, role_id) VALUES ({$staffId}, {$roleId})");

        // 4. Test BVA (Bắt lỗi Stock < 0)
        try {
            $service->updateStockQuantities($staffId, [
                [
                    'variant_id' => 10,
                    'quantity' => -1 // Dưới biên
                ]
            ]);
            $this->fail("Expected Exception was not thrown");
        } catch (\Exception $e) {
            $this->assertStringContainsString("Invalid stock. Stock quantity cannot be less than 0.", $e->getMessage());
        } finally {
            // 5. Dọn dẹp Database (Teardown)
            $db->exec("DELETE FROM user_roles WHERE user_id = {$staffId}");
            $db->exec("DELETE FROM `user` WHERE id = {$staffId}");
        }
    }

    // ==========================================
    // 7. PAYMENT SERVICE (EP)
    // ==========================================

    public function test_payment_unsupported_method_throws_exception(): void
    {
        $service = new \App\Application\PaymentService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Unsupported payment method");

        $service->processPayment(999999, 'crypto', 100.0);
    }

    // ==========================================
    // 8. TICKET SERVICE (PRIORITY EP)
    // ==========================================

    public function test_ticket_unsupported_priority_throws_exception(): void
    {
        $service = new \App\Application\SupportTicketService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Unsupported ticket priority.");

        $service->createTicket(1, "Subject", "1234567890", null, 'urgent');
    }

    // ==========================================
    // 9. AUTH SERVICE (EMAIL EP)
    // ==========================================

    public function test_auth_invalid_email_format_throws_exception(): void
    {
        $service = new \App\Application\AuthService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid email format.");

        $service->register([
            'email' => 'userexample.com', // Thiếu @
            'password' => '12345678',
            'name' => 'Test'
        ]);
    }

    // ==========================================
    // 10. ORDER SERVICE (WORKFLOW EP)
    // ==========================================

    public function test_order_invalid_state_transition_throws_exception(): void
    {
        $service = new \App\Application\OrderService();
        $db = \Core\Database::getInstance();

        // Tạo order giả có trạng thái 'processing'
        $db->exec("INSERT INTO `order` (user_id, order_number, status, total_amount, shipping_address, placed_at) VALUES (1, 'ORD-TEST-123', 'processing', 100, 'Test Address', NOW())");
        $orderId = (int)$db->lastInsertId();

        try {
            $service->transitionStatus($orderId, 'paid', 1); // Không thể lùi từ processing về paid
            $this->fail("Expected Exception was not thrown");
        } catch (\Exception $e) {
            $this->assertStringContainsString("Invalid status transition from processing to paid.", $e->getMessage());
        } finally {
            $db->exec("DELETE FROM `order` WHERE id = {$orderId}");
        }
    }
    // ==========================================
    // 11. CATALOG SERVICE (PRICE BVA)
    // ==========================================

    public function test_catalog_price_below_boundary_throws_exception(): void
    {
        $service = new \App\Application\CatalogService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid price boundaries.");

        $service->createProduct([
            'name' => 'BVA Test Product',
            'base_price' => -1
        ]);
    }

    public function test_catalog_price_above_boundary_throws_exception(): void
    {
        $service = new \App\Application\CatalogService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid price boundaries.");

        $service->createProduct([
            'name' => 'BVA Test Product',
            'base_price' => 100000001
        ]);
    }
}
