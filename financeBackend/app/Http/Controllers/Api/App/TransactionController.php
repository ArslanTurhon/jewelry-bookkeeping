<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BusinessDictionary;
use App\Support\FinanceStats;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request, BusinessDictionary $dictionary)
    {
        $user = $this->user($request);
        if (! $user) {
            return $this->unauthorized();
        }

        $language = $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE));
        $query = $user->transactions()->latest('transaction_date')->latest('id');
        $this->applyFilters($query, $request);

        $page = $query->paginate($request->integer('per_page', 20));
        $page->getCollection()->transform(fn (Transaction $transaction) => $this->present($transaction, $dictionary, $language));

        return response()->json($page);
    }

    public function store(Request $request, BusinessDictionary $dictionary)
    {
        $user = $this->user($request);
        if (! $user) {
            return $this->unauthorized();
        }

        $data = $this->validatedData($request);
        $transaction = $user->transactions()->create($this->normalize($data));
        $language = $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE));

        return response()->json($this->present($transaction, $dictionary, $language), 201);
    }

    public function update(Request $request, Transaction $transaction, BusinessDictionary $dictionary)
    {
        $user = $this->user($request);
        if (! $user || $transaction->user_id !== $user->id) {
            return $this->unauthorized();
        }

        $transaction->update($this->normalize($this->validatedData($request)));
        $language = $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE));

        return response()->json($this->present($transaction, $dictionary, $language));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $user = $this->user($request);
        if (! $user || $transaction->user_id !== $user->id) {
            return $this->unauthorized();
        }

        $transaction->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function currentStats(Request $request, FinanceStats $stats)
    {
        $user = $this->user($request);
        if (! $user) {
            return $this->unauthorized();
        }

        return response()->json($stats->current($user->id, $request->string('month', now()->format('Y-m'))->toString()));
    }

    public function monthlyStats(Request $request, FinanceStats $stats)
    {
        return $this->currentStats($request, $stats);
    }

    private function user(Request $request): ?User
    {
        $token = $request->bearerToken();

        return $token ? User::query()->where('api_token', $token)->first() : null;
    }

    private function unauthorized()
    {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'business_type' => ['required', Rule::in(['sale', 'recycle', 'operating_expense'])],
            'payment_account' => ['required', Rule::in(['cash', 'online'])],
            'online_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'stock_bucket' => ['nullable', Rule::in(['sale_stock', 'scrap_stock'])],
            'product_type' => ['nullable', Rule::in(['pure_gold', 'pure_silver', 'gold_wrapped'])],
            'wrap_material' => ['nullable', Rule::in(['silver', 'copper'])],
            'pure_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'wrapped_gold_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'material_weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'material_pieces' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'expense_category' => ['nullable', Rule::in(['rent', 'electricity', 'water', 'salary', 'supplies', 'other'])],
            'transaction_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['payment_account'] === 'online' && empty($data['online_method'])) {
            abort(response()->json(['message' => '线上收支必须选择银行、微信或支付宝'], 422));
        }
        if ($data['payment_account'] === 'cash' && ! empty($data['online_method'])) {
            abort(response()->json(['message' => '现金收支不能选择线上方式'], 422));
        }
        if ($data['business_type'] === 'operating_expense' && empty($data['expense_category'])) {
            abort(response()->json(['message' => '店铺成本支出必须选择分类'], 422));
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

        if ($data['business_type'] === 'operating_expense') {
            return array_merge($data, [
                'stock_bucket' => null,
                'product_type' => null,
                'wrap_material' => null,
                'pure_gold_weight' => 0,
                'wrapped_gold_weight' => 0,
                'material_weight' => 0,
                'material_pieces' => 0,
            ]);
        }

        $data['stock_bucket'] = $data['business_type'] === 'sale' ? 'sale_stock' : 'scrap_stock';
        $data['expense_category'] = null;
        $data['pure_gold_weight'] = $data['product_type'] === 'pure_gold' ? ($data['pure_gold_weight'] ?? 0) : 0;
        $data['wrapped_gold_weight'] = $data['product_type'] === 'gold_wrapped' ? ($data['wrapped_gold_weight'] ?? 0) : 0;
        $data['material_weight'] = in_array($data['product_type'], ['pure_silver', 'gold_wrapped'], true) ? ($data['material_weight'] ?? 0) : 0;
        $data['material_pieces'] = $data['material_pieces'] ?? 0;
        $data['wrap_material'] = $data['product_type'] === 'gold_wrapped' ? $data['wrap_material'] : null;

        return $data;
    }

    private function applyFilters($query, Request $request): void
    {
        foreach (['business_type', 'payment_account', 'online_method', 'stock_bucket', 'product_type', 'wrap_material', 'expense_category'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->filled('month')) {
            $query->where('transaction_date', 'like', $request->string('month').'%');
        }
        if ($request->filled('keyword')) {
            $query->where('remark', 'like', '%'.$request->string('keyword').'%');
        }
    }

    private function present(Transaction $transaction, BusinessDictionary $dictionary, string $language): array
    {
        return array_merge($transaction->toArray(), [
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
