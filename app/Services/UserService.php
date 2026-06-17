<?php

namespace App\Services;

use App\DAO\UserDAOInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(private UserDAOInterface $userDAO) {}

    public function listAllUsersForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->userDAO->paginateAllUsersForAdmin($perPage)
            ->through(fn (User $user) => $this->formatAdminUser($user));
    }

    public function listStoreOwnersForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->userDAO->paginateStoreOwnersForAdmin($perPage)
            ->through(fn (User $user) => $this->formatAdminUser($user));
    }

    public function listServiceProvidersForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->userDAO->paginateServiceProvidersForAdmin($perPage)
            ->through(fn (User $user) => $this->formatAdminUser($user));
    }

    public function listCustomersForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->userDAO->paginateCustomersForAdmin($perPage)
            ->through(fn (User $user) => $this->formatAdminUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdminUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phoneNumber' => $user->phoneNumber,
            'image' => $user->image_url,
            'status' => $user->status,
            'is_verified' => (bool) $user->is_verified,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ];
    }
}
