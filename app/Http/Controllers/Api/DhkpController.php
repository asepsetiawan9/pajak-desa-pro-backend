<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesCollectorDusunFilter;
use App\Http\Controllers\Controller;
use App\Services\DhkpService;
use Illuminate\Http\Request;

class DhkpController extends Controller
{
    use HandlesCollectorDusunFilter;

    public function __construct(protected DhkpService $dhkpService) {}

    public function index(Request $request)
    {
        $filters = $request->all();
        $effectiveDusun = $this->getEffectiveDusunFilter($request);
        if ($effectiveDusun) {
            $filters['dusun'] = $effectiveDusun;
        }

        $data = $this->dhkpService->getPaginated($filters);

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $dhkp = $this->dhkpService->getDetail($id);

        if ($request->user() && !$this->isDusunAllowedForUser($request->user(), $dhkp->dusun)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Data SPPT berada di luar wilayah dusun penugasan Anda.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $dhkp,
        ]);
    }

    public function summary(Request $request)
    {
        $tahun = (int) ($request->tahun ?? 2026);
        $dusun = $this->getEffectiveDusunFilter($request);
        $desaId = $request->desa_id ? (int) $request->desa_id : null;
        $summary = $this->dhkpService->getKpiSummary($tahun, $dusun, $desaId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || $user->role === 'SUPER_ADMIN' || is_null($user->desa_id));

        $validated = $request->validate([
            'nop' => 'required|string|max:30',
            'desa_id' => 'nullable|integer',
            'nama_wp' => 'required|string|max:255',
            'alamat_wp' => 'nullable|string',
            'alamat_op' => 'nullable|string',
            'dusun' => 'required|string',
            'blok' => 'nullable|string',
            'rt_rw' => 'nullable|string',
            'luas_bumi' => 'required|integer',
            'luas_bangunan' => 'required|integer',
            'njop_bumi' => 'required|integer',
            'njop_bangunan' => 'required|integer',
            'ketetapan_pbb' => 'required|integer',
            'denda' => 'integer',
            'fee_kolektor' => 'integer',
            'domisili' => 'string|in:DALAM_DESA,LUAR_DESA',
            'tahun' => 'integer',
        ]);

        if (!$isSuperAdmin || empty($validated['desa_id'])) {
            $validated['desa_id'] = $user->desa_id ?? $validated['desa_id'] ?? 1;
        }

        $dhkp = $this->dhkpService->createSppt($validated);

        return response()->json([
            'success' => true,
            'message' => 'SPPT DHKP berhasil ditambahkan',
            'data' => $dhkp,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || $user->role === 'SUPER_ADMIN' || is_null($user->desa_id));

        $validated = $request->validate([
            'desa_id' => 'sometimes|nullable|integer',
            'nama_wp' => 'sometimes|string|max:255',
            'alamat_wp' => 'nullable|string',
            'alamat_op' => 'nullable|string',
            'dusun' => 'sometimes|string',
            'blok' => 'sometimes|string',
            'rt_rw' => 'nullable|string',
            'luas_bumi' => 'sometimes|integer',
            'luas_bangunan' => 'sometimes|integer',
            'njop_bumi' => 'sometimes|integer',
            'njop_bangunan' => 'sometimes|integer',
            'ketetapan_pbb' => 'sometimes|integer',
            'denda' => 'sometimes|integer',
            'fee_kolektor' => 'sometimes|integer',
            'domisili' => 'sometimes|string|in:DALAM_DESA,LUAR_DESA',
            'status_bayar' => 'sometimes|string|in:LUNAS,BELUM_BAYAR',
        ]);

        if (!$isSuperAdmin && isset($validated['desa_id'])) {
            unset($validated['desa_id']);
        }

        $dhkp = $this->dhkpService->updateSppt($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'SPPT DHKP berhasil diperbarui',
            'data' => $dhkp,
        ]);
    }

    public function destroy(int $id)
    {
        $this->dhkpService->deleteSppt($id);

        return response()->json([
            'success' => true,
            'message' => 'SPPT DHKP berhasil dihapus',
            'data' => ['id' => $id],
        ]);
    }

    public function import(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || $user->role === 'SUPER_ADMIN' || is_null($user->desa_id));
        $rows = $request->input('rows', []);
        if (!is_array($rows) || empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data baris yang diimport.',
            ], 422);
        }

        // Auto-bind desa_id to every row for multi-tenant isolation
        foreach ($rows as &$row) {
            if (!$isSuperAdmin || empty($row['desa_id'])) {
                $row['desa_id'] = $user->desa_id ?? $row['desa_id'] ?? 1;
            }
        }

        $result = $this->dhkpService->importSppt($rows);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$result['total']} data SPPT DHKP ({$result['created']} baru, {$result['updated']} diperbarui).",
            'data' => $result,
        ]);
    }
}
