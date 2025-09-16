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
}
