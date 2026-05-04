<?php

namespace App\DAO;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserDAOInterface
{
    public function createUser(array $data): User;

    public function findByEmail(string $email): ?User;

    public function findUserByEmail(string $email): ?User;

    public function updateUser(User $user, array $data): User;

    public function deleteUser(User $user): bool;

    public function paginateAllUsersForAdmin(int $perPage): LengthAwarePaginator;

    public function paginateStoreOwnersForAdmin(int $perPage): LengthAwarePaginator;

    public function paginateServiceProvidersForAdmin(int $perPage): LengthAwarePaginator;

    public function paginateCustomersForAdmin(int $perPage): LengthAwarePaginator;
}
