<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request)
    {
        $users = $this->userService->getAll($request->all(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'desa_id' => 'nullable|exists:desas,id',
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'nip' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:SUPER_ADMIN_SYSTEM,SUPER_ADMIN,BENDAHARA,KOLEKTOR,KEPALA_DESA',
            'dusun_akses' => 'nullable|string',
            'status_aktif' => 'boolean',
        ]);

        $user = $this->userService->create($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil ditambahkan',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'desa_id' => 'nullable|exists:desas,id',
            'name' => 'sometimes|string|max:255',
            'username' => "sometimes|string|max:100|unique:users,username,{$id}",
            'nip' => 'nullable|string|max:50',
            'email' => "nullable|email|unique:users,email,{$id}",
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|in:SUPER_ADMIN_SYSTEM,SUPER_ADMIN,BENDAHARA,KOLEKTOR,KEPALA_DESA',
            'dusun_akses' => 'nullable|string',
            'status_aktif' => 'boolean',
        ]);

        $user = $this->userService->update($id, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil diperbarui',
            'data' => $user,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->userService->delete($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $user = $this->userService->toggleStatus($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Status pengguna berhasil diperbarui',
            'data' => $user,
        ]);
    }
}
