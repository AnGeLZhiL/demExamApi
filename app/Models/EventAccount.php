<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventAccount extends Model
{
    use HasFactory;

    // Поля которые можно массово заполнять
    protected $fillable = [
        'user_id', 'event_id', 'login', 'password', 'password_plain', 'seat_number', 'role_id'
    ];

    //Поля которые скрывать в API
    protected $hidden = [
        'password'
    ];

    protected $appends = [
        'has_password'
    ];

    // 🔴 ВАЖНО: При маппинге в массив
    public function toArray()
    {
        $array = parent::toArray();
        
        // Отправляем сырой пароль вместо хэша
        if (isset($array['password_plain'])) {
            $array['password'] = $array['password_plain'];
        }
        
        // Удаляем password_plain из ответа (опционально)
        unset($array['password_plain']);
        
        return $array;
    }

    // Геттер для проверки наличия пароля
    public function getHasPasswordAttribute()
    {
        return !empty($this->password_plain);
    }

    // 🔴 Метод для безопасного получения пароля (с проверкой прав)
    public function getPasswordForDisplay()
    {
        // Здесь можно добавить логику проверки прав
        return $this->password_plain;
    }

    // учетная запись имеет одного пользователя
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // учетная запись привязана к мероприятию  
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    //роль привязана к учетной записи
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // учетная запись имеет много БД
    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    // учетная запись имеет много репозиториев
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }
}
