<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin System',
                'username' => 'superadmin',
                'nip' => '00000000 000000 0 000',
                'email' => 'superadmin@lentera.id',
                'phone' => '080011223344',
                'password' => Hash::make('superadmin123'),
                'role' => 'SUPER_ADMIN_SYSTEM',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => null,
            ],
            [
                'name' => 'Asep Setiawan, S.Kom',
                'username' => 'admin.desa',
                'nip' => '19880512 201201 1 004',
                'email' => 'admin@barudua.desa.id',
                'phone' => '081234567890',
                'password' => Hash::make('admin123'),
                'role' => 'SUPER_ADMIN',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Deden Sudrajat',
                'username' => 'kolektor.balok',
                'nip' => '-',
                'email' => 'deden@barudua.desa.id',
                'phone' => '085712349876',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'BALOK, CIDERES',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Maman Suherman',
                'username' => 'kolektor.cideres',
                'nip' => '-',
                'email' => 'maman@barudua.desa.id',
                'phone' => '081399887766',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'CIDERES',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Aep Saepudin',
                'username' => 'kolektor.puncak',
                'nip' => '-',
                'email' => 'aep@barudua.desa.id',
                'phone' => '081311223344',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'PUNCAK SARI, CIPEDES',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Endang Yana',
                'username' => 'kades.barudua',
                'nip' => '19750804 200501 1 002',
                'email' => 'kades@barudua.desa.id',
                'phone' => '081122334455',
                'password' => Hash::make('password123'),
                'role' => 'KEPALA_DESA',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
        ];

        // Clean up legacy malangbong users and existing KEPALA_DESA records
        User::where('username', 'like', '%malangbong%')
            ->orWhere('email', 'like', '%malangbong%')
            ->orWhere('role', 'KEPALA_DESA')
            ->delete();

        foreach ($users as $userData) {
            User::where('username', $userData['username'])
                ->orWhere('email', $userData['email'])
                ->delete();
            User::create($userData);
        }
    }
}
