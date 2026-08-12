<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password yang Anda masukkan salah.'],
            ]);
        }

        if (!$user->status_aktif) {
            throw ValidationException::withMessages([
                'username' => ['Akun Anda dalam status Non-Aktif. Silakan hubungi Administrator.'],
            ]);
        }

        $clientPlatform = strtolower($request->header('X-Client-Platform', $request->input('client_platform', 'web')));
        $normalizedRole = strtolower(str_replace('_', '', $user->role));
        if ($clientPlatform === 'mobile' && !in_array($normalizedRole, ['kolektor', 'kepaladesa'])) {
            throw ValidationException::withMessages([
                'username' => ['Akses aplikasi mobile hanya diizinkan untuk Kolektor dan Kepala Desa.'],
            ]);
        }

        $user->load('desa');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'desa_id' => $user->desa_id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'dusun_akses' => $user->dusun_akses,
                    'status_aktif' => $user->status_aktif,
                    'desa' => $user->desa ? [
                        'id' => $user->desa->id,
                        'kode_desa' => $user->desa->kode_desa,
                        'nama_desa' => $user->desa->nama_desa,
                        'nama_kecamatan' => $user->desa->nama_kecamatan,
                        'nama_kabupaten' => $user->desa->nama_kabupaten,
                        'nama_provinsi' => $user->desa->nama_provinsi,
                        'nama_kades' => $user->desa->nama_kades,
                        'nip_kades' => $user->desa->nip_kades,
                        'subdomain' => $user->desa->subdomain,
                        'logo_path' => $user->desa->logo_path,
                    ] : null,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('desa');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}
