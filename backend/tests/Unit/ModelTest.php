<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Core\Model (abstract class)
 * Test qua một ConcreteModel giả lập, chỉ test logic không cần DB.
 */
class ModelTest extends TestCase
{
    // ─────────────────────────────────────────────────────────
    // Attributes & Magic Methods
    // ─────────────────────────────────────────────────────────

    public function test_constructor_sets_attributes(): void
    {
        $model = new ConcreteModel(['name' => 'John', 'email' => 'john@test.com']);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@test.com', $model->email);
    }

    public function test_magic_getter_returns_null_for_missing_attribute(): void
    {
        $model = new ConcreteModel([]);

        $this->assertNull($model->nonexistent);
    }

    public function test_magic_setter_assigns_attribute(): void
    {
        $model = new ConcreteModel([]);
        $model->name = 'Jane';

        $this->assertEquals('Jane', $model->name);
    }

    public function test_isset_returns_true_for_existing_attribute(): void
    {
        $model = new ConcreteModel(['name' => 'John']);

        $this->assertTrue(isset($model->name));
    }

    public function test_isset_returns_false_for_missing_attribute(): void
    {
        $model = new ConcreteModel([]);

        $this->assertFalse(isset($model->age));
    }

    // ─────────────────────────────────────────────────────────
    // toArray()
    // ─────────────────────────────────────────────────────────

    public function test_to_array_returns_all_attributes(): void
    {
        $data = ['id' => 1, 'name' => 'John', 'role' => 'admin'];
        $model = new ConcreteModel($data);

        $this->assertEquals($data, $model->toArray());
    }

    public function test_to_array_returns_empty_for_empty_model(): void
    {
        $model = new ConcreteModel([]);

        $this->assertEquals([], $model->toArray());
    }

    // ─────────────────────────────────────────────────────────
    // getTable()
    // ─────────────────────────────────────────────────────────

    public function test_get_table_returns_explicit_table_name(): void
    {
        $this->assertEquals('my_custom_table', ConcreteModel::getTable());
    }

    public function test_get_table_falls_back_to_class_name(): void
    {
        $this->assertEquals('autonamedmodel', AutoNamedModel::getTable());
    }

    // ─────────────────────────────────────────────────────────
    // Attribute mutation after construction
    // ─────────────────────────────────────────────────────────

    public function test_attributes_can_be_updated(): void
    {
        $model = new ConcreteModel(['name' => 'John']);
        $model->name = 'Jane';

        $this->assertEquals('Jane', $model->name);
        $this->assertEquals(['name' => 'Jane'], $model->toArray());
    }

    public function test_new_attributes_can_be_added(): void
    {
        $model = new ConcreteModel(['name' => 'John']);
        $model->email = 'john@test.com';

        $this->assertEquals('john@test.com', $model->email);
        $this->assertCount(2, $model->toArray());
    }
}

/**
 * Concrete implementation of abstract Model for testing.
 * Uses explicit table name.
 */
class ConcreteModel extends \Core\Model
{
    protected static string $table = 'my_custom_table';
}

/**
 * Concrete implementation without explicit table name.
 * Tests automatic table name convention.
 */
class AutoNamedModel extends \Core\Model
{
    protected static string $table = '';
}
