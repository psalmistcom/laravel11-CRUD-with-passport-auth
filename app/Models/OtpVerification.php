<?php

namespace App\Models;

use App\Enum\StatusEnum;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class OtpVerification extends Model
{
    use HasFactory;

    public const PURPOSE_PASSWORD_RESET = 'password_reset';
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    public const PURPOSE_PHONE_VERIFICATION = 'phone_verification';
    public const PURPOSE_WITHDRAWAL_REQUEST = 'withdrawal_request';
    public const PURPOSE_PIN_RESET = 'pin_reset';

    public const TYPE_MAIL = 'mail';
    public const TYPE_SMS = 'sms';

    public function isExpired(): bool
    {
        $expiresAt = new Carbon($this['expires_at']);
        return $expiresAt->lt(now());
    }

    public function markAsExpired(): bool
    {
        return $this->forceFill(['status' => StatusEnum::EXPIRED])->save();
    }

    public function markAsVerified(): bool
    {
        return $this->forceFill([
            'status' => StatusEnum::VERIFIED,
            'verified_at' => now()
        ])->save();
    }

    public function otpExpired(?Closure $callback): void
    {
        if ($this->isExpired()) {
            $this->markAsExpired();
            DB::commit();
            is_callable($callback) && $callback();
        }
    }

    public function otpverifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
