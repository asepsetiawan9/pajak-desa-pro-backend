<?php

namespace App\Services;

use App\Models\Dusun;
use App\Repositories\DusunRepository;
use Illuminate\Validation\ValidationException;

class DusunService
{
    public function __construct(protected DusunRepository $dusunRepository) {}

    /**
     * Mendapatkan daftar master dusun lengkap dengan filter
     */
    public function getAll(array $filters = [])
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        if (!$isSuperAdmin) {
            $filters['desa_id'] = $user?->desa_id;
        }

        return $this->dusunRepository->getAll($filters);
    }

    /**
     * Mendapatkan detail satu dusun berdasarkan ID
     */
    public function getById(int $id): Dusun
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        $dusun = $this->dusunRepository->findById($id);

        if (!$dusun) {
            throw ValidationException::withMessages(['dusun' => 'Data dusun tidak ditemukan.']);
        }

        if (!$isSuperAdmin && $dusun->desa_id !== $user?->desa_id) {
            throw ValidationException::withMessages(['dusun' => 'Anda tidak memiliki otorisasi untuk mengakses dusun desa lain.']);
        }

        return $dusun;
    }

    /**
     * Tambah master dusun baru
     */
    public function create(array $data): Dusun
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        $effectiveDesaId = $isSuperAdmin
            ? ($data['desa_id'] ?? $user?->desa_id)
            : $user?->desa_id;

        if (empty($effectiveDesaId)) {
            throw ValidationException::withMessages(['desa_id' => 'Desa ID wajib dipilih untuk menentukan wilayah dusun.']);
        }

        $namaDusun = trim((string)($data['nama_dusun'] ?? ''));
        if ($namaDusun === '') {
            throw ValidationException::withMessages(['nama_dusun' => 'Nama dusun wajib diisi.']);
        }

        // Cek duplikasi nama dusun dalam desa yang sama
        $existing = $this->dusunRepository->findByNameAndDesa($namaDusun, (int)$effectiveDesaId);
        if ($existing) {
            throw ValidationException::withMessages(['nama_dusun' => "Dusun '{$namaDusun}' sudah terdaftar pada desa ini."]);
        }

        $createPayload = [
            'desa_id' => $effectiveDesaId,
            'nama_dusun' => $namaDusun,
            'kode_dusun' => !empty($data['kode_dusun']) ? trim((string)$data['kode_dusun']) : null,
            'rt_rw' => !empty($data['rt_rw']) ? trim((string)$data['rt_rw']) : null,
            'status_aktif' => isset($data['status_aktif']) ? (bool)$data['status_aktif'] : true,
        ];

        return $this->dusunRepository->create($createPayload);
    }

    /**
     * Update master dusun
     */
    public function update(int $id, array $data): Dusun
    {
        $dusun = $this->getById($id);

        $namaDusun = isset($data['nama_dusun']) ? trim((string)$data['nama_dusun']) : $dusun->nama_dusun;
        if ($namaDusun === '') {
            throw ValidationException::withMessages(['nama_dusun' => 'Nama dusun tidak boleh kosong.']);
        }

        // Jika nama dusun diubah, cek duplikasi
        if (strtoupper($namaDusun) !== strtoupper($dusun->nama_dusun)) {
            $existing = $this->dusunRepository->findByNameAndDesa($namaDusun, $dusun->desa_id);
            if ($existing && $existing->id !== $dusun->id) {
                throw ValidationException::withMessages(['nama_dusun' => "Dusun '{$namaDusun}' sudah digunakan di desa ini."]);
            }
        }

        $updatePayload = [
            'nama_dusun' => $namaDusun,
        ];

        if (array_key_exists('kode_dusun', $data)) {
            $updatePayload['kode_dusun'] = !empty($data['kode_dusun']) ? trim((string)$data['kode_dusun']) : null;
        }

        if (array_key_exists('rt_rw', $data)) {
            $updatePayload['rt_rw'] = !empty($data['rt_rw']) ? trim((string)$data['rt_rw']) : null;
        }

        if (array_key_exists('status_aktif', $data)) {
            $updatePayload['status_aktif'] = (bool)$data['status_aktif'];
        }

        return $this->dusunRepository->update($dusun, $updatePayload);
    }

    /**
     * Hapus master dusun
     */
    public function delete(int $id): bool
    {
        $dusun = $this->getById($id);
        return $this->dusunRepository->delete($dusun);
    }

    /**
     * Toggle status aktif dusun
     */
    public function toggleStatus(int $id): Dusun
    {
        $dusun = $this->getById($id);
        return $this->dusunRepository->update($dusun, [
            'status_aktif' => !$dusun->status_aktif,
        ]);
    }

    /**
     * Bulk create daftar dusun untuk satu desa (misal saat registrasi desa baru)
     */
    public function bulkCreate(int $desaId, array|string $dusuns): array
    {
        if (is_string($dusuns)) {
            $dusunList = array_map('trim', explode(',', $dusuns));
        } else {
            $dusunList = $dusuns;
        }

        $created = [];
        foreach ($dusunList as $dusunName) {
            $cleanName = trim((string)$dusunName);
            if ($cleanName === '') continue;

            $existing = $this->dusunRepository->findByNameAndDesa($cleanName, $desaId);
            if (!$existing) {
                $created[] = $this->dusunRepository->create([
                    'desa_id' => $desaId,
                    'nama_dusun' => $cleanName,
                    'status_aktif' => true,
                ]);
            } else {
                $created[] = $existing;
            }
        }

        return $created;
    }

    /**
     * Mendapatkan daftar nama dusun unik untuk dropdown/selector berdasarkan ID Desa
     *
     * @param int|string|null $desaId
     * @return array<string>
     */
    public function getDusunsByDesa($desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        $effectiveDesaId = $isSuperAdmin ? $desaId : $user?->desa_id;

        return $this->dusunRepository->getUniqueDusunsByDesa($effectiveDesaId);
    }
}
