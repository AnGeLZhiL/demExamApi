<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    use HasFactory;

    // атрибуты, которые можно массово присваивать.
    protected $fillable = [
        'name', 'url', 'server_id', 'type_id', 'event_account_id', 'module_id', 'is_active', 'is_public'
    ];

    // репозиторий относится к одному серверу
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    // репозиторий имеет один тип
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // репозиторий имеет аккаунт
    public function eventAccount()
    {
        return $this->belongsTo(EventAccount::class);
    }

    // репозиторий относится к модулю
    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
