<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    /**
     * Get summary metrics of database and backups
     */
    public function summary(Request $request): JsonResponse
    {
        $desaId = $request->query('desa_id') ? (int) $request->query('desa_id') : null;
        $summary = $this->backupService->getSummary($desaId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * List all available backup files
     */
    public function index(Request $request): JsonResponse
    {
        $desaId = $request->query('desa_id') ? (int) $request->query('desa_id') : null;
        $backups = $this->backupService->listBackups($desaId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $backups,
        ]);
    }

    /**
     * Create a new backup on the server
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'nullable|string|in:zip,sql,json',
            'desa_id' => 'nullable',
            'notes' => 'nullable|string|max:255',
        ]);

        $result = $this->backupService->createBackup($request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Cadangan data berhasil dibuat di server.',
            'data' => $result,
        ], 201);
    }

    /**
     * Download backup file from server
     */
    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $fileInfo = $this->backupService->getBackupForDownload($filename, $request->user());

        return response()->download($fileInfo['path'], $fileInfo['filename'], [
            'Content-Type' => $fileInfo['mime'],
        ]);
    }

    /**
     * Delete a backup file from server with password confirmation
     */
    public function destroy(Request $request, string $filename): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $deleted = $this->backupService->deleteBackup($filename, $request->input('password'), $request->user());

        return response()->json([
            'success' => $deleted,
            'message' => 'Berkas backup berhasil dihapus dari server.',
        ]);
    }

    /**
     * Restore database from file or storage
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'filename' => 'nullable|string',
            'file_content' => 'nullable|string',
            'desa_id' => 'nullable',
        ]);

        $result = $this->backupService->restoreBackup($request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Database berhasil dipulihkan dari cadangan.',
            'data' => $result,
        ]);
    }
}
