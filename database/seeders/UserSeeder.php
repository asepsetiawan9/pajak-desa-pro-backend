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
                'name' => 'Asep Setiawan, S.Kom',
                'username' => 'admin.desa',
                'nip' => '19880512 201201 1 004',
                'email' => 'admin@malangbong.desa.id',
                'phone' => '081234567890',
                'password' => Hash::make('admin123'),
                'role' => 'SUPER_ADMIN',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
            ],
            [
                'name' => 'Hj. Ratna Sari, S.E.',
                'username' => 'bendahara.pbb',
                'nip' => '19910320 201503 2 008',
                'email' => 'bendahara@malangbong.desa.id',
                'phone' => '082198765432',
                'password' => Hash::make('password123'),
                'role' => 'BENDAHARA',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
            ],
            [
                'name' => 'Deden Sudrajat',
                'username' => 'kolektor.balok',
                'nip' => '-',
                'email' => 'deden@malangbong.desa.id',
                'phone' => '085712349876',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'BALOK, CIDERES',
                'status_aktif' => true,
            ],
            [
                'name' => 'Maman Suherman',
                'username' => 'kolektor.cideres',
                'nip' => '-',
                'email' => 'maman@malangbong.desa.id',
                'phone' => '081399887766',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'CIDERES',
                'status_aktif' => true,
            ],
            [
                'name' => 'Aep Saepudin',
                'username' => 'kolektor.puncak',
                'nip' => '-',
                'email' => 'aep@malangbong.desa.id',
                'phone' => '081311223344',
                'password' => Hash::make('password123'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'PUNCAK SARI, CIPEDES',
                'status_aktif' => true,
            ],
            [
                'name' => 'H. Dadang Kusnadi, S.H.',
                'username' => 'kades.malangbong',
                'nip' => '19750804 200501 1 002',
                'email' => 'kades@malangbong.desa.id',
                'phone' => '081122334455',
                'password' => Hash::make('password123'),
                'role' => 'KEPALA_DESA',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::where('username', $userData['username'])
                ->orWhere('email', $userData['email'])
                ->delete();
            User::create($userData);
        }
    }
}
