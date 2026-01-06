<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * атрибуты, которые можно массово присваивать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'last_name',
        'first_name', 
        'middle_name',
        'passport_data',
        'birth_date',
        'group_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     * трибуты, которые должны быть скрыты для сериализации. Поля которые скрывать в API
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'passport_data'
    ];

    // /**
    //  * The attributes that should be cast.
    //  * Атрибуты, которые должны быть приведены в действие.
    //  *
    //  * @var array<string, string>
    //  */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    //     'password' => 'hashed',
    // ];

    // у пользователя одна группа
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // у пользователя много учетных записей
    public function eventAccounts()
    {
        return $this->hasMany(EventAccount::class, 'user_id');
    }

    // Атрибут для статуса «системный»
    protected $appends = ['is_system_account', 'system_role'];

    //получить все аккаунты с системной ролью
    public function getIsSystemAccountAttribute()
    {
        return $this->eventAccounts()
            ->join('roles', 'event_accounts.role_id', '=', 'roles.id')
            ->where('roles.system_role', true)
            ->exists();
    }

    public function getSystemRoleAttribute()
    {
        $systemAccount = $this->eventAccounts()
            ->whereHas('role', function ($query) {
                $query->where('system_role', true);
            })
            ->with('role')
            ->first();
        
        return $systemAccount ? $systemAccount->role : null;
    }

    // получить все мероприятия пользователя
    public function events()
    {
        return $this->hasManyThrough(Event::class, EventAccount::class, 'user_id', 'id', 'id', 'event_id');
    }

    // получить роль пользователя в конкретном мероприятии
    public function getRoleInEvent($eventId)
    {
        $account = $this->eventAccounts()
                        ->where('event_id', $eventId)
                        ->first();
        
        return $account ? $account->role : null;
    }
}
