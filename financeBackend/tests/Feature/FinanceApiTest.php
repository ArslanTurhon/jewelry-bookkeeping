<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AdminUser;
use App\Models\OpeningBalance;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $login->assertJsonPath('admin.is_super_admin', true);

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
        ])->assertStatus(422)->assertJsonFragment(['message' => '非线上账户不能选择线上方式']);
    }

    public function test_admin_can_record_pure_gold_recycle_with_fund_and_unit_prices(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ]);

        $login->assertOk();
        $token = $login->json('token');

        $this->withToken($token)->postJson('/api/admin/opening-balance', [
            'pure_gold_fund' => 10000,
        ])->assertOk();

        $this->withToken($token)->postJson('/api/admin/recycle-price', [
            'price_date' => '2026-06-15',
            'reference_gold_price' => 530,
            'reference_silver_price' => 6,
        ])->assertOk();

        $this->withToken($token)->postJson('/api/admin/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'pure_gold_fund',
            'product_type' => 'pure_gold',
            'transaction_date' => '2026-06-15',
            'remark' => '后台回收纯金',
            'item_weights' => [
                ['pure_gold_weight' => 2, 'gold_unit_price' => 510],
                ['pure_gold_weight' => 3, 'gold_unit_price' => 520],
            ],
        ])->assertCreated()
            ->assertJsonPath('amount', '2580.00')
            ->assertJsonPath('material_pieces', 2);

        $this->withToken($token)
            ->getJson('/api/admin/stats/current?month=2026-06')
            ->assertOk()
            ->assertJsonPath('pure_gold_fund', 7420)
            ->assertJsonPath('recycle_cost.pure_gold.pure_gold_weight', 5)
            ->assertJsonPath('recycle_cost.pure_gold.average_gold_price', 516);
    }

    public function test_admin_transactions_default_to_50_per_page_and_filter_by_date_range(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ]);

        $login->assertOk();
        $token = $login->json('token');

        for ($i = 1; $i <= 55; $i++) {
            $this->withToken($token)->postJson('/api/admin/transactions', [
                'business_type' => 'income',
                'payment_account' => 'cash',
                'amount' => 10 + $i,
                'transaction_date' => $i <= 5 ? '2026-06-03' : '2026-06-04',
                'remark' => '分页测试 '.$i,
            ])->assertCreated();
        }

        $this->withToken($token)
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('total', 55)
            ->assertJsonCount(50, 'data');

        $this->withToken($token)
            ->getJson('/api/admin/transactions?date_from=2026-06-03&date_to=2026-06-03')
            ->assertOk()
            ->assertJsonPath('total', 5);
    }

    public function test_super_admin_can_manage_users_and_reset_password(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');
        $defaultStoreId = Store::query()->where('is_default', true)->value('id');

        $created = $this->withToken($token)->postJson('/api/admin/users', [
            'name' => '店员A',
            'username' => 'staff-a',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'store_id' => $defaultStoreId,
            'enabled' => true,
            'permissions' => ['dashboard', 'transactions', 'users'],
        ])->assertCreated()
            ->assertJsonPath('permissions', ['dashboard', 'transactions'])
            ->json();

        $this->withToken($token)->putJson('/api/admin/users/'.$created['id'], [
            'name' => '店员A改',
            'username' => 'staff-a',
            'email' => 'staff@example.test',
            'store_id' => $defaultStoreId,
            'enabled' => true,
            'permissions' => ['dashboard'],
        ])->assertOk()->assertJsonPath('permissions', ['dashboard']);

        $this->withToken($token)->postJson('/api/admin/users/'.$created['id'].'/reset-password', [
            'password' => 'newpass123',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => 'staff@example.test',
            'password' => 'newpass123',
        ])->assertOk()->assertJsonPath('admin.permissions', ['dashboard']);
    }

    public function test_regular_admin_permissions_are_enforced(): void
    {
        $this->seed();

        $staff = AdminUser::query()->create([
            'name' => '只看首页',
            'email' => 'dashboard@example.test',
            'password' => Hash::make('password'),
            'enabled' => true,
            'permissions' => ['dashboard'],
        ]);
        $staff->forceFill(['api_token' => 'staff-token'])->save();

        $this->withToken('staff-token')->getJson('/api/admin/stats/current?month=2026-06')->assertOk();
        $this->withToken('staff-token')->getJson('/api/admin/transactions')->assertForbidden();
        $this->withToken('staff-token')->getJson('/api/admin/users')->assertForbidden();

        $staff->forceFill(['enabled' => false])->save();

        $this->postJson('/api/admin/login', [
            'email' => 'dashboard@example.test',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonFragment(['message' => '账号已停用']);
    }

    public function test_recycle_clerk_can_only_create_and_query_authorized_recycle_records(): void
    {
        $this->seed();

        $staff = AdminUser::query()->create([
            'name' => '纯金回收店员',
            'email' => 'gold-recycle@example.test',
            'password' => Hash::make('password'),
            'enabled' => true,
            'permissions' => ['recycle_pure_gold'],
        ]);
        $staff->forceFill(['api_token' => 'gold-recycle-token'])->save();

        $created = $this->withToken('gold-recycle-token')->postJson('/api/admin/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'pure_gold_fund',
            'product_type' => 'pure_gold',
            'transaction_date' => '2026-06-15',
            'remark' => '店员录入纯金回收',
            'item_weights' => [
                ['pure_gold_weight' => 1.5, 'gold_unit_price' => 500],
            ],
        ])->assertCreated()->json();

        $this->withToken('gold-recycle-token')->postJson('/api/admin/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'cash',
            'product_type' => 'gold_wrapped',
            'wrap_material' => 'silver',
            'transaction_date' => '2026-06-15',
            'item_weights' => [
                ['wrapped_gold_weight' => 0.2, 'material_weight' => 3, 'gold_unit_price' => 300, 'silver_unit_price' => 4],
            ],
        ])->assertForbidden();

        $this->withToken('gold-recycle-token')
            ->getJson('/api/admin/transactions?per_page=50')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.product_type', 'pure_gold');

        $this->withToken('gold-recycle-token')->putJson('/api/admin/transactions/'.$created['id'], [
            'business_type' => 'recycle',
            'payment_account' => 'pure_gold_fund',
            'product_type' => 'pure_gold',
            'transaction_date' => '2026-06-15',
            'item_weights' => [
                ['pure_gold_weight' => 2, 'gold_unit_price' => 500],
            ],
        ])->assertForbidden();

        $this->withToken('gold-recycle-token')->deleteJson('/api/admin/transactions/'.$created['id'])->assertForbidden();
    }

    public function test_gold_wrapped_recycle_permission_can_create_gold_wrapped_silver_only(): void
    {
        $this->seed();

        $staff = AdminUser::query()->create([
            'name' => '金包银回收店员',
            'email' => 'wrapped-recycle@example.test',
            'password' => Hash::make('password'),
            'enabled' => true,
            'permissions' => ['recycle_gold_wrapped'],
        ]);
        $staff->forceFill(['api_token' => 'wrapped-recycle-token'])->save();

        $this->withToken('wrapped-recycle-token')->postJson('/api/admin/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'cash',
            'product_type' => 'gold_wrapped',
            'wrap_material' => 'silver',
            'transaction_date' => '2026-06-15',
            'remark' => '店员录入金包银回收',
            'item_weights' => [
                ['wrapped_gold_weight' => 0.3, 'material_weight' => 5, 'gold_unit_price' => 300, 'silver_unit_price' => 4],
            ],
        ])->assertCreated();

        $this->withToken('wrapped-recycle-token')->postJson('/api/admin/transactions', [
            'business_type' => 'recycle',
            'payment_account' => 'pure_gold_fund',
            'product_type' => 'pure_gold',
            'transaction_date' => '2026-06-15',
            'item_weights' => [
                ['pure_gold_weight' => 1, 'gold_unit_price' => 500],
            ],
        ])->assertForbidden();

        $this->withToken('wrapped-recycle-token')
            ->getJson('/api/admin/transactions?per_page=50')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.product_type', 'gold_wrapped')
            ->assertJsonPath('data.0.wrap_material', 'silver');
    }

    public function test_super_admin_is_protected_from_user_management_changes(): void
    {
        $this->seed();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@finance.local',
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('token');
        $adminId = $login->json('admin.id');

        $this->withToken($token)->putJson('/api/admin/users/'.$adminId, [
            'name' => '管理员',
            'email' => 'admin@finance.local',
            'enabled' => false,
            'permissions' => [],
        ])->assertStatus(422);

        $this->withToken($token)->deleteJson('/api/admin/users/'.$adminId)->assertStatus(422);
    }

    public function test_default_store_owns_existing_finance_data(): void
    {
        $this->seed();

        $store = Store::query()->where('is_default', true)->sole();
        $owner = AdminUser::query()->where('is_super_admin', true)->sole();

        $this->assertSame('总店', $store->name);
        $this->assertNotEmpty($owner->username);
        $this->assertNull($owner->store_id);
        $this->assertSame(0, Transaction::query()->whereNull('store_id')->count());
        $this->assertSame(0, OpeningBalance::query()->whereNull('store_id')->count());
    }

    public function test_staff_can_only_read_and_write_their_own_store(): void
    {
        $this->seed();
        $first = Store::query()->where('is_default', true)->sole();
        $second = Store::query()->create(['name' => '二店', 'enabled' => true]);
        $staff = AdminUser::query()->create([
            'store_id' => $first->id,
            'name' => '一店员工',
            'username' => 'first-staff',
            'email' => 'first-staff@example.test',
            'password' => 'password',
            'enabled' => true,
            'permissions' => ['transactions'],
        ]);
        $staff->forceFill(['api_token' => 'first-store-token'])->save();
        $ledgerUser = User::query()->create([
            'openid' => 'store-isolation-user',
            'name' => '测试',
            'api_token' => 'store-isolation-user-token',
        ]);

        foreach ([[$first->id, '一店记录'], [$second->id, '二店记录']] as [$storeId, $remark]) {
            Transaction::query()->create([
                'store_id' => $storeId,
                'user_id' => $ledgerUser->id,
                'business_type' => 'sale',
                'payment_account' => 'cash',
                'amount' => 100,
                'stock_bucket' => 'sale_stock',
                'product_type' => 'pure_gold',
                'pure_gold_weight' => 1,
                'transaction_date' => '2026-06-29',
                'remark' => $remark,
            ]);
        }

        $this->withToken('first-store-token')
            ->withHeader('X-Store-Id', (string) $second->id)
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.remark', '一店记录');

        $created = $this->withToken('first-store-token')->postJson('/api/admin/transactions', [
            'business_type' => 'sale',
            'payment_account' => 'cash',
            'amount' => 200,
            'product_type' => 'pure_gold',
            'pure_gold_weight' => 2,
            'transaction_date' => '2026-06-29',
        ])->assertCreated()->json();

        $this->assertSame($first->id, $created['store_id']);
        $this->assertSame($staff->id, $created['recorded_by_admin_id']);
    }

    public function test_owner_manages_stores_and_assigns_staff_to_one_store(): void
    {
        $this->seed();
        $owner = AdminUser::query()->where('is_super_admin', true)->sole();
        $owner->forceFill(['api_token' => 'owner-store-token'])->save();

        $store = $this->withToken('owner-store-token')
            ->postJson('/api/admin/stores', ['name' => '二店'])
            ->assertCreated()
            ->json();

        $staff = $this->withToken('owner-store-token')->postJson('/api/admin/users', [
            'name' => '二店员工',
            'username' => 'second-store-staff',
            'email' => 'second-store@example.test',
            'password' => 'secret123',
            'store_id' => $store['id'],
            'enabled' => true,
            'permissions' => ['dashboard', 'transactions'],
        ])->assertCreated()
            ->assertJsonPath('store.id', $store['id'])
            ->json();

        AdminUser::query()->findOrFail($staff['id'])->forceFill(['api_token' => 'second-store-token'])->save();
        $this->withToken('second-store-token')
            ->postJson('/api/admin/stores', ['name' => '无权创建'])
            ->assertForbidden();

        $this->withToken('owner-store-token')
            ->deleteJson('/api/admin/stores/'.$store['id'])
            ->assertOk();
        $this->assertFalse(Store::query()->findOrFail($store['id'])->enabled);
    }

    public function test_user_can_change_own_profile_and_password(): void
    {
        $this->seed();
        $owner = AdminUser::query()->where('is_super_admin', true)->sole();
        $owner->forceFill(['api_token' => 'owner-profile-token'])->save();

        $this->withToken('owner-profile-token')->putJson('/api/admin/me/profile', [
            'name' => '珠宝店老板',
            'username' => 'boss',
        ])->assertOk()
            ->assertJsonPath('name', '珠宝店老板')
            ->assertJsonPath('username', 'boss');

        $this->withToken('owner-profile-token')->putJson('/api/admin/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertStatus(422);

        $this->withToken('owner-profile-token')->putJson('/api/admin/me/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertOk();

        $this->withToken('owner-profile-token')->getJson('/api/admin/me')->assertUnauthorized();
        $this->postJson('/api/admin/login', [
            'account' => 'boss',
            'password' => 'new-secret-123',
        ])->assertOk()->assertJsonPath('admin.username', 'boss');
    }
}
