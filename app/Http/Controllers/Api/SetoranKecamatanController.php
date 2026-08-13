<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SetoranKecamatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetoranKecamatanController extends Controller
{
    public function __construct(
        protected SetoranKecamatanService $service
    ) {}

    /**
     * GET /api/v1/setoran-kecamatan
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));
        $filters = $request->only(['desa_id', 'status', 'tahun', 'kategori', 'search']);

        $setoranList = $this->service->getFilteredSetoran($filters, $perPage);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $setoranList->items(),
            'pagination' => [
                'current_page' => $setoranList->currentPage(),
                'last_page' => $setoranList->lastPage(),
                'per_page' => $setoranList->perPage(),
                'total' => $setoranList->total(),
            ],
            'meta' => [
                'current_page' => $setoranList->currentPage(),
                'last_page' => $setoranList->lastPage(),
                'per_page' => $setoranList->perPage(),
                'total' => $setoranList->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/setoran-kecamatan/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['desa_id', 'tahun']);
        $summary = $this->service->getSummary($filters);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/v1/setoran-kecamatan/{id}
     */
    public function show(int $id): JsonResponse
    {
        $setoran = $this->service->getById($id);
        if (!$setoran) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Data setoran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $setoran,
        ]);
    }

    /**
     * POST /api/v1/setoran-kecamatan
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tanggal_setor' => 'required|date',
            'kategori' => 'nullable|string|in:SETOR_KECAMATAN,KEGIATAN_DESA,OPERASIONAL_DESA,ADMINISTRASI,LAINNYA',
            'tahun' => 'nullable|integer',
            'nominal' => 'required|numeric|min:1',
            'metode_setoran' => 'required|string|max:30',
            'bank_tujuan' => 'nullable|string|max:100',
            'nomor_referensi' => 'nullable|string|max:100',
            'penyetor_nama' => 'required|string|max:100',
            'penyetor_jabatan' => 'nullable|string|max:100',
            'penerima_kecamatan' => 'nullable|string|max:100',
            'catatan_desa' => 'nullable|string',
            'bukti_file' => 'nullable|string',
            'desa_id' => 'nullable|integer|exists:desas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setoran = $this->service->createSetoran($validator->validated());
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Data pengeluaran / setoran kas berhasil disimpan.',
                'data' => $setoran,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PUT /api/v1/setoran-kecamatan/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tanggal_setor' => 'sometimes|required|date',
            'kategori' => 'nullable|string|in:SETOR_KECAMATAN,KEGIATAN_DESA,OPERASIONAL_DESA,ADMINISTRASI,LAINNYA',
            'tahun' => 'nullable|integer',
            'nominal' => 'sometimes|required|numeric|min:1',
            'metode_setoran' => 'sometimes|required|string|max:30',
            'bank_tujuan' => 'nullable|string|max:100',
            'nomor_referensi' => 'nullable|string|max:100',
            'penyetor_nama' => 'sometimes|required|string|max:100',
            'penyetor_jabatan' => 'nullable|string|max:100',
            'penerima_kecamatan' => 'nullable|string|max:100',
            'catatan_desa' => 'nullable|string',
            'bukti_file' => 'nullable|string',
            'desa_id' => 'nullable|integer|exists:desas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setoran = $this->service->updateSetoran($id, $validator->validated());
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Data setoran berhasil diperbarui.',
                'data' => $setoran,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/v1/setoran-kecamatan/{id}/verify
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:DITERIMA,DITOLAK,PENDING',
            'catatan_kecamatan' => 'nullable|string',
            'tanggal_diterima' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setoran = $this->service->verifySetoran(
                $id,
                $request->input('status'),
                $request->input('catatan_kecamatan'),
                $request->input('tanggal_diterima')
            );

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => "Verifikasi status setoran berhasil diubah menjadi {$setoran->status}.",
                'data' => $setoran,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * DELETE /api/v1/setoran-kecamatan/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteSetoran($id);
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Data setoran berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
