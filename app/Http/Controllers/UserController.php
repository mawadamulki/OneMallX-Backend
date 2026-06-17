<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

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
        return response()->json([
            'success' => true,
            'user' => $this->userService->getCurrentUserDetail(\Illuminate\Support\Facades\Auth::id()),
        ]);
    }

    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $result = $this->userService->updateProfilePicture(
            (int) \Illuminate\Support\Facades\Auth::id(),
            $request->file('photo')
        );

        return response()->json($result, $result['http_status'] ?? 200);
    }
}
