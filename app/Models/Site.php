<?php

namespace App\Models;

use App\Enums\FindingStatus;
use App\Enums\ScanFrequency;
use App\Enums\VerificationMethod;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A website we monitor.
 *
 * @property int $id
 * @property int $team_id
 * @property int $client_id
 * @property string $url
 * @property string $host
 * @property string $verification_token
 * @property VerificationMethod|null $verification_method
 * @property Carbon|null $verified_at
 * @property Carbon|null $verification_checked_at
 * @property bool|null $is_laravel
 * @property int|null $score
 * @property string|null $grade
 * @property int|null $last_scan_id
 * @property Carbon|null $last_scanned_at
 * @property ScanFrequency $scan_frequency
 * @property string|null $agent_token_hash
 * @property string|null $agent_token_last_four
 * @property Carbon|null $agent_last_seen_at
 * @property Carbon|null $certificate_alerted_for
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Client $client
 * @property-read Collection<int, Scan> $scans
 * @property-read Scan|null $lastScan
 * @property-read Collection<int, Finding> $findings
 * @property-read Collection<int, DependencySnapshot> $dependencySnapshots
 * @property-read DependencySnapshot|null $latestSnapshot
 * @property-read Collection<int, PackageFinding> $packageFindings
 */
#[Fillable([
    'team_id', 'client_id', 'url', 'host', 'verification_token', 'verification_method', 'verified_at',
    'verification_checked_at', 'is_laravel', 'score', 'grade', 'last_scan_id', 'last_scanned_at',
    'scan_frequency', 'certificate_alerted_for',
])]
#[Hidden(['agent_token_hash', 'verification_token'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scan_frequency' => 'weekly',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->verification_token)) {
                $site->verification_token = static::newVerificationToken();
            }

            if (empty($site->host)) {
                $site->host = (string) parse_url($site->url, PHP_URL_HOST);
            }
        });
    }

    public static function newVerificationToken(): string
    {
        return 'lw-'.Str::lower(Str::random(32));
    }

    /**
     * Normalise user input like "Example.com/path" to "https://example.com".
     * Returns null when the input is not a usable http(s) URL.
     */
    public static function normalizeUrl(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (! preg_match('/^[a-z0-9.-]+$/', $host) && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * @return BelongsTo<Scan, $this>
     */
    public function lastScan(): BelongsTo
    {
        return $this->belongsTo(Scan::class, 'last_scan_id');
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function openFindings(): HasMany
    {
        return $this->findings()->where('status', FindingStatus::Open);
    }

    /**
     * @return HasMany<DependencySnapshot, $this>
     */
    public function dependencySnapshots(): HasMany
    {
        return $this->hasMany(DependencySnapshot::class);
    }

    /**
     * @return HasOne<DependencySnapshot, $this>
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(DependencySnapshot::class)->latestOfMany('reported_at');
    }

    /**
     * @return HasMany<PackageFinding, $this>
     */
    public function packageFindings(): HasMany
    {
        return $this->hasMany(PackageFinding::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function scheme(): string
    {
        return (string) parse_url($this->url, PHP_URL_SCHEME);
    }

    public function isHttps(): bool
    {
        return $this->scheme() === 'https';
    }

    /**
     * The domain used for email (SPF/DMARC) checks: the host without "www.".
     */
    public function emailDomain(): string
    {
        return (string) preg_replace('/^www\./', '', $this->host);
    }

    public function activeScan(): ?Scan
    {
        return $this->scans()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();
    }

    /**
     * Create a new agent API token. The plain token is returned once and only its hash is stored.
     */
    public function issueAgentToken(): string
    {
        $plain = 'lw_'.Str::random(40);

        $this->forceFill([
            'agent_token_hash' => hash('sha256', $plain),
            'agent_token_last_four' => substr($plain, -4),
        ])->save();

        return $plain;
    }

    public function revokeAgentToken(): void
    {
        $this->forceFill([
            'agent_token_hash' => null,
            'agent_token_last_four' => null,
        ])->save();
    }

    public static function findByAgentToken(string $plain): ?self
    {
        if ($plain === '' || ! str_starts_with($plain, 'lw_')) {
            return null;
        }

        return static::where('agent_token_hash', hash('sha256', $plain))->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_method' => VerificationMethod::class,
            'verified_at' => 'datetime',
            'verification_checked_at' => 'datetime',
            'is_laravel' => 'boolean',
            'score' => 'integer',
            'last_scanned_at' => 'datetime',
            'scan_frequency' => ScanFrequency::class,
            'agent_last_seen_at' => 'datetime',
            'certificate_alerted_for' => 'date',
        ];
    }
}
