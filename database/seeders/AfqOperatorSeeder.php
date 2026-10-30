<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use Illuminate\Database\Seeder;

/**
 * Provisions the afq operator (afq-casino front-end + afq-integrations back-end,
 * game shown as "DeepDive").
 *
 * The id and secret are fixed so they can be mirrored 1:1 in
 * afq-integrations config/orange.php (operator_id / api_secret) — keep them in sync.
 * Players are created on Orange by BetPlacementService on their first bet,
 * external id format "{client_id}*{user_id}".
 *
 *   php artisan db:seed --class=AfqOperatorSeeder
 */
class AfqOperatorSeeder extends Seeder
{
    private const OPERATOR_ID = '01a18d34-0474-7364-a50c-d9dbf112f352';

    private const SECRET = 'fb4c3c723ad2dd07d3f90e0862e2a0783066c011fb44a8db9500be59e0dc3011';

    // afq-integrations wallet callbacks: Orange appends /wallet/{debit|credit|rollback}.
    // Editable later from the Filament admin (Operators → callback_base_url).
    private const CALLBACK_BASE_URL = 'https://dd.cysplatform.com/dd';

    public function run(): void
    {
        $operator = Operator::withTrashed()->find(self::OPERATOR_ID)
            ?? Operator::withTrashed()->where('slug', 'afq')->first()
            ?? new Operator;

        $operator->forceFill([
            'id' => self::OPERATOR_ID,
            'name' => 'AFQ (DeepDive)',
            'slug' => 'afq',
            'status' => OperatorStatus::Active,
            'callback_base_url' => self::CALLBACK_BASE_URL,
            'secret_key' => self::SECRET,
            'ip_whitelist' => [],
            'config' => [],
            'deleted_at' => null,
        ])->save();

        $this->command?->info(sprintf('afq operator ready: id=%s slug=%s', $operator->id, $operator->slug));
    }
}
