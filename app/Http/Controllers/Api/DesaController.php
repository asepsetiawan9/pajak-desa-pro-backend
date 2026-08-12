<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DesaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DesaController extends Controller
{
    public function __construct(protected DesaService $desaService) {}

    protected function checkSuperAdminSystem(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'SUPER_ADMIN_SYSTEM' && $user->desa_id !== null) {
            throw ValidationException::withMessages([
                'auth' => 'Hanya Super Admin System yang memiliki otorisasi untuk mengelola data desa.',
            ]);
        }
    }

    public function index(Request $request)
    {
        $this->checkSuperAdminSystem($request);

        $desas = $this->desaService->getAll($request->only(['search']));

        return response()->json([
            'success' => true,
            'data' => $desas,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $this->checkSuperAdminSystem($request);

        $desa = $this->desaService->getById($id);

        return response()->json([
            'success' => true,
            'data' => $desa,
        ]);
    }

    public function store(Request $request)
    {
        $this->checkSuperAdminSystem($request);

        $validated = $request->validate([
            'kode_desa' => 'required|string|size:10',
            'nama_desa' => 'required|string|max:100',
            'nama_kecamatan' => 'nullable|string|max:100',
            'nama_kabupaten' => 'nullable|string|max:100',
            'nama_provinsi' => 'nullable|string|max:100',
            'nama_kades' => 'nullable|string|max:100',
            'nip_kades' => 'nullable|string|max:30',
            'subdomain' => 'nullable|string|max:50',
            'logo_path' => 'nullable|string|max:255',
            'admin_password' => 'nullable|string|min:6',
            'default_password' => 'nullable|string|min:6',
        ]);

        $result = $this->desaService->createDesaWithDefaultUsers($validated);

        return response()->json([
            'success' => true,
            'message' => "Desa {$result['desa']->nama_desa} dan 3 akun pengguna (Admin Desa, Kolektor, Kades) berhasil dibuat!",
            'data' => $result,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->checkSuperAdminSystem($request);

        $validated = $request->validate([
            'kode_desa' => 'sometimes|required|string|size:10',
            'nama_desa' => 'sometimes|required|string|max:100',
            'nama_kecamatan' => 'nullable|string|max:100',
            'nama_kabupaten' => 'nullable|string|max:100',
            'nama_provinsi' => 'nullable|string|max:100',
            'nama_kades' => 'nullable|string|max:100',
            'nip_kades' => 'nullable|string|max:30',
            'subdomain' => 'nullable|string|max:50',
            'logo_path' => 'nullable|string|max:255',
            'status_aktif' => 'nullable|boolean',
        ]);

        $desa = $this->desaService->updateDesa($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Data Desa berhasil diperbarui.',
            'data' => $desa,
        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $this->checkSuperAdminSystem($request);

        $desa = $this->desaService->toggleStatus($id);

        return response()->json([
            'success' => true,
            'message' => "Status Desa {$desa->nama_desa} berhasil diubah.",
            'data' => $desa,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->checkSuperAdminSystem($request);

        $this->desaService->deleteDesa($id);

        return response()->json([
            'success' => true,
            'message' => 'Desa dan akun penggunanya berhasil dihapus.',
        ]);
    }
}
