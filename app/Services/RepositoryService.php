<?php

namespace App\Services;

use App\Models\Repository;
use App\Models\EventAccount;
use App\Models\Module;
use App\Models\Role;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RepositoryService
{
    // Создать репозитории для всех участников модуля
    public function createRepositoriesForModule($moduleId)
    {
        $module = Module::findOrFail($moduleId);
        
        if (!$module->event_id) {
            throw new \Exception('Модуль не привязан к мероприятию');
        }
        
        // Вариант 1: Получаем ID роли "Участник"
        $participantRoleId = Role::where('name', 'Участник')->value('id');
        
        if (!$participantRoleId) {
            throw new \Exception('Роль "Участник" не найдена в системе');
        }
        
        // Получаем участников мероприятия с ролью "Участник"
        $participants = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRoleId) // Используем ID роли
            ->with(['user'])
            ->get();
        
        if ($participants->isEmpty()) {
            // Давайте получим отладочную информацию
            $debugInfo = $this->getDebugInfo($module);
            throw new \Exception(
                'В мероприятии нет участников с ролью "Участник". ' .
                'Всего EventAccounts: ' . $debugInfo['total_accounts'] . '. ' .
                'Распределение по ролям: ' . json_encode($debugInfo['roles_distribution'])
            );
        }
        
        $results = [
            'total' => $participants->count(),
            'successful' => 0,
            'failed' => 0,
            'repositories' => []
        ];
        
        foreach ($participants as $participant) {
            try {
                // Проверяем, не существует ли уже репозиторий
                $existingRepository = Repository::where('module_id', $moduleId)
                    ->where('event_account_id', $participant->id)
                    ->first();
                
                if ($existingRepository) {
                    throw new \Exception("Репозиторий для участника уже существует (ID: {$existingRepository->id})");
                }
                
                // Создаем репозиторий
                $repository = $this->createRepository($module, $participant);
                
                $results['successful']++;
                $results['repositories'][] = [
                    'success' => true,
                    'repository_id' => $repository->id,
                    'repository_name' => $repository->name,
                    'repository_url' => $repository->url,
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Неизвестно',
                    'mock_gogs' => true
                ];
                
                Log::info('Создан репозиторий для участника', [
                    'module_id' => $moduleId,
                    'participant_id' => $participant->id,
                    'repository_id' => $repository->id
                ]);
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['repositories'][] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Неизвестно'
                ];
                
                Log::error('Ошибка создания репозитория', [
                    'module_id' => $moduleId,
                    'event_id' => $module->event_id,
                    'participant_id' => $participant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Отладочная информация
     */
    private function getDebugInfo(Module $module)
    {
        $allAccounts = EventAccount::where('event_id', $module->event_id)
            ->with(['role'])
            ->get();
        
        $rolesDistribution = [];
        foreach ($allAccounts as $account) {
            $roleName = $account->role->name ?? 'Unknown';
            $rolesDistribution[$roleName] = ($rolesDistribution[$roleName] ?? 0) + 1;
        }
        
        return [
            'total_accounts' => $allAccounts->count(),
            'roles_distribution' => $rolesDistribution,
            'event_id' => $module->event_id,
            'module_id' => $module->id
        ];
    }
    
    /**
     * Создать репозиторий для участника
     */
    private function createRepository(Module $module, EventAccount $participant)
    {
        $repoName = $this->generateRepositoryName($module, $participant);
        $repoUrl = $this->generateMockGogsUrl($repoName);
        
        $user = $participant->user;
        $participantName = $user->name ?? $user->email ?? 'Участник';
        
        // Получаем ID статуса "Активен" для репозиториев
        $activeStatusId = $this->getActiveStatusId();
        
        // Получаем сервер Gogs
        $serverId = $this->getGogsServerId();
        
        // Получаем тип репозитория
        $typeId = $this->getRepositoryTypeId();
        
        // Используем метод из модели или создаем напрямую
        $repository = Repository::create([
            'name' => $repoName,
            'url' => $repoUrl,
            'description' => "Репозиторий для участника {$participantName} в модуле '{$module->name}'",
            'server_id' => $serverId,
            'type_id' => $typeId,
            'event_account_id' => $participant->id,
            'module_id' => $module->id,
            'status_id' => $activeStatusId,
            'is_active' => true,
            'gogs_repo_id' => rand(1000, 9999),
            'ssh_url' => "git@mock-gogs.local:admin/{$repoName}.git",
            'clone_url' => "https://mock-gogs.local/admin/{$repoName}.git",
            'metadata' => [
                'created_in_mock' => true,
                'created_at' => now()->toISOString(),
                'module_name' => $module->name,
                'participant_name' => $participantName,
                'participant_email' => $user->email ?? null,
                'event_name' => $module->event->name ?? 'Unknown'
            ]
        ]);
        
        return $repository;
    }

    /**
     * Получить ID сервера Gogs
     */
    private function getGogsServerId()
    {
        $gogsType = \App\Models\Type::where('name', 'Gogs')
            ->whereHas('context', function($q) {
                $q->where('name', 'server');
            })->first();
        
        if (!$gogsType) {
            throw new \Exception('Тип сервера "Gogs" не найден');
        }
        
        $server = \App\Models\Server::where('type_id', $gogsType->id)
            ->where('is_active', true)
            ->first();
        
        if (!$server) {
            throw new \Exception('Активный Gogs сервер не найден');
        }
        
        return $server->id;
    }

    /**
     * Получить ID типа репозитория
     */
    private function getRepositoryTypeId()
    {
        $repoType = \App\Models\Type::where('name', 'Рабочий')
            ->whereHas('context', function($q) {
                $q->where('name', 'repository');
            })->first();
        
        if (!$repoType) {
            // Если нет типа "Рабочий", берем первый доступный
            $context = \App\Models\Context::where('name', 'repository')->first();
            $repoType = \App\Models\Type::where('context_id', $context->id)->first();
        }
        
        if (!$repoType) {
            throw new \Exception('Тип репозитория не найден');
        }
        
        return $repoType->id;
    }

    /**
     * Получить ID статуса "Активен" для репозиториев
     */
    private function getActiveStatusId()
    {
        // Находим контекст "repository"
        $repositoryContext = \App\Models\Context::where('name', 'repository')->first();
        
        if (!$repositoryContext) {
            throw new \Exception('Контекст "repository" не найден');
        }
        
        // Находим статус "Активен" для этого контекста
        $activeStatus = \App\Models\Status::where('name', 'Активен')
            ->where('context_id', $repositoryContext->id)
            ->first();
        
        if (!$activeStatus) {
            throw new \Exception('Статус "Активен" для репозиториев не найден');
        }
        
        return $activeStatus->id;
    }

    /**
     * Получить ID статуса "Отключен" для репозиториев
     */
    private function getInactiveStatusId()
    {
        $repositoryContext = \App\Models\Context::where('name', 'repository')->first();
        
        $inactiveStatus = \App\Models\Status::where('name', 'Отключен')
            ->where('context_id', $repositoryContext->id)
            ->first();
        
        return $inactiveStatus->id ?? null;
    }
    
    /**
     * Генерация имени репозитория
     */
    private function generateRepositoryName(Module $module, EventAccount $participant)
    {
        // Формируем безопасное имя
        $moduleSlug = $this->slugify($module->name);
        $participantSlug = $this->slugify($participant->user->name ?? 'participant');
        
        // Добавляем уникальный идентификатор
        $uniqueId = substr(md5($module->id . $participant->id . time()), 0, 6);
        
        return "module-{$moduleSlug}-{$participantSlug}-{$uniqueId}";
    }
    
    /**
     * Генерация URL для mock Gogs
     */
    private function generateMockGogsUrl($repoName)
    {
        $baseUrl = config('services.gogs.mock_url', 'http://localhost:3000');
        return "{$baseUrl}/admin/{$repoName}";
    }
    
    /**
     * Преобразование строки в slug
     */
    private function slugify($string)
    {
        if (empty($string)) {
            return 'unknown';
        }
        
        // Транслитерация русских букв
        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];
        
        $string = mb_strtolower($string, 'UTF-8');
        $string = strtr($string, $transliteration);
        
        return preg_replace('/[^a-z0-9]/', '-', trim($string));
    }
    
    /**
     * Получить репозитории модуля
     */
    public function getModuleRepositories($moduleId)
    {
        try {
            $repositories = Repository::with([
                'eventAccount.user',
                'module',
                'server',
                'type',
                'status'
            ])
            ->where('module_id', $moduleId)
            ->orderBy('created_at', 'desc')
            ->get();
            
            return $repositories->map(function($repo) {
                // Безопасное получение данных
                $statusName = null;
                if ($repo->status && is_object($repo->status)) {
                    $statusName = $repo->status->name;
                } elseif (is_string($repo->status)) {
                    $statusName = $repo->status;
                }
                
                $moduleName = null;
                if ($repo->module && is_object($repo->module)) {
                    $moduleName = $repo->module->name;
                }
                
                $participantData = null;
                if ($repo->eventAccount && is_object($repo->eventAccount)) {
                    $user = $repo->eventAccount->user ?? null;
                    $participantData = [
                        'id' => $repo->eventAccount->user_id,
                        'name' => $user && is_object($user) 
                            ? ($user->full_name ?? $user->name ?? 'Участник') 
                            : 'Участник',
                        'login' => $repo->eventAccount->login,
                        'seat_number' => $repo->eventAccount->seat_number,
                        'role_id' => $repo->eventAccount->role_id
                    ];
                }
                
                // МАППИНГ СТАТУСОВ ДЛЯ ФРОНТЕНДА
                $frontendStatus = $this->mapStatusForFrontend($repo);
                
                return [
                    'id' => $repo->id,
                    'name' => $repo->name,
                    'description' => $repo->description,
                    'url' => $repo->url,
                    'ssh_url' => $repo->ssh_url,
                    'clone_url' => $repo->clone_url,
                    'status' => $frontendStatus, // Используем маппированный статус
                    'original_status' => $statusName, // Оригинальный статус для отладки
                    'status_id' => $repo->status_id,
                    'is_active' => $repo->is_active,
                    'gogs_repo_id' => $repo->gogs_repo_id,
                    'participant' => $participantData,
                    'module' => $moduleName,
                    'module_id' => $repo->module_id,
                    'created_at' => $repo->created_at ? $repo->created_at->format('d.m.Y H:i') : null,
                    'created_at_full' => $repo->created_at,
                    'updated_at' => $repo->updated_at,
                    'metadata' => $repo->metadata,
                ];
            });
            
        } catch (\Exception $e) {
            \Log::error('Error in getModuleRepositories: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Маппинг статусов для фронтенда
     */
    private function mapStatusForFrontend($repository)
    {
        if (!$repository->status) {
            return 'pending';
        }
        
        $statusName = $repository->status->name;
        
        // Основной маппинг
        if ($statusName === 'Активен') {
            return $repository->is_active ? 'active' : 'disabled';
        }
        
        if ($statusName === 'Отключен') {
            return 'disabled';
        }
        
        // Дополнительные статусы если будут
        $mapping = [
            'Создается' => 'pending',
            'Ожидает' => 'pending',
            'Ошибка' => 'error',
            'Архивный' => 'disabled',
        ];
        
        return $mapping[$statusName] ?? strtolower($statusName);
    }

    /**
     * Определить текст статуса для фронтенда
     */
    private function determineStatusText($repository)
    {
        // Если у репозитория нет статуса
        if (!$repository->status) {
            return 'unknown';
        }
        
        // Маппинг наших статусов на то, что ожидает фронтенд
        $statusMapping = [
            'Активен' => 'active',    // или 'ready'
            'Отключен' => 'disabled',
            'Создается' => 'pending', // или 'creating'
            'Ошибка' => 'error',
        ];
        
        $statusName = $repository->status->name;
        
        // Возвращаем маппированное значение или оригинальное
        return $statusMapping[$statusName] ?? strtolower($statusName);
    }
}