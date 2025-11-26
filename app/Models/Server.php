<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    //атрибуты, которые можно массово присваивать
    protected $fillable = [
        'name', 'type_id', 'url', 'port', 'api_token', 'is_active'
    ];

    // Поля которые скрывать в API
    protected $hidden = [
        'api_token'
    ];

    // сервер имеет тип
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // у сервера много репозиториев
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }

    // у сервера много БД
    public function databases()
    {
        return $this->hasMany(Database::class);
    }
}
