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
        'role_id',
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

    // у пользователя одна роль
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // у пользователя одна группа
    public function group()
    {
        return $this->belongsTo(Group::class);
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
}
