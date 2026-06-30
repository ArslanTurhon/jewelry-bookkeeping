<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const SECRET_KEYS = ['password', 'api_token', 'remember_token'];

    public function record(
        AdminUser $actor,
        Model $subject,
        string $action,
        ?string $reason,
        ?array $before,
        ?array $after,
    ): AuditLog {
        return AuditLog::query()->create([
            'store_id' => $subject instanceof Store ? $subject->id : $subject->getAttribute('store_id'),
            'actor_admin_id' => $actor->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'reason' => $reason,
            'before_data' => $this->sanitize($before),
            'after_data' => $this->sanitize($after),
        ]);
    }

    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::SECRET_KEYS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
