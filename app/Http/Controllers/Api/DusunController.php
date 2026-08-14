<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DusunService;
use Illuminate\Http\Request;

class DusunController extends Controller
{
    public function __construct(protected DusunService $dusunService) {}

    /**
     * Menampilkan daftar master dusun atau list nama string
     */
    public function index(Request $request)
    {
        $format = $request->query('format');

        // Jika request meminta format nama dusun saja (untuk dropdown/selector)
        if ($format === 'names') {
            $desaId = $request->query('desa_id');
            $dusuns = $this->dusunService->getDusunsByDesa($desaId);

            return response()->json([
                'success' => true,
                'data' => $dusuns,
            ]);
        }

        // Default: kembalikan list master dusun model lengkap
        $filters = [
            'desa_id' => $request->query('desa_id'),
            'search' => $request->query('search'),
            'status_aktif' => $request->query('status_aktif'),
        ];

        $dusuns = $this->dusunService->getAll($filters);

        return response()->json([
            'success' => true,
            'data' => $dusuns,
        ]);
    }

    /**
     * Detail satu dusun
     */
    public function show(int $id)
    {
        $dusun = $this->dusunService->getById($id);

        return response()->json([
            'success' => true,
            'data' => $dusun,
        ]);
    }

    /**
     * Tambah master dusun baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_dusun' => 'required|string|max:100',
            'desa_id' => 'nullable|exists:desas,id',
            'kode_dusun' => 'nullable|string|max:20',
            'rt_rw' => 'nullable|string|max:50',
            'status_aktif' => 'nullable|boolean',
        ]);

        $dusun = $this->dusunService->create($validated);

        return response()->json([
            'success' => true,
            'message' => "Master Dusun '{$dusun->nama_dusun}' berhasil ditambahkan.",
            'data' => $dusun,
        ], 201);
    }

    /**
     * Update master dusun
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'nama_dusun' => 'nullable|string|max:100',
            'kode_dusun' => 'nullable|string|max:20',
            'rt_rw' => 'nullable|string|max:50',
            'status_aktif' => 'nullable|boolean',
        ]);

        $dusun = $this->dusunService->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => "Data dusun '{$dusun->nama_dusun}' berhasil diperbarui.",
            'data' => $dusun,
        ]);
    }

    /**
     * Hapus master dusun
     */
    public function destroy(int $id)
    {
        $this->dusunService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Data dusun berhasil dihapus.',
        ]);
    }

    /**
     * Toggle status aktif dusun
     */
    public function toggleStatus(int $id)
    {
        $dusun = $this->dusunService->toggleStatus($id);

        return response()->json([
            'success' => true,
            'message' => "Status dusun '{$dusun->nama_dusun}' berhasil diubah.",
            'data' => $dusun,
        ]);
    }
}
