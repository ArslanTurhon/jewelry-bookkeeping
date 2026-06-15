<?php

namespace App\Support;

use App\Models\OpeningBalance;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class FinanceStats
{
    public const BALANCE_KEYS = [
        'cash' => 0,
        'online_bank' => 0,
        'online_wechat' => 0,
        'online_alipay' => 0,
    ];

    public const STOCK_KEYS = [
        'sale_stock.pure_gold.pure_gold_weight' => 0,
        'sale_stock.pure_gold.pieces' => 0,
        'sale_stock.pure_silver.silver_weight' => 0,
        'sale_stock.pure_silver.pieces' => 0,
        'sale_stock.gold_wrapped_silver.wrapped_gold_weight' => 0,
        'sale_stock.gold_wrapped_silver.silver_weight' => 0,
        'sale_stock.gold_wrapped_silver.pieces' => 0,
        'sale_stock.gold_wrapped_copper.wrapped_gold_weight' => 0,
        'sale_stock.gold_wrapped_copper.copper_weight' => 0,
        'sale_stock.gold_wrapped_copper.pieces' => 0,
        'scrap_stock.pure_gold.pure_gold_weight' => 0,
        'scrap_stock.pure_gold.pieces' => 0,
        'scrap_stock.pure_silver.silver_weight' => 0,
        'scrap_stock.pure_silver.pieces' => 0,
        'scrap_stock.gold_wrapped_silver.wrapped_gold_weight' => 0,
        'scrap_stock.gold_wrapped_silver.silver_weight' => 0,
        'scrap_stock.gold_wrapped_silver.pieces' => 0,
        'scrap_stock.gold_wrapped_copper.wrapped_gold_weight' => 0,
        'scrap_stock.gold_wrapped_copper.copper_weight' => 0,
        'scrap_stock.gold_wrapped_copper.pieces' => 0,
    ];

    public function current(?int $userId = null, ?string $month = null): array
    {
        $records = Transaction::query()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->get();

        $monthly = Transaction::query()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($month, fn (Builder $query) => $query->where('transaction_date', 'like', $month.'%'))
            ->get();

        $cash = $this->opening('cash');
        $online = [
            'bank' => $this->opening('online_bank'),
            'wechat' => $this->opening('online_wechat'),
            'alipay' => $this->opening('online_alipay'),
        ];

        $stock = [
            'sale_stock' => $this->emptyBucket('sale_stock'),
            'scrap_stock' => $this->emptyBucket('scrap_stock'),
        ];
        $this->applyOpeningStock($stock);

        foreach ($records as $record) {
            $amountSign = match ($record->business_type) {
                'sale' => 1,
                'recycle', 'operating_expense' => -1,
                default => 0,
            };

            if ($record->payment_account === 'cash') {
                $cash += $amountSign * (float) $record->amount;
            } elseif ($record->online_method) {
                $online[$record->online_method] += $amountSign * (float) $record->amount;
            }

            if ($record->business_type === 'sale') {
                $this->applyStock($stock, $record, -1);
            } elseif ($record->business_type === 'recycle') {
                $this->applyStock($stock, $record, 1);
            }
        }

        $monthlySales = (float) $monthly->where('business_type', 'sale')->sum('amount');
        $monthlyRecycle = (float) $monthly->where('business_type', 'recycle')->sum('amount');
        $monthlyExpenses = (float) $monthly->where('business_type', 'operating_expense')->sum('amount');

        return [
            'cash' => round($cash, 2),
            'online' => [
                'bank' => round($online['bank'], 2),
                'wechat' => round($online['wechat'], 2),
                'alipay' => round($online['alipay'], 2),
                'total' => round(array_sum($online), 2),
            ],
            'total' => round($cash + array_sum($online), 2),
            'stock' => $this->roundedStock($stock),
            'month' => $month,
            'monthly' => [
                'sales' => round($monthlySales, 2),
                'recycle' => round($monthlyRecycle, 2),
                'operating_expenses' => round($monthlyExpenses, 2),
                'net' => round($monthlySales - $monthlyRecycle - $monthlyExpenses, 2),
            ],
        ];
    }

    public function openingBalances(): array
    {
        return OpeningBalance::query()
            ->pluck('value', 'key')
            ->map(fn ($value) => (float) $value)
            ->all() + self::BALANCE_KEYS + self::STOCK_KEYS;
    }

    public function saveOpeningBalances(array $balances): array
    {
        foreach (self::BALANCE_KEYS + self::STOCK_KEYS as $key => $default) {
            OpeningBalance::query()->updateOrCreate(
                ['scope' => 'store', 'key' => $key],
                ['value' => $balances[$key] ?? $default],
            );
        }

        return $this->openingBalances();
    }

    private function opening(string $key): float
    {
        return (float) (OpeningBalance::query()
            ->where('scope', 'store')
            ->where('key', $key)
            ->value('value') ?? 0);
    }

    private function applyOpeningStock(array &$stock): void
    {
        foreach (self::STOCK_KEYS as $key => $default) {
            [$bucket, $product, $field] = explode('.', $key);
            $stock[$bucket]['products'][$product][$field] = $this->opening($key);
        }
    }

    private function emptyBucket(string $bucket): array
    {
        return [
            'code' => $bucket,
            'summary' => [
                'pure_gold_weight' => 0,
                'wrapped_gold_weight' => 0,
                'silver_weight' => 0,
                'copper_weight' => 0,
            ],
            'products' => [
                'pure_gold' => ['pure_gold_weight' => 0, 'pieces' => 0],
                'pure_silver' => ['silver_weight' => 0, 'pieces' => 0],
                'gold_wrapped_silver' => ['wrapped_gold_weight' => 0, 'silver_weight' => 0, 'pieces' => 0],
                'gold_wrapped_copper' => ['wrapped_gold_weight' => 0, 'copper_weight' => 0, 'pieces' => 0],
            ],
        ];
    }

    private function applyStock(array &$stock, Transaction $record, int $sign): void
    {
        if (! $record->stock_bucket || ! isset($stock[$record->stock_bucket])) {
            return;
        }

        $product = $this->productKey($record);
        if (! $product) {
            return;
        }

        $bucket = &$stock[$record->stock_bucket]['products'][$product];
        if ($record->product_type === 'pure_gold') {
            $bucket['pure_gold_weight'] += $sign * (float) $record->pure_gold_weight;
        } elseif ($record->product_type === 'pure_silver') {
            $bucket['silver_weight'] += $sign * (float) $record->material_weight;
        } elseif ($record->wrap_material === 'silver') {
            $bucket['wrapped_gold_weight'] += $sign * (float) $record->wrapped_gold_weight;
            $bucket['silver_weight'] += $sign * (float) $record->material_weight;
        } elseif ($record->wrap_material === 'copper') {
            $bucket['wrapped_gold_weight'] += $sign * (float) $record->wrapped_gold_weight;
            $bucket['copper_weight'] += $sign * (float) $record->material_weight;
        }
        $bucket['pieces'] += $sign * (int) $record->material_pieces;
    }

    private function roundedStock(array $stock): array
    {
        foreach ($stock as $bucketKey => $bucket) {
            $summary = [
                'pure_gold_weight' => 0,
                'wrapped_gold_weight' => 0,
                'silver_weight' => 0,
                'copper_weight' => 0,
            ];

            foreach ($bucket['products'] as $productKey => $product) {
                foreach ($product as $field => $value) {
                    $stock[$bucketKey]['products'][$productKey][$field] = $field === 'pieces'
                        ? (int) $value
                        : round((float) $value, 3);
                }

                $summary['pure_gold_weight'] += $product['pure_gold_weight'] ?? 0;
                $summary['wrapped_gold_weight'] += $product['wrapped_gold_weight'] ?? 0;
                $summary['silver_weight'] += $product['silver_weight'] ?? 0;
                $summary['copper_weight'] += $product['copper_weight'] ?? 0;
            }

            $stock[$bucketKey]['summary'] = collect($summary)
                ->map(fn ($value) => round((float) $value, 3))
                ->all();
        }

        return $stock;
    }

    private function productKey(Transaction $record): ?string
    {
        return match ($record->product_type) {
            'pure_gold' => 'pure_gold',
            'pure_silver' => 'pure_silver',
            'gold_wrapped' => $record->wrap_material === 'copper' ? 'gold_wrapped_copper' : 'gold_wrapped_silver',
            default => null,
        };
    }
}
