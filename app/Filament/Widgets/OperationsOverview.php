<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\StockLevel;
use App\Support\InventoryStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OperationsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $threshold = InventoryStatus::lowStockThreshold();

        $lowStockVariants = (int) DB::table('stock_levels')
            ->select('product_variant_id')
            ->groupBy('product_variant_id')
            ->havingRaw('SUM(quantity_on_hand - quantity_reserved) > 0')
            ->havingRaw('SUM(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->count();

        $outOfStockVariants = (int) DB::table('stock_levels')
            ->select('product_variant_id')
            ->groupBy('product_variant_id')
            ->havingRaw('SUM(quantity_on_hand - quantity_reserved) <= 0')
            ->count();

        return [
            Stat::make('Orders today', Order::query()->whereDate('created_at', today())->count()),
            Stat::make('Pending payments', Payment::query()->where('status', 'pending')->count()),
            Stat::make('Awaiting processing', Order::query()->where('payment_status', 'paid')->where('order_status', 'paid')->count()),
            Stat::make('Low-stock variants', $lowStockVariants),
            Stat::make('Out-of-stock variants', $outOfStockVariants),
        ];
    }
}
