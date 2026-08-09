<?php namespace Majos\Conference\Models;

use Model;
use Hash;
use Str;

/**
 * Reviewer Model
 *
 * Represents an external (non-backend) reviewer who accesses the abstract
 * review portal via a secure token link and a password.
 */
class Reviewer extends Model
{
    public $table = 'majos_conference_reviewers';

    protected $fillable = [
        'name',
        'email',
        'token',
        'password_hash',
        'token_expires_at',
        'last_login_at',
    ];

    protected $hidden = ['password_hash', 'token'];

    protected $dates = [
        'token_expires_at',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    public $hasMany = [
        'submissions' => [
            'Majos\Conference\Models\Submission',
            'key' => 'reviewer_id',
        ],
    ];

    // ── Static finders ────────────────────────────────────────────────────

    /**
     * Find an active (non-expired) reviewer by token.
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where(fn ($q) => $q->whereNull('token_expires_at')
                                 ->orWhere('token_expires_at', '>', now()))
            ->first();
    }

    // ── Credential generation ─────────────────────────────────────────────

    /**
     * Generate a fresh token + password for this reviewer.
     *
     * Saves token, password_hash, and token_expires_at on the model instance
     * (does NOT persist — call save() after).
     *
     * @return string The plain-text password to include in the assignment email.
     */
    public function generateCredentials(int $expiryDays = 90): string
    {
        $this->token            = Str::random(64);
        $this->token_expires_at = now()->addDays($expiryDays);

        // Human-readable password: e.g. "ABX-5821-KQZ"
        $plainPassword       = strtoupper(Str::random(3))
                             . '-' . random_int(1000, 9999)
                             . '-' . strtoupper(Str::random(3));
        $this->password_hash = Hash::make($plainPassword);

        return $plainPassword;
    }

    /**
     * Verify a plain-text password against the stored hash.
     */
    public function verifyPassword(string $plain): bool
    {
        return Hash::check($plain, $this->password_hash);
    }
}
