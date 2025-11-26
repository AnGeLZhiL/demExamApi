<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'path', 'size', 'mime_type', 
        'module_id', 'event_account_id', 'is_public'
    ];

    // файл относится к модулю
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // файл относится к учетной записи
    public function eventAccount()
    {
        return $this->belongsTo(EventAccount::class);
    }

    // файл относится к пользователю (кто загрузил)
    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            EventAccount::class, 
            'id', 
            'id',
            'event_account_id',
            'user_id'
        );
    }
}
