# 第一阶段：多店铺和账户 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在不改变现有资金和库存结果的前提下，让老板管理多个店铺、员工固定所属店铺，并支持本人修改资料和密码。

**Architecture:** 新增店铺表，并把流水、期初数据、回收参考价和员工归到具体店铺。服务器通过统一的店铺上下文决定当前账户能读写哪家店，员工传入其他店铺编号也会被拒绝；老板可以选择单店或全部店铺查看。

**Tech Stack:** Laravel 12、PHPUnit、SQLite/MySQL、Vue 3、Element Plus、Axios

---

## 文件安排

- Create: `financeBackend/app/Models/Store.php` — 店铺资料和关联关系。
- Create: `financeBackend/app/Support/StoreContext.php` — 统一决定老板当前选择和员工固定店铺。
- Create: `financeBackend/app/Http/Controllers/Api/Admin/StoreController.php` — 老板管理店铺。
- Create: `financeBackend/database/migrations/2026_06_29_220000_add_multi_store_support.php` — 建店铺表、迁移旧数据并增加店铺关联。
- Modify: `financeBackend/app/Models/AdminUser.php` — 员工所属店铺、登录名和店铺关系。
- Modify: `financeBackend/app/Models/Transaction.php` — 流水所属店铺和实际后台操作人。
- Modify: `financeBackend/app/Models/OpeningBalance.php` — 期初数据所属店铺。
- Modify: `financeBackend/app/Models/RecyclePrice.php` — 参考价所属店铺。
- Modify: `financeBackend/app/Support/AdminAccess.php` — 返回老板/员工身份和所属店铺。
- Modify: `financeBackend/app/Support/FinanceStats.php` — 按店铺计算资金和库存。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/AuthController.php` — 登录名登录、本人资料和密码。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/UserController.php` — 老板创建员工时必须指定店铺。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php` — 查询和写入强制归店。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/OpeningBalanceController.php` — 期初数据强制归店。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/RecyclePriceController.php` — 参考价强制归店。
- Modify: `financeBackend/database/seeders/DatabaseSeeder.php` — 创建唯一老板和默认第一家店。
- Modify: `financeBackend/routes/api.php` — 店铺及本人资料接口。
- Modify: `financeBackend/tests/Feature/FinanceApiTest.php` — 多店隔离、账户和迁移测试。
- Modify: `financeFrontend/src/api.js` — 老板选择店铺时发送店铺编号。
- Modify: `financeFrontend/src/App.vue` — 店铺切换、店铺管理、员工所属店铺和本人改密。
- Modify: `financeFrontend/src/style.css` — 新页面和移动端样式。

### Task 1: 建立店铺资料并安全迁移旧账

**Files:**
- Create: `financeBackend/database/migrations/2026_06_29_220000_add_multi_store_support.php`
- Create: `financeBackend/app/Models/Store.php`
- Modify: `financeBackend/app/Models/AdminUser.php`
- Modify: `financeBackend/app/Models/Transaction.php`
- Modify: `financeBackend/app/Models/OpeningBalance.php`
- Modify: `financeBackend/app/Models/RecyclePrice.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写迁移失败测试**

新增测试，先建立旧结构数据，再运行新迁移并断言：

```php
public function test_existing_finance_data_is_assigned_to_the_default_store(): void
{
    $this->seed();

    $store = Store::query()->where('is_default', true)->sole();
    $owner = AdminUser::query()->where('is_super_admin', true)->sole();

    $this->assertSame('总店', $store->name);
    $this->assertNull($owner->store_id);
    $this->assertNotNull($owner->username);
    $this->assertSame(0, Transaction::query()->whereNull('store_id')->count());
    $this->assertSame(0, OpeningBalance::query()->whereNull('store_id')->count());
}
```

- [ ] **Step 2: 确认测试因缺少店铺结构而失败**

Run:

```bash
cd financeBackend
php artisan test --filter=existing_finance_data_is_assigned_to_the_default_store
```

Expected: FAIL，提示 `stores`、`store_id` 或 `username` 不存在。

- [ ] **Step 3: 实现最小迁移和模型**

迁移必须：

```php
Schema::create('stores', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->boolean('enabled')->default(true);
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

然后为 `admin_users` 增加唯一 `username` 和可空 `store_id`，为 `transactions` 增加 `store_id`、`recorded_by_admin_id`，为 `opening_balances`、`recycle_prices` 增加 `store_id`。迁移创建名为“总店”的默认店铺，将所有旧流水、期初和参考价归到该店，并从原邮箱生成不重复的登录名。老板的 `store_id` 保持为空，员工必须在接口层绑定店铺。

期初数据唯一约束改为：

```php
$table->unique(['store_id', 'scope', 'key']);
```

参考价唯一约束改为：

```php
$table->unique(['store_id', 'price_date']);
```

- [ ] **Step 4: 运行迁移测试和完整后端测试**

```bash
cd financeBackend
php artisan test --filter=existing_finance_data_is_assigned_to_the_default_store
php artisan test
```

Expected: 新测试通过，原有 14 项测试仍通过。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Models financeBackend/database/migrations financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add store ownership to finance data"
```

### Task 2: 服务器强制执行店铺隔离

**Files:**
- Create: `financeBackend/app/Support/StoreContext.php`
- Modify: `financeBackend/app/Support/FinanceStats.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/OpeningBalanceController.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/RecyclePriceController.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写跨店访问失败测试**

```php
public function test_staff_is_forced_to_their_store_and_owner_can_switch_stores(): void
{
    $first = Store::query()->create(['name' => '一店', 'enabled' => true]);
    $second = Store::query()->create(['name' => '二店', 'enabled' => true]);
    $staff = AdminUser::query()->create([
        'name' => '一店员工',
        'username' => 'store-one-staff',
        'email' => 'one@example.test',
        'password' => 'password',
        'store_id' => $first->id,
        'enabled' => true,
        'permissions' => ['dashboard', 'transactions'],
    ]);
    $staff->forceFill(['api_token' => 'store-one-token'])->save();

    $ledgerUser = User::query()->create([
        'openid' => 'store-test-user',
        'name' => '测试用户',
        'api_token' => 'store-test-user-token',
    ]);
    foreach ([[$first, '一店记录'], [$second, '二店记录']] as [$store, $remark]) {
        Transaction::query()->create([
            'store_id' => $store->id,
            'user_id' => $ledgerUser->id,
            'business_type' => 'sale',
            'payment_account' => 'cash',
            'amount' => 100,
            'product_type' => 'pure_gold',
            'stock_bucket' => 'sale_stock',
            'pure_gold_weight' => 1,
            'transaction_date' => '2026-06-29',
            'remark' => $remark,
        ]);
    }

    $this->withToken('store-one-token')
        ->withHeader('X-Store-Id', (string) $second->id)
        ->getJson('/api/admin/transactions')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.remark', '一店记录');
}
```

另加断言：员工写入的 `store_id` 永远是所属店铺；老板未选择具体店铺时不能新增流水、保存期初或参考价。

- [ ] **Step 2: 确认测试失败**

```bash
cd financeBackend
php artisan test --filter=staff_is_forced_to_their_store_and_owner_can_switch_stores
```

Expected: FAIL，当前接口会返回两家店的数据。

- [ ] **Step 3: 实现统一店铺判断**

`StoreContext` 提供：

```php
public function readableStoreId(AdminUser $admin, Request $request): ?int;
public function writableStore(AdminUser $admin, Request $request): Store;
public function scope(Builder $query, AdminUser $admin, Request $request): Builder;
```

规则：

- 员工永远使用自身 `store_id`，忽略请求头；
- 老板读取时可传具体店铺；不传代表全部店铺；
- 老板写入时必须传具体且启用的店铺；
- 所有流水、统计、账户明细、期初和参考价都调用同一个上下文；
- 纯金回收专用资金继续属于具体店铺。

- [ ] **Step 4: 运行针对性测试和完整测试**

```bash
cd financeBackend
php artisan test --filter=store
php artisan test
```

Expected: 所有跨店读取与写入测试通过。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Support financeBackend/app/Http/Controllers/Api/Admin financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: enforce store isolation in finance APIs"
```

### Task 3: 店铺管理和员工所属店铺

**Files:**
- Create: `financeBackend/app/Http/Controllers/Api/Admin/StoreController.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/UserController.php`
- Modify: `financeBackend/app/Support/AdminAccess.php`
- Modify: `financeBackend/routes/api.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写老板和员工权限测试**

新增三个测试并分别执行这些实际请求：

```php
$createdStore = $this->withToken('owner-token')
    ->postJson('/api/admin/stores', ['name' => '二店'])
    ->assertCreated()
    ->json();

$this->withToken('owner-token')->postJson('/api/admin/users', [
    'name' => '二店员工',
    'username' => 'store-two-staff',
    'password' => 'secret123',
    'store_id' => $createdStore['id'],
    'enabled' => true,
    'permissions' => ['dashboard', 'transactions'],
])->assertCreated()->assertJsonPath('store.id', $createdStore['id']);

$this->withToken('staff-token')
    ->postJson('/api/admin/stores', ['name' => '无权创建'])
    ->assertForbidden();

$this->withToken('owner-token')
    ->deleteJson('/api/admin/stores/'.$createdStore['id'])
    ->assertOk();

$this->assertFalse(Store::query()->findOrFail($createdStore['id'])->enabled);
```

老板新增员工时请求必须包含：

```json
{
  "name": "一店员工",
  "username": "shop1-staff",
  "password": "secret123",
  "store_id": 1,
  "enabled": true,
  "permissions": ["dashboard", "transactions"]
}
```

- [ ] **Step 2: 确认测试失败**

```bash
cd financeBackend
php artisan test --filter=owner_manages_stores
```

Expected: FAIL，店铺接口不存在。

- [ ] **Step 3: 实现店铺和员工接口**

新增路由：

```php
Route::apiResource('stores', AdminStoreController::class)->except(['show']);
```

规则：

- 只有老板可访问；
- 店名必填且同一老板下不重复；
- 有账目或员工的店铺执行停用，不物理删除；
- 员工必须选择启用店铺；
- 员工列表返回店铺名称；
- 对外只显示“老板”和“员工”，不再显示“超级管理员”和“普通用户”。

- [ ] **Step 4: 运行测试**

```bash
cd financeBackend
php artisan test --filter='store|manage_users'
php artisan test
```

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin financeBackend/app/Support/AdminAccess.php financeBackend/routes/api.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: let owner manage stores and staff assignments"
```

### Task 4: 老板和员工修改自己的资料与密码

**Files:**
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/AuthController.php`
- Modify: `financeBackend/routes/api.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写本人资料和改密测试**

```php
public function test_user_can_change_own_profile_and_password_with_current_password(): void
{
    $this->seed();
    $owner = AdminUser::query()->where('is_super_admin', true)->sole();
    $owner->forceFill(['api_token' => 'owner-token'])->save();

    $this->withToken('owner-token')->putJson('/api/admin/me/profile', [
        'name' => '老板',
        'username' => 'owner',
    ])->assertOk()->assertJsonPath('username', 'owner');

    $this->withToken('owner-token')->putJson('/api/admin/me/password', [
        'current_password' => 'wrong',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertStatus(422);

    $this->withToken('owner-token')->putJson('/api/admin/me/password', [
        'current_password' => 'password',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertOk();
}
```

- [ ] **Step 2: 确认测试失败**

```bash
cd financeBackend
php artisan test --filter=user_can_change_own_profile
```

- [ ] **Step 3: 实现接口**

新增：

```php
Route::put('me/profile', [AdminAuthController::class, 'updateProfile']);
Route::put('me/password', [AdminAuthController::class, 'updatePassword']);
```

登录接口接受 `username`，保留邮箱登录兼容现有老板。改密必须验证旧密码，新密码至少 8 位并二次确认，成功后撤销旧 token，要求重新登录。

- [ ] **Step 4: 运行测试**

```bash
cd financeBackend
php artisan test --filter='profile|password|login'
php artisan test
```

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin/AuthController.php financeBackend/routes/api.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: support username login and self-service password changes"
```

### Task 5: 前端增加店铺切换、店铺管理和我的账户

**Files:**
- Modify: `financeFrontend/src/api.js`
- Modify: `financeFrontend/src/App.vue`
- Modify: `financeFrontend/src/style.css`

- [ ] **Step 1: 建立前端可验证结果**

在实现前运行：

```bash
cd financeFrontend
npm run build
```

记录当前构建通过。实现后的人工验收目标：

- 老板顶部可选择“全部店铺、总店、第二家店”；
- 员工顶部只显示所属店名，没有切换按钮；
- 老板可以新增、改名、停用店铺；
- 新增员工必须选一家店；
- 页面显示“老板/员工”；
- 老板和员工都能在“我的账户”修改姓名、登录名和密码；
- 修改密码后跳回登录页。

- [ ] **Step 2: 让 API 自动发送店铺编号**

在 Axios 请求拦截器加入：

```js
const storeId = localStorage.getItem('selected_store_id')
if (storeId) config.headers['X-Store-Id'] = storeId
```

- [ ] **Step 3: 实现页面**

在 `App.vue` 增加：

- `stores`、`selectedStoreId`、店铺编辑表单；
- 老板店铺切换，切换后重新加载统计、流水、期初和参考价；
- 店铺管理菜单；
- 员工表单中的所属店铺；
- “我的账户”对话框；
- 登录字段由邮箱改为“登录账号”，仍兼容输入邮箱；
- 移动端同样可操作。

- [ ] **Step 4: 构建并用 Google Chrome 验收**

```bash
cd financeFrontend
npm run build
```

然后在 Google Chrome 中分别以老板和员工登录，检查上述所有目标，并确认员工无法切店。

- [ ] **Step 5: 提交**

```bash
git add financeFrontend/src
git commit -m "feat: add store and account management UI"
```

### Task 6: 完整回归和迁移安全检查

**Files:**
- Modify: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 检查 SQLite 全新安装**

```bash
cd financeBackend
php artisan migrate:fresh --seed --env=testing
php artisan test
```

Expected: 所有测试通过。

- [ ] **Step 2: 检查 MySQL 专用语句保护**

确认所有修改 ENUM 的原生 SQL 只在 MySQL 执行，新增迁移同时兼容 SQLite 测试。

- [ ] **Step 3: 检查代码格式**

```bash
cd financeBackend
./vendor/bin/pint
./vendor/bin/pint --test
```

Expected: PASS。

- [ ] **Step 4: 检查前端**

```bash
cd financeFrontend
npm run build
```

Expected: 构建成功；记录依赖本身的注释和包体积警告。

- [ ] **Step 5: 最终检查**

```bash
git diff --check
git status --short
```

确认没有 Token、`.env`、数据库文件、草图或备份进入 Git。

- [ ] **Step 6: 固定阶段版本**

第一阶段验收通过后，再按版本规则更新 `VERSION`、创建补丁标签、本地备份并推送 GitHub。此步骤不得在用户验收前执行。
