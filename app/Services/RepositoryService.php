<?php

namespace App\Services;

use App\Models\Repository;
use App\Models\EventAccount;
use App\Models\Module;
use App\Models\Role;
use App\Models\Server;
use App\Models\Type;
use App\Models\Context;
use App\Models\Status;
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
        
        // Получаем ID роли "Участник"
        $participantRoleId = Role::where('name', 'Участник')->value('id');
        
        if (!$participantRoleId) {
            throw new \Exception('Роль "Участник" не найдена в системе');
        }
        
        // Получаем участников мероприятия с ролью "Участник"
        $participants = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRoleId)
            ->with(['user'])
            ->get();
        
        if ($participants->isEmpty()) {
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
        
        // Используем GogsService если настроен
        $useRealGogs = !config('services.gogs.mock', true);
        $gogsService = $useRealGogs ? new GogsService() : null;
        
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
                if ($useRealGogs && $gogsService) {
                    $repository = $this->createRealGogsRepository($module, $participant, $gogsService);
                } else {
                    $repository = $this->createMockRepository($module, $participant);
                }
                
                $results['successful']++;
                $results['repositories'][] = [
                    'success' => true,
                    'repository_id' => $repository->id,
                    'repository_name' => $repository->name,
                    'repository_url' => $repository->url,
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Неизвестно',
                    'mock_gogs' => !$useRealGogs,
                    'gogs_username' => $repository->metadata['gogs_username'] ?? null,
                    'gogs_password' => $repository->metadata['gogs_password'] ?? null,
                ];
                
                Log::info('Создан репозиторий для участника', [
                    'module_id' => $moduleId,
                    'participant_id' => $participant->id,
                    'repository_id' => $repository->id,
                    'real_gogs' => $useRealGogs
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
     * Создать реальный репозиторий в Gogs
     */
    private function createRealGogsRepository(Module $module, EventAccount $participant, GogsService $gogsService)
    {
        $user = $participant->user;
        $participantName = $user->name ?? $user->email ?? 'Участник';
        
        // Генерируем уникальные имена
        $username = 'student-' . $module->id . '-' . $participant->id;
        $repoName = 'exam-module-' . $module->id . '-' . $participant->id;
        
        // 1. Создаем пользователя в Gogs
        $userResult = $gogsService->createUser(
            $username,
            $participantName,
            $user->email ?? ($username . '@exam.local')
        );
        
        // 2. Создаем репозиторий
        $repoResult = $gogsService->createRepository(
            'adminangelina', // создаем под админом
            $repoName,
            "Экзаменационный репозиторий для {$participantName} в модуле '{$module->name}'"
        );
        
        // 3. Сохраняем в базу данных
        $repository = Repository::create([
            'name' => $repoName,
            'url' => $repoResult['web_url'],
            'description' => $repoResult['repository']['description'],
            'server_id' => $this->getGogsServerId(),
            'type_id' => $this->getRepositoryTypeId(),
            'event_account_id' => $participant->id,
            'module_id' => $module->id,
            'status_id' => $this->getActiveStatusId(),
            'is_active' => true,
            'gogs_repo_id' => $repoResult['repository']['id'],
            'ssh_url' => null,
            'clone_url' => $repoResult['clone_url'],
            'metadata' => [
                'created_in_gogs' => true,
                'gogs_username' => $username,
                'gogs_password' => $userResult['password'],
                'gogs_data' => $repoResult['repository'],
                'participant_info' => [
                    'name' => $participantName,
                    'email' => $user->email ?? null,
                ],
                'created_at' => now()->toISOString(),
            ]
        ]);
        
        return $repository;
    }
    
    /**
     * Создать mock-репозиторий (для тестов)
     */
    private function createMockRepository(Module $module, EventAccount $participant)
    {
        $repoName = $this->generateRepositoryName($module, $participant);
        $repoUrl = $this->generateMockGogsUrl($repoName);
        
        $user = $participant->user;
        $participantName = $user->name ?? $user->email ?? 'Участник';
        
        $repository = Repository::create([
            'name' => $repoName,
            'url' => $repoUrl,
            'description' => "Репозиторий для участника {$participantName} в модуле '{$module->name}'",
            'server_id' => $this->getGogsServerId(),
            'type_id' => $this->getRepositoryTypeId(),
            'event_account_id' => $participant->id,
            'module_id' => $module->id,
            'status_id' => $this->getActiveStatusId(),
            'is_active' => true,
            'gogs_repo_id' => rand(1000, 9999),
            'ssh_url' => null,
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
                
                // Метаданные из Gogs
                $metadata = $repo->metadata ?? [];
                $isRealGogs = $metadata['created_in_gogs'] ?? false;
                $gogsUsername = $metadata['gogs_username'] ?? null;
                $gogsPassword = $metadata['gogs_password'] ?? null;
                
                // Статус для фронтенда
                $frontendStatus = $this->mapStatusForFrontend($repo);
                
                return [
                    'id' => $repo->id,
                    'name' => $repo->name,
                    'description' => $repo->description,
                    'url' => $repo->url,
                    'ssh_url' => $repo->ssh_url,
                    'clone_url' => $repo->clone_url,
                    'status' => $frontendStatus,
                    'original_status' => $statusName,
                    'status_id' => $repo->status_id,
                    'is_active' => $repo->is_active,
                    'gogs_repo_id' => $repo->gogs_repo_id,
                    'participant' => $participantData,
                    'module' => $moduleName,
                    'module_id' => $repo->module_id,
                    'created_at' => $repo->created_at ? $repo->created_at->format('d.m.Y H:i') : null,
                    'created_at_full' => $repo->created_at,
                    'updated_at' => $repo->updated_at,
                    'metadata' => $metadata,
                    'is_real_gogs' => $isRealGogs,
                    'gogs_credentials' => $gogsUsername ? [
                        'username' => $gogsUsername,
                        'password' => $gogsPassword ? '********' : null,
                        'has_password' => !empty($gogsPassword)
                    ] : null,
                ];
            });
            
        } catch (\Exception $e) {
            Log::error('Error in getModuleRepositories: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    // Остальные методы (getGogsServerId, getRepositoryTypeId, getActiveStatusId, 
    // generateRepositoryName, generateMockGogsUrl, slugify, mapStatusForFrontend)
    // остаются как в вашем исходном коде
    
    private function getGogsServerId()
    {
        $gogsType = Type::where('name', 'Gogs')
            ->whereHas('context', function($q) {
                $q->where('name', 'server');
            })->first();
        
        if (!$gogsType) {
            throw new \Exception('Тип сервера "Gogs" не найден');
        }
        
        $server = Server::where('type_id', $gogsType->id)
            ->where('is_active', true)
            ->first();
        
        if (!$server) {
            throw new \Exception('Активный Gogs сервер не найден');
        }
        
        return $server->id;
    }
    
    private function getRepositoryTypeId()
    {
        $repoType = Type::where('name', 'Рабочий')
            ->whereHas('context', function($q) {
                $q->where('name', 'repository');
            })->first();
        
        if (!$repoType) {
            $context = Context::where('name', 'repository')->first();
            $repoType = Type::where('context_id', $context->id)->first();
        }
        
        if (!$repoType) {
            throw new \Exception('Тип репозитория не найден');
        }
        
        return $repoType->id;
    }
    
    private function getActiveStatusId()
    {
        $repositoryContext = Context::where('name', 'repository')->first();
        
        if (!$repositoryContext) {
            throw new \Exception('Контекст "repository" не найден');
        }
        
        $activeStatus = Status::where('name', 'Активен')
            ->where('context_id', $repositoryContext->id)
            ->first();
        
        if (!$activeStatus) {
            throw new \Exception('Статус "Активен" для репозиториев не найден');
        }
        
        return $activeStatus->id;
    }
    
    private function generateRepositoryName(Module $module, EventAccount $participant)
    {
        $moduleSlug = $this->slugify($module->name);
        $participantSlug = $this->slugify($participant->user->name ?? 'participant');
        $uniqueId = substr(md5($module->id . $participant->id . time()), 0, 6);
        
        return "module-{$moduleSlug}-{$participantSlug}-{$uniqueId}";
    }
    
    private function generateMockGogsUrl($repoName)
    {
        $baseUrl = config('services.gogs.mock_url', 'http://localhost:3000');
        return "{$baseUrl}/admin/{$repoName}";
    }
    
    private function slugify($string)
    {
        if (empty($string)) return 'unknown';
        
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
    
    private function mapStatusForFrontend($repository)
    {
        if (!$repository->status) {
            return 'pending';
        }
        
        $statusName = $repository->status->name;
        
        if ($statusName === 'Активен') {
            return $repository->is_active ? 'active' : 'disabled';
        }
        
        if ($statusName === 'Отключен') {
            return 'disabled';
        }
        
        $mapping = [
            'Создается' => 'pending',
            'Ожидает' => 'pending',
            'Ошибка' => 'error',
            'Архивный' => 'disabled',
        ];
        
        return $mapping[$statusName] ?? strtolower($statusName);
    }
    
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
}