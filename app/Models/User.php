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
        'group_id',
        'system_role_id'
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

    // Системная роль (админ/наблюдатель)
    public function systemRole()
    {
        return $this->belongsTo(SystemRole::class);
    }

    // у пользователя много учетных записей
    public function eventAccounts()
    {
        return $this->hasMany(EventAccount::class);
    }

    // получить все мероприятия пользователя
    public function events()
    {
        return $this->hasManyThrough(Event::class, EventAccount::class, 'user_id', 'id', 'id', 'event_id');
    }

    public function isAdmin()
    {
        return $this->systemRole && $this->systemRole->name === 'admin';
    }
    
    public function isObserver()
    {
        return $this->systemRole && $this->systemRole->name === 'observer';
    }
    
    public function isSystemUser()
    {
        return !is_null($this->system_role_id);
    }
    
    public function hasSystemAccess()
    {
        return $this->isAdmin() || $this->isObserver();
    }

    // получить роль пользователя в конкретном мероприятии
    public function getRoleInEvent($eventId)
    {
        $account = $this->eventAccounts()
                        ->where('event_id', $eventId)
                        ->first();
        
        return $account ? $account->role : null;
    }

     public function scopeAdmins($query)
    {
        return $query->whereHas('systemRole', function($q) {
            $q->where('name', 'admin');
        });
    }
    
    public function scopeObservers($query)
    {
        return $query->whereHas('systemRole', function($q) {
            $q->where('name', 'observer');
        });
    }
    
    public function scopeRegularUsers($query)
    {
        return $query->whereNull('system_role_id');
    }
    
    // Проверить, есть ли у пользователя доступ к мероприятию
    public function hasAccessToEvent($eventId)
    {
        // Админы/наблюдатели имеют доступ ко всем мероприятиям
        if ($this->hasSystemAccess()) {
            return true;
        }
        
        // Обычные пользователи - только если есть event_account
        return $this->eventAccounts()
                    ->where('event_id', $eventId)
                    ->exists();
    }
    
    // Получить все доступные мероприятия пользователя
    public function getAccessibleEvents()
    {
        // Админы/наблюдатели видят все мероприятия
        if ($this->hasSystemAccess()) {
            return Event::all();
        }
        
        // Обычные пользователи - только свои мероприятия
        return $this->events;
    }
}
