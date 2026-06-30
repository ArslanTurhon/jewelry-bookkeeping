<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\DailyReconciliation;
use App\Models\ReconciliationSection;
use App\Models\Store;
use App\Support\AdminAccess;
use App\Support\AuditLogger;
use App\Support\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationController extends Controller
{
    public function today(Request $request, ReconciliationService $service)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $report = $this->reportFor($admin, $service);
        $sections = collect($service->allowedSections($admin))->map(function (string $type) use ($report, $service): array {
            $section = $report->sections()->firstOrCreate(['section_type' => $type]);

            return $this->presentSection($section, false) + ['fields' => $service->fieldDefinitions($type)];
        });

        return response()->json([
            'date' => $this->businessDate(),
            'store_id' => $admin->store_id,
            'status' => $report->status,
            'sections' => $sections,
        ]);
    }

    public function saveDraft(Request $request, string $sectionType, ReconciliationService $service)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! in_array($sectionType, $service->allowedSections($admin), true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'no_business' => ['required', 'boolean'],
            'business_summary' => ['nullable', 'array'],
            'business_summary.*' => ['numeric', 'min:0'],
            'actual_snapshot' => ['nullable', 'array'],
            'actual_snapshot.*' => ['numeric', 'min:0'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $section = DB::transaction(function () use ($admin, $sectionType, $service, $data): ReconciliationSection {
            $report = $this->reportFor($admin, $service);
            $section = $report->sections()->lockForUpdate()->firstOrCreate(['section_type' => $sectionType]);
            if ($section->status !== 'draft') {
                throw ValidationException::withMessages(['section' => '只有未提交交账可以保存草稿']);
            }
            $section->update([
                'no_business' => $data['no_business'],
                'business_summary' => $data['business_summary'] ?? [],
                'actual_snapshot' => $data['actual_snapshot'] ?? [],
                'difference_reason' => $data['difference_reason'] ?? null,
            ]);

            return $section->fresh();
        });

        return response()->json($this->presentSection($section, false));
    }

    public function submit(
        Request $request,
        string $sectionType,
        ReconciliationService $service,
        AuditLogger $audit,
    ) {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! in_array($sectionType, $service->allowedSections($admin), true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'no_business' => ['required', 'boolean'],
            'business_summary' => ['nullable', 'array'],
            'actual_snapshot' => ['required', 'array'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $fields = $service->fieldDefinitions($sectionType);
        if (array_diff($fields, array_keys($data['actual_snapshot'])) || array_diff(array_keys($data['actual_snapshot']), $fields)) {
            throw ValidationException::withMessages(['actual_snapshot' => '盘点项目不完整']);
        }

        $result = DB::transaction(function () use ($admin, $sectionType, $service, $audit, $data): array {
            $report = $this->reportFor($admin, $service);
            $section = $report->sections()->lockForUpdate()->firstOrCreate(['section_type' => $sectionType]);
            if (in_array($section->status, ['submitted', 'confirmed'], true)) {
                throw ValidationException::withMessages(['section' => '该部分已经提交']);
            }

            $book = $service->snapshot($admin->store_id, $sectionType);
            $differences = $service->differences($data['actual_snapshot'], $book);
            if ($service->hasDifferences($differences) && blank($data['difference_reason'] ?? null)) {
                throw ValidationException::withMessages(['difference_reason' => '有差额时必须填写原因']);
            }

            $before = $section->exists ? $section->toArray() : null;
            $resubmitting = $section->status === 'returned';
            $version = (int) ($section->version ?? 1);
            $section->fill([
                'status' => 'submitted',
                'submitted_by_admin_id' => $admin->id,
                'version' => $resubmitting ? $version + 1 : $version,
                'no_business' => $data['no_business'],
                'business_summary' => $data['business_summary'] ?? [],
                'actual_snapshot' => $data['actual_snapshot'],
                'book_snapshot' => $book,
                'differences' => $differences,
                'difference_reason' => $data['difference_reason'] ?? null,
                'submitted_at' => now(),
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'return_reason' => null,
            ])->save();
            $audit->record(
                $admin,
                $section,
                $resubmitting ? 'reconciliation.resubmitted' : 'reconciliation.submitted',
                $data['difference_reason'] ?? null,
                $before,
                $section->fresh()->toArray(),
            );
            $report->recalculateStatus();

            return $this->presentSection($section->fresh(), true);
        });

        return response()->json($result, $result['version'] === 1 ? 201 : 200);
    }

    public function index(Request $request)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->is_super_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $date = $request->filled('date')
            ? $request->date('date')->format('Y-m-d')
            : $this->businessDate();
        $service = app(ReconciliationService::class);
        Store::query()->where('enabled', true)->each(function (Store $store) use ($date, $service): void {
            $this->reportForStore($store->id, $date, $service);
        });

        $reports = DailyReconciliation::query()
            ->with(['store', 'sections.submitter', 'sections.reviewer'])
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('reconciliation_date', $request->date('date')))
            ->latest('reconciliation_date')
            ->get()
            ->map(fn (DailyReconciliation $report) => $this->presentReport($report));

        return response()->json(['data' => $reports]);
    }

    public function mine(Request $request, ReconciliationService $service)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        abort_unless($admin->store_id, 422, '员工必须归属店铺');
        $allowed = $service->allowedSections($admin);
        $reports = DailyReconciliation::query()
            ->where('store_id', $admin->store_id)
            ->whereHas('sections', fn ($query) => $query->whereIn('section_type', $allowed))
            ->with([
                'store',
                'sections' => fn ($query) => $query->whereIn('section_type', $allowed),
                'sections.submitter',
                'sections.reviewer',
            ])
            ->latest('reconciliation_date')
            ->get()
            ->map(fn (DailyReconciliation $report) => $this->presentReport($report));

        return response()->json(['data' => $reports]);
    }

    public function confirm(Request $request, ReconciliationSection $section, AuditLogger $audit)
    {
        return $this->review($request, $section, $audit, true);
    }

    public function returnSection(Request $request, ReconciliationSection $section, AuditLogger $audit)
    {
        return $this->review($request, $section, $audit, false);
    }

    private function review(Request $request, ReconciliationSection $section, AuditLogger $audit, bool $confirm)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->is_super_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($section->status !== 'submitted') {
            throw ValidationException::withMessages(['section' => '只能审核已提交的交账']);
        }
        $reason = $confirm ? null : $request->validate([
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ])['reason'];

        DB::transaction(function () use ($section, $admin, $audit, $confirm, $reason): void {
            $before = $section->toArray();
            $section->update([
                'status' => $confirm ? 'confirmed' : 'returned',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'return_reason' => $reason,
            ]);
            $audit->record(
                $admin,
                $section,
                $confirm ? 'reconciliation.confirmed' : 'reconciliation.returned',
                $reason,
                $before,
                $section->fresh()->toArray(),
            );
            $section->reconciliation->recalculateStatus();
        });

        return response()->json($this->presentSection($section->fresh(), true));
    }

    private function reportFor(AdminUser $admin, ReconciliationService $service): DailyReconciliation
    {
        abort_unless($admin->store_id, 422, '员工必须归属店铺');

        return $this->reportForStore($admin->store_id, $this->businessDate(), $service);
    }

    private function reportForStore(int $storeId, string $date, ReconciliationService $service): DailyReconciliation
    {
        $report = DailyReconciliation::query()->firstOrCreate(
            ['store_id' => $storeId, 'reconciliation_date' => $date],
            ['required_sections' => $service->requiredSectionsForStore($storeId)],
        );
        if ($report->required_sections === null) {
            $report->update(['required_sections' => $service->requiredSectionsForStore($storeId)]);
        }
        foreach ($report->required_sections ?? [] as $type) {
            $report->sections()->firstOrCreate(['section_type' => $type]);
        }

        return $report->fresh();
    }

    private function businessDate(): string
    {
        return now('Asia/Shanghai')->toDateString();
    }

    private function presentReport(DailyReconciliation $report): array
    {
        return [
            'id' => $report->id,
            'date' => $report->reconciliation_date->format('Y-m-d'),
            'status' => $report->status,
            'store' => $report->store,
            'sections' => $report->sections->map(fn (ReconciliationSection $section) => $this->presentSection($section, true)),
        ];
    }

    private function presentSection(ReconciliationSection $section, bool $showBook): array
    {
        $data = [
            'id' => $section->id,
            'section_type' => $section->section_type,
            'status' => $section->status,
            'version' => $section->version,
            'no_business' => $section->no_business,
            'business_summary' => $section->business_summary ?? [],
            'actual_snapshot' => $section->actual_snapshot ?? [],
            'difference_reason' => $section->difference_reason,
            'return_reason' => $section->return_reason,
            'submitted_at' => $section->submitted_at,
            'submitted_by' => $section->submitter,
            'reviewed_by' => $section->reviewer,
        ];
        if ($showBook && $section->status !== 'draft') {
            $data['book_snapshot'] = $section->book_snapshot ?? [];
            $data['differences'] = $section->differences ?? [];
        }

        return $data;
    }
}
