<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Context extends Model
{
    use HasFactory;

    /**
     * Поля, которые можно массово назначать
     */
    protected $fillable = ['name'];
    
    /**
     * Статусы, принадлежащие этому контексту
     */
    public function statuses()
    {
        return $this->hasMany(Status::class);
    }
    
    /**
     * Типы, принадлежащие этому контексту
     */
    public function types()
    {
        return $this->hasMany(Type::class);
    }
}
