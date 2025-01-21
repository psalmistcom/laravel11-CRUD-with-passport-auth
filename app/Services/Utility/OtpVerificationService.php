<?php

namespace App\Services\Utility;

use App\Enum\StatusEnum;
use App\Events\RequestOtpEvent;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpVerificationService
{
    public function __construct(protected OtpVerification $otpVerification)
    {
        //
    }

    public function sendOtp(
        mixed $model,
        User|Authenticatable $user,
        string $purpose,
        string $recipient,
        string $type,
        bool $event = true
    ): OtpVerification {
        if (!is_null($otpVerification = $this->otpExistsAndPending($model, $user, $purpose, $recipient)) && !$otpVerification->isExpired()) {
            $event ? event(new RequestOtpEvent($otpVerification, $user)) : true;

            return $otpVerification;
        }
        $this->otpVerification->otpverifiable_type = $model;
        $this->otpVerification->otpverifiable_id = $user->id;
        $this->otpVerification->recipient = $recipient;
        $this->otpVerification->purpose = $purpose;
        $this->otpVerification->code = $this->generateOtp();
        $this->otpVerification->type = $type;
        $this->otpVerification['expires_at'] = now()->addHour();
        $this->otpVerification->save();

        $event ? event(new RequestOtpEvent($this->otpVerification, $user)) : true;
        return $this->otpVerification;
    }

    private function otpExistsAndPending(
        mixed $model,
        User $user,
        string $purpose,
        string $recipient
    ) {
        return OtpVerification::query()
            ->whereHasMorph('otpverifiable', $model)
            ->whereStatus(StatusEnum::PENDING)
            ->wherePurpose($purpose)
            ->whereOtpverifiableId($user->id)
            ->whereRecipient($recipient)
            ->first();
    }

    public function verifyOtp(
        mixed $model,
        User $user,
        string $code,
        string $purpose,
        string $recipient,
        string $type,
        int $otpVerificationId,
    ): OtpVerification {
        $otpVerification = OtpVerification::query()
            ->whereHasMorph('otpverifiable', $model)
            ->whereOtpverifiableId($user->id)
            ->whereCode($code)
            ->wherePurpose($purpose)
            ->whereRecipient($recipient)
            ->whereType($type)
            ->find($otpVerificationId);

        if (!$otpVerification) {
            throw new HttpException('The otp entered is invalid');
        }

        $otpVerification->otpExpired(static function () {
            throw new HttpException('Otp expired. Please generate a new code');
        });

        $otpVerification->markAsVerified();

        return $otpVerification;
    }

    /**
     * Generates a random string to use as otp.
     * @return string
     * @throws Exception
     */
    public function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }
}
