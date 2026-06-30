<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Support\AdminAccess;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->is_super_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(AuditLog::query()
            ->with(['actor', 'store'])
            ->when($data['store_id'] ?? null, fn ($query, $value) => $query->where('store_id', $value))
            ->when($data['subject_type'] ?? null, fn ($query, $value) => $query->where('subject_type', $value))
            ->when($data['action'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($data['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($data['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')
            ->latest('id')
            ->paginate($data['per_page'] ?? 50));
    }
}
