<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_read_dictionary(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'admin']);

        $this->withToken($login->json('token'))
            ->getJson('/api/admin/i18n')
            ->assertOk()
            ->assertJsonFragment(['business_type.sale' => '销售']);
    }

    public function test_admin_dictionary_switches_to_uyghur(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ]);

        $login->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/admin/i18n?lang=ug-CN')
            ->assertOk()
            ->assertJsonFragment(['nav.home' => 'باش بەت'])
            ->assertJsonFragment(['page.dictionary.title' => 'كۆپ تىللىق لۇغەت']);
    }

    public function test_app_login_requires_wechat_configuration(): void
    {
        $this->postJson('/api/app/login', ['code' => 'test-code'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'WECHAT_APPID 和 WECHAT_SECRET 尚未配置']);
    }

    public function test_sales_recycle_and_expenses_update_balances_and_inventory(): void
    {
        $this->seed();

        $user = User::query()->create([
            'name' => '测试用户',
            'openid' => 'openid-test',
            'api_token' => 'token-test',
        ]);

        $this->withToken($user->api_token)->postJson('/api/app/opening-balance', [
            'cash' => 1000,
            'online_wechat' => 500,
            'sale_stock.pure_gold.pure_gold_weight' => 100,
            'sale_stock.pure_gold.pieces' => 10,
            'scrap_stock.gold_wrapped_copper.wrapped_gold_weight' => 1,
            'scrap_stock.gold_wrapped_copper.copper_weight' => 5,
            'scrap_stock.gold_wrapped_copper.pieces' => 1,
        ])->assertOk();

        $this->withToken($user->api_token)->postJson('/api/app/transactions', [
            'business_type' => 'sale',
            'payment_account' => 'cash',
            'amount' => 200,
            'product_type' => 'pure_gold',
            'pure_gold_weight' => 10,
            'material_pieces' => 1,
            'transaction_date' => '2026-06-10',
            'remark' => '卖出纯金',
        ])->assertCreated();

        $this->withToken($user->api_token)->postJson('/api/app/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'online',
            'online_method' => 'wechat',
            'amount' => 80,
            'product_type' => 'gold_wrapped',
            'wrap_material' => 'copper',
            'wrapped_gold_weight' => 2,
            'material_weight' => 8,
            'material_pieces' => 2,
            'transaction_date' => '2026-06-10',
            'remark' => '回收金包铜',
        ])->assertCreated();

        $this->withToken($user->api_token)->postJson('/api/app/transactions', [
            'business_type' => 'operating_expense',
            'payment_account' => 'cash',
            'amount' => 50,
            'expense_category' => 'electricity',
            'transaction_date' => '2026-06-10',
            'remark' => '电费',
        ])->assertCreated()->assertJsonFragment(['remark' => '电费']);

        $this->withToken($user->api_token)
            ->getJson('/api/app/stats/current?month=2026-06')
            ->assertOk()
            ->assertJsonPath('cash', 1150)
            ->assertJsonPath('online.wechat', 420)
            ->assertJsonPath('monthly.sales', 200)
            ->assertJsonPath('monthly.recycle', 80)
            ->assertJsonPath('monthly.operating_expenses', 50)
            ->assertJsonPath('stock.sale_stock.products.pure_gold.pure_gold_weight', 90)
            ->assertJsonPath('stock.scrap_stock.products.gold_wrapped_copper.wrapped_gold_weight', 3)
            ->assertJsonPath('stock.scrap_stock.products.gold_wrapped_copper.copper_weight', 13);
    }

    public function test_payment_validation_rules(): void
    {
        $this->seed();

        $user = User::query()->create([
            'name' => '测试用户',
            'openid' => 'openid-test',
            'api_token' => 'token-test',
        ]);

        $this->withToken($user->api_token)->postJson('/api/app/transactions', [
            'business_type' => 'operating_expense',
            'payment_account' => 'online',
            'amount' => 10,
            'expense_category' => 'rent',
            'transaction_date' => '2026-06-10',
        ])->assertStatus(422)->assertJsonFragment(['message' => '线上收支必须选择银行、微信或支付宝']);

        $this->withToken($user->api_token)->postJson('/api/app/transactions', [
            'business_type' => 'operating_expense',
            'payment_account' => 'cash',
            'online_method' => 'bank',
            'amount' => 10,
            'expense_category' => 'rent',
            'transaction_date' => '2026-06-10',
        ])->assertStatus(422)->assertJsonFragment(['message' => '现金收支不能选择线上方式']);
    }
}
