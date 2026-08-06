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
        $summary = $this->dhkpService->getKpiSummary($tahun, $dusun);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nop' => 'required|string|max:30',
            'nama_wp' => 'required|string|max:255',
            'alamat_wp' => 'nullable|string',
            'alamat_op' => 'nullable|string',
            'dusun' => 'required|string',
            'blok' => 'required|string',
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

        $dhkp = $this->dhkpService->createSppt($validated);

        return response()->json([
            'success' => true,
            'message' => 'SPPT DHKP berhasil ditambahkan',
            'data' => $dhkp,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
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
        $rows = $request->input('rows', []);
        if (!is_array($rows) || empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data baris yang diimport.',
            ], 422);
        }

        $result = $this->dhkpService->importSppt($rows);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$result['total']} data SPPT DHKP ({$result['created']} baru, {$result['updated']} diperbarui).",
            'data' => $result,
        ]);
    }
}
