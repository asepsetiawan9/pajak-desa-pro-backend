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
    public function index()
    {
        $settings = $this->settingService->getAllSettings();

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
            $settingsData = $request->except(['_token', '_method']);
        }

        if (empty($settingsData)) {
            return response()->json([
                'success' => false,
                'message' => 'Payload settings tidak boleh kosong',
            ], 422);
        }

        $updated = $this->settingService->updateSettings($settingsData);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan sistem berhasil disimpan ke database',
            'data' => $updated,
        ]);
    }
}
