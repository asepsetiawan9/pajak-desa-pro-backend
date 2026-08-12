<?php

namespace Database\Seeders;

use App\Models\Desa;
use Illuminate\Database\Seeder;

class DesaSeeder extends Seeder
{
    public function run(): void
    {
        Desa::updateOrCreate(
            ['id' => 1],
            [
                'kode_desa' => '3205120004',
                'nama_desa' => 'Desa Barudua',
                'nama_kecamatan' => 'Malangbong',
                'nama_kabupaten' => 'Garut',
                'nama_provinsi' => 'Jawa Barat',
                'nama_kades' => 'Endang Yana',
                'nip_kades' => '197505122008011002',
                'subdomain' => 'barudua',
                'logo_path' => '/lentera-logo.png',
                'status_aktif' => true,
            ]
        );

        Desa::updateOrCreate(
            ['id' => 2],
            [
                'kode_desa' => '3205120005',
                'nama_desa' => 'Desa Cihaur',
                'nama_kecamatan' => 'Malangbong',
                'nama_kabupaten' => 'Garut',
                'nama_provinsi' => 'Jawa Barat',
                'nama_kades' => 'H. Agus Subagja',
                'nip_kades' => '197803152009021001',
                'subdomain' => 'cihaur',
                'logo_path' => '/lentera-logo.png',
                'status_aktif' => true,
            ]
        );

        Desa::updateOrCreate(
            ['id' => 3],
            [
                'kode_desa' => '3205120006',
                'nama_desa' => 'Desa Sukamaju',
                'nama_kecamatan' => 'Malangbong',
                'nama_kabupaten' => 'Garut',
                'nama_provinsi' => 'Jawa Barat',
                'nama_kades' => 'Deden Kurnia',
                'nip_kades' => '198106202010011003',
                'subdomain' => 'sukamaju',
                'logo_path' => '/lentera-logo.png',
                'status_aktif' => true,
            ]
        );
    }
}
