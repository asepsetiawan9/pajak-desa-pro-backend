<?php

namespace App\Repositories;

use App\Models\Desa;

class DesaRepository
{
    public function getAllDesas(?string $search = null)
    {
        $query = Desa::query()->withCount(['users', 'dhkpRows', 'transactions']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_desa', 'like', "%{$search}%")
                  ->orWhere('kode_desa', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('nama_kecamatan', 'like', "%{$search}%")
                  ->orWhere('nama_kabupaten', 'like', "%{$search}%")
                  ->orWhere('nama_kades', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->get();
    }

    public function findById(int $id): ?Desa
    {
        return Desa::with(['users', 'settings'])->find($id);
    }

    public function findByKode(string $kodeDesa): ?Desa
    {
        return Desa::where('kode_desa', $kodeDesa)->first();
    }

    public function findBySubdomain(string $subdomain): ?Desa
    {
        return Desa::where('subdomain', $subdomain)->first();
    }

    public function create(array $data): Desa
    {
        return Desa::create($data);
    }

    public function update(Desa $desa, array $data): Desa
    {
        $desa->update($data);
        return $desa->fresh(['users']);
    }

    public function delete(Desa $desa): bool
    {
        return $desa->delete();
    }
}
