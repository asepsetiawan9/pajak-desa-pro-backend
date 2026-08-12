<?php

namespace App\Services;

use App\Models\Desa;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\DesaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DesaService
{
    public function __construct(protected DesaRepository $desaRepository) {}

    public function getAll(array $filters)
    {
        return $this->desaRepository->getAllDesas($filters['search'] ?? null);
    }

    public function getById(int $id): Desa
    {
        $desa = $this->desaRepository->findById($id);
        if (!$desa) {
            throw ValidationException::withMessages(['desa' => 'Desa tidak ditemukan.']);
        }
        return $desa;
    }

    public function createDesaWithDefaultUsers(array $data): array
    {
        // Sanitize subdomain
        $subdomain = Str::slug($data['subdomain'] ?? Str::slug($data['nama_desa']));

        // Check uniqueness
        if ($this->desaRepository->findByKode($data['kode_desa'])) {
            throw ValidationException::withMessages(['kode_desa' => 'Kode Desa 10-digit ini sudah terdaftar.']);
        }

        if ($this->desaRepository->findBySubdomain($subdomain)) {
            throw ValidationException::withMessages(['subdomain' => 'Subdomain desa ini sudah digunakan.']);
        }

        return DB::transaction(function () use ($data, $subdomain) {
            // 1. Create Desa Record
            $desaData = [
                'kode_desa' => $data['kode_desa'],
                'nama_desa' => $data['nama_desa'],
                'nama_kecamatan' => $data['nama_kecamatan'] ?? 'Malangbong',
                'nama_kabupaten' => $data['nama_kabupaten'] ?? 'Garut',
                'nama_provinsi' => $data['nama_provinsi'] ?? 'Jawa Barat',
                'nama_kades' => $data['nama_kades'] ?? null,
                'nip_kades' => $data['nip_kades'] ?? null,
                'subdomain' => $subdomain,
                'logo_path' => $data['logo_path'] ?? '/lentera-logo.png',
                'status_aktif' => true,
            ];

            $desa = $this->desaRepository->create($desaData);

            // 2. Auto-Generate 3 Default Users per Desa (Admin Desa, Kolektor, Kepala Desa)
            $defaultPassword = $data['default_password'] ?? 'password123';
            $adminPassword = $data['admin_password'] ?? 'admin123';

            $userTemplates = [
                [
                    'name' => 'Admin ' . $desa->nama_desa,
                    'username' => 'admin.' . $subdomain,
                    'email' => 'admin@' . $subdomain . '.desa.id',
                    'nip' => '19880512 201201 1 ' . str_pad($desa->id, 3, '0', STR_PAD_LEFT),
                    'phone' => '0812' . str_pad($desa->id, 8, '0', STR_PAD_LEFT),
                    'plain_password' => $adminPassword,
                    'role' => 'SUPER_ADMIN',
                    'dusun_akses' => 'ALL',
                ],
                [
                    'name' => 'Kolektor ' . $desa->nama_desa,
                    'username' => 'kolektor.' . $subdomain,
                    'email' => 'kolektor@' . $subdomain . '.desa.id',
                    'nip' => '-',
                    'phone' => '0857' . str_pad($desa->id, 8, '0', STR_PAD_LEFT),
                    'plain_password' => $defaultPassword,
                    'role' => 'KOLEKTOR',
                    'dusun_akses' => 'ALL',
                ],
                [
                    'name' => $desa->nama_kades ?: ('Kepala Desa ' . $desa->nama_desa),
                    'username' => 'kades.' . $subdomain,
                    'email' => 'kades@' . $subdomain . '.desa.id',
                    'nip' => $desa->nip_kades ?: ('19750804 200501 1 ' . str_pad($desa->id, 3, '0', STR_PAD_LEFT)),
                    'phone' => '0811' . str_pad($desa->id, 8, '0', STR_PAD_LEFT),
                    'plain_password' => $defaultPassword,
                    'role' => 'KEPALA_DESA',
                    'dusun_akses' => 'ALL',
                ],
            ];

            $createdUsers = [];
            foreach ($userTemplates as $tpl) {
                $user = User::create([
                    'desa_id' => $desa->id,
                    'name' => $tpl['name'],
                    'username' => $tpl['username'],
                    'email' => $tpl['email'],
                    'nip' => $tpl['nip'],
                    'phone' => $tpl['phone'],
                    'password' => Hash::make($tpl['plain_password']),
                    'role' => $tpl['role'],
                    'dusun_akses' => $tpl['dusun_akses'],
                    'status_aktif' => true,
                ]);

                $createdUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'plain_password' => $tpl['plain_password'],
                ];
            }

            // 3. Seed Default Settings for New Desa
            $defaultSettings = [
                'nama_desa' => $desa->nama_desa,
                'namaDesa' => $desa->nama_desa,
                'kode_desa' => $desa->kode_desa,
                'kodeDesa' => $desa->kode_desa,
                'nama_kecamatan' => $desa->nama_kecamatan,
                'kecamatan' => $desa->nama_kecamatan,
                'nama_instansi' => 'Pemerintah Kabupaten ' . $desa->nama_kabupaten,
                'kabupaten' => 'Pemerintah Kabupaten ' . $desa->nama_kabupaten,
                'nama_kades' => $desa->nama_kades ?: 'Kepala Desa',
                'namaKades' => $desa->nama_kades ?: 'Kepala Desa',
                'nip_kades' => $desa->nip_kades ?: '-',
                'nipKades' => $desa->nip_kades ?: '-',
                'tahun_pajak' => '2026',
                'tahunAktif' => '2026',
                'jatuh_tempo_bulan' => '09',
                'jatuhTempoBulan' => '09',
                'jatuh_tempo_tanggal' => '30',
                'jatuhTempoTanggal' => '30',
                'pembulatan_ribuan' => 'true',
                'pembulatanRibuan' => 'true',
                'printer_format' => '58mm',
                'printerFormat' => '58mm',
                'header_resi_thermal' => 'PEMERINTAH KABUPATEN ' . strtoupper($desa->nama_kabupaten) . "\nKECAMATAN " . strtoupper($desa->nama_kecamatan) . "\nPEMERINTAH " . strtoupper($desa->nama_desa),
                'footer_resi_thermal' => "Terima kasih atas partisipasi Anda dalam pembangunan desa.\nSimpan resi ini sebagai bukti pembayaran sah PBB-P2.",
            ];

            foreach ($defaultSettings as $sKey => $sVal) {
                Setting::create([
                    'desa_id' => $desa->id,
                    'key' => $sKey,
                    'value' => (string) $sVal,
                    'description' => "Initial setting for {$sKey}",
                ]);
            }

            return [
                'desa' => $desa->load('users'),
                'created_users' => $createdUsers,
            ];
        });
    }

    public function updateDesa(int $id, array $data): Desa
    {
        $desa = $this->getById($id);

        if (isset($data['kode_desa']) && $data['kode_desa'] !== $desa->kode_desa) {
            if ($this->desaRepository->findByKode($data['kode_desa'])) {
                throw ValidationException::withMessages(['kode_desa' => 'Kode Desa 10-digit ini sudah digunakan oleh desa lain.']);
            }
        }

        if (isset($data['subdomain']) && $data['subdomain'] !== $desa->subdomain) {
            $subdomain = Str::slug($data['subdomain']);
            if ($this->desaRepository->findBySubdomain($subdomain)) {
                throw ValidationException::withMessages(['subdomain' => 'Subdomain ini sudah digunakan oleh desa lain.']);
            }
            $data['subdomain'] = $subdomain;
        }

        return $this->desaRepository->update($desa, $data);
    }

    public function toggleStatus(int $id): Desa
    {
        $desa = $this->getById($id);
        return $this->desaRepository->update($desa, [
            'status_aktif' => !$desa->status_aktif,
        ]);
    }

    public function deleteDesa(int $id): bool
    {
        $desa = $this->getById($id);

        if ($desa->dhkpRows()->count() > 0 || $desa->transactions()->count() > 0) {
            throw ValidationException::withMessages([
                'desa' => 'Desa ini memiliki data DHKP atau Transaksi aktif dan tidak dapat dihapus. Silakan nonaktifkan statusnya.',
            ]);
        }

        return DB::transaction(function () use ($desa) {
            User::where('desa_id', $desa->id)->delete();
            Setting::where('desa_id', $desa->id)->delete();
            return $this->desaRepository->delete($desa);
        });
    }
}
