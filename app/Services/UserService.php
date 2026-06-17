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

    public function getCurrentUserDetail(int $userId): array
    {
        $user = User::with('media')->findOrFail($userId);

        return $this->formatAdminUser($user);
    }

    public function updateProfilePicture(int $userId, \Illuminate\Http\UploadedFile $file): array
    {
        $user = User::findOrFail($userId);

        // Delete old profile pictures if any
        foreach ($user->media as $oldMedia) {
            $relative = $oldMedia->publicDiskRelativePath();
            if ($relative) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($relative);
            }
            $oldMedia->forceDelete();
        }

        $path = $file->store("users/{$user->id}/profile", 'public');

        $media = $user->media()->create([
            'fileType' => $file->getClientMimeType(),
            'url' => $path,
        ]);

        return [
            'success' => true,
            'message' => 'Profile picture updated.',
            'image_url' => $media->url,
        ];
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
