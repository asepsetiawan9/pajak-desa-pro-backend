<?php

namespace App\Repositories;

use App\Models\AuditLog;

class AuditLogRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15)
    {
        $query = AuditLog::query()->with(['user:id,name,username,role,desa_id', 'user.desa:id,nama_desa']);

        // Multi-Tenant scoping
        if (isset($filters['desa_id']) && $filters['desa_id'] !== null && $filters['desa_id'] !== 'all') {
            $desaId = (int) $filters['desa_id'];
            $query->where(function ($q) use ($desaId) {
                $q->whereHas('user', function ($uq) use ($desaId) {
                    $uq->where('desa_id', $desaId);
                })->orWhereRaw("JSON_EXTRACT(payload, '$.desa_id') = ?", [$desaId]);
            });
        }

        // Module filter
        if (!empty($filters['module']) && $filters['module'] !== 'ALL') {
            $query->where('module', strtoupper($filters['module']));
        }

        // Action filter
        if (!empty($filters['action']) && $filters['action'] !== 'ALL') {
            $query->where('action', strtoupper($filters['action']));
        }

        // Search query
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function createLog(array $data): AuditLog
    {
        return AuditLog::create($data);
    }
}
