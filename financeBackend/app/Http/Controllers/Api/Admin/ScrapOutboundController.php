<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\ScrapOutbound;
use App\Support\AdminAccess;
use App\Support\AuditLogger;
use App\Support\FinanceStats;
use App\Support\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScrapOutboundController extends Controller
{
    public function index(Request $request, StoreContext $stores)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->hasPermission('scrap_outbound')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $query = ScrapOutbound::query()->with('recorder')->latest('outbound_date')->latest('id');
        $stores->scope($query, $admin, $request);
        $page = $query->paginate($request->integer('per_page', 50));
        $page->getCollection()->transform(fn (ScrapOutbound $outbound) => $this->present($outbound, $admin));

        return response()->json($page);
    }

    public function store(Request $request, StoreContext $stores, FinanceStats $stats, AuditLogger $audit)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->hasPermission('scrap_outbound')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'product_type' => ['required', Rule::in(['pure_gold', 'pure_silver', 'gold_wrapped'])],
            'wrap_material' => ['nullable', Rule::in(['silver', 'copper'])],
            'pure_gold_weight' => ['nullable', 'numeric', 'min:0'],
            'wrapped_gold_weight' => ['nullable', 'numeric', 'min:0'],
            'material_weight' => ['nullable', 'numeric', 'min:0'],
            'material_pieces' => ['required', 'integer', 'min:0'],
            'gross_amount' => ['required', 'numeric', 'gt:0'],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'payment_account' => ['required', Rule::in(['cash', 'online', 'pure_gold_fund'])],
            'online_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay'])],
            'fees' => ['nullable', 'array'],
            'fees.*.category' => ['required', Rule::in(['processing', 'refining', 'transport', 'other'])],
            'fees.*.amount' => ['required', 'numeric', 'gt:0'],
            'fees.*.payment_account' => ['required', Rule::in(['deducted', 'cash', 'online'])],
            'fees.*.online_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay'])],
            'outbound_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $store = $stores->writableStore($admin, $request);
        $this->validateAccounts($data);
        $snapshot = $stats->current(null, null, $store->id);
        $cost = $this->costAndValidateStock($data, $snapshot);
        $fees = collect($data['fees'] ?? []);
        $deducted = (float) $fees->where('payment_account', 'deducted')->sum('amount');
        if (abs((float) $data['gross_amount'] - (float) $data['received_amount'] - $deducted) > 0.009) {
            throw ValidationException::withMessages(['received_amount' => '卖出总价减去买方扣费后必须等于实际到账']);
        }
        $totalFees = (float) $fees->sum('amount');
        $outbound = ScrapOutbound::query()->create($data + [
            'store_id' => $store->id,
            'recorded_by_admin_id' => $admin->id,
            'fees' => $fees->values()->all(),
            'cost_amount' => round($cost, 2),
            'profit_amount' => round((float) $data['gross_amount'] - $cost - $totalFees, 2),
        ]);
        $audit->record($admin, $outbound, 'scrap_outbound.created', null, null, $outbound->toArray());

        return response()->json($this->present($outbound->load('recorder'), $admin), 201);
    }

    private function validateAccounts(array $data): void
    {
        if ($data['product_type'] === 'pure_gold' && $data['payment_account'] !== 'pure_gold_fund') {
            throw ValidationException::withMessages(['payment_account' => '纯金旧料回款必须进入纯金专用资金']);
        }
        if ($data['payment_account'] === 'online' && empty($data['online_method'])) {
            throw ValidationException::withMessages(['online_method' => '线上到账必须选择微信、支付宝或银行卡']);
        }
        foreach ($data['fees'] ?? [] as $fee) {
            if ($fee['payment_account'] === 'online' && empty($fee['online_method'])) {
                throw ValidationException::withMessages(['fees' => '线上支付费用必须选择具体账户']);
            }
        }
    }

    private function costAndValidateStock(array $data, array $snapshot): float
    {
        $product = match ($data['product_type']) {
            'pure_gold' => 'pure_gold',
            'pure_silver' => 'pure_silver',
            default => $data['wrap_material'] === 'copper' ? 'gold_wrapped_copper' : 'gold_wrapped_silver',
        };
        $stock = data_get($snapshot, 'stock.scrap_stock.products.'.$product, []);
        $requested = match ($data['product_type']) {
            'pure_gold' => (float) ($data['pure_gold_weight'] ?? 0),
            'pure_silver' => (float) ($data['material_weight'] ?? 0),
            default => (float) ($data['wrapped_gold_weight'] ?? 0),
        };
        $available = match ($data['product_type']) {
            'pure_gold' => (float) ($stock['pure_gold_weight'] ?? 0),
            'pure_silver' => (float) ($stock['silver_weight'] ?? 0),
            default => (float) ($stock['wrapped_gold_weight'] ?? 0),
        };
        if ($requested <= 0 || $requested > $available || (int) $data['material_pieces'] > (int) ($stock['pieces'] ?? 0)) {
            throw ValidationException::withMessages(['weight' => '出库重量或件数超过现有旧料库存']);
        }
        $average = match ($product) {
            'pure_gold' => (float) data_get($snapshot, 'recycle_cost.pure_gold.average_gold_price', 0),
            'pure_silver' => (float) data_get($snapshot, 'recycle_cost.pure_silver.average_silver_price', 0),
            default => (float) data_get($snapshot, 'recycle_cost.'.$product.'.average_total_price_per_gold_gram', 0),
        };

        return $requested * $average;
    }

    private function present(ScrapOutbound $outbound, AdminUser $admin): array
    {
        $data = $outbound->toArray();
        if (! $admin->is_super_admin) {
            unset($data['cost_amount'], $data['profit_amount']);
        }

        return $data;
    }
}
