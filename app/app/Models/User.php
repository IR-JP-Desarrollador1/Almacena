<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
        'must_change_password',
        'department_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'session_version' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_user_id');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class)->withTimestamps();
    }

    public function hasGlobalWarehouseAccess(): bool
    {
        return $this->hasPermission('companies.manage');
    }

    public function accessibleWarehouses(): Builder
    {
        $companyId = Company::query()->where('singleton_key', 'primary')->value('id');

        return Warehouse::query()
            ->where('is_active', true)
            ->whereHas('branch', fn (Builder $query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->when(
                ! $this->hasGlobalWarehouseAccess(),
                fn (Builder $query) => $query->whereHas(
                    'users',
                    fn (Builder $users) => $users->whereKey($this->id),
                ),
            );
    }

    public function canAccessWarehouse(int $warehouseId): bool
    {
        return $this->accessibleWarehouses()->whereKey($warehouseId)->exists();
    }

    public function canTransferBetweenWarehouses(int $originWarehouseId, int $destinationWarehouseId): bool
    {
        return $originWarehouseId !== $destinationWarehouseId
            && $this->canAccessWarehouse($originWarehouseId)
            && $this->canAccessWarehouse($destinationWarehouseId);
    }

    public function requestedRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'requester_user_id');
    }

    public function assignedRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'approver_user_id');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }
}
