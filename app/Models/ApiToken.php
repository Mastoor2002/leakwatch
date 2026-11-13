<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A personal access token for the mobile app and the JSON API.
 *
 * Only the SHA-256 hash is stored, the same way agent tokens work, so a
 * database leak does not leak working credentials.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property string $last_four
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'token_hash', 'last_four', 'last_used_at', 'expires_at'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    public const PREFIX = 'lwu_';

    /**
     * Create a token for a user and return the plain-text value exactly once.
     *
     * @return array{token: string, model: self}
     */
    public static function issue(User $user, string $name, ?CarbonInterface $expiresAt = null): array
    {
        $plain = self::PREFIX.Str::random(48);

        $model = $user->apiTokens()->create([
            'name' => Str::limit($name, 80, ''),
            'token_hash' => self::hashToken($plain),
            'last_four' => substr($plain, -4),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plain, 'model' => $model];
    }

    public static function findByPlainToken(string $plain): ?self
    {
        if ($plain === '' || ! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        $token = self::query()->where('token_hash', self::hashToken($plain))->first();

        if ($token === null || $token->isExpired()) {
            return null;
        }

        return $token;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
