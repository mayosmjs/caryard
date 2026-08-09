<?php namespace Majos\Conference\Models;

use Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * VerificationCode Model
 *
 * Stores email verification codes with expiration for paper submissions.
 * Each code expires after 15 minutes and can only be used once.
 */
class VerificationCode extends Model
{
    public $table = 'majos_conference_verification_codes';

    protected $dates = ['expires_at', 'used_at'];

    protected $fillable = ['email', 'code', 'expires_at'];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only unused, non-expired codes.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                     ->where('expires_at', '>', Carbon::now());
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Invalidate all previous codes for this email and create a fresh one.
     */
    public static function generate(string $email): self
    {
        // Remove all previous unused codes for this email
        static::where('email', $email)->whereNull('used_at')->delete();

        $code = strtoupper(Str::random(6));

        return static::create([
            'email'      => $email,
            'code'       => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);
    }

    /**
     * Find a valid (unused + non-expired) code for the given email and code string.
     */
    public static function findValid(string $email, string $code): ?self
    {
        return static::valid()
                     ->where('email', $email)
                     ->where('code', strtoupper($code))
                     ->first();
    }

    // -------------------------------------------------------------------------
    // Instance helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    public function markUsed(): void
    {
        $this->used_at = Carbon::now();
        $this->save();
    }

    /**
     * How many minutes remain until this code expires (0 if already expired).
     */
    public function minutesRemaining(): int
    {
        $diff = Carbon::now()->diffInMinutes($this->expires_at, false);
        return max(0, (int) $diff);
    }
}
