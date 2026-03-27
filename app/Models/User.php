<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    protected $guard_name = 'web';
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    const ROLE_HIERARCHY = [
        'controlador'   => 1,
        'supervisor'    => 2,
        'administrador' => 3,
        'gerente'       => 4,
        'director'      => 5,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name','username','email','password',
        'document_type','document_number','phone','headquarter_id','status'
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function headquarter()
    {
        return $this->belongsTo(Headquarter::class);
    }


    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    public function departures()
    {
        return $this->hasMany(\App\Models\Departure::class);
    }

    public function debtDayDetails()
    {
        return $this->hasMany(\App\Models\DebtDayDetail::class);
    }

    public function incomes()
    {
        return $this->hasMany(\App\Models\Income::class);
    }

    public function headquarters() {
        return $this->belongsToMany(\App\Models\Headquarter::class)->withTimestamps()->withPivot('is_default');
    }

    public function users() {
        return $this->belongsToMany(\App\Models\User::class)->withTimestamps()->withPivot('is_default');
    }

    public function getAvatarUrlAttribute()
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=128&background=0D8ABC&color=fff';
    }

    public function getRoleLevel(): int
    {
        $roleName = $this->roles->first()?->name;
        return self::ROLE_HIERARCHY[$roleName] ?? 0;
    }

    public function isDirector(): bool
    {
        return $this->hasRole('director');
    }

    public function canManageUser(User $target): bool
    {
        if ($this->isDirector()) {
            return $this->getRoleLevel() >= $target->getRoleLevel();
        }
        return $this->getRoleLevel() > $target->getRoleLevel();
    }
}
