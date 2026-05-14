<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Enums\WebhookStatus;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'event',
        'payload',
        'status',
        'attempts',
        'next_retry_at',
        'delivered_at',
        'last_response',
    ];

    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'status' => WebhookStatus::class,
            'payload' => 'array',
            'last_response' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
