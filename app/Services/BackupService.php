<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\BackupRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class BackupService
{
    public function __construct(
        protected BackupRepository $backupRepository
    ) {}

    /**
     * Get system database summary
     */
    public function getSummary(?int $desaId = null, ?User $user = null): array
    {
        $resolvedDesaId = $this->resolveDesaId($desaId, $user);
        return $this->backupRepository->getSummary($resolvedDesaId);
    }

    /**
     * List all available backup files
     */
    public function listBackups(?int $desaId = null, ?User $user = null): array
    {
        $resolvedDesaId = $this->resolveDesaId($desaId, $user);
        return $this->backupRepository->listFiles($resolvedDesaId);
    }

    /**
     * Create a new backup file
     */
    public function createBackup(array $params, User $user): array
    {
        $format = strtolower($params['format'] ?? 'zip');
        if (!in_array($format, ['zip', 'sql', 'json'])) {
            $format = 'zip';
        }

        $notes = $params['notes'] ?? 'Cadangan data otomatis via portal LENTERA';
        $requestedDesaId = isset($params['desa_id']) && $params['desa_id'] !== 'all' ? (int) $params['desa_id'] : null;
        $resolvedDesaId = $this->resolveDesaId($requestedDesaId, $user);

        $extracted = $this->backupRepository->extractData($resolvedDesaId);
        $tablesData = $extracted['tables'];
        $counts = $extracted['counts'];
        $totalRecords = $extracted['total_records'];

        $timestamp = date('Ymd_His');
        $scopeTag = $resolvedDesaId ? "DESA_{$resolvedDesaId}" : "FULL";
        $baseFilename = "BACKUP_LENTERA_{$scopeTag}_{$timestamp}";

        $metadata = [
            'application' => 'LENTERA (Layanan Elektronik Terpadu Pajak Daerah)',
            'version' => '1.1.0',
            'created_at' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'created_by' => $user->name . ' (' . ($user->role ?? 'Admin') . ')',
            'user_id' => $user->id,
            'scope' => $resolvedDesaId ? "Khusus Desa ID {$resolvedDesaId}" : 'Full Database (Seluruh Desa)',
            'desa_id' => $resolvedDesaId,
            'total_records' => $totalRecords,
            'counts' => $counts,
            'notes' => $notes,
            'format' => $format,
        ];

        $jsonPayload = json_encode([
            '_metadata' => $metadata,
            'data' => $tablesData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sqlContent = $this->backupRepository->generateSqlDump($tablesData, $resolvedDesaId, $metadata['created_by']);

        $savedFilename = "{$baseFilename}.{$format}";
        $filePath = null;

        if ($format === 'zip') {
            $filePath = $this->backupRepository->createZipBackup($savedFilename, $sqlContent, $jsonPayload, $metadata);
        } elseif ($format === 'sql') {
            $filePath = $this->backupRepository->saveBackupFile($savedFilename, $sqlContent);
        } else {
            // json
            $filePath = $this->backupRepository->saveBackupFile($savedFilename, $jsonPayload);
        }

        $checksum = File::exists($filePath) ? hash_file('sha256', $filePath) : null;
        $sizeBytes = File::exists($filePath) ? File::size($filePath) : 0;

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'desa_id' => $resolvedDesaId ?? $user->desa_id,
            'action' => 'BACKUP_CREATED',
            'module' => 'BACKUP',
            'payload' => [
                'filename' => $savedFilename,
                'size_bytes' => $sizeBytes,
                'total_records' => $totalRecords,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return [
            'success' => true,
            'filename' => basename($filePath),
            'format' => $format,
            'size_bytes' => $sizeBytes,
            'size_human' => $this->backupRepository->formatBytes($sizeBytes),
            'total_records' => $totalRecords,
            'counts' => $counts,
            'checksum_sha256' => $checksum,
            'created_at' => $metadata['created_at'],
            'scope' => $metadata['scope'],
            'download_url' => "/api/v1/backups/" . basename($filePath) . "/download",
        ];
    }

    /**
     * Get backup file path for download
     */
    public function getBackupForDownload(string $filename, User $user): array
    {
        $safeName = basename($filename);
        $path = $this->backupRepository->getFilePath($safeName);

        if (!$path || !File::exists($path)) {
            throw ValidationException::withMessages([
                'filename' => 'Berkas backup tidak ditemukan di server.',
            ]);
        }

        // Check desa isolation if file name targets specific desa
        if ($user->desa_id && str_contains($safeName, 'DESA_') && !str_contains($safeName, "DESA_{$user->desa_id}_")) {
            throw ValidationException::withMessages([
                'auth' => 'Anda tidak memiliki hak akses untuk mengunduh backup desa lain.',
            ]);
        }

        // Log download
        AuditLog::create([
            'user_id' => $user->id,
            'desa_id' => $user->desa_id,
            'action' => 'BACKUP_DOWNLOADED',
            'module' => 'BACKUP',
            'payload' => ['filename' => $safeName],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return [
            'path' => $path,
            'filename' => $safeName,
            'mime' => $this->getMimeType($safeName),
        ];
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(string $filename, string $password, User $user): bool
    {
        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password verifikasi yang Anda masukkan salah.',
            ]);
        }

        $safeName = basename($filename);
        $path = $this->backupRepository->getFilePath($safeName);

        if (!$path) {
            throw ValidationException::withMessages([
                'filename' => 'Berkas backup tidak ditemukan di storage.',
            ]);
        }

        if ($user->desa_id && str_contains($safeName, 'DESA_') && !str_contains($safeName, "DESA_{$user->desa_id}_")) {
            throw ValidationException::withMessages([
                'auth' => 'Anda tidak memiliki otoritas menghapus backup desa lain.',
            ]);
        }

        $deleted = $this->backupRepository->deleteFile($safeName);

        if ($deleted) {
            AuditLog::create([
                'user_id' => $user->id,
                'desa_id' => $user->desa_id,
                'action' => 'BACKUP_DELETED',
                'module' => 'BACKUP',
                'payload' => ['filename' => $safeName],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        }

        return $deleted;
    }

    /**
     * Restore database from file
     */
    public function restoreBackup(array $payload, User $user): array
    {
        $password = $payload['password'] ?? '';
        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password otentikasi salah. Restorasi database dibatalkan demi keamanan.',
            ]);
        }

        $tablesData = [];
        $targetDesaId = $this->resolveDesaId($payload['desa_id'] ?? null, $user);

        if (isset($payload['filename'])) {
            // Restore from server storage file
            $safeName = basename($payload['filename']);
            $path = $this->backupRepository->getFilePath($safeName);
            if (!$path || !File::exists($path)) {
                throw ValidationException::withMessages([
                    'filename' => 'Berkas backup di server tidak ditemukan.',
                ]);
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'json') {
                $content = File::get($path);
                $decoded = json_decode($content, true);
                $tablesData = $decoded['data'] ?? $decoded;
            } elseif ($ext === 'zip' && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($path) === true) {
                    $jsonStr = $zip->getFromName('backup.json');
                    $zip->close();
                    if ($jsonStr) {
                        $decoded = json_decode($jsonStr, true);
                        $tablesData = $decoded['data'] ?? $decoded;
                    }
                }
            }
        } elseif (isset($payload['file_content'])) {
            // Uploaded JSON direct
            $decoded = json_decode($payload['file_content'], true);
            if (!is_array($decoded)) {
                throw ValidationException::withMessages([
                    'file' => 'Format file backup tidak valid atau rusak.',
                ]);
            }
            $tablesData = $decoded['data'] ?? $decoded;
        }

        if (empty($tablesData) || !is_array($tablesData)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data tabel yang dapat diekstraksi dari file backup ini.',
            ]);
        }

        // Execute restore
        $result = $this->backupRepository->restoreFromTablesData($tablesData, $targetDesaId);

        // Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'desa_id' => $targetDesaId ?? $user->desa_id,
            'action' => 'BACKUP_RESTORED',
            'module' => 'BACKUP',
            'payload' => [
                'restored_tables' => array_keys($result['restored_counts'] ?? []),
                'total_restored' => $result['total_restored'] ?? 0,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return $result;
    }

    /**
     * Resolve target Desa ID according to user role permissions
     */
    protected function resolveDesaId(?int $requestedDesaId, ?User $user): ?int
    {
        if (!$user) {
            return $requestedDesaId;
        }

        $role = $user->role ?? '';
        $isSuperAdmin = $role === 'SUPER_ADMIN_SYSTEM' || (!$user->desa_id && $role === 'SUPER_ADMIN');

        if ($isSuperAdmin) {
            return $requestedDesaId;
        }

        // Enforce tenant boundary for Admin Desa / others
        return $user->desa_id;
    }

    /**
     * Get MIME Type based on file extension
     */
    protected function getMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'zip' => 'application/zip',
            'sql' => 'application/sql',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
