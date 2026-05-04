<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {

        $validated = $request->validate([
            'name'=>'required',
            'email'=>'required|email|unique:users',
            'phoneNumber' => 'required|unique:users',
            'password'=>'required|min:6'
        ]);

        return response()->json(
            $this->authService->register($validated)
        );
    }


    public function verifyOtp(Request $request)
    {

        $request->validate([
            'email'=>'required|email',
            'otp'=>'required'
        ]);

        return response()->json(
            $this->authService->verifyOtp($request->all())
        );
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email'=>'required|email'
        ]);

        return response()->json(
            $this->authService->resendOtp($request->all())
        );
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $result = $this->authService->requestPasswordReset($validated);

        $status = ! empty($result['throttled']) ? 429 : 200;

        return response()->json($result, $status);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->resetPasswordWithOtp($validated);

        return response()->json(
            $result,
            $result['success'] ? 200 : 422
        );
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'=>'required|email',
            'password'=>'required'
        ]);

        $result = $this->authService->login($request->all());

        if (! $result || empty($result['token'] ?? null)) {
            return response()->json($result ?? [
                'message' => __('app.invalid_credentials'),
            ], 401);
        }

        return response()->json($result);
    }

    public function logout()
    {
        $user = Auth::user();

        $user->currentAccessToken()->delete();



        return response()->json([
            'message' => __('app.logged_out'),
        ]);
    }
}
