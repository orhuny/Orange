<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Models;

use Database\Factories\RngSeedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RngSeed extends Model
{
    /** @use HasFactory<RngSeedFactory> */
    use HasFactory;

    protected $fillable = [
        'server_seed',
        'server_seed_hash',
        'rotated_at',
        'revealed_at',
        'used_round_count',
    ];

    protected $hidden = [
        'server_seed',
    ];

    protected function casts(): array
    {
        return [
            'server_seed' => 'encrypted',
            'rotated_at' => 'datetime',
            'revealed_at' => 'datetime',
            'used_round_count' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->rotated_at === null && $this->revealed_at === null;
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }

    /** @param  Builder<RngSeed>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('rotated_at')->whereNull('revealed_at');
    }
}
