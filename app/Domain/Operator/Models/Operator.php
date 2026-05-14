<?php

declare(strict_types=1);

namespace App\Domain\Operator\Models;

use App\Domain\Operator\Enums\OperatorStatus;
use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    /** @return HasMany<OperatorApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(OperatorApiKey::class);
    }

    public function isActive(): bool
    {
        return $this->status->canCallApi();
    }
}
