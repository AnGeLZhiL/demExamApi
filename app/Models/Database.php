<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    use HasFactory;

    //атрибуты, которые можно массово присваивать.
    protected $fillable = [
        'name', 'server_id', 'type_id', 'event_account_id', 'module_id', 'is_active', 'is_public'
    ];

    // БД относится к одному серверу
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    // БД имеет один тип
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // БД относится к аккаунту
    public function eventAccount()
    {
        return $this->belongsTo(EventAccount::class);
    }

    // БД относится к модулю
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // публичный БД модуль (от админа для всех)
    public function publicDatabases($moduleId)
    {
        return $this->where('module_id', $moduleId)
                    ->where('is_public', true)
                    ->get();
    }
}
