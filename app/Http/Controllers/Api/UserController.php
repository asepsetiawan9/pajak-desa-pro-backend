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
        $users = $this->userService->getAll($request->all());

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'nip' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:SUPER_ADMIN,BENDAHARA,KOLEKTOR,KEPALA_DESA',
            'dusun_akses' => 'nullable|string',
            'status_aktif' => 'boolean',
        ]);

        $user = $this->userService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil ditambahkan',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => "sometimes|string|max:100|unique:users,username,{$id}",
            'nip' => 'nullable|string|max:50',
            'email' => "nullable|email|unique:users,email,{$id}",
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|in:SUPER_ADMIN,BENDAHARA,KOLEKTOR,KEPALA_DESA',
            'dusun_akses' => 'nullable|string',
            'status_aktif' => 'boolean',
        ]);

        $user = $this->userService->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil diperbarui',
            'data' => $user,
        ]);
    }

    public function destroy(int $id)
    {
        $this->userService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }

    public function toggleStatus(int $id)
    {
        $user = $this->userService->toggleStatus($id);

        return response()->json([
            'success' => true,
            'message' => 'Status pengguna berhasil diperbarui',
            'data' => $user,
        ]);
    }
}
