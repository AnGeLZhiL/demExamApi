<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // Поля которые можно массово заполнять
    protected $fillable = [
        'name',
        'date', 
        'status_id'
    ];

    // у мероприятия один статус
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    // у мероприятия может быть много модулей
    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    // одно мероприятие может иметь много учетных записей (многие-ко-многим)
    public function eventAccounts()
    {
        return $this->hasMany(EventAccount::class);
    }

    // получить всех пользователей мероприятия
    public function users()
    {
        return $this->hasManyThrough(User::class, EventAccount::class, 'event_id', 'id', 'id', 'user_id');
    }
}
