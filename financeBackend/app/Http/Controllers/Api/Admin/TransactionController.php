<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\App\TransactionController as AppTransactionController;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Transaction;
use App\Support\BusinessDictionary;
use App\Support\FinanceStats;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request, BusinessDictionary $dictionary)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        $language = $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE));
        $query = Transaction::query()->with('user')->latest('transaction_date')->latest('id');
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

        $page = $query->paginate($request->integer('per_page', 20));
        $page->getCollection()->transform(fn (Transaction $transaction) => array_merge($transaction->toArray(), [
            'user' => $transaction->user,
            'business_type_label' => $dictionary->label('business_type', $transaction->business_type, $language),
            'payment_account_label' => $dictionary->label('payment_account', $transaction->payment_account, $language),
            'online_method_label' => $dictionary->label('online_method', $transaction->online_method, $language),
            'stock_bucket_label' => $dictionary->label('stock_bucket', $transaction->stock_bucket, $language),
            'product_type_label' => $dictionary->label('product_type', $transaction->product_type, $language),
            'wrap_material_label' => $dictionary->label('wrap_material', $transaction->wrap_material, $language),
            'expense_category_label' => $dictionary->label('expense_category', $transaction->expense_category, $language),
        ]));

        return response()->json($page);
    }

    public function monthlyStats(Request $request, FinanceStats $stats)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        return response()->json($stats->current(null, $request->string('month', now()->format('Y-m'))->toString()));
    }

    public function currentStats(Request $request, FinanceStats $stats)
    {
        return $this->monthlyStats($request, $stats);
    }

    private function admin(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }

    private function unauthorized()
    {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
