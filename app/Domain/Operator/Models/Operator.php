<?php

declare(strict_types=1);

namespace App\Domain\Operator\Models;

use App\Domain\Game\Models\OperatorGame;
use App\Domain\Operator\Enums\OperatorStatus;
use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Operator extends Model
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'callback_base_url',
        'secret_key',
        'ip_whitelist',
        'config',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperatorStatus::class,
            'secret_key' => 'encrypted',
            'ip_whitelist' => 'array',
            'config' => 'array',
        ];
    }

    /** @return HasMany<OperatorGame, $this> */
    public function operatorGames(): HasMany
    {
        return $this->hasMany(OperatorGame::class);
    }

    /** @return HasMany<OperatorApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(OperatorApiKey::class);
    }

    /**
     * Origins this operator embeds Orange's game and lobby pages from
     * (config.embed_origins), e.g. ["https://casino.example"].
     *
     * @return list<string>
     */
    public function embedOrigins(): array
    {
        $config = $this->getAttribute('config');
        $origins = is_array($config) ? ($config['embed_origins'] ?? []) : [];
        // Edited as a flat key/value pair in the panel: a comma-separated string, or a list.
        $origins = is_string($origins) ? explode(',', $origins) : (array) $origins;

        return array_values(array_filter(array_map(fn (mixed $o): mixed => is_string($o) ? trim($o) : $o, $origins), fn (mixed $o): bool => is_string($o)
            && preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#i', $o) === 1));
    }

    /**
     * How long a play session may go without a player request before it ends, in seconds;
     * null = never. config.session_idle_minutes (a whole number, at most a day) overrides
     * games.launch.idle_timeout_seconds; 0 turns it off, for operators that end sessions
     * themselves.
     */
    public function sessionIdleSeconds(): ?int
    {
        $config = $this->getAttribute('config');
        $minutes = is_array($config) ? ($config['session_idle_minutes'] ?? null) : null;
        // Edited as a flat key/value pair in the panel: a number or a numeric string.
        if (is_int($minutes) || (is_string($minutes) && ctype_digit(trim($minutes)))) {
            $minutes = (int) $minutes;

            return $minutes === 0 ? null : min($minutes, 24 * 60) * 60;
        }
        $default = (int) config('games.launch.idle_timeout_seconds', 15 * 60);

        return $default > 0 ? $default : null;
    }

    /**
     * The operator's lobby look, from config (lobby_logo_url, lobby_*_color). Only an
     * https logo URL and #rrggbb colours are passed on; anything else falls back.
     *
     * @return array{logo_url: string|null, primary: string, accent: string, background: string}
     */
    public function lobbyTheme(): array
    {
        $config = $this->getAttribute('config');
        $config = is_array($config) ? $config : [];
        $color = fn (string $key, string $default): string => is_string($config[$key] ?? null)
            && preg_match('/^#[0-9a-f]{6}$/i', $config[$key]) === 1 ? $config[$key] : $default;
        $logo = $config['lobby_logo_url'] ?? null;

        return [
            'logo_url' => is_string($logo) && filter_var($logo, FILTER_VALIDATE_URL) && str_starts_with($logo, 'https://') ? $logo : null,
            'primary' => $color('lobby_primary_color', '#ff8a00'),
            'accent' => $color('lobby_accent_color', '#22c55e'),
            'background' => $color('lobby_background_color', '#04121f'),
        ];
    }

    /**
     * Every active operator's embed origins (cached for a minute): a page nested in an
     * Orange host page must allow the operator's origin too, since frame-ancestors is
     * checked against every ancestor.
     *
     * @return list<string>
     */
    public static function allEmbedOrigins(): array
    {
        return Cache::remember('operators.embed-origins', 60, fn (): array => array_values(array_unique(
            self::query()->where('status', OperatorStatus::Active)->get(['id', 'config'])
                ->flatMap(fn (Operator $operator): array => $operator->embedOrigins())
                ->all(),
        )));
    }

    public function isActive(): bool
    {
        return $this->status->canCallApi();
    }
}
