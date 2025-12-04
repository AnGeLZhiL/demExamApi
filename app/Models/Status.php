<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // Один статус имеет много событий
    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
