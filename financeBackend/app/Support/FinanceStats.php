<?php

namespace App\Support;

use App\Models\OpeningBalance;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class FinanceStats
{
    public const BALANCE_KEYS = [
        'cash' => 0,
        'online_bank' => 0,
        'online_wechat' => 0,
        'online_alipay' => 0,
        'pure_gold_fund' => 0,
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

    public function current(?int $userId = null, ?string $month = null, ?int $storeId = null): array
    {
        $records = Transaction::query()
            ->active()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->get();

        $monthly = Transaction::query()
            ->active()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($month, fn (Builder $query) => $query->where('transaction_date', 'like', $month.'%'))
            ->get();

        $cash = $this->opening('cash', $storeId);
        $pureGoldFund = $this->opening('pure_gold_fund', $storeId);
        $online = [
            'bank' => $this->opening('online_bank', $storeId),
            'wechat' => $this->opening('online_wechat', $storeId),
            'alipay' => $this->opening('online_alipay', $storeId),
        ];

        $stock = [
            'sale_stock' => $this->emptyBucket('sale_stock'),
            'scrap_stock' => $this->emptyBucket('scrap_stock'),
        ];
        $this->applyOpeningStock($stock, $storeId);
        $recycleCost = $this->emptyRecycleCost();

        foreach ($records as $record) {
            $amountSign = match ($record->business_type) {
                'sale', 'income' => 1,
                'recycle', 'operating_expense' => -1,
                default => 0,
            };

            if ($cashAmount = $this->cashAmount($record)) {
                $cash += $amountSign * $cashAmount;
            } elseif ($record->payment_account === 'pure_gold_fund') {
                $pureGoldFund += $amountSign * (float) $record->amount;
            }

            if ($record->online_method && ($onlineAmount = $this->onlineAmount($record))) {
                $online[$record->online_method] += $amountSign * $onlineAmount;
            }

            if ($record->business_type === 'sale') {
                $this->applyStock($stock, $record, -1);
            } elseif ($record->business_type === 'recycle') {
                $this->applyStock($stock, $record, 1);
                $this->applyRecycleCost($recycleCost, $record);
            }
        }

        $monthlySales = (float) $monthly->where('business_type', 'sale')->sum('amount');
        $monthlyIncome = (float) $monthly->where('business_type', 'income')->sum('amount');
        $monthlyRecycle = (float) $monthly->where('business_type', 'recycle')->sum('amount');
        $monthlyExpenses = (float) $monthly->where('business_type', 'operating_expense')->sum('amount');
        $onlineTotal = array_sum($online);
        $total = $cash + $onlineTotal + $pureGoldFund;

        return [
            'cash' => round($cash, 2),
            'online' => [
                'bank' => round($online['bank'], 2),
                'wechat' => round($online['wechat'], 2),
                'alipay' => round($online['alipay'], 2),
                'total' => round($onlineTotal, 2),
            ],
            'pure_gold_fund' => round($pureGoldFund, 2),
            'total' => round($total, 2),
            'stock' => $this->roundedStock($stock),
            'recycle_cost' => $this->roundedRecycleCost($recycleCost),
            'month' => $month,
            'monthly' => [
                'sales' => round($monthlySales, 2),
                'income' => round($monthlyIncome, 2),
                'recycle' => round($monthlyRecycle, 2),
                'operating_expenses' => round($monthlyExpenses, 2),
                'net' => round($monthlySales + $monthlyIncome - $monthlyRecycle - $monthlyExpenses, 2),
            ],
        ];
    }

    public function accountDetails(string $account, ?string $month = null, string $range = 'month', ?int $storeId = null): array
    {
        $records = Transaction::query()
            ->active()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $keys = $this->accountKeys($account);
        $opening = collect($keys)->sum(fn (string $key) => $this->opening($key, $storeId));
        $balance = $opening;
        $entries = [];

        foreach ($records as $record) {
            $signed = $this->signedAmountForAccount($record, $account);
            if ($signed === null) {
                continue;
            }

            $balance += $signed;
            $date = (string) $record->transaction_date->format('Y-m-d');
            $inRange = $range === 'all' || ! $month || str_starts_with($date, $month);

            if ($inRange) {
                $entries[] = [
                    'id' => $record->id,
                    'transaction_date' => $date,
                    'business_type' => $record->business_type,
                    'payment_account' => $record->payment_account,
                    'online_method' => $record->online_method,
                    'amount' => round((float) $record->amount, 2),
                    'signed_amount' => round($signed, 2),
                    'balance_after' => round($balance, 2),
                    'remark' => $record->remark,
                ];
            }
        }

        return [
            'account' => $account,
            'range' => $range,
            'month' => $month,
            'opening' => round($opening, 2),
            'ending' => round($balance, 2),
            'entries' => array_reverse($entries),
        ];
    }

    public function openingBalances(?int $storeId = null): array
    {
        return OpeningBalance::query()
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->pluck('value', 'key')
            ->map(fn ($value) => (float) $value)
            ->all() + self::BALANCE_KEYS + self::STOCK_KEYS;
    }

    public function saveOpeningBalances(array $balances, ?int $storeId = null): array
    {
        $storeId ??= (int) Store::query()->where('is_default', true)->value('id');
        foreach (self::BALANCE_KEYS + self::STOCK_KEYS as $key => $default) {
            OpeningBalance::query()->updateOrCreate(
                ['store_id' => $storeId, 'scope' => 'store', 'key' => $key],
                ['value' => $balances[$key] ?? $default],
            );
        }

        return $this->openingBalances($storeId);
    }

    private function opening(string $key, ?int $storeId = null): float
    {
        return (float) (OpeningBalance::query()
            ->where('scope', 'store')
            ->where('key', $key)
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->sum('value'));
    }

    private function applyOpeningStock(array &$stock, ?int $storeId = null): void
    {
        foreach (self::STOCK_KEYS as $key => $default) {
            [$bucket, $product, $field] = explode('.', $key);
            $stock[$bucket]['products'][$product][$field] = $this->opening($key, $storeId);
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

    private function emptyRecycleCost(): array
    {
        return [
            'pure_gold' => ['amount' => 0, 'pure_gold_weight' => 0],
            'gold_wrapped_silver' => ['amount' => 0, 'wrapped_gold_weight' => 0, 'silver_weight' => 0],
        ];
    }

    private function applyRecycleCost(array &$cost, Transaction $record): void
    {
        if ($record->product_type === 'pure_gold') {
            $cost['pure_gold']['amount'] += (float) $record->amount;
            $cost['pure_gold']['pure_gold_weight'] += (float) $record->pure_gold_weight;
        }

        if ($record->product_type === 'gold_wrapped' && $record->wrap_material === 'silver') {
            $cost['gold_wrapped_silver']['amount'] += (float) $record->amount;
            $cost['gold_wrapped_silver']['wrapped_gold_weight'] += (float) $record->wrapped_gold_weight;
            $cost['gold_wrapped_silver']['silver_weight'] += (float) $record->material_weight;
        }
    }

    private function roundedRecycleCost(array $cost): array
    {
        $goldWeight = $cost['pure_gold']['pure_gold_weight'];
        $wrappedGoldWeight = $cost['gold_wrapped_silver']['wrapped_gold_weight'];
        $silverWeight = $cost['gold_wrapped_silver']['silver_weight'];

        return [
            'pure_gold' => [
                'amount' => round($cost['pure_gold']['amount'], 2),
                'pure_gold_weight' => round($goldWeight, 3),
                'average_gold_price' => $goldWeight > 0 ? round($cost['pure_gold']['amount'] / $goldWeight, 2) : 0,
            ],
            'gold_wrapped_silver' => [
                'amount' => round($cost['gold_wrapped_silver']['amount'], 2),
                'wrapped_gold_weight' => round($wrappedGoldWeight, 3),
                'silver_weight' => round($silverWeight, 3),
                'average_total_price_per_gold_gram' => $wrappedGoldWeight > 0 ? round($cost['gold_wrapped_silver']['amount'] / $wrappedGoldWeight, 2) : 0,
                'average_total_price_per_material_gram' => $silverWeight > 0 ? round($cost['gold_wrapped_silver']['amount'] / $silverWeight, 2) : 0,
            ],
        ];
    }

    private function signedAmountForAccount(Transaction $record, string $account): ?float
    {
        $sign = match ($record->business_type) {
            'sale', 'income' => 1,
            'recycle', 'operating_expense' => -1,
            default => 0,
        };

        $amount = match ($account) {
            'cash' => $this->cashAmount($record),
            'online' => $this->onlineAmount($record),
            'pure_gold_fund' => $record->payment_account === 'pure_gold_fund' ? (float) $record->amount : null,
            'total' => (float) $record->amount,
            default => null,
        };

        return $amount === null || $amount == 0.0 ? null : $sign * $amount;
    }

    private function cashAmount(Transaction $record): ?float
    {
        if ($record->payment_account === 'cash') {
            return (float) $record->amount;
        }

        if ($record->payment_account === 'mixed') {
            return (float) $record->cash_amount;
        }

        return null;
    }

    private function onlineAmount(Transaction $record): ?float
    {
        if (! $record->online_method) {
            return null;
        }

        if ($record->payment_account === 'online') {
            return (float) $record->amount;
        }

        if ($record->payment_account === 'mixed') {
            return (float) $record->online_amount;
        }

        return null;
    }

    private function accountKeys(string $account): array
    {
        return match ($account) {
            'cash' => ['cash'],
            'online' => ['online_bank', 'online_wechat', 'online_alipay'],
            'pure_gold_fund' => ['pure_gold_fund'],
            'total' => ['cash', 'online_bank', 'online_wechat', 'online_alipay', 'pure_gold_fund'],
            default => [],
        };
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
