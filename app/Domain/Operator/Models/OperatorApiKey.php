<?php

declare(strict_types=1);

namespace App\Domain\Operator\Models;

use Database\Factories\OperatorApiKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorApiKey extends Model
{
    /** @use HasFactory<OperatorApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'key_id',
        'secret_hash',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /** @param  Builder<OperatorApiKey>  $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }
}
