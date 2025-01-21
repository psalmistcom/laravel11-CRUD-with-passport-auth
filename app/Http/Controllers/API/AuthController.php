<?php

namespace App\Http\Controllers\API;

use App\Enum\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Models\OtpVerification;
use App\Models\User;
use App\Notifications\NewCustomerNotification;
use App\Traits\JsonResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\Utility\OtpVerificationService;

class AuthController extends Controller
{
    use JsonResponseTrait;

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $createUser = User::create([
                'full_name' => $request->input('full_name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);
            $response['user'] = $createUser;
            $response['token'] = $createUser->createToken('user')->accessToken;
            $otp = $this->sendOtp($createUser, false);
            $response['otp_verification_id'] = $otp->id;
            $createUser->notify(new NewCustomerNotification($otp));
            DB::commit();
            return $this->successResponse($response, 'Registration Successful');
        } catch (Exception $th) {
            DB::rollBack();
            return $this->fatalErrorResponse($th);
        }
    }

    public function login()
    {
        //
    }

    /**
     * @param User $user
     * @param bool $event
     * @return mixed
     */
    public function sendOtp(User $user, bool $event): mixed
    {
        return app(OtpVerificationService::class)->sendOtp(
            User::class,
            $user,
            OtpVerification::PURPOSE_EMAIL_VERIFICATION,
            $user['email'],
            'mail',
            $event
        );
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            $otp = $this->sendOtp($request->user(), true);
            return $this->successResponse(['otp_verification_id' => $otp->id], 'Otp sent succesfully');
        } catch (Exception $th) {
            return $this->fatalErrorResponse($th);
        }
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            app(OtpVerificationService::class)->verifyOtp(
                User::class,
                $request->user(),
                $request->input('code'),
                OtpVerification::PURPOSE_EMAIL_VERIFICATION,
                $request->user()['email'],
                'mail',
                $request->input('otp_verification_id')
            );
            $request->user()['email_verified_at'] = now();
            $request->user()['status'] = StatusEnum::VERIFIED->value;
            $request->user()->save();

            DB::commit();

            return $this->success('Email verified succesfully');
        } catch (Exception $th) {
            DB::rollBack();
            return $this->fatalErrorResponse($th);
        }
    }
}
