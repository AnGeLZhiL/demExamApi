<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'context_id'];

    public function context()
    {
        return $this->belongsTo(Context::class);
    }

    // Один тип имеет много модулей
    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    // Один тип имеет много репозиториев
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }

    // Один тип имеет много серверов
    public function serveres()
    {
        return $this->hasMany(Server::class);
    }
}
