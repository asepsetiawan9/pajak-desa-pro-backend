<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function getAllUsers(?string $role = null, ?bool $statusAktif = null, ?string $search = null)
    {
        $query = User::query();

        if ($role && $role !== 'ALL') {
            $query->where('role', $role);
        }

        if ($statusAktif !== null) {
            $query->where('status_aktif', $statusAktif);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->get();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }
}
