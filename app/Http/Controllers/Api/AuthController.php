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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'dusun_akses' => $user->dusun_akses,
                    'status_aktif' => $user->status_aktif,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
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
