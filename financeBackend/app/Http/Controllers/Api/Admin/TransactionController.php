<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\RecyclePrice;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\BusinessDictionary;
use App\Support\FinanceStats;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request, BusinessDictionary $dictionary)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $this->canReadTransactions($admin)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $language = $this->language($request);
        $query = Transaction::query()->with('user')->latest('transaction_date')->latest('id');
        $this->applyReadableScope($query, $admin);
        foreach (['business_type', 'payment_account', 'online_method', 'stock_bucket', 'product_type', 'wrap_material', 'expense_category'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->filled('month')) {
            $query->where('transaction_date', 'like', $request->string('month').'%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('keyword')) {
            $query->where('remark', 'like', '%'.$request->string('keyword').'%');
        }

        $page = $query->paginate($request->integer('per_page', 50));
        $page->getCollection()->transform(fn (Transaction $transaction) => $this->present($transaction, $dictionary, $language));

        return response()->json($page);
    }

    public function store(Request $request, BusinessDictionary $dictionary)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $this->validatedData($request);
        if (! $this->canCreateTransaction($admin, $data)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transaction = Transaction::query()->create($this->normalize($data) + [
            'user_id' => $this->backofficeUser()->id,
        ]);

        return response()->json($this->present($transaction->load('user'), $dictionary, $this->language($request)), 201);
    }

    public function update(Request $request, Transaction $transaction, BusinessDictionary $dictionary)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->is_super_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transaction->update($this->normalize($this->validatedData($request)));

        return response()->json($this->present($transaction->fresh('user'), $dictionary, $this->language($request)));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->is_super_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transaction->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function monthlyStats(Request $request, FinanceStats $stats)
    {
        $admin = AdminAccess::require($request, 'dashboard');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json($stats->current(null, $request->string('month', now()->format('Y-m'))->toString()));
    }

    public function currentStats(Request $request, FinanceStats $stats)
    {
        return $this->monthlyStats($request, $stats);
    }

    public function accountDetails(Request $request, FinanceStats $stats)
    {
        $admin = AdminAccess::require($request, 'dashboard');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $request->validate([
            'account' => ['required', Rule::in(['cash', 'online', 'total', 'pure_gold_fund'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'range' => ['nullable', Rule::in(['month', 'all'])],
        ]);

        return response()->json($stats->accountDetails(
            $data['account'],
            $data['month'] ?? now()->format('Y-m'),
            $data['range'] ?? 'month',
        ));
    }

    private function language(Request $request): string
    {
        return $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE));
    }

    private function canReadTransactions(AdminUser $admin): bool
    {
        return $admin->hasPermission('transactions')
            || $admin->hasPermission('recycle_pure_gold')
            || $admin->hasPermission('recycle_gold_wrapped');
    }

    private function applyReadableScope($query, AdminUser $admin): void
    {
        if ($admin->hasPermission('transactions')) {
            return;
        }

        $query->where(function ($query) use ($admin): void {
            if ($admin->hasPermission('recycle_pure_gold')) {
                $query->orWhere(function ($query): void {
                    $query->where('business_type', 'recycle')->where('product_type', 'pure_gold');
                });
            }
            if ($admin->hasPermission('recycle_gold_wrapped')) {
                $query->orWhere(function ($query): void {
                    $query->where('business_type', 'recycle')
                        ->where('product_type', 'gold_wrapped')
                        ->where('wrap_material', 'silver');
                });
            }
        });
    }

    private function canCreateTransaction(AdminUser $admin, array $data): bool
    {
        if ($admin->hasPermission('transactions')) {
            return true;
        }

        if (($data['business_type'] ?? null) !== 'recycle') {
            return false;
        }

        if (($data['product_type'] ?? null) === 'pure_gold') {
            return $admin->hasPermission('recycle_pure_gold');
        }

        return ($data['product_type'] ?? null) === 'gold_wrapped'
            && ($data['wrap_material'] ?? null) === 'silver'
            && $admin->hasPermission('recycle_gold_wrapped');
    }

    private function backofficeUser(): User
    {
        return User::query()->firstOrCreate(
            ['openid' => 'admin-backoffice'],
            ['name' => '后台录入', 'api_token' => Str::random(60)],
        );
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'business_type' => ['required', Rule::in(['sale', 'recycle', 'income', 'operating_expense'])],
            'payment_account' => ['required', Rule::in(['cash', 'online', 'pure_gold_fund'])],
            'online_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay'])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999.99'],
            'product_type' => ['nullable', Rule::in(['pure_gold', 'pure_silver', 'gold_wrapped'])],
            'wrap_material' => ['nullable', Rule::in(['silver', 'copper'])],
            'pure_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'wrapped_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'material_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'material_pieces' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'item_weights' => ['nullable', 'array'],
            'item_weights.*.pure_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'item_weights.*.wrapped_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'item_weights.*.material_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'item_weights.*.gold_unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'item_weights.*.silver_unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'gold_unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'silver_unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'expense_category' => ['nullable', Rule::in(['rent', 'electricity', 'water', 'salary', 'supplies', 'other'])],
            'transaction_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['payment_account'] === 'online' && empty($data['online_method'])) {
            abort(response()->json(['message' => '线上收支必须选择银行、微信或支付宝'], 422));
        }
        if ($data['payment_account'] !== 'online' && ! empty($data['online_method'])) {
            abort(response()->json(['message' => '非线上账户不能选择线上方式'], 422));
        }
        if ($data['business_type'] === 'operating_expense' && empty($data['expense_category'])) {
            abort(response()->json(['message' => '店铺成本支出必须选择分类'], 422));
        }
        if (in_array($data['business_type'], ['sale', 'income', 'operating_expense'], true) && empty($data['amount'])) {
            abort(response()->json(['message' => '请填写金额'], 422));
        }
        if (in_array($data['business_type'], ['sale', 'recycle'], true) && empty($data['product_type'])) {
            abort(response()->json(['message' => '销售和回收必须选择商品类型'], 422));
        }
        if (($data['product_type'] ?? null) === 'gold_wrapped' && empty($data['wrap_material'])) {
            abort(response()->json(['message' => '金包类必须选择银或铜'], 422));
        }

        return $data;
    }

    private function normalize(array $data): array
    {
        $data['online_method'] = $data['payment_account'] === 'online' ? $data['online_method'] : null;
        $price = RecyclePrice::query()->whereDate('price_date', $data['transaction_date'])->first();
        $data['reference_gold_price'] = $price?->reference_gold_price ?? 0;
        $data['reference_silver_price'] = $price?->reference_silver_price ?? 0;

        if (in_array($data['business_type'], ['income', 'operating_expense'], true)) {
            return array_merge($data, [
                'stock_bucket' => null,
                'product_type' => null,
                'wrap_material' => null,
                'pure_gold_weight' => 0,
                'wrapped_gold_weight' => 0,
                'material_weight' => 0,
                'material_pieces' => 0,
                'item_weights' => null,
                'gold_unit_price' => null,
                'silver_unit_price' => null,
            ]);
        }

        $data['stock_bucket'] = $data['business_type'] === 'sale' ? 'sale_stock' : 'scrap_stock';
        $data['expense_category'] = null;
        $data = $this->applyItemWeights($data);
        $data['pure_gold_weight'] = $data['product_type'] === 'pure_gold' ? ($data['pure_gold_weight'] ?? 0) : 0;
        $data['wrapped_gold_weight'] = $data['product_type'] === 'gold_wrapped' ? ($data['wrapped_gold_weight'] ?? 0) : 0;
        $data['material_weight'] = in_array($data['product_type'], ['pure_silver', 'gold_wrapped'], true) ? ($data['material_weight'] ?? 0) : 0;
        $data['material_pieces'] = $data['material_pieces'] ?? 0;
        $data['wrap_material'] = $data['product_type'] === 'gold_wrapped' ? $data['wrap_material'] : null;

        return $data;
    }

    private function applyItemWeights(array $data): array
    {
        $items = collect($data['item_weights'] ?? [])
            ->map(function (array $item) use ($data): array {
                $goldWeight = round((float) ($item['pure_gold_weight'] ?? 0), 3);
                $wrappedGoldWeight = round((float) ($item['wrapped_gold_weight'] ?? 0), 3);
                $materialWeight = round((float) ($item['material_weight'] ?? 0), 3);
                $goldUnitPrice = round((float) ($item['gold_unit_price'] ?? $data['gold_unit_price'] ?? 0), 2);
                $silverUnitPrice = round((float) ($item['silver_unit_price'] ?? $data['silver_unit_price'] ?? 0), 2);
                $amount = match ($data['product_type']) {
                    'pure_gold' => $goldWeight * $goldUnitPrice,
                    'gold_wrapped' => ($wrappedGoldWeight * $goldUnitPrice) + ($materialWeight * $silverUnitPrice),
                    default => $materialWeight * $silverUnitPrice,
                };

                return [
                    'pure_gold_weight' => $goldWeight,
                    'wrapped_gold_weight' => $wrappedGoldWeight,
                    'material_weight' => $materialWeight,
                    'gold_unit_price' => $goldUnitPrice,
                    'silver_unit_price' => $silverUnitPrice,
                    'amount' => round($amount, 2),
                ];
            })
            ->filter(function (array $item) use ($data): bool {
                return match ($data['product_type']) {
                    'pure_gold' => $item['pure_gold_weight'] > 0,
                    'pure_silver' => $item['material_weight'] > 0,
                    'gold_wrapped' => $item['wrapped_gold_weight'] > 0 || $item['material_weight'] > 0,
                    default => false,
                };
            })
            ->values()
            ->all();

        if ($items === []) {
            $data['item_weights'] = null;

            return $data;
        }

        $data['item_weights'] = $items;
        $data['material_pieces'] = count($items);
        $data['pure_gold_weight'] = collect($items)->sum('pure_gold_weight');
        $data['wrapped_gold_weight'] = collect($items)->sum('wrapped_gold_weight');
        $data['material_weight'] = collect($items)->sum('material_weight');
        $data['gold_unit_price'] = collect($items)->avg('gold_unit_price') ?: null;
        $data['silver_unit_price'] = collect($items)->avg('silver_unit_price') ?: null;

        if ($data['business_type'] === 'recycle') {
            $data['amount'] = collect($items)->sum('amount');
        }

        return $data;
    }

    private function present(Transaction $transaction, BusinessDictionary $dictionary, string $language): array
    {
        return array_merge($transaction->toArray(), [
            'user' => $transaction->user,
            'business_type_label' => $dictionary->label('business_type', $transaction->business_type, $language),
            'payment_account_label' => $dictionary->label('payment_account', $transaction->payment_account, $language),
            'online_method_label' => $dictionary->label('online_method', $transaction->online_method, $language),
            'stock_bucket_label' => $dictionary->label('stock_bucket', $transaction->stock_bucket, $language),
            'product_type_label' => $dictionary->label('product_type', $transaction->product_type, $language),
            'wrap_material_label' => $dictionary->label('wrap_material', $transaction->wrap_material, $language),
            'expense_category_label' => $dictionary->label('expense_category', $transaction->expense_category, $language),
        ]);
    }
}
