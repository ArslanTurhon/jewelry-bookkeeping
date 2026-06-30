<?php

namespace App\Support;

use App\Models\AdminUser;

class ReconciliationService
{
    public function __construct(private FinanceStats $stats) {}

    public function allowedSections(AdminUser $admin): array
    {
        if ($admin->is_super_admin) {
            return ['pure_gold', 'general'];
        }

        $sections = [];
        if ($admin->hasPermission('recycle_pure_gold')) {
            $sections[] = 'pure_gold';
        }
        if ($admin->hasPermission('transactions')) {
            $sections[] = 'general';
        }

        return $sections;
    }

    public function requiredSectionsForStore(int $storeId): array
    {
        $staff = AdminUser::query()
            ->where('store_id', $storeId)
            ->where('enabled', true)
            ->get();
        $sections = [];
        if ($staff->contains(fn (AdminUser $user) => $user->hasPermission('recycle_pure_gold'))) {
            $sections[] = 'pure_gold';
        }
        if ($staff->contains(fn (AdminUser $user) => $user->hasPermission('transactions'))) {
            $sections[] = 'general';
        }

        return $sections;
    }

    public function fieldDefinitions(string $sectionType): array
    {
        return $sectionType === 'pure_gold'
            ? ['pure_gold_fund', 'scrap_pure_gold_weight', 'scrap_pure_gold_pieces']
            : [
                'cash', 'online_bank', 'online_wechat', 'online_alipay',
                'sale_pure_gold_weight', 'sale_pure_gold_pieces',
                'sale_pure_silver_weight', 'sale_pure_silver_pieces',
                'sale_gold_wrapped_silver_gold_weight', 'sale_gold_wrapped_silver_silver_weight',
                'sale_gold_wrapped_silver_pieces',
                'scrap_pure_silver_weight', 'scrap_pure_silver_pieces',
                'scrap_gold_wrapped_silver_gold_weight', 'scrap_gold_wrapped_silver_silver_weight',
                'scrap_gold_wrapped_silver_pieces',
            ];
    }

    public function snapshot(int $storeId, string $sectionType): array
    {
        $stats = $this->stats->current(null, null, $storeId);
        $values = [
            'pure_gold_fund' => $stats['pure_gold_fund'],
            'scrap_pure_gold_weight' => data_get($stats, 'stock.scrap_stock.products.pure_gold.pure_gold_weight', 0),
            'scrap_pure_gold_pieces' => data_get($stats, 'stock.scrap_stock.products.pure_gold.pieces', 0),
            'cash' => $stats['cash'],
            'online_bank' => data_get($stats, 'online.bank', 0),
            'online_wechat' => data_get($stats, 'online.wechat', 0),
            'online_alipay' => data_get($stats, 'online.alipay', 0),
            'sale_pure_gold_weight' => data_get($stats, 'stock.sale_stock.products.pure_gold.pure_gold_weight', 0),
            'sale_pure_gold_pieces' => data_get($stats, 'stock.sale_stock.products.pure_gold.pieces', 0),
            'sale_pure_silver_weight' => data_get($stats, 'stock.sale_stock.products.pure_silver.silver_weight', 0),
            'sale_pure_silver_pieces' => data_get($stats, 'stock.sale_stock.products.pure_silver.pieces', 0),
            'sale_gold_wrapped_silver_gold_weight' => data_get($stats, 'stock.sale_stock.products.gold_wrapped_silver.wrapped_gold_weight', 0),
            'sale_gold_wrapped_silver_silver_weight' => data_get($stats, 'stock.sale_stock.products.gold_wrapped_silver.silver_weight', 0),
            'sale_gold_wrapped_silver_pieces' => data_get($stats, 'stock.sale_stock.products.gold_wrapped_silver.pieces', 0),
            'scrap_pure_silver_weight' => data_get($stats, 'stock.scrap_stock.products.pure_silver.silver_weight', 0),
            'scrap_pure_silver_pieces' => data_get($stats, 'stock.scrap_stock.products.pure_silver.pieces', 0),
            'scrap_gold_wrapped_silver_gold_weight' => data_get($stats, 'stock.scrap_stock.products.gold_wrapped_silver.wrapped_gold_weight', 0),
            'scrap_gold_wrapped_silver_silver_weight' => data_get($stats, 'stock.scrap_stock.products.gold_wrapped_silver.silver_weight', 0),
            'scrap_gold_wrapped_silver_pieces' => data_get($stats, 'stock.scrap_stock.products.gold_wrapped_silver.pieces', 0),
        ];

        return collect($this->fieldDefinitions($sectionType))
            ->mapWithKeys(fn (string $key) => [$key => $values[$key] ?? 0])
            ->all();
    }

    public function differences(array $actual, array $book): array
    {
        return collect($book)->mapWithKeys(function ($value, string $key) use ($actual): array {
            $precision = str_contains($key, 'pieces') ? 0 : (str_contains($key, 'weight') ? 3 : 2);

            return [$key => round((float) ($actual[$key] ?? 0) - (float) $value, $precision)];
        })->all();
    }

    public function hasDifferences(array $differences): bool
    {
        return collect($differences)->contains(fn ($value) => (float) $value !== 0.0);
    }
}
