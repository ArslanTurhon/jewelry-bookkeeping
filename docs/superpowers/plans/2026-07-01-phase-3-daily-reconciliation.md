# Phase 3 Daily Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a daily store reconciliation workflow where pure-gold and general staff submit only their assigned sections, the system calculates blind-count differences, and the owner confirms or returns each section.

**Architecture:** Add one daily report per store/date and one or two responsibility sections beneath it. A focused service determines responsibility from existing permissions, captures authoritative FinanceStats snapshots, calculates differences, and writes audit records; controllers remain responsible for authentication, validation, and responses. The Vue application adds employee and owner views while continuing to use the existing single-page administration shell.

**Tech Stack:** Laravel 12, Eloquent, SQLite/MySQL-compatible migrations, PHPUnit feature tests, Vue 3, Element Plus, Axios, Vite.

---

### Task 1: Add reconciliation storage and model relationships

**Files:**
- Create: `financeBackend/database/migrations/2026_07_01_100000_add_daily_reconciliations.php`
- Create: `financeBackend/app/Models/DailyReconciliation.php`
- Create: `financeBackend/app/Models/ReconciliationSection.php`
- Modify: `financeBackend/app/Models/Transaction.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: Write the failing storage test**

Add a feature test that creates one report for a store/date, creates `pure_gold` and `general` sections, and asserts the database prevents a duplicate report and duplicate section type.

- [ ] **Step 2: Run the focused test**

Run:

```bash
cd financeBackend
php artisan test --filter=reconciliation_storage
```

Expected: FAIL because the reconciliation tables and models do not exist.

- [ ] **Step 3: Add the migration**

Create:

```php
Schema::create('daily_reconciliations', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->date('reconciliation_date');
    $table->string('status')->default('pending');
    $table->timestamps();
    $table->unique(['store_id', 'reconciliation_date']);
});

Schema::create('reconciliation_sections', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('daily_reconciliation_id')->constrained()->cascadeOnDelete();
    $table->string('section_type');
    $table->string('status')->default('draft');
    $table->foreignId('submitted_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
    $table->unsignedInteger('version')->default(1);
    $table->boolean('no_business')->default(false);
    $table->json('business_summary')->nullable();
    $table->json('actual_snapshot')->nullable();
    $table->json('book_snapshot')->nullable();
    $table->json('differences')->nullable();
    $table->text('difference_reason')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->text('return_reason')->nullable();
    $table->timestamps();
    $table->unique(['daily_reconciliation_id', 'section_type']);
});

Schema::table('transactions', function (Blueprint $table): void {
    $table->foreignId('reconciliation_section_id')->nullable()
        ->after('recorded_by_admin_id')
        ->constrained('reconciliation_sections')
        ->nullOnDelete();
});
```

Provide a reverse-order `down()` implementation and keep the migration compatible with SQLite.

- [ ] **Step 4: Add focused models and relationships**

`DailyReconciliation` owns `sections`, belongs to `store`, casts the date, and exposes a `recalculateStatus()` method.

`ReconciliationSection` belongs to report, submitter, reviewer, and generated transactions; it casts JSON fields, timestamps, version, and `no_business`.

Add `reconciliation_section_id` to the transaction fillable fields and a `reconciliationSection()` relation.

- [ ] **Step 5: Run the focused test**

Run the same command. Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add financeBackend/database/migrations/2026_07_01_100000_add_daily_reconciliations.php \
  financeBackend/app/Models/DailyReconciliation.php \
  financeBackend/app/Models/ReconciliationSection.php \
  financeBackend/app/Models/Transaction.php \
  financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add daily reconciliation storage"
```

### Task 2: Define responsibility, blind-count snapshots, and difference rules

**Files:**
- Create: `financeBackend/app/Support/ReconciliationService.php`
- Modify: `financeBackend/app/Support/FinanceStats.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: Write failing permission and calculation tests**

Add tests proving:

- pure-gold-only staff receive only `pure_gold`;
- transaction staff receive `general`;
- staff with both permissions receive both;
- pre-submit responses contain field definitions but no book values;
- pure-gold differences compare fund, recovered gold weight, and pieces;
- general differences compare cash, each online account, sales stock, and non-pure-gold scrap stock;
- a non-zero difference requires a reason.

- [ ] **Step 2: Run the focused tests**

```bash
cd financeBackend
php artisan test --filter=reconciliation_responsibility
php artisan test --filter=reconciliation_difference
```

Expected: FAIL because `ReconciliationService` does not exist.

- [ ] **Step 3: Expose normalized store snapshots**

Add a public FinanceStats method:

```php
public function reconciliationSnapshot(int $storeId, string $sectionType): array
```

It calls `current(null, null, $storeId)` and returns a flat, rounded key/value array restricted to the requested section. It must not duplicate balance calculations.

- [ ] **Step 4: Implement the reconciliation service**

The service must expose:

```php
public function allowedSections(AdminUser $admin): array;
public function fieldDefinitions(string $sectionType): array;
public function calculateDifferences(array $actual, array $book): array;
public function hasDifferences(array $differences): bool;
public function snapshot(int $storeId, string $sectionType): array;
```

Use existing permissions:

- `recycle_pure_gold` grants `pure_gold`;
- `transactions` grants `general`;
- owner grants both.

Round money to 2 decimals, weights to 3 decimals, and pieces to integers.

- [ ] **Step 5: Run focused tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add financeBackend/app/Support/ReconciliationService.php \
  financeBackend/app/Support/FinanceStats.php \
  financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add reconciliation responsibility rules"
```

### Task 3: Add employee draft and submission APIs

**Files:**
- Create: `financeBackend/app/Http/Controllers/Api/Admin/ReconciliationController.php`
- Modify: `financeBackend/routes/api.php`
- Modify: `financeBackend/app/Models/DailyReconciliation.php`
- Modify: `financeBackend/app/Models/ReconciliationSection.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: Write failing employee workflow tests**

Cover:

- today endpoint returns only the logged-in employee's sections;
- employee cannot choose another store, section, submitter, or date;
- draft save persists entered values without exposing book values;
- no-business submission still requires actual counts;
- employee cannot submit a past date;
- submitted section becomes read-only;
- returned section can be resubmitted and increments its version;
- two employees can submit separate sections of the same store report.

- [ ] **Step 2: Run the focused tests**

```bash
cd financeBackend
php artisan test --filter=employee_reconciliation
```

Expected: FAIL with missing routes.

- [ ] **Step 3: Add routes**

```php
Route::get('reconciliations/today', [AdminReconciliationController::class, 'today']);
Route::put('reconciliations/today/{sectionType}/draft', [AdminReconciliationController::class, 'saveDraft']);
Route::post('reconciliations/today/{sectionType}/submit', [AdminReconciliationController::class, 'submit']);
Route::get('reconciliations/mine', [AdminReconciliationController::class, 'mine']);
```

- [ ] **Step 4: Implement validation and submission**

Use a database transaction and row lock. Derive store, date, employee, and allowed section from the authenticated account.

Accepted payload:

```php
[
    'no_business' => ['required', 'boolean'],
    'business_summary' => ['nullable', 'array'],
    'actual_snapshot' => ['required', 'array'],
    'difference_reason' => ['nullable', 'string', 'max:500'],
]
```

Validate each nested key against `fieldDefinitions()`. On submission capture the book snapshot, calculate differences, require a reason when differences exist, set status to `submitted`, and write `reconciliation.submitted` or `reconciliation.resubmitted` through `AuditLogger`.

- [ ] **Step 5: Run focused tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin/ReconciliationController.php \
  financeBackend/routes/api.php \
  financeBackend/app/Models/DailyReconciliation.php \
  financeBackend/app/Models/ReconciliationSection.php \
  financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add employee daily reconciliation"
```

### Task 4: Add owner listing, confirmation, return, and audit behavior

**Files:**
- Modify: `financeBackend/app/Http/Controllers/Api/Admin/ReconciliationController.php`
- Modify: `financeBackend/routes/api.php`
- Modify: `financeBackend/app/Models/DailyReconciliation.php`
- Test: `financeBackend/tests/Feature/FinanceApiTest.php`

- [ ] **Step 1: Write failing owner workflow tests**

Cover:

- staff cannot access owner list or review actions;
- owner can filter by store and date;
- owner sees submitter, book values, actual values, differences, and report status;
- owner can confirm one section without changing the other;
- return requires a reason;
- returned section becomes editable only by its responsible employee;
- report status becomes `partial`, `submitted`, `returned`, or `confirmed` from section states;
- review actions create audit records.

- [ ] **Step 2: Run focused tests**

```bash
cd financeBackend
php artisan test --filter=owner_reconciliation
```

Expected: FAIL because owner routes do not exist.

- [ ] **Step 3: Add owner routes**

```php
Route::get('reconciliations', [AdminReconciliationController::class, 'index']);
Route::get('reconciliations/{dailyReconciliation}', [AdminReconciliationController::class, 'show']);
Route::post('reconciliation-sections/{section}/confirm', [AdminReconciliationController::class, 'confirm']);
Route::post('reconciliation-sections/{section}/return', [AdminReconciliationController::class, 'returnSection']);
```

- [ ] **Step 4: Implement owner review methods**

Require owner access. Confirmation accepts only `submitted`, sets reviewer and timestamps, writes `reconciliation.confirmed`, then recalculates report status.

Return validates:

```php
['reason' => ['required', 'string', 'min:2', 'max:500']]
```

It sets `returned`, records the reason and reviewer, writes `reconciliation.returned`, then recalculates report status.

- [ ] **Step 5: Run focused and complete backend tests**

```bash
cd financeBackend
php artisan test --filter=owner_reconciliation
php artisan test
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add financeBackend/app/Http/Controllers/Api/Admin/ReconciliationController.php \
  financeBackend/routes/api.php \
  financeBackend/app/Models/DailyReconciliation.php \
  financeBackend/tests/Feature/FinanceApiTest.php
git commit -m "feat: add owner reconciliation review"
```

### Task 5: Build employee and owner reconciliation screens

**Files:**
- Modify: `financeFrontend/src/App.vue`
- Modify: `financeFrontend/src/style.css`

- [ ] **Step 1: Add navigation and state**

Add a `今日交账` menu item for staff with either responsibility and a `每日交账` menu item for the owner. Add state for today data, drafts, owner filters, list, selected report, return dialog, loading, and errors.

- [ ] **Step 2: Add employee screen**

Render only server-provided sections. Each section contains:

- today's business summary;
- actual fund balances;
- actual stock weights and pieces;
- no-business checkbox;
- difference reason after a submit attempt indicates differences;
- save draft and submit commands.

Never render book values for a draft section. Render book, actual, and differences only after submission.

- [ ] **Step 3: Add owner screen**

Use a compact table with date, store, overall status, pure-gold status, general status, and submitters. A drawer shows each section's business summary, book values, actual values, differences, and audit-relevant timestamps. Add confirm and return commands per section.

- [ ] **Step 4: Add responsive styles**

Use the existing design language, 8px-or-less radii, stable form widths, no nested cards, and a single-column employee form on narrow screens. Ensure long labels wrap without overlapping inputs.

- [ ] **Step 5: Build**

```bash
cd financeFrontend
npm run build
```

Expected: build succeeds; existing dependency annotation and chunk-size warnings may remain.

- [ ] **Step 6: Commit**

```bash
git add financeFrontend/src/App.vue financeFrontend/src/style.css
git commit -m "feat: add daily reconciliation screens"
```

### Task 6: Final verification and Chrome acceptance

**Files:**
- Modify only if verification reveals a tested defect.

- [ ] **Step 1: Run formatting on changed PHP files**

```bash
cd financeBackend
git diff --name-only 99afa7b..HEAD -- '*.php' | sed 's#^financeBackend/##' | xargs ./vendor/bin/pint --test
```

Expected: PASS.

- [ ] **Step 2: Run complete tests and build**

```bash
cd financeBackend
php artisan test

cd ../financeFrontend
npm run build
```

Expected: all backend tests pass and frontend build succeeds.

- [ ] **Step 3: Start isolated preview**

Use a separate SQLite database, run migrations and seed data, create:

- one pure-gold employee;
- one general employee in the first store;
- one employee with both responsibilities in the second store.

Start backend and frontend on free localhost ports.

- [ ] **Step 4: Verify in Google Chrome**

Check:

- pure-gold employee sees only the pure-gold section;
- general employee sees only the general section;
- second-store employee sees both sections;
- book values are hidden before submission;
- owner sees the merged store report;
- owner confirms one section and returns the other;
- returned employee can resubmit;
- desktop and mobile-width layouts do not overlap.

- [ ] **Step 5: Inspect final repository state**

```bash
git status --short --branch
git log --oneline --decorate -10
```

Expected: only intended commits and no unexpected working-tree files.
