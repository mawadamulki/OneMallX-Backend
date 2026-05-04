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
}
