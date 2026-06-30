<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Validation\ValidationException;

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

    public function businessSummaryFields(string $sectionType): array
    {
        return $sectionType === 'pure_gold'
            ? ['recycle_amount', 'recycle_pure_gold_weight', 'recycle_pure_gold_pieces']
            : [
                'sales_amount',
                'sales_cash', 'sales_wechat', 'sales_alipay', 'sales_bank',
                'sales_pure_gold_weight', 'sales_pure_gold_pieces',
                'sales_pure_silver_weight', 'sales_pure_silver_pieces',
                'sales_gold_wrapped_gold_weight', 'sales_gold_wrapped_silver_weight', 'sales_gold_wrapped_pieces',
                'recycle_amount',
                'recycle_cash', 'recycle_wechat', 'recycle_alipay', 'recycle_bank',
                'recycle_pure_silver_weight', 'recycle_pure_silver_pieces',
                'recycle_gold_wrapped_gold_weight', 'recycle_gold_wrapped_silver_weight', 'recycle_gold_wrapped_pieces',
            ];
    }

    public function validateBusinessSummary(string $sectionType, bool $noBusiness, array $summary): void
    {
        if ($noBusiness) {
            if (collect($summary)->contains(fn ($value) => (float) $value !== 0.0)) {
                throw ValidationException::withMessages(['business_summary' => '选择今日无业务后，业务合计必须为零']);
            }

            return;
        }

        $fields = $this->businessSummaryFields($sectionType);
        if (array_diff($fields, array_keys($summary)) || array_diff(array_keys($summary), $fields)) {
            throw ValidationException::withMessages(['business_summary' => '业务合计项目不完整']);
        }
        foreach ($summary as $value) {
            if (! is_numeric($value) || (float) $value < 0) {
                throw ValidationException::withMessages(['business_summary' => '业务合计只能填写零或正数']);
            }
        }
        if ($sectionType === 'general') {
            $salesPayments = collect(['sales_cash', 'sales_wechat', 'sales_alipay', 'sales_bank'])->sum(
                fn (string $field) => (float) $summary[$field],
            );
            $recyclePayments = collect(['recycle_cash', 'recycle_wechat', 'recycle_alipay', 'recycle_bank'])->sum(
                fn (string $field) => (float) $summary[$field],
            );
            if (abs($salesPayments - (float) $summary['sales_amount']) > 0.009) {
                throw ValidationException::withMessages(['business_summary' => '销售收款合计必须等于销售总额']);
            }
            if (abs($recyclePayments - (float) $summary['recycle_amount']) > 0.009) {
                throw ValidationException::withMessages(['business_summary' => '回收付款合计必须等于回收总额']);
            }
        }
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
