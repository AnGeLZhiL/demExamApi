<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    //атрибуты, которые можно массово присваивать.
    protected $fillable = [
        'name', 'event_id', 'type_id', 'status_id'
    ];

    // модуль относится к одному  мероприятию
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // модуль имеет один тип
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // модуль имеет один статус
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    // модуль имеет много репозиториев
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }

    // модуль имеет много БД
    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    // модуль имеет много файлов
    public function files()
    {
        return $this->hasMany(File::class);
    }
}
