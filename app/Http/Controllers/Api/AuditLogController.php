<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\AuditLogRepository;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditLogRepository $auditLogRepository
    ) {}

    public function index(Request $request)
    {
        $filters = $request->all();
        $user = $request->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        // If not super admin system, isolate log viewing to user's desa
        if (!$isSuperAdmin && $user && $user->desa_id) {
            $filters['desa_id'] = $user->desa_id;
        }

        $perPage = (int) $request->input('per_page', 15);
        $logs = $this->auditLogRepository->getPaginated($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs berhasil dimuat',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
