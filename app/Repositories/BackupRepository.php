<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupRepository
{
    protected string $storagePath;

    /**
     * Core tables in chronological/dependency order
     */
    protected array $tables = [
        'desas',
        'users',
        'dusuns',
        'dhkp_rows',
        'transactions',
        'setoran_kecamatans',
        'dusun_targets',
        'kolektor_targets',
        'settings',
        'audit_logs',
    ];

    public function __construct()
    {
        $this->storagePath = storage_path('app/backups');
        if (!File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Get summary metrics of database and backup files
     */
    public function getSummary(?int $desaId = null): array
    {
        $tableCounts = [];
        $totalRecords = 0;

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                $query = DB::table($table);
                if ($desaId && Schema::hasColumn($table, 'desa_id')) {
                    $query->where('desa_id', $desaId);
                }
                $count = $query->count();
                $tableCounts[$table] = $count;
                $totalRecords += $count;
            }
        }

        // List files in storagePath
        $files = $this->listFiles($desaId);
        $totalBackupSize = array_sum(array_column($files, 'size_bytes'));
        $lastBackup = !empty($files) ? $files[0] : null;

        return [
            'total_tables' => count($tableCounts),
            'total_records' => $totalRecords,
            'table_counts' => $tableCounts,
            'total_backups_stored' => count($files),
            'total_backup_size_bytes' => $totalBackupSize,
            'total_backup_size_human' => $this->formatBytes($totalBackupSize),
            'last_backup' => $lastBackup,
        ];
    }

    /**
     * List all backup files in storage
     */
    public function listFiles(?int $desaId = null): array
    {
        if (!File::exists($this->storagePath)) {
            return [];
        }

        $allFiles = File::files($this->storagePath);
        $result = [];

        foreach ($allFiles as $file) {
            $filename = $file->getFilename();
            // Optional filter by desa if naming convention contains desa_
            if ($desaId && str_contains($filename, 'desa_') && !str_contains($filename, "desa_{$desaId}_")) {
                continue;
            }

            $size = $file->getSize();
            $ext = strtolower($file->getExtension());
            $mtime = $file->getMTime();

            $meta = $this->readBackupMetadata($file->getPathname(), $ext);

            $result[] = [
                'filename' => $filename,
                'path' => $file->getPathname(),
                'format' => $ext,
                'size_bytes' => $size,
                'size_human' => $this->formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'timestamp' => $mtime,
                'scope' => $meta['scope'] ?? ($desaId ? "Desa ID {$desaId}" : 'Semua Desa (Full)'),
                'total_records' => $meta['total_records'] ?? null,
                'created_by' => $meta['created_by'] ?? null,
                'notes' => $meta['notes'] ?? null,
            ];
        }

        // Sort by created_at DESC (latest first)
        usort($result, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $result;
    }

    /**
     * Get full data payload for backup
     */
    public function extractData(?int $desaId = null): array
    {
        $payload = [];
        $tableCounts = [];
        $totalRecords = 0;

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                $query = DB::table($table);
                if ($desaId && Schema::hasColumn($table, 'desa_id')) {
                    $query->where('desa_id', $desaId);
                }
                $rows = $query->get()->map(fn($row) => (array) $row)->toArray();
                $payload[$table] = $rows;
                $count = count($rows);
                $tableCounts[$table] = $count;
                $totalRecords += $count;
            }
        }

        return [
            'tables' => $payload,
            'counts' => $tableCounts,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Generate SQL dump script
     */
    public function generateSqlDump(array $tablesData, ?int $desaId = null, string $createdBy = 'System'): string
    {
        $date = date('Y-m-d H:i:s');
        $scope = $desaId ? "Khusus Desa ID {$desaId}" : 'Full Database (Seluruh Desa)';
        
        $sql = "-- ========================================================\n";
        $sql .= "-- LENTERA (Layanan Elektronik Terpadu Pajak Daerah)\n";
        $sql .= "-- Database Backup SQL Dump\n";
        $sql .= "-- Generated At: {$date}\n";
        $sql .= "-- Scope: {$scope}\n";
        $sql .= "-- Created By: {$createdBy}\n";
        $sql .= "-- ========================================================\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n\n";

        foreach ($tablesData as $table => $rows) {
            $count = count($rows);
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Data untuk tabel `{$table}` ({$count} baris)\n";
            $sql .= "-- --------------------------------------------------------\n";

            if ($count === 0) {
                $sql .= "-- (Tidak ada data)\n\n";
                continue;
            }

            // Chunk inserts by 100 rows
            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $columns = array_keys($chunk[0]);
                $colNames = implode('`, `', $columns);

                $valueStrings = [];
                foreach ($chunk as $row) {
                    $escapedValues = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_numeric($val)) {
                            return $val;
                        }
                        return "'" . addslashes((string) $val) . "'";
                    }, array_values($row));

                    $valueStrings[] = "(" . implode(', ', $escapedValues) . ")";
                }

                $sql .= "INSERT INTO `{$table}` (`{$colNames}`) VALUES\n" . implode(",\n", $valueStrings) . ";\n";
            }

            $sql .= "\n";
        }

        $sql .= "COMMIT;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * Store backup to disk
     */
    public function saveBackupFile(string $filename, string $content): string
    {
        $path = $this->storagePath . DIRECTORY_SEPARATOR . $filename;
        File::put($path, $content);
        return $path;
    }

    /**
     * Create ZIP backup containing SQL and JSON manifest
     */
    public function createZipBackup(string $zipFilename, string $sqlContent, string $jsonContent, array $metadata): string
    {
        $zipPath = $this->storagePath . DIRECTORY_SEPARATOR . $zipFilename;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('backup.sql', $sqlContent);
                $zip->addFromString('backup.json', $jsonContent);
                $zip->addFromString('manifest.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $zip->close();
                return $zipPath;
            }
        }

        // Fallback: If ZipArchive not available, save as JSON
        $fallbackFilename = str_replace('.zip', '.json', $zipFilename);
        $this->saveBackupFile($fallbackFilename, $jsonContent);
        return $this->storagePath . DIRECTORY_SEPARATOR . $fallbackFilename;
    }

    /**
     * Find a backup file path by filename
     */
    public function getFilePath(string $filename): ?string
    {
        $safeName = basename($filename);
        $path = $this->storagePath . DIRECTORY_SEPARATOR . $safeName;
        return File::exists($path) ? $path : null;
    }

    /**
     * Delete a backup file
     */
    public function deleteFile(string $filename): bool
    {
        $safeName = basename($filename);
        $path = $this->storagePath . DIRECTORY_SEPARATOR . $safeName;
        if (File::exists($path)) {
            return File::delete($path);
        }
        return false;
    }

    /**
     * Restore database from structured tables array
     */
    public function restoreFromTablesData(array $tablesData, ?int $desaId = null): array
    {
        $restoredCounts = [];

        DB::beginTransaction();
        try {
            // Disable foreign key checks if MySQL
            $isMysql = DB::connection()->getDriverName() === 'mysql';
            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            foreach ($this->tables as $table) {
                if (isset($tablesData[$table]) && Schema::hasTable($table)) {
                    $rows = $tablesData[$table];
                    $query = DB::table($table);

                    if ($desaId && Schema::hasColumn($table, 'desa_id')) {
                        // Only truncate/delete scoped desa records
                        $query->where('desa_id', $desaId)->delete();
                    } else {
                        // Full system: Truncate / Delete all
                        $query->delete();
                    }

                    if (!empty($rows)) {
                        // Chunk inserts to avoid packet limit
                        $chunks = array_chunk($rows, 200);
                        foreach ($chunks as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }

                    $restoredCounts[$table] = count($rows);
                }
            }

            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            DB::commit();

            return [
                'status' => true,
                'restored_counts' => $restoredCounts,
                'total_restored' => array_sum($restoredCounts),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            if (isset($isMysql) && $isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
            throw $e;
        }
    }

    /**
     * Parse and read metadata from backup file
     */
    protected function readBackupMetadata(string $path, string $ext): array
    {
        if ($ext === 'json') {
            try {
                $content = File::get($path);
                $json = json_decode($content, true);
                if (isset($json['_metadata'])) {
                    return $json['_metadata'];
                }
            } catch (\Throwable $e) {
                // Ignore parse errors for corrupt files
            }
        } elseif ($ext === 'zip' && class_exists('ZipArchive')) {
            try {
                $zip = new ZipArchive();
                if ($zip->open($path) === true) {
                    $manifestStr = $zip->getFromName('manifest.json');
                    $zip->close();
                    if ($manifestStr) {
                        $meta = json_decode($manifestStr, true);
                        if (is_array($meta)) {
                            return $meta;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return [];
    }

    /**
     * Format bytes to human readable string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
