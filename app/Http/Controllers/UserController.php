<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function adminAllUsers(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 50);

        return response()->json(
            $this->userService->listAllUsersForAdmin($perPage)
        );
    }

    public function adminStoreOwners(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 50);

        return response()->json(
            $this->userService->listStoreOwnersForAdmin($perPage)
        );
    }

    public function adminServiceProviders(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 50);

        return response()->json(
            $this->userService->listServiceProvidersForAdmin($perPage)
        );
    }

    public function adminCustomers(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 50);

        return response()->json(
            $this->userService->listCustomersForAdmin($perPage)
        );
    }

    public function me()
    {
        return response()->json(
            $this->userService->getProfile((int) Auth::id())
        );
    }

    public function getProfile()
    {
        return response()->json(
            $this->userService->getProfile((int) Auth::id())
        );
    }

    public function updateProfile(Request $request)
    {
        $userId = (int) Auth::id();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phoneNumber' => 'sometimes|string|max:50|unique:users,phoneNumber,'.$userId,
            'photo' => 'sometimes|image|max:5120',
        ]);

        $profileData = collect($validated)->except('photo')->filter(
            fn ($value) => $value !== null && $value !== ''
        )->all();
        $photo = $request->file('photo');

        if ($profileData === [] && $photo === null) {
            return response()->json(['message' => 'At least one field is required.'], 422);
        }

        $result = $this->userService->updateProfile($userId, $profileData, $photo);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 422);
        }

        return response()->json($result);
    }

    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->userService->updateProfilePicture(
            (int) Auth::id(),
            $request->file('photo')
        );

        return response()->json($result, $result['http_status'] ?? 200);
    }
}
