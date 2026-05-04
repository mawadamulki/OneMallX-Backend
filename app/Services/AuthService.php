<?php

namespace App\Services;

use App\DAO\UserDAOInterface;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthService
{

    protected $userDAO;

    public function __construct(UserDAOInterface $userDAO)
    {
        $this->userDAO = $userDAO;
    }

    public function register($data)
    {

        $otp = rand(100000,999999);

        $data['password'] = bcrypt($data['password']);
        $data['otp_code'] = $otp;
        $data['otp_expires_at'] = now()->addMinutes(10);
        $data['status'] = 'inactive';
        $data['is_verified'] = false;

        $user = $this->userDAO->createUser($data);

        Mail::to($user->email)->send(new OtpMail($otp));

        $user->assignRole('Customer');

        return [
            'message' => __('app.register_success_check_otp'),
            'user' => $user,
        ];
    }

    public function verifyOtp($data)
    {
        $user = $this->userDAO->findUserByEmail($data['email']);

        if(!$user)
        {
            return ['message' => __('app.user_not_found')];
        }

        if($user->otp_code != $data['otp'])
        {
            return ['message' => __('app.incorrect_otp')];
        }

        if(now()->greaterThan($user->otp_expires_at))
        {
            return ['message' => __('app.otp_expired')];
        }

        $this->userDAO->updateUser($user,[
            "otp_code"=>null,
            "otp_expires_at"=>null,
            "is_verified"=>true,
            "status"=>"active"
        ]);

        return [
            'message' => __('app.account_verified_successfully'),
        ];
    }


    public function login($data)
    {
        $user = $this->userDAO->findByEmail($data['email']);

        if(!$user)
        {
            return null;
        }

        if(!Hash::check($data['password'], $user->password))
        {
            return null;
        }

        if($user->status != 'active')
        {
            return [
                'message' => __('app.account_not_activated'),
            ];
        }


        $token = $user->createToken('token')->plainTextToken;

        return [
            'message' => __('app.login_successful'),
            'token' => $token,
            'user' => $user
        ];
    }

    public function resendOtp($data)
    {
        $user = $this->userDAO->findUserByEmail($data['email']);

        if(!$user)
        {
            return ['message' => __('app.user_not_found')];
        }

        if($user->is_verified)
        {
            return ['message' => __('app.user_already_verified')];
        }

        $otp = rand(100000,999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        Mail::to($user->email)->send(new OtpMail($otp));

        return ['message' => __('app.otp_resent_successfully')];
    }

    /**
     * @return array{message: string, throttled?: bool}
     */
    public function requestPasswordReset(array $data): array
    {
        $user = $this->userDAO->findUserByEmail($data['email']);

        if (! $user) {
            return [
                'message' => __('app.password_reset_email_sent_generic'),
            ];
        }

        $rateKey = 'password-reset-send:'.Str::lower($data['email']);
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            return [
                'message' => __('app.password_reset_throttled'),
                'throttled' => true,
            ];
        }

        $otp = random_int(100000, 999999);
        RateLimiter::hit($rateKey, 60);

        $this->userDAO->updateUser($user, [
            'password_reset_otp' => $otp,
            'password_reset_otp_expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new OtpMail(
            $otp,
            'app.email_password_reset_subject',
            15,
            true,
        ));

        return [
            'message' => __('app.password_reset_email_sent_generic'),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function resetPasswordWithOtp(array $data): array
    {
        $user = $this->userDAO->findUserByEmail($data['email']);

        if (! $user) {
            return [
                'success' => false,
                'message' => __('app.password_reset_email_unknown'),
            ];
        }

        if ($user->password_reset_otp === null || $user->password_reset_otp_expires_at === null) {
            return [
                'success' => false,
                'message' => __('app.password_reset_otp_invalid'),
            ];
        }

        if ((string) $user->password_reset_otp !== (string) $data['otp']) {
            return [
                'success' => false,
                'message' => __('app.password_reset_otp_invalid'),
            ];
        }

        if (now()->greaterThan($user->password_reset_otp_expires_at)) {
            return [
                'success' => false,
                'message' => __('app.password_reset_otp_expired'),
            ];
        }

        $this->userDAO->updateUser($user, [
            'password' => $data['password'],
            'password_reset_otp' => null,
            'password_reset_otp_expires_at' => null,
        ]);

        $user->tokens()->delete();

        return [
            'success' => true,
            'message' => __('app.password_reset_success'),
        ];
    }
}

