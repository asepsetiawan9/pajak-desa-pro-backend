<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KolektorTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KolektorTargetController extends Controller
{
    public function __construct(
        protected KolektorTargetService $service
    ) {}

    /**
     * GET /kolektor-targets — List targets & performance semua kolektor.
     */
    public function index(Request $request): JsonResponse
    {
        $tahun = (int) ($request->query('tahun') ?? date('Y'));
        $desaId = $request->query('desa_id');

        if ($desaId && $desaId !== 'ALL' && $desaId !== 'all') {
            $desaId = (int) $desaId;
        } else {
            $desaId = null;
        }

        $data = $this->service->listTargets($tahun, $desaId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /kolektor-targets/my-performance — Performance kolektor sendiri.
     */
    public function myPerformance(Request $request): JsonResponse
    {
        $tahun = (int) ($request->query('tahun') ?? date('Y'));
        $data = $this->service->getMyPerformance($tahun);

        if (!$data) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Belum ada target yang ditetapkan untuk Anda pada tahun ini.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /kolektor-targets/leaderboard — Ranking kolektor.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $tahun = (int) ($request->query('tahun') ?? date('Y'));
        $desaId = $request->query('desa_id');

        if ($desaId && $desaId !== 'ALL' && $desaId !== 'all') {
            $desaId = (int) $desaId;
        } else {
            $desaId = null;
        }

        $data = $this->service->getLeaderboard($tahun, $desaId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /kolektor-targets — Set/update target kolektor.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kolektor_id' => 'required|integer|exists:users,id',
            'tahun' => 'required|integer|min:2020|max:2050',
            'target_nominal' => 'required|integer|min:0',
            'target_sppt' => 'nullable|integer|min:0',
            'desa_id' => 'nullable|integer|exists:desas,id',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $data = $this->service->setTarget($validated);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Target kolektor berhasil disimpan.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /kolektor-targets/{id} — Detail performance 1 kolektor.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tahun = (int) ($request->query('tahun') ?? date('Y'));
        $data = $this->service->getKolektorDetail($id, $tahun);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data target kolektor tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /kolektor-targets/{id}/dusun-breakdown — Breakdown capaian per dusun kolektor.
     */
    public function dusunBreakdown(Request $request, int $id): JsonResponse
    {
        $tahun = (int) ($request->query('tahun') ?? date('Y'));
        $data = $this->service->getCapaianPerDusun($id, $tahun);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * DELETE /kolektor-targets/{id} — Hapus target.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->service->deleteTarget($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target kolektor tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Target kolektor berhasil dihapus.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
