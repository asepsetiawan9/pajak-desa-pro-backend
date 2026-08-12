<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}

    public function getAll(array $filters, ?User $authUser = null)
    {
        $desaId = null;

        // If authenticated user is an Admin Desa (not global Super Admin System), restrict to their own desa_id
        if ($authUser && $authUser->role !== 'SUPER_ADMIN_SYSTEM' && $authUser->desa_id !== null) {
            $desaId = $authUser->desa_id;
        } else if (isset($filters['desa_id']) && $filters['desa_id'] !== '' && $filters['desa_id'] !== 'ALL') {
            $desaId = (int) $filters['desa_id'];
        }

        return $this->userRepository->getAllUsers(
            $filters['role'] ?? null,
            isset($filters['status_aktif']) ? filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN) : null,
            $filters['search'] ?? null,
            $desaId
        );
    }

    public function create(array $data, ?User $authUser = null): User
    {
        // Enforce tenant isolation for Admin Desa
        if ($authUser && $authUser->role !== 'SUPER_ADMIN_SYSTEM' && $authUser->desa_id !== null) {
            $data['desa_id'] = $authUser->desa_id;

            if (isset($data['role']) && $data['role'] === 'SUPER_ADMIN_SYSTEM') {
                throw ValidationException::withMessages([
                    'role' => 'Admin Desa tidak diizinkan membuat akun dengan peran Super Admin System.',
                ]);
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userRepository->create($data);
    }

    public function update(int $id, array $data, ?User $authUser = null): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }

        // Enforce tenant isolation for Admin Desa
        if ($authUser && $authUser->role !== 'SUPER_ADMIN_SYSTEM' && $authUser->desa_id !== null) {
            if ($user->desa_id !== $authUser->desa_id) {
                throw ValidationException::withMessages([
                    'user' => 'Anda tidak memiliki hak akses untuk mengedit pengguna desa lain.',
                ]);
            }
            $data['desa_id'] = $authUser->desa_id;

            if (isset($data['role']) && $data['role'] === 'SUPER_ADMIN_SYSTEM') {
                throw ValidationException::withMessages([
                    'role' => 'Admin Desa tidak diizinkan mengubah peran menjadi Super Admin System.',
                ]);
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function delete(int $id, ?User $authUser = null): bool
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }

        // Enforce tenant isolation for Admin Desa
        if ($authUser && $authUser->role !== 'SUPER_ADMIN_SYSTEM' && $authUser->desa_id !== null) {
            if ($user->desa_id !== $authUser->desa_id) {
                throw ValidationException::withMessages([
                    'user' => 'Anda tidak memiliki hak akses untuk menghapus pengguna desa lain.',
                ]);
            }
        }

        return $this->userRepository->delete($user);
    }

    public function toggleStatus(int $id, ?User $authUser = null): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }

        // Enforce tenant isolation for Admin Desa
        if ($authUser && $authUser->role !== 'SUPER_ADMIN_SYSTEM' && $authUser->desa_id !== null) {
            if ($user->desa_id !== $authUser->desa_id) {
                throw ValidationException::withMessages([
                    'user' => 'Anda tidak memiliki hak akses untuk mengubah status pengguna desa lain.',
                ]);
            }
        }

        return $this->userRepository->update($user, [
            'status_aktif' => !$user->status_aktif,
        ]);
    }
}
