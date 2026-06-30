# 第二阶段：权限和留痕 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 阻止员工访问店铺支出和跨店数据，并为流水、店铺和员工的修改与作废建立不可变审计记录。

**Architecture:** 新增通用 `audit_logs` 表和 `AuditLogger` 服务，在数据库事务中同步保存业务变更与前后快照。流水使用作废字段代替物理删除，所有财务查询通过统一有效流水作用域排除作废记录；权限判断继续由后端执行，前端只呈现允许的操作。

**Tech Stack:** Laravel 12、Eloquent、PHPUnit、SQLite/MySQL、Vue 3、Element Plus、Axios

---

## 文件安排

- Create: `financeBackend/database/migrations/2026_06_30_100000_add_audit_logs_and_transaction_voiding.php` — 审计表和流水作废字段。
- Create: `financeBackend/app/Models/AuditLog.php` — 审计日志模型及 JSON 转换。
- Create: `financeBackend/app/Support/AuditLogger.php` — 过滤秘密字段并写入统一日志。
- Create: `financeBackend/app/Http/Controllers/Api/Admin/AuditLogController.php` — 老板专属审计查询。
- Modify: `financeBackend/app/Models/Transaction.php` — 作废字段、关系和有效流水作用域。
- Modify: `financeBackend/app/Support/FinanceStats.php` — 所有计算排除作废流水。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php` — 支出隔离、带原因修改和作废。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/UserController.php` — 员工修改和停用留痕。
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/StoreController.php` — 店铺修改和停用留痕。
- Modify: `financeBackend/routes/api.php` — 审计日志查询路由。
- Modify: `financeBackend/tests/Feature/FinanceApiTest.php` — 权限、审计和财务结果测试。
- Modify: `financeFrontend/src/App.vue` — 作废交互、原因字段、审计页面和员工敏感信息隐藏。
- Modify: `financeFrontend/src/style.css` — 审计详情和作废状态样式。

### Task 1: 建立审计和作废数据结构

**Files:**
- Create: `financeBackend/database/migrations/2026_06_30_100000_add_audit_logs_and_transaction_voiding.php`
- Create: `financeBackend/app/Models/AuditLog.php`
- Create: `financeBackend/app/Support/AuditLogger.php`
- Modify: `financeBackend/app/Models/Transaction.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写失败测试**

新增 `test_audit_logger_records_safe_before_and_after_snapshots`，创建老板、店铺和流水后调用期望中的 `AuditLogger::record()`，断言动作、操作人、店铺、原因及 JSON 快照正确，并断言 `password`、`api_token` 不存在。

- [ ] **Step 2: 运行测试确认失败**

```bash
cd financeBackend
php artisan test --filter=audit_logger_records_safe_before_and_after_snapshots
```

Expected: FAIL，提示 `AuditLogger`、`AuditLog` 或 `audit_logs` 不存在。

- [ ] **Step 3: 实现迁移、模型和服务**

迁移创建 `audit_logs`，包含 `store_id`、`actor_admin_id`、`subject_type`、`subject_id`、`action`、`reason`、`before_data`、`after_data` 和时间戳；为 `transactions` 增加 `voided_at`、`voided_by_admin_id`、`void_reason`。

`AuditLogger::record()` 接收：

```php
public function record(
    AdminUser $actor,
    Model $subject,
    string $action,
    ?string $reason,
    ?array $before,
    ?array $after,
): AuditLog
```

服务在保存前递归移除 `password`、`api_token`、`remember_token`。`Transaction` 增加 `scopeActive(Builder $query)`，条件为 `whereNull('voided_at')`。

- [ ] **Step 4: 运行测试**

```bash
cd financeBackend
php artisan test --filter=audit_logger_records_safe_before_and_after_snapshots
```

Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/database/migrations financeBackend/app/Models financeBackend/app/Support/AuditLogger.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add immutable finance audit records"
```

### Task 2: 强制员工支出隔离

**Files:**
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php`
- Modify: `financeBackend/app/Support/FinanceStats.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写失败测试**

新增测试创建同店销售和支出，断言拥有 `dashboard`、`transactions` 的员工：

```php
$this->withToken('staff-token')
    ->getJson('/api/admin/transactions')
    ->assertOk()
    ->assertJsonMissing(['business_type' => 'operating_expense']);

$this->withToken('staff-token')
    ->getJson('/api/admin/transactions?business_type=operating_expense')
    ->assertOk()
    ->assertJsonPath('total', 0);

$this->withToken('staff-token')
    ->postJson('/api/admin/transactions', $expensePayload)
    ->assertForbidden();

$this->withToken('staff-token')
    ->getJson('/api/admin/stats/current')
    ->assertOk()
    ->assertJsonMissingPath('monthly.operating_expenses');
```

- [ ] **Step 2: 运行测试确认失败**

```bash
cd financeBackend
php artisan test --filter=staff_cannot_access_store_expenses
```

Expected: FAIL，员工仍能读取或创建支出，统计仍返回支出金额。

- [ ] **Step 3: 实现后端隔离**

`applyReadableScope()` 对非老板始终增加 `where('business_type', '!=', 'operating_expense')`。`canCreateTransaction()` 在业务类型为 `operating_expense` 且账户不是老板时立即返回 `false`。

`FinanceStats::current()` 增加 `bool $includeSensitive = true` 参数；员工统计调用传 `false`，并从 `monthly` 响应同时移除 `operating_expenses` 和 `net`，避免反推支出。老板保持完整响应。

- [ ] **Step 4: 运行权限测试和完整测试**

```bash
cd financeBackend
php artisan test --filter=staff_cannot_access_store_expenses
php artisan test
```

Expected: PASS，原有权限和多店隔离测试不回退。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php financeBackend/app/Support/FinanceStats.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "fix: hide store expenses from staff"
```

### Task 3: 流水修改、作废和财务计算

**Files:**
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php`
- Modify: `financeBackend/app/Models/Transaction.php`
- Modify: `financeBackend/app/Support/FinanceStats.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写修改和作废失败测试**

覆盖：

- 修改缺少 `change_reason` 返回 `422`；
- 修改成功产生 `transaction.updated`，前后快照不同；
- 作废缺少 `reason` 返回 `422`；
- 作废后记录仍存在且产生 `transaction.voided`；
- 重复作废或修改已作废流水返回 `422`；
- 作废前后现金、库存、月统计和账户明细按预期恢复。

- [ ] **Step 2: 运行测试确认失败**

```bash
cd financeBackend
php artisan test --filter=transaction_audit
php artisan test --filter=voided_transaction
```

Expected: FAIL，当前接口物理删除且没有审计记录。

- [ ] **Step 3: 实现事务化修改和作废**

修改接口验证：

```php
'change_reason' => ['required', 'string', 'min:2', 'max:500'],
```

作废接口验证：

```php
'reason' => ['required', 'string', 'min:2', 'max:500'],
```

两种操作使用 `DB::transaction()`。修改时排除 `store_id`、`recorded_by_admin_id`、`user_id`；作废时写入 `voided_at`、`voided_by_admin_id`、`void_reason`。所有 `FinanceStats` 流水查询和默认流水列表调用 `active()`；老板显式传 `status=voided|all` 时列表按状态筛选。

- [ ] **Step 4: 运行针对性和完整测试**

```bash
cd financeBackend
php artisan test --filter=transaction_audit
php artisan test --filter=voided_transaction
php artisan test
```

Expected: PASS，作废流水不再影响任何财务结果。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin/TransactionController.php financeBackend/app/Models/Transaction.php financeBackend/app/Support/FinanceStats.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: audit and void finance transactions"
```

### Task 4: 店铺、员工和审计查询接口

**Files:**
- Create: `financeBackend/app/Http/Controllers/Api/Admin/AuditLogController.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/UserController.php`
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/StoreController.php`
- Modify: `financeBackend/routes/api.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: 写失败测试**

断言店铺修改写 `store.updated`；员工资料修改写 `user.updated`；仅启用状态变化写 `user.enabled` 或 `user.disabled`；快照没有密码或 token；员工访问 `/api/admin/audit-logs` 返回 `403`；老板按 `store_id` 和 `action` 查询得到正确分页结果。

- [ ] **Step 2: 运行测试确认失败**

```bash
cd financeBackend
php artisan test --filter=audit_log
```

Expected: FAIL，当前管理操作无日志且路由不存在。

- [ ] **Step 3: 实现管理留痕和查询**

控制器在 `DB::transaction()` 中保存业务对象和日志。`AuditLogController@index` 只接受老板，验证筛选字段并预加载 `actor`、`store`，按 `created_at` 和 `id` 倒序分页。路由：

```php
Route::get('audit-logs', [AuditLogController::class, 'index']);
```

- [ ] **Step 4: 运行测试**

```bash
cd financeBackend
php artisan test --filter=audit_log
php artisan test
```

Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin financeBackend/routes/api.php financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: expose owner audit history"
```

### Task 5: 前端作废和操作记录

**Files:**
- Modify: `financeFrontend/src/App.vue`
- Modify: `financeFrontend/src/style.css`

- [ ] **Step 1: 实现角色感知界面**

增加老板专属 `audit` 菜单、审计筛选和详情抽屉。员工隐藏支出统计卡、支出按钮和支出筛选项。流水编辑增加 `change_reason`，作废使用带输入框的 `ElMessageBox.prompt()` 提交 `{ reason }`。

- [ ] **Step 2: 增加流水状态体验**

请求支持 `status` 筛选；表格显示“有效/已作废”，已作废行禁用编辑和作废按钮，并展示作废原因。成功修改或作废后同时刷新流水、统计和审计列表。

- [ ] **Step 3: 构建前端**

```bash
cd financeFrontend
npm run build
```

Expected: Vite 构建成功，无 Vue 编译错误。

- [ ] **Step 4: 提交**

```bash
git add financeFrontend/src/App.vue financeFrontend/src/style.css
git commit -m "feat: add transaction voiding and audit UI"
```

### Task 6: 完整验证

**Files:**
- Verify only

- [ ] **Step 1: 后端格式和测试**

```bash
cd financeBackend
./vendor/bin/pint --test
php artisan test
```

Expected: 格式检查和全部测试通过。

- [ ] **Step 2: 前端构建**

```bash
cd financeFrontend
npm run build
```

Expected: 构建成功。

- [ ] **Step 3: Chrome 验证**

启动本地服务后，使用 `chrome:control-chrome` 验证老板修改、作废、审计详情，以及员工看不到支出和操作记录。检查桌面和移动宽度无重叠。

- [ ] **Step 4: 检查最终差异**

```bash
git status --short
git diff --check
git log --oneline -8
```

Expected: 只有计划内改动，工作树无意外文件，提交历史包含各阶段独立提交。
