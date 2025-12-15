<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'context_id'];

    // Один статус имеет много событий
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    // Один статус имеет много БД
    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    // Один статус имеет много репозиториев
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }

    // Один статус имеет один контекст
    public function context()
    {
        return $this->belongsTo(Context::class);
    }
}
