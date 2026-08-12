<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settingService) {}

    /**
     * Mengambil daftar pengaturan sistem lengkap
     */
    public function index(Request $request)
    {
        $desaId = null;
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            $user->role === 'SUPER_ADMIN' ||
            is_null($user->desa_id)
        );

        if ($isSuperAdmin && $request->has('desa_id') && $request->input('desa_id') !== 'all' && $request->input('desa_id') !== 'ALL') {
            $desaId = (int) $request->input('desa_id');
        }

        $settings = $this->settingService->getAllSettings($desaId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Memperbarui pengaturan sistem secara masal
     */
    public function update(Request $request)
    {
        $settingsData = $request->input('settings');
        if (!is_array($settingsData)) {
            $settingsData = $request->except(['_token', '_method', 'desa_id']);
        } else {
            unset($settingsData['desa_id']);
        }

        if (empty($settingsData)) {
            return response()->json([
                'success' => false,
                'message' => 'Payload settings tidak boleh kosong',
            ], 422);
        }

        $desaId = null;
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            $user->role === 'SUPER_ADMIN' ||
            is_null($user->desa_id)
        );

        if ($isSuperAdmin && $request->has('desa_id') && $request->input('desa_id') !== 'all' && $request->input('desa_id') !== 'ALL') {
            $desaId = (int) $request->input('desa_id');
        }

        $updated = $this->settingService->updateSettings($settingsData, $desaId);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan sistem berhasil disimpan ke database',
            'data' => $updated,
        ]);
    }
}
