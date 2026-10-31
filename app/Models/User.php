<?php

namespace App\Models;

use App\Domain\Operator\Models\Operator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Admin-panel account. operator_id null = platform staff; set = an operator's
 * staff, limited to that operator's data (see App\Support\AccessControl).
 *
 * @property string|null $operator_id
 * @property bool $is_active
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'operator_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function isOperatorUser(): bool
    {
        return $this->operator_id !== null;
    }

    /**
     * Role-based: an active account with at least one role; operator users also
     * need their operator to still exist. What they see inside is decided by
     * the role's permissions (and, for operator users, their operator).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active || $this->roles->isEmpty()) {
            return false;
        }

        return ! $this->isOperatorUser() || $this->operator()->exists();
    }
}
