<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventAccount extends Model
{
    use HasFactory;

    // Поля которые можно массово заполнять
    protected $fillable = [
        'user_id', 'event_id', 'login', 'password', 'seat_number'
    ];

    //Поля которые скрывать в API
    protected $hidden = [
        'password' 
    ];

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
