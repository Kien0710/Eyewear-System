<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Core\ApiResponse;

/**
 * Unit tests for Core\ApiResponse
 * Kiểm tra tất cả các phương thức trả response chuẩn của hệ thống.
 */
class ApiResponseTest extends TestCase
{
    // ─────────────────────────────────────────────────────────
    // success()
    // ─────────────────────────────────────────────────────────

    public function test_success_returns_correct_structure(): void
    {
        $result = ApiResponse::success(['id' => 1], 'OK');

        $this->assertTrue($result['success']);
        $this->assertEquals(['id' => 1], $result['data']);
        $this->assertEquals('OK', $result['message']);
    }

    public function test_success_without_data(): void
    {
        $result = ApiResponse::success();

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('data', $result);
        $this->assertArrayNotHasKey('message', $result);
    }

    public function test_success_with_only_message(): void
    {
        $result = ApiResponse::success(null, 'Done');

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('data', $result);
        $this->assertEquals('Done', $result['message']);
    }

    public function test_success_with_empty_array_data(): void
    {
        $result = ApiResponse::success([]);

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['data']);
    }

    // ─────────────────────────────────────────────────────────
    // created()
    // ─────────────────────────────────────────────────────────

    public function test_created_returns_success_with_default_message(): void
    {
        $result = ApiResponse::created(['id' => 42]);

        $this->assertTrue($result['success']);
        $this->assertEquals(['id' => 42], $result['data']);
        $this->assertEquals('Created successfully', $result['message']);
    }

    public function test_created_with_custom_message(): void
    {
        $result = ApiResponse::created(null, 'Product created');

        $this->assertTrue($result['success']);
        $this->assertEquals('Product created', $result['message']);
    }

    // ─────────────────────────────────────────────────────────
    // paginated()
    // ─────────────────────────────────────────────────────────

    public function test_paginated_returns_correct_meta(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $result = ApiResponse::paginated($items, 50, 2, 15);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(50, $result['meta']['total']);
        $this->assertEquals(2, $result['meta']['page']);
        $this->assertEquals(15, $result['meta']['per_page']);
        $this->assertEquals(4, $result['meta']['last_page']); // ceil(50/15) = 4
    }

    public function test_paginated_with_zero_per_page_does_not_divide_by_zero(): void
    {
        $result = ApiResponse::paginated([], 10, 1, 0);

        $this->assertEquals(10, $result['meta']['last_page']); // ceil(10/1)
    }

    public function test_paginated_with_empty_items(): void
    {
        $result = ApiResponse::paginated([], 0, 1, 10);

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['meta']['total']);
        $this->assertEquals(0, $result['meta']['last_page']);
    }

    // ─────────────────────────────────────────────────────────
    // Error responses
    // ─────────────────────────────────────────────────────────

    public function test_unauthorized_returns_correct_structure(): void
    {
        $result = ApiResponse::unauthorized();

        $this->assertFalse($result['success']);
        $this->assertEquals('Unauthorized', $result['message']);
    }

    public function test_unauthorized_with_custom_message(): void
    {
        $result = ApiResponse::unauthorized('Token expired');

        $this->assertFalse($result['success']);
        $this->assertEquals('Token expired', $result['message']);
    }

    public function test_forbidden_returns_correct_structure(): void
    {
        $result = ApiResponse::forbidden();

        $this->assertFalse($result['success']);
        $this->assertEquals('Forbidden', $result['message']);
    }

    public function test_not_found_returns_correct_structure(): void
    {
        $result = ApiResponse::notFound();

        $this->assertFalse($result['success']);
        $this->assertEquals('Resource not found', $result['message']);
    }

    public function test_not_found_with_custom_message(): void
    {
        $result = ApiResponse::notFound('Product not found');

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found', $result['message']);
    }

    public function test_error_returns_correct_structure(): void
    {
        $result = ApiResponse::error('Bad request');

        $this->assertFalse($result['success']);
        $this->assertEquals('Bad request', $result['message']);
    }

    public function test_server_error_returns_correct_structure(): void
    {
        $result = ApiResponse::serverError();

        $this->assertFalse($result['success']);
        $this->assertEquals('Internal server error', $result['message']);
    }

    public function test_server_error_with_custom_message(): void
    {
        $result = ApiResponse::serverError('Database connection failed');

        $this->assertFalse($result['success']);
        $this->assertEquals('Database connection failed', $result['message']);
    }

    // ─────────────────────────────────────────────────────────
    // validationError()
    // ─────────────────────────────────────────────────────────

    public function test_validation_error_without_field_errors(): void
    {
        $result = ApiResponse::validationError('Validation failed');

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_validation_error_with_field_errors(): void
    {
        $errors = ['email' => 'Email is required', 'name' => 'Name is required'];
        $result = ApiResponse::validationError('Validation failed', $errors);

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertEquals($errors, $result['errors']);
    }
}
