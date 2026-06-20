<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\Catalog\ProductFilter;

/**
 * Áp dụng kỹ thuật kiểm thử EP (Equivalence Partitioning - Phân vùng tương đương)
 * Mục tiêu: Kiểm tra bộ lọc Giới tính (Gender) và Trạng thái (Active)
 * 
 * LÝ THUYẾT PHÂN VÙNG TƯƠNG ĐƯƠNG:
 * Thay vì test mọi giá trị, ta chia input thành các vùng (partitions).
 * Một giá trị đại diện trong vùng pass/fail thì coi như cả vùng đó pass/fail.
 */
class EquivalencePartitioningTest extends TestCase
{
    private ProductFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new ProductFilter();
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Phân vùng tương đương cho Giới tính (Gender)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Vùng hợp lệ 1 (Valid Class): Giới tính cụ thể hợp lệ (ví dụ: 'male', 'female', 'unisex')
     */
    public function test_ep_valid_specific_gender(): void
    {
        $result = $this->filter->buildFilterQuery(['gender' => 'male']);
        
        $this->assertStringContainsString('p.gender = ?', $result['sql']);
        $this->assertEquals(['male'], $result['params']);
    }

    /**
     * Vùng hợp lệ 2 (Valid Class): Nhiều giới tính cùng lúc (Mảng các string)
     */
    public function test_ep_valid_array_of_genders(): void
    {
        $result = $this->filter->buildFilterQuery(['gender' => ['male', 'unisex']]);
        
        $this->assertStringContainsString('p.gender IN (?,?)', $result['sql']);
        $this->assertEquals(['male', 'unisex'], $result['params']);
    }

    /**
     * Vùng đặc biệt (Special Class): Giới tính 'all' (Tất cả)
     * Hệ thống thiết kế để bỏ qua từ khoá này và không đưa vào câu SQL.
     */
    public function test_ep_special_gender_all(): void
    {
        $result = $this->filter->buildFilterQuery(['gender' => 'all']);
        
        $this->assertStringNotContainsString('p.gender', $result['sql']);
        $this->assertEmpty($result['params']);
    }

    /**
     * Vùng không hợp lệ (Invalid Class): Kiểu dữ liệu không mong muốn (vd: số, mảng rỗng)
     */
    public function test_ep_invalid_gender_type_empty_array(): void
    {
        $result = $this->filter->buildFilterQuery(['gender' => []]);
        
        // Filter nên bỏ qua mảng rỗng thay vì sinh ra lỗi SQL "IN ()"
        $this->assertStringNotContainsString('p.gender IN', $result['sql']);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Phân vùng tương đương cho Trạng thái (Active)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Vùng hợp lệ 1 (Valid Class): Boolean True (Lọc sản phẩm đang hoạt động)
     */
    public function test_ep_valid_active_true(): void
    {
        $result = $this->filter->buildFilterQuery(['active' => true]);
        
        $this->assertStringContainsString('p.is_active = 1', $result['sql']);
    }

    /**
     * Vùng hợp lệ 2 (Valid Class): Boolean False (Bỏ qua lọc trạng thái hoặc lọc inactive)
     * Logic hiện tại: Nếu false thì KHÔNG filter (cho phép hiển thị tất cả, hoặc do mặc định lấy active)
     */
    public function test_ep_valid_active_false(): void
    {
        $result = $this->filter->buildFilterQuery(['active' => false]);
        
        $this->assertStringNotContainsString('p.is_active', $result['sql']);
    }

    /**
     * Vùng không hợp lệ (Invalid Class): Giá trị Truthy nhưng không phải boolean (vd: string "1", "true")
     * Lưu ý: Tuỳ thuộc vào ngôn ngữ, PHP có thể tự ép kiểu. Ta test để hiểu rõ hành vi của filter.
     */
    public function test_ep_invalid_active_string_truthy(): void
    {
        $result = $this->filter->buildFilterQuery(['active' => 'true']);
        
        // ProductFilter hiện tại sử dụng empty() hoặc kiểm tra strict boolean?
        // Nếu nó dùng `if (!empty($filters['active']))`, chuỗi 'true' sẽ tính là có filter.
        $this->assertStringContainsString('p.is_active = 1', $result['sql']);
    }
}
