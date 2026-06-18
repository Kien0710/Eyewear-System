<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Middleware\AuthMiddleware;

/**
 * Unit tests for AuthMiddleware
 * Kiểm tra logic xác thực Bearer token.
 */
class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset auth state
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
            $_SERVER['AUTH_TOKEN'],
            $_SERVER['AUTH_USER_ID'],
            $_SERVER['AUTH_USER_ROLE'],
            $_SERVER['AUTH_TOKEN_GUARD']
        );
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
            $_SERVER['AUTH_TOKEN'],
            $_SERVER['AUTH_USER_ID'],
            $_SERVER['AUTH_USER_ROLE'],
            $_SERVER['AUTH_TOKEN_GUARD']
        );
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────
    // Missing / empty token
    // ─────────────────────────────────────────────────────────

    public function test_returns_false_when_no_authorization_header(): void
    {
        ob_start();
        $result = AuthMiddleware::handle();
        $output = ob_get_clean();

        $this->assertFalse($result);
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
    }

    public function test_returns_false_when_empty_authorization_header(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = '';

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────
    // Invalid tokens
    // ─────────────────────────────────────────────────────────

    public function test_returns_false_for_non_base64_token(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer !!!invalid-base64!!!';

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertFalse($result);
    }

    public function test_returns_false_for_token_without_colon_separator(): void
    {
        // base64 of "justtext" — no colon separator
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('justtext');

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertFalse($result);
    }

    public function test_returns_false_for_token_with_non_numeric_user_id(): void
    {
        // base64 of "abc:admin" — user_id is not numeric
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('abc:admin');

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────
    // Valid tokens
    // ─────────────────────────────────────────────────────────

    public function test_returns_true_for_valid_token(): void
    {
        // base64 of "1:admin" — valid format
        $token = base64_encode('1:admin');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals(1, $_SERVER['AUTH_USER_ID']);
        $this->assertEquals('admin', $_SERVER['AUTH_USER_ROLE']);
        $this->assertEquals($token, $_SERVER['AUTH_TOKEN']);
    }

    public function test_valid_token_with_customer_role(): void
    {
        $token = base64_encode('42:customer');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals(42, $_SERVER['AUTH_USER_ID']);
        $this->assertEquals('customer', $_SERVER['AUTH_USER_ROLE']);
    }

    public function test_valid_token_with_three_parts(): void
    {
        // base64 of "5:staff:extra" — 3 parts, should still work
        $token = base64_encode('5:staff:extra');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals(5, $_SERVER['AUTH_USER_ID']);
        $this->assertEquals('staff', $_SERVER['AUTH_USER_ROLE']);
    }

    // ─────────────────────────────────────────────────────────
    // Guard parameter
    // ─────────────────────────────────────────────────────────

    public function test_stores_guard_parameter(): void
    {
        $token = base64_encode('1:admin');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        ob_start();
        $result = AuthMiddleware::handle('sanctum');
        ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals('sanctum', $_SERVER['AUTH_TOKEN_GUARD']);
    }

    // ─────────────────────────────────────────────────────────
    // REDIRECT_HTTP_AUTHORIZATION fallback
    // ─────────────────────────────────────────────────────────

    public function test_reads_from_redirect_http_authorization(): void
    {
        $token = base64_encode('10:customer');
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals(10, $_SERVER['AUTH_USER_ID']);
    }

    // ─────────────────────────────────────────────────────────
    // Non-Bearer auth header
    // ─────────────────────────────────────────────────────────

    public function test_returns_false_for_basic_auth_header(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        ob_start();
        $result = AuthMiddleware::handle();
        ob_get_clean();

        $this->assertFalse($result);
    }
}
