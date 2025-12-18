<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'description', 'server_id', 'type_id', 
        'event_account_id', 'module_id', 'status_id', 'is_active',
        'gogs_repo_id', 'ssh_url', 'clone_url', 'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // УДАЛИТЬ константы STATUS_* если используем таблицу statuses
    
    // Отношения
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function eventAccount()
    {
        return $this->belongsTo(EventAccount::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // Accessor для удобного получения названия статуса
    public function getStatusNameAttribute()
    {
        return $this->status ? $this->status->name : $this->status;
    }

    public function getParticipantAttribute()
    {
        return $this->eventAccount->user ?? null;
    }

    public function getParticipantLoginAttribute()
    {
        return $this->eventAccount->login ?? null;
    }

    // Scope для активных репозиториев (is_active = true + статус "Активен")
    public function scopeActive($query)
    {
        // Находим ID статуса "Активен"
        $activeStatusId = Status::where('name', 'Активен')
            ->whereHas('context', function($q) {
                $q->where('name', 'repository');
            })->value('id');
            
        return $query->where('is_active', true)
                     ->where('status_id', $activeStatusId);
    }

    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForParticipant($query, $participantId)
    {
        return $query->whereHas('eventAccount', function($q) use ($participantId) {
            $q->where('user_id', $participantId);
        });
    }

    // Метод для создания репозитория
    public static function createForParticipant($moduleId, $eventAccount, $serverId = null, $typeId = null)
    {
        $module = Module::findOrFail($moduleId);
        
        // Генерируем имя
        $repoName = "module-{$module->name}-participant-{$eventAccount->id}-" . substr(md5(time()), 0, 6);
        $repoName = strtolower(preg_replace('/[^a-z0-9]/', '-', $repoName));
        
        // Получаем ID статуса "Активен"
        $activeStatusId = Status::where('name', 'Активен')
            ->whereHas('context', function($q) {
                $q->where('name', 'repository');
            })->value('id');
        
        // Для mock-режима
        $isMock = app()->environment('local', 'testing') || config('services.gogs.mock');
        
        if ($isMock) {
            $url = "http://localhost:3000/admin/{$repoName}";
            $sshUrl = "git@localhost:10022:admin/{$repoName}.git";
        } else {
            $url = config('services.gogs.url') . "/admin/{$repoName}";
            $sshUrl = config('services.gogs.ssh_url') . ":admin/{$repoName}.git";
        }
        
        return self::create([
            'name' => $repoName,
            'description' => "Репозиторий участника " . ($eventAccount->user->name ?? ''),
            'url' => $url,
            'ssh_url' => $sshUrl,
            'clone_url' => $url . '.git',
            'server_id' => $serverId,
            'type_id' => $typeId,
            'status_id' => $activeStatusId,
            'event_account_id' => $eventAccount->id,
            'module_id' => $moduleId,
            'is_active' => true,
            'gogs_repo_id' => $isMock ? rand(1000, 9999) : null,
            'metadata' => [
                'created_via' => 'auto',
                'participant_login' => $eventAccount->login,
                'participant_seat' => $eventAccount->seat_number,
                'mock' => $isMock,
                'created_at' => now()->toISOString()
            ]
        ]);
    }

    // Метод для массового создания
    public static function createForModuleParticipants($moduleId, $participants)
    {
        $created = [];
        
        foreach ($participants as $eventAccount) {
            $repo = self::createForParticipant($moduleId, $eventAccount);
            $created[] = $repo;
        }
        
        return $created;
    }
}