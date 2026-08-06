<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}

    public function getAll(array $filters)
    {
        return $this->userRepository->getAllUsers(
            $filters['role'] ?? null,
            isset($filters['status_aktif']) ? filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN) : null,
            $filters['search'] ?? null
        );
    }

    public function create(array $data): User
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        return $this->userRepository->create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function delete(int $id): bool
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }
        return $this->userRepository->delete($user);
    }

    public function toggleStatus(int $id): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Pengguna tidak ditemukan.']);
        }

        return $this->userRepository->update($user, [
            'status_aktif' => !$user->status_aktif,
        ]);
    }
}
