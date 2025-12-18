<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    use HasFactory;

    //атрибуты, которые можно массово присваивать.
    protected $fillable = [
        'name',
        'username',
        'password',
        'server_id',
        'type_id',
        'event_account_id',
        'module_id',
        'status_id',
        'is_active',
        'is_public',
        'has_demo_data',
        'is_empty',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'has_demo_data' => 'boolean',
        'is_empty' => 'boolean',
        'metadata' => 'array',
        'password' => 'encrypted', // Автоматическое шифрование пароля
    ];

    protected $appends = [
        'connection_info',
        'participant_info'
    ];

    // БД относится к одному серверу
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    //БД имеет один статус
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    // БД имеет один тип
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    // БД относится к аккаунту
    public function eventAccount()
    {
        return $this->belongsTo(EventAccount::class)->with('user');
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeEmpty($query)
    {
        return $query->where('is_empty', true);
    }

    public function scopeWithDemoData($query)
    {
        return $query->where('has_demo_data', true);
    }

    // Accessor для информации о подключении
    public function getConnectionInfoAttribute()
    {
        if (!$this->server) {
            return null;
        }

        return [
            'host' => $this->server->url ?? 'localhost',
            'port' => $this->server->port ?? 5432,
            'database' => $this->name,
            'username' => $this->username,
            'password' => '********', // Не показываем реальный пароль
            'connection_string' => $this->getConnectionString()
        ];
    }

    // Accessor для информации об участнике
    public function getParticipantInfoAttribute()
    {
        if (!$this->eventAccount) {
            return null;
        }

        return [
            'id' => $this->eventAccount->id,
            'login' => $this->eventAccount->login,
            'seat_number' => $this->eventAccount->seat_number,
            'user_name' => $this->eventAccount->user->name ?? null,
            'user_email' => $this->eventAccount->user->email ?? null
        ];
    }

    // Генерация строки подключения
    public function getConnectionString()
    {
        $host = $this->server->url ?? 'localhost';
        $port = $this->server->port ?? 5432;
        
        return [
            'psql' => "psql -h {$host} -p {$port} -U {$this->username} -d {$this->name}",
            'pgadmin' => "postgresql://{$this->username}:[PASSWORD]@{$host}:{$port}/{$this->name}",
            'pdo' => "pgsql:host={$host};port={$port};dbname={$this->name};user={$this->username}",
            'jdbc' => "jdbc:postgresql://{$host}:{$port}/{$this->name}?user={$this->username}"
        ];
    }

    // Проверка, существует ли реальная БД в PostgreSQL
    public function checkDatabaseExists()
    {
        try {
            $pdo = new \PDO(
                "pgsql:host=" . ($this->server->url ?? 'localhost') . ";port=" . ($this->server->port ?? 5432),
                env('DB_USERNAME', 'postgres'),
                env('DB_PASSWORD', '061241angel')
            );
            
            $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$this->name}'");
            return (bool) $stmt->fetch();
        } catch (\Exception $e) {
            return false;
        }
    }
}
