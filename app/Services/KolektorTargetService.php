<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\KolektorTargetRepository;
use Illuminate\Validation\ValidationException;

class KolektorTargetService
{
    public function __construct(
        protected KolektorTargetRepository $repository
    ) {}

    /**
     * List semua target kolektor di suatu desa.
     */
    public function listTargets(int $tahun, ?int $desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if (!$isSuperAdmin && $user && $user->desa_id) {
            $desaId = $user->desa_id;
        }

        if (!$desaId && !$isSuperAdmin) {
            return [];
        }

        // Jika Super Admin tanpa filter desa, return leaderboard se-kecamatan
        if ($isSuperAdmin && !$desaId) {
            return $this->repository->getLeaderboard($tahun, null);
        }

        return $this->repository->getByDesaAndTahun($desaId, $tahun);
    }

    /**
     * Performance kolektor yang sedang login (self).
     */
    public function getMyPerformance(int $tahun): ?array
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $performance = $this->repository->getByKolektorAndTahun(
            $user->id,
            $tahun,
            $user->desa_id
        );

        if (!$performance) {
            return null;
        }

        // Tambahkan trend & breakdown dusun
        $performance['trend_harian'] = $this->repository->getTrendHarian(
            $user->id,
            $tahun,
            $user->desa_id
        );
        $performance['trend_mingguan'] = $this->repository->getTrendMingguan(
            $user->id,
            $tahun,
            $user->desa_id
        );
        $performance['dusun_breakdown'] = $this->repository->getCapaianPerDusun(
            $user->id,
            $tahun,
            $user->desa_id
        );

        return $performance;
    }

    /**
     * Detail performance 1 kolektor spesifik (untuk admin).
     */
    public function getKolektorDetail(int $kolektorId, int $tahun): ?array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        $desaId = $isSuperAdmin ? null : $user->desa_id;

        $performance = $this->repository->getByKolektorAndTahun($kolektorId, $tahun, $desaId);
        if (!$performance) {
            return null;
        }

        $performance['trend_harian'] = $this->repository->getTrendHarian($kolektorId, $tahun, $desaId);
        $performance['trend_mingguan'] = $this->repository->getTrendMingguan($kolektorId, $tahun, $desaId);
        $performance['dusun_breakdown'] = $this->repository->getCapaianPerDusun($kolektorId, $tahun, $desaId);

        return $performance;
    }

    /**
     * Breakdown capaian per dusun kolektor.
     */
    public function getCapaianPerDusun(int $kolektorId, int $tahun): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $desaId = $isSuperAdmin ? null : $user->desa_id;

        return $this->repository->getCapaianPerDusun($kolektorId, $tahun, $desaId);
    }

    /**
     * Leaderboard ranking kolektor.
     */
    public function getLeaderboard(int $tahun, ?int $desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if (!$isSuperAdmin && $user && $user->desa_id) {
            $desaId = $user->desa_id;
        }

        $leaderboard = $this->repository->getLeaderboard($tahun, $desaId);

        // Inject ranking position
        foreach ($leaderboard as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        return $leaderboard;
    }

    /**
     * Set / update target kolektor (hanya Admin Desa, Kades, Super Admin).
     */
    public function setTarget(array $data): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $isKepalaDesa = $user && $user->role === 'KEPALA_DESA';
        $isAdminDesa = $user && $user->role === 'SUPER_ADMIN' && $user->desa_id;

        if (!$isSuperAdmin && !$isKepalaDesa && !$isAdminDesa) {
            throw ValidationException::withMessages([
                'auth' => 'Hanya Admin Desa, Kepala Desa, atau Super Admin yang dapat menetapkan target kolektor.',
            ]);
        }

        // Validasi kolektor ada dan berstatus aktif
        $kolektor = User::find($data['kolektor_id']);
        if (!$kolektor || strtolower($kolektor->role) !== 'kolektor') {
            throw ValidationException::withMessages([
                'kolektor_id' => 'User yang dipilih bukan kolektor yang valid.',
            ]);
        }

        // Tentukan desa_id
        if (!$isSuperAdmin) {
            $data['desa_id'] = $user->desa_id;

            // Pastikan kolektor milik desa yang sama
            if ($kolektor->desa_id && (int) $kolektor->desa_id !== (int) $user->desa_id) {
                throw ValidationException::withMessages([
                    'kolektor_id' => 'Kolektor ini bukan anggota desa Anda.',
                ]);
            }
        }

        if (empty($data['desa_id'])) {
            $data['desa_id'] = $kolektor->desa_id;
        }

        // Validasi target nominal > 0
        if (($data['target_nominal'] ?? 0) <= 0 && ($data['target_sppt'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'target' => 'Target nominal atau target SPPT harus lebih besar dari 0.',
            ]);
        }

        $target = $this->repository->upsertTarget($data);

        AuditLog::create([
            'desa_id' => $data['desa_id'],
            'user_id' => $user->id,
            'action' => 'KOLEKTOR_TARGET_SET',
            'module' => 'KOLEKTOR_TARGET',
            'payload' => [
                'kolektor_id' => $data['kolektor_id'],
                'kolektor_name' => $kolektor->name,
                'tahun' => $data['tahun'],
                'target_nominal' => $data['target_nominal'] ?? 0,
                'target_sppt' => $data['target_sppt'] ?? 0,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Return performance data terupdate
        $realisasi = $this->repository->getByKolektorAndTahun(
            $data['kolektor_id'],
            $data['tahun'],
            $data['desa_id']
        );

        return $realisasi ?? ['message' => 'Target berhasil disimpan.'];
    }

    /**
     * Hapus target kolektor.
     */
    public function deleteTarget(int $id): bool
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $isAdminDesa = $user && $user->role === 'SUPER_ADMIN' && $user->desa_id;

        if (!$isSuperAdmin && !$isAdminDesa) {
            throw ValidationException::withMessages([
                'auth' => 'Hanya Admin Desa atau Super Admin yang dapat menghapus target.',
            ]);
        }

        $deleted = $this->repository->deleteTarget($id);

        if ($deleted) {
            AuditLog::create([
                'desa_id' => $user->desa_id,
                'user_id' => $user->id,
                'action' => 'KOLEKTOR_TARGET_DELETED',
                'module' => 'KOLEKTOR_TARGET',
                'payload' => ['target_id' => $id],
                'ip_address' => request()->ip(),
            ]);
        }

        return $deleted;
    }
}
