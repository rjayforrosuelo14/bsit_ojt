<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OTP extends Model
{
    protected $table = 'otps';

    protected $fillable = [
        'email',
        'otp_code',
        'type',
        'expires_at',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * Check if OTP is valid
     */
    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    /**
     * Mark OTP as used
     */
    public function markAsUsed()
    {
        $this->is_used = true;
        $this->used_at = now();
        $this->save();
    }

    /**
     * Find valid OTP by email and type
     */
    public static function findValidOTP($email, $type = 'verification')
    {
        return static::where('email', $email)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Generate unique OTP code
     */
    public static function generateOTPCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create OTP for email
     */
    public static function createOTP($email, $type = 'verification', $expiryMinutes = 10)
    {
        // Invalidate previous OTPs for this email and type
        static::where('email', $email)
            ->where('type', $type)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Create new OTP
        return static::create([
            'email' => $email,
            'otp_code' => static::generateOTPCode(),
            'type' => $type,
            'expires_at' => now()->addMinutes($expiryMinutes),
            'is_used' => false,
        ]);
    }

    /**
     * Verify OTP code
     */
    public static function verifyOTP($email, $code, $type = 'verification')
    {
        $otp = static::findValidOTP($email, $type);

        if (!$otp) {
            return false;
        }

        if ($otp->otp_code !== $code) {
            return false;
        }

        return $otp;
    }
}
