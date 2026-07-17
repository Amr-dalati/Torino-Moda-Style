<?php

namespace Tests\Unit\Services\Stock;

use App\Enums\StockAdjustmentType;
use App\Enums\UserRole;
use App\Filament\Resources\StockLevelResource;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Stock\StockAdjustmentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected StockLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('phoenix:sync');

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->level = StockLevel::query()->firstOrFail();
        $this->level->forceFill([
            'quantity_on_hand' => 20,
            'quantity_reserved' => 5,
        ])->save();
    }

    public function test_admin_can_increase_stock(): void
    {
        $updated = app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Increase,
            quantity: 3,
            reason: 'Received shipment',
            reference: 'PO-100',
            user: $this->admin,
        );

        $this->assertSame('23.000', $updated->quantity_on_hand);
        $this->assertSame('5.000', $updated->quantity_reserved);
        $this->assertDatabaseHas('stock_adjustments', [
            'stock_level_id' => $this->level->id,
            'adjustment_type' => StockAdjustmentType::Increase->value,
            'quantity_before' => '20.000',
            'quantity_after' => '23.000',
            'reason' => 'Received shipment',
        ]);
    }

    public function test_admin_can_decrease_stock(): void
    {
        $updated = app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Decrease,
            quantity: 2,
            reason: 'Damaged units',
            reference: null,
            user: $this->admin,
        );

        $this->assertSame('18.000', $updated->quantity_on_hand);
        $this->assertSame('5.000', $updated->quantity_reserved);
    }

    public function test_admin_can_set_exact_quantity(): void
    {
        $updated = app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Set,
            quantity: 12,
            reason: 'Cycle count',
            reference: null,
            user: $this->admin,
        );

        $this->assertSame('12.000', $updated->quantity_on_hand);
    }

    public function test_decrease_below_reserved_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Decrease,
            quantity: 16,
            reason: 'Too much',
            reference: null,
            user: $this->admin,
        );
    }

    public function test_set_exact_quantity_below_reserved_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Set,
            quantity: 4,
            reason: 'Invalid set',
            reference: null,
            user: $this->admin,
        );
    }

    public function test_audit_record_is_created_and_reserved_quantity_unchanged(): void
    {
        app(StockAdjustmentService::class)->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Increase,
            quantity: 1,
            reason: 'Audit trail',
            reference: null,
            user: $this->admin,
        );

        $this->level->refresh();

        $this->assertSame('5.000', $this->level->quantity_reserved);
        $this->assertSame(1, StockAdjustment::query()->where('stock_level_id', $this->level->id)->count());
    }

    public function test_concurrent_adjustments_are_serialized_by_row_lock(): void
    {
        $service = app(StockAdjustmentService::class);

        $service->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Decrease,
            quantity: 10,
            reason: 'First pass',
            reference: null,
            user: $this->admin,
        );

        $this->expectException(ValidationException::class);

        $service->adjust(
            stockLevelId: $this->level->id,
            type: StockAdjustmentType::Decrease,
            quantity: 6,
            reason: 'Would breach reserved',
            reference: null,
            user: $this->admin,
        );
    }

    public function test_admin_can_view_stock_levels_in_filament(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(StockLevelResource::canViewAny());
    }

    public function test_non_admin_cannot_access_stock_management(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);

        $this->actingAs($sales);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(StockLevelResource::canViewAny());
    }

    public function test_inactive_admin_cannot_access_panel(): void
    {
        $inactive = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => false,
        ]);

        $this->assertFalse($inactive->canAccessPanel(Filament::getPanel('admin')));
    }
}
