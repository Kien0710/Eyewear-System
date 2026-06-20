<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\Catalog\ProductFilter;

/**
 * Áp dụng kỹ thuật kiểm thử BVA (Boundary Value Analysis - Phân tích giá trị biên)
 * Mục tiêu: Lọc giá sản phẩm (Price Filter)
 * Biên giả định: Giá hợp lệ nằm trong khoảng từ 0 đến 999999.99 (giới hạn DECIMAL(8,2) của cơ sở dữ liệu)
 */
class BoundaryValueAnalysisTest extends TestCase
{
    private ProductFilter $filter;

    // Các giá trị biên (Boundary Values)
    private const MIN_PRICE_LIMIT = 0.0;
    private const MAX_PRICE_LIMIT = 999999.99;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new ProductFilter();
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Biên dưới (Lower Boundary) - min_price
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Test BVA: Dưới biên dưới (min_price < 0) - min - 1
     * Thực tế hệ thống có thể bỏ qua giá âm hoặc vẫn truyền vào tuỳ logic thiết kế.
     * Trong filter hiện tại, nó vẫn nhận giá trị âm, điều này cảnh báo một lỗi tiềm ẩn cần validate.
     */
    public function test_bva_below_lower_boundary_min_price(): void
    {
        $belowMin = self::MIN_PRICE_LIMIT - 1.0; // -1.0
        $result = $this->filter->buildFilterQuery(['min_price' => $belowMin]);
        
        // Hiện tại ProductFilter không chặn số âm, nó đẩy thẳng vào SQL.
        $this->assertStringContainsString('p.base_price >= ?', $result['sql']);
        $this->assertEquals([$belowMin], $result['params']);
    }

    /**
     * Test BVA: Ngay tại biên dưới (min_price = 0) - min
     */
    public function test_bva_at_lower_boundary_min_price(): void
    {
        $atMin = self::MIN_PRICE_LIMIT; // 0.0
        $result = $this->filter->buildFilterQuery(['min_price' => $atMin]);

        $this->assertStringContainsString('p.base_price >= ?', $result['sql']);
        $this->assertEquals([$atMin], $result['params']);
    }

    /**
     * Test BVA: Trên biên dưới (min_price > 0) - min + 1
     */
    public function test_bva_above_lower_boundary_min_price(): void
    {
        $aboveMin = self::MIN_PRICE_LIMIT + 1.0; // 1.0
        $result = $this->filter->buildFilterQuery(['min_price' => $aboveMin]);

        $this->assertStringContainsString('p.base_price >= ?', $result['sql']);
        $this->assertEquals([$aboveMin], $result['params']);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Biên trên (Upper Boundary) - max_price
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Test BVA: Dưới biên trên - max - 1
     */
    public function test_bva_below_upper_boundary_max_price(): void
    {
        $belowMax = self::MAX_PRICE_LIMIT - 1.0; // 999998.99
        $result = $this->filter->buildFilterQuery(['max_price' => $belowMax]);

        $this->assertStringContainsString('p.base_price <= ?', $result['sql']);
        $this->assertEquals([$belowMax], $result['params']);
    }

    /**
     * Test BVA: Ngay tại biên trên - max
     */
    public function test_bva_at_upper_boundary_max_price(): void
    {
        $atMax = self::MAX_PRICE_LIMIT; // 999999.99
        $result = $this->filter->buildFilterQuery(['max_price' => $atMax]);

        $this->assertStringContainsString('p.base_price <= ?', $result['sql']);
        $this->assertEquals([$atMax], $result['params']);
    }

    /**
     * Test BVA: Trên biên trên - max + 1
     * Nếu filter nhận giá trị này mà không báo lỗi, thì tuỳ thuộc vào DB có lưu được không.
     */
    public function test_bva_above_upper_boundary_max_price(): void
    {
        $aboveMax = self::MAX_PRICE_LIMIT + 1.0; // 1000000.99
        $result = $this->filter->buildFilterQuery(['max_price' => $aboveMax]);

        $this->assertStringContainsString('p.base_price <= ?', $result['sql']);
        $this->assertEquals([$aboveMax], $result['params']);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Biên của kiểu dữ liệu (Data Type Boundaries)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Test BVA: Truyền string rỗng (Empty Boundary cho số)
     */
    public function test_bva_empty_string_price(): void
    {
        $result = $this->filter->buildFilterQuery(['min_price' => '']);
        // BVA string rỗng: Filter bỏ qua, không thêm vào câu SQL
        $this->assertStringNotContainsString('base_price >=', $result['sql']);
        $this->assertEmpty($result['params']);
    }

    /**
     * Test BVA: Truyền chữ thay vì số (Type Boundary)
     */
    public function test_bva_non_numeric_price(): void
    {
        $result = $this->filter->buildFilterQuery(['min_price' => 'abc']);
        // BVA không phải số: Filter sẽ bỏ qua (is_numeric = false)
        $this->assertStringNotContainsString('base_price >=', $result['sql']);
        $this->assertEmpty($result['params']);
    }
}
