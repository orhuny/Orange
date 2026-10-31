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
        $origins = is_array($config) ? (array) ($config['embed_origins'] ?? []) : [];

        return array_values(array_filter($origins, fn (mixed $o): bool => is_string($o)
            && preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#i', $o) === 1));
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
