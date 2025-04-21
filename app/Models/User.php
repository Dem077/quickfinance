<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'avatar_url',
        'department_id',
        'password',
        'designation',
        'location_id',
        'bank_account_name',
        'bank_account_no',
        'hod_of',
        'is_hod',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return asset('storage/'.$this->avatar_url);
        } else {
            $hash = md5(strtolower(trim($this->email)));

            return 'https://www.gravatar.com/avatar/'.$hash.'?d=mp&r=g&s=250';
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }

    public function approved_by(): HasMany
    {
        return $this->HasMany(User::class, 'approved_canceled_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function advanceforms(): HasMany
    {
        return $this->hasMany(AdvanceForm::class, 'generated_by');  
    }

    public function budgetTransfer(): HasMany
    {
        return $this->hasMany(BudgetTransfer::class, 'user_id');
    }

    public function pettycashreimbursment(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'user_id');
    }  
    
    public function budgettransactionhistory(): HasMany
    {
        return $this->hasMany(BudgetTransactionHistory::class, 'transaction_by');
    }

    public function hodof(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'hod_of');
    }

    public function pettycashapprovedby(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'approved_by');
    }

    public function pettycashverifiedby(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'verified_by');
    }   

    public function hodapprovedby(): HasMany
    {
        return $this->hasMany(PurchaseRequests::class, 'approved_by_hod');
    }
    
}
