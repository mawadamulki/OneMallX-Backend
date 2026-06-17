<?php

namespace App\DAO;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserDAO implements UserDAOInterface
{
    public function paginateAllUsersForAdmin(int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'media'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateStoreOwnersForAdmin(int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->role('Store Owner')
            ->with(['roles', 'media'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateServiceProvidersForAdmin(int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->role('Service Provider')
            ->with(['roles', 'media'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateCustomersForAdmin(int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->role('Customer')
            ->with(['roles', 'media'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByEmail(string $email): ?User
    {
        $user = User::query()->where('email', $email)->with('roles')->first();

        if ($user === null) {
            return null;
        }

        $user->setAttribute('role_names', $user->roles->pluck('name')->values()->all());
        $user->makeHidden('roles');

        return $user;
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }
}
