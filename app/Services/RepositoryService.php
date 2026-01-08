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
use Illuminate\Support\Str;

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
        
        // Используем логин из учетной записи
        $username = $participant->login;
        
        if (empty($username)) {
            $username = 'student-module' . $module->id . '-' . $participant->id;
        }
        
        // Генерируем УНИКАЛЬНОЕ имя репозитория с timestamp
        $timestamp = time();
        $uniqueSuffix = substr(md5($username . $timestamp), 0, 8);
        $repoName = 'exam-module-' . $module->id . '-' . $username . '-' . $uniqueSuffix;
        
        try {
            // 1. Получаем или создаем пароль
            $password = $participant->password_plain;
            
            if (empty($password)) {
                $password = Str::random(12);
                $participant->update([
                    'password_plain' => $password,
                    'password' => bcrypt($password)
                ]);
            }
            
            // 2. Создаем/проверяем пользователя в Gogs
            $userResult = $gogsService->createUserWithCredentials(
                $username,
                $password,
                $participantName,
                $user->email ?? ($username . '@exam.local')
            );
            
            // 3. Создаем репозиторий В АККАУНТЕ СТУДЕНТА
            $repoResult = $gogsService->createRepositoryForUser(
                $username, // ← Репозиторий в аккаунте студента
                $repoName,
                "Экзаменационный репозиторий для {$participantName} в модуле '{$module->name}'"
            );
            
            // 4. Сохраняем в БД
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
                    'gogs_password' => $password,
                    'gogs_owner' => $username, // ← Важно: студент - владелец
                    'gogs_repo_name' => $repoName,
                    'full_repo_path' => $username . '/' . $repoName,
                    'uses_existing_credentials' => !empty($participant->password_plain),
                    'gogs_data' => $repoResult['repository'],
                    'participant_info' => [
                        'name' => $participantName,
                        'email' => $user->email ?? null,
                        'login' => $username,
                    ],
                    'created_at' => now()->toISOString(),
                ]
            ]);
            
            return $repository;
            
        } catch (\Exception $e) {
            Log::error('Failed to create Gogs repository: ' . $e->getMessage());
            throw $e;
        }
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

    /**
     * Умное создание/пересоздание репозиториев
     */
    public function smartRepositoriesAction($moduleId, $recreate = false)
    {
        $module = Module::findOrFail($moduleId);
        
        if (!$module->event_id) {
            throw new \Exception('Модуль не привязан к мероприятию');
        }
        
        // Получаем участников
        $participantRoleId = Role::where('name', 'Участник')->value('id');
        $participants = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRoleId)
            ->with(['user'])
            ->get();
        
        if ($participants->isEmpty()) {
            throw new \Exception('В мероприятии нет участников с ролью "Участник"');
        }
        
        // Получаем ВСЕ существующие репозитории модуля
        $existingRepositories = Repository::where('module_id', $moduleId)->get();
        
        // Используем GogsService если настроен
        $useRealGogs = !config('services.gogs.mock', true);
        $gogsService = $useRealGogs ? new GogsService() : null;
        
        // Если нужно пересоздать - удаляем старые репозитории
        if ($recreate && $useRealGogs && $gogsService) {
            $gogsDeletionResults = [
                'repositories_deleted' => 0,
                'repositories_delete_errors' => 0,
                'users_deleted' => 0,
                'users_delete_errors' => 0
            ];
            
            // Удаляем репозитории из Gogs
            foreach ($existingRepositories as $repo) {
                try {
                    $metadata = $repo->metadata ?? [];
                    $gogsUsername = $metadata['gogs_username'] ?? null;
                    $repoName = $repo->name;
                    
                    if ($gogsUsername && $repoName) {
                        $deleteResult = $gogsService->deleteRepository($gogsUsername, $repoName);
                        
                        if ($deleteResult['success']) {
                            $gogsDeletionResults['repositories_deleted']++;
                            Log::info("Удален репозиторий из Gogs: {$gogsUsername}/{$repoName}");
                        } else {
                            $gogsDeletionResults['repositories_delete_errors']++;
                            Log::warning("Не удалось удалить репозиторий из Gogs: {$gogsUsername}/{$repoName} - {$deleteResult['message']}");
                        }
                    }
                    
                    // Также можно удалить пользователей (если хотите полный сброс)
                    // $gogsService->deleteUser($gogsUsername);
                    
                } catch (\Exception $e) {
                    $gogsDeletionResults['repositories_delete_errors']++;
                    Log::error("Ошибка при удалении репозитория {$repo->id} из Gogs: " . $e->getMessage());
                }
            }
            
            // Логируем результаты удаления
            Log::info("Удаление из Gogs: " . json_encode($gogsDeletionResults));
        }
        
        // Удаляем записи из БД
        if ($recreate) {
            $deletedCount = Repository::where('module_id', $moduleId)->delete();
            Log::info("Удалено {$deletedCount} записей репозиториев из БД для пересоздания");
        }
        
        $results = [
            'total' => $participants->count(),
            'successful' => 0,
            'failed' => 0,
            'repositories' => [],
            'action' => $recreate ? 'recreate' : 'create',
            'deleted_count' => $recreate ? $existingRepositories->count() : 0,
            'gogs_deletion' => $gogsDeletionResults ?? null
        ];
        
        // Создаем новые репозитории
        foreach ($participants as $participant) {
            try {
                // Проверяем, есть ли уже репозиторий у этого участника (только если НЕ пересоздаем)
                if (!$recreate) {
                    $existingForParticipant = $existingRepositories
                        ->where('event_account_id', $participant->id)
                        ->first();
                    
                    if ($existingForParticipant) {
                        $results['successful']++;
                        $results['repositories'][] = [
                            'success' => true,
                            'repository_id' => $existingForParticipant->id,
                            'repository_name' => $existingForParticipant->name,
                            'repository_url' => $existingForParticipant->url,
                            'participant_id' => $participant->id,
                            'participant_name' => $participant->user->name ?? 'Неизвестно',
                            'action' => 'already_exists',
                            'message' => 'Репозиторий уже существует'
                        ];
                        continue;
                    }
                }
                
                // Проверяем участника на валидность
                if (!$participant->user) {
                    throw new \Exception("У участника нет привязанного пользователя");
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
                    'action' => $recreate ? 'recreated' : 'created'
                ];
                
                Log::info(($recreate ? 'Пересоздан' : 'Создан') . ' репозиторий для участника', [
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
                
                Log::error('Ошибка ' . ($recreate ? 'пересоздания' : 'создания') . ' репозитория', [
                    'module_id' => $moduleId,
                    'participant_id' => $participant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Пересоздать репозиторий для одного участника
     */
    public function recreateRepositoryForParticipant($moduleId, $eventAccountId)
    {
        $module = Module::findOrFail($moduleId);
        $participant = EventAccount::with(['user'])->findOrFail($eventAccountId);
        
        // Удаляем старый репозиторий
        $oldRepository = Repository::where('module_id', $moduleId)
            ->where('event_account_id', $eventAccountId)
            ->first();
        
        if ($oldRepository) {
            // Здесь можно добавить удаление из Gogs
            $oldRepository->delete();
            Log::info("Удален старый репозиторий для пересоздания", [
                'repository_id' => $oldRepository->id,
                'participant_id' => $eventAccountId
            ]);
        }
        
        // Создаем новый
        $useRealGogs = !config('services.gogs.mock', true);
        $gogsService = $useRealGogs ? new GogsService() : null;
        
        if ($useRealGogs && $gogsService) {
            $repository = $this->createRealGogsRepository($module, $participant, $gogsService);
        } else {
            $repository = $this->createMockRepository($module, $participant);
        }
        
        return [
            'success' => true,
            'repository_id' => $repository->id,
            'repository_name' => $repository->name,
            'repository_url' => $repository->url,
            'participant_id' => $participant->id,
            'participant_name' => $participant->user->name ?? 'Неизвестно',
            'mock_gogs' => !$useRealGogs,
            'gogs_username' => $repository->metadata['gogs_username'] ?? null,
            'message' => 'Репозиторий успешно пересоздан'
        ];
    }
    
    public  function getActiveStatusId()
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

    /**
 * Создать/пересоздать репозиторий для одного участника
 */
public function createOrRecreateSingleRepository($moduleId, $eventAccountId, $recreate = false)
{
    $module = Module::findOrFail($moduleId);
    $participant = EventAccount::with(['user'])->findOrFail($eventAccountId);
    
    // Проверяем существующий репозиторий
    $existingRepository = Repository::where('module_id', $moduleId)
        ->where('event_account_id', $eventAccountId)
        ->first();
    
    // Если нужно пересоздать и репозиторий существует - удаляем
    if ($recreate && $existingRepository) {
        try {
            // Удаляем из Gogs
            $metadata = $existingRepository->metadata ?? [];
            $owner = $metadata['gogs_owner'] ?? null;
            $repoName = $metadata['gogs_repo_name'] ?? $existingRepository->name;
            
            if ($owner && $repoName) {
                $gogsService = new GogsService();
                $deleteResult = $gogsService->deleteRepository($owner, $repoName);
                
                if (!$deleteResult['success']) {
                    Log::warning("Не удалось удалить репозиторий из Gogs: {$deleteResult['message']}");
                }
            }
            
            // Удаляем запись из БД
            $existingRepository->delete();
            Log::info("Удален репозиторий для пересоздания", [
                'repository_id' => $existingRepository->id,
                'participant_id' => $eventAccountId
            ]);
            
        } catch (\Exception $e) {
            Log::error("Ошибка при удалении репозитория для пересоздания: " . $e->getMessage());
        }
    }
    
    // Если не пересоздаем и репозиторий уже есть - возвращаем ошибку
    if (!$recreate && $existingRepository) {
        throw new \Exception("Репозиторий для участника уже существует");
    }
    
    // Создаем новый репозиторий
    $useRealGogs = !config('services.gogs.mock', true);
    $gogsService = $useRealGogs ? new GogsService() : null;
    
    if ($useRealGogs && $gogsService) {
        $repository = $this->createRealGogsRepository($module, $participant, $gogsService);
    } else {
        $repository = $this->createMockRepository($module, $participant);
    }
    
    return [
        'success' => true,
        'repository_id' => $repository->id,
        'repository_name' => $repository->name,
        'repository_url' => $repository->url,
        'participant_id' => $participant->id,
        'participant_name' => $participant->user->name ?? 'Неизвестно',
        'mock_gogs' => !$useRealGogs,
        'gogs_username' => $repository->metadata['gogs_username'] ?? null,
        'action' => $recreate ? 'recreated' : 'created',
        'message' => $recreate ? 'Репозиторий успешно пересоздан' : 'Репозиторий успешно создан'
    ];
}

/**
 * Создать учетные записи экспертов для модуля
 */
public function createExpertAccounts($moduleId)
{
    $module = Module::findOrFail($moduleId);
    
    if (!$module->event_id) {
        throw new \Exception('Модуль не привязан к мероприятию');
    }
    
    // Получаем ID ролей экспертов
    $expertRoleId = Role::where('name', 'Эксперт')->value('id');
    $chiefExpertRoleId = Role::where('name', 'Главный эксперт')->value('id');
    $techExpertRoleId = Role::where('name', 'Технический эксперт')->value('id');
    
    if (!$expertRoleId || !$chiefExpertRoleId || !$techExpertRoleId) {
        throw new \Exception('Роли экспертов не найдены в системе');
    }
    
    // Получаем всех экспертов мероприятия
    $expertAccounts = EventAccount::where('event_id', $module->event_id)
        ->whereIn('role_id', [$expertRoleId, $chiefExpertRoleId, $techExpertRoleId])
        ->with(['user', 'role'])
        ->get();
    
    if ($expertAccounts->isEmpty()) {
        throw new \Exception('В мероприятии нет экспертов');
    }
    
    $useRealGogs = !config('services.gogs.mock', true);
    $gogsService = $useRealGogs ? new GogsService() : null;
    
    $results = [
        'total' => $expertAccounts->count(),
        'successful' => 0,
        'failed' => 0,
        'accounts' => []
    ];
    
    foreach ($expertAccounts as $expertAccount) {
        try {
            $user = $expertAccount->user;
            $role = $expertAccount->role;
            
            if (!$user) {
                throw new \Exception('У эксперта нет привязанного пользователя');
            }
            
            // Генерируем логин для Gogs
            $username = $expertAccount->login;
            if (empty($username)) {
                $username = strtolower(preg_replace('/[^a-z0-9]/', '', $user->last_name . '_' . $user->first_name)) 
                    . '_expert_' . $moduleId;
            }
            
            // Получаем или создаем пароль
            $password = $expertAccount->password_plain;
            if (empty($password)) {
                $password = Str::random(12);
                $expertAccount->update([
                    'password_plain' => $password,
                    'password' => bcrypt($password)
                ]);
            }
            
            // Создаем пользователя в Gogs
            if ($useRealGogs && $gogsService) {
                $userResult = $gogsService->createUserWithCredentials(
                    $username,
                    $password,
                    $user->full_name ?? $user->name,
                    $user->email ?? ($username . '@exam.local')
                );
            }
            
            $results['successful']++;
            $results['accounts'][] = [
                'success' => true,
                'event_account_id' => $expertAccount->id,
                'user_id' => $user->id,
                'name' => $user->full_name ?? $user->name,
                'role' => $role->name,
                'username' => $username,
                'password' => $password,
                'email' => $user->email,
                'created_in_gogs' => $useRealGogs
            ];
            
            Log::info('Создана учетная запись эксперта в Gogs', [
                'module_id' => $moduleId,
                'expert_id' => $expertAccount->id,
                'username' => $username,
                'role' => $role->name
            ]);
            
        } catch (\Exception $e) {
            $results['failed']++;
            $results['accounts'][] = [
                'success' => false,
                'event_account_id' => $expertAccount->id,
                'name' => $expertAccount->user->name ?? 'Эксперт',
                'role' => $expertAccount->role->name ?? 'Неизвестно',
                'error' => $e->getMessage()
            ];
            
            Log::error('Ошибка создания учетной записи эксперта', [
                'module_id' => $moduleId,
                'expert_id' => $expertAccount->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    return $results;
}

/**
 * Блокировать/разблокировать ВСЕ репозитории модуля
 */
public function bulkToggleRepositories($moduleId, $isActive)
{
    $module = Module::findOrFail($moduleId);
    
    // Получаем все репозитории модуля
    $repositories = Repository::where('module_id', $moduleId)->get();
    
    $results = [
        'total' => $repositories->count(),
        'updated' => 0,
        'failed' => 0,
        'details' => [],
        'new_status' => $isActive ? 'active' : 'locked'
    ];
    
    $gogsService = null;
    $useRealGogs = !config('services.gogs.mock', true);
    if ($useRealGogs) {
        $gogsService = new GogsService();
    }
    
    foreach ($repositories as $repository) {
        try {
            // Получаем информацию о участнике для прав доступа
            $eventAccount = EventAccount::with(['user', 'role'])
                ->find($repository->event_account_id);
            
            $username = null;
            if ($eventAccount) {
                $metadata = $repository->metadata ?? [];
                $username = $metadata['gogs_username'] ?? $eventAccount->login;
            }
            
            // 1. Обновляем статус в БД
            $repository->update([
                'is_active' => $isActive,
                'status_id' => $isActive 
                    ? $this->getActiveStatusId() 
                    : $this->getLockedStatusId()
            ]);
            
            // 2. Обновляем права в Gogs если используется реальный Gogs
            if ($useRealGogs && $gogsService && $username && $eventAccount) {
                try {
                    // Определяем права на основе роли пользователя
                    $permission = $this->getParticipantPermission(
                        $eventAccount->role_id,
                        $isActive
                    );
                    
                    Log::info("Определены права для {$username}: роль {$eventAccount->role_id} -> {$permission}", [
                        'is_active' => $isActive,
                        'repository' => $repository->name
                    ]);
                    
                    // Обновляем права в Gogs
                    $collabResult = $gogsService->updateCollaboratorPermission(
                        $metadata['gogs_owner'] ?? 'adminangelina',
                        $repository->name,
                        $username,
                        $permission
                    );
                    
                    Log::info("Обновлены права в Gogs для {$username}: {$permission}", [
                        'repository' => $repository->name,
                        'module_id' => $moduleId
                    ]);
                    
                } catch (\Exception $gogsError) {
                    Log::warning("Не удалось обновить права в Gogs: " . $gogsError->getMessage());
                }
            }
            
            // 3. Обновляем метаданные
            $metadata = $repository->metadata ?? [];
            $metadata['last_lock_status'] = [
                'action' => $isActive ? 'unlocked' : 'locked',
                'timestamp' => now()->toISOString(),
                'permission' => $isActive ? 'write' : 'read'
            ];
            
            $repository->update([
                'metadata' => $metadata
            ]);
            
            $results['updated']++;
            $results['details'][] = [
                'success' => true,
                'repository_id' => $repository->id,
                'repository_name' => $repository->name,
                'participant_name' => $eventAccount?->user?->name ?? 'Неизвестно',
                'old_status' => !$isActive ? 'active' : 'locked',
                'new_status' => $isActive ? 'active' : 'locked',
                'permission' => $isActive ? 'write' : 'read'
            ];
            
        } catch (\Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'success' => false,
                'repository_id' => $repository->id,
                'repository_name' => $repository->name,
                'error' => $e->getMessage()
            ];
            
            Log::error("Ошибка при обновлении статуса репозитория {$repository->id}: " . $e->getMessage());
        }
    }
    
    return $results;
}

/**
 * Получить ID статуса "Заблокирован" для репозиториев
 */
public  function getLockedStatusId()
{
    $repositoryContext = Context::where('name', 'repository')->first();
    
    if (!$repositoryContext) {
        throw new \Exception('Контекст "repository" не найден');
    }
    
    $lockedStatus = Status::where('name', 'Заблокирован')
        ->where('context_id', $repositoryContext->id)
        ->first();
    
    if (!$lockedStatus) {
        // Создаем статус если его нет
        $lockedStatus = Status::create([
            'name' => 'Заблокирован',
            'description' => 'Репозиторий заблокирован (только чтение)',
            'context_id' => $repositoryContext->id,
            'is_active' => true
        ]);
    }
    
    return $lockedStatus->id;
}

/**
 * Определить права доступа в зависимости от роли и статуса
 */
public  function getParticipantPermission($roleId, $isActive)
{
    // ID ролей (сопоставьте с вашими ID в базе данных)
    $participantRoleId = 4;    // Участник
    $expertRoleId = 2;         // Эксперт
    $chiefExpertRoleId = 1;    // Главный эксперт
    $techExpertRoleId = 3;     // Технический эксперт
    $adminRoleId = 5;          // Администратор
    $observerRoleId = 6;       // Наблюдатель
    
    // Если блокируем (делаем read-only)
    if (!$isActive) {
        // Участникам даем только чтение
        if ($roleId == $participantRoleId) {
            return 'read';
        }
        // Наблюдателям только чтение
        if ($roleId == $observerRoleId) {
            return 'read';
        }
        // Экспертам тоже только чтение (но можно оставить write если нужно)
        if ($roleId == $expertRoleId) {
            return 'read'; // или 'write' если хотите чтобы эксперты могли менять
        }
        // Главным/техническим экспертам и админам оставляем текущие права
        if (in_array($roleId, [$chiefExpertRoleId, $techExpertRoleId, $adminRoleId])) {
            return 'write'; // или 'admin'
        }
    }
    
    // При разблокировке
    if ($roleId == $participantRoleId) {
        return 'write';
    }
    
    // Наблюдателям всегда только чтение
    if ($roleId == $observerRoleId) {
        return 'read';
    }
    
    // Экспертам - write
    if ($roleId == $expertRoleId) {
        return 'write';
    }
    
    // Главным/техническим экспертам и админам - admin
    if (in_array($roleId, [$chiefExpertRoleId, $techExpertRoleId, $adminRoleId])) {
        return 'admin';
    }
    
    // По умолчанию
    return 'read';
}

/**
 * Создать публичный репозиторий для модуля с АВТОМАТИЧЕСКОЙ настройкой доступа
 */
public function createPublicRepository($moduleId)
{
    $module = Module::with(['event'])->findOrFail($moduleId);
    $useRealGogs = !config('services.gogs.mock', true);
    $gogsService = $useRealGogs ? new GogsService() : null;
    
    // Имя публичного репозитория
    $repoName = 'public-module-' . $moduleId . '-shared';
    
    // 1. Проверяем и удаляем старый репозиторий в БД
    $existingPublicRepo = Repository::where('module_id', $moduleId)
        ->where(function($query) {
            $query->whereHas('type', function($q) {
                    $q->where('name', 'Публичный');
                })
                ->orWhereJsonContains('metadata->is_public', true);
        })
        ->first();
    
    if ($existingPublicRepo) {
        Log::info('Найден существующий публичный репозиторий, удаляем: ' . $existingPublicRepo->id);
        $existingPublicRepo->delete();
    }
    
    // 2. Проверяем и удаляем старый репозиторий в Gogs
    if ($useRealGogs && $gogsService) {
        try {
            $repoInfo = $gogsService->getRepository('adminangelina', $repoName);
            if ($repoInfo['success']) {
                Log::info('Репозиторий найден в Gogs, удаляем: adminangelina/' . $repoName);
                $deleteResult = $gogsService->deleteRepository('adminangelina', $repoName);
            }
        } catch (\Exception $e) {
            // Репозитория нет в Gogs - это нормально
        }
    }
    
    try {
        // 3. Создаем новый репозиторий в аккаунте админа
        $repoResult = null;
        if ($useRealGogs && $gogsService) {
            $repoResult = $gogsService->createRepository(
                'adminangelina',
                $repoName,
                "Публичный репозиторий для модуля '{$module->name}'"
            );
        }
        
        // 4. Получаем ID типа "Публичный" или создаем новый
        $publicTypeId = Type::where('name', 'Публичный')
            ->whereHas('context', function($q) {
                $q->where('name', 'repository');
            })
            ->value('id');
        
        if (!$publicTypeId) {
            $repositoryContext = Context::where('name', 'repository')->first();
            if ($repositoryContext) {
                $publicType = Type::create([
                    'name' => 'Публичный',
                    'description' => 'Публичные репозитории для общего доступа',
                    'context_id' => $repositoryContext->id,
                    'is_active' => true
                ]);
                $publicTypeId = $publicType->id;
            }
        }
        
        // 5. Находим или создаем владельца
        $ownerId = $this->findOrCreateRepositoryOwner($module);
        
        Log::info('Создаем публичный репозиторий с владельцем ID: ' . $ownerId);
        
        // 6. Сохраняем в БД
        $repository = Repository::create([
            'name' => $repoName,
            'url' => $useRealGogs ? ($repoResult['web_url'] ?? '#') : '#',
            'description' => "Публичный репозиторий для модуля '{$module->name}'",
            'server_id' => $this->getGogsServerId(),
            'type_id' => $publicTypeId,
            'event_account_id' => $ownerId,
            'module_id' => $moduleId,
            'status_id' => $this->getActiveStatusId(),
            'is_active' => true,
            'is_public' => true,
            'gogs_repo_id' => $useRealGogs ? ($repoResult['repository']['id'] ?? null) : null,
            'ssh_url' => null,
            'clone_url' => $useRealGogs ? ($repoResult['clone_url'] ?? '#') : '#',
            'metadata' => [
                'is_public' => true,
                'created_in_gogs' => $useRealGogs,
                'gogs_owner' => 'adminangelina',
                'gogs_repo_name' => $repoName,
                'module_name' => $module->name,
                'event_name' => $module->event->name ?? 'Unknown',
                'is_admin_repository' => true,
                'owner_type' => 'Системный',
                'owner_name' => 'Администратор',
                'owner_id' => $ownerId,
                'previous_deleted' => $existingPublicRepo ? $existingPublicRepo->id : null,
                'created_at' => now()->toISOString(),
            ]
        ]);
        
        Log::info('Публичный репозиторий успешно создан: ' . $repository->id);
        
        // 7. ✅ ВАЖНО: АВТОМАТИЧЕСКАЯ НАСТРОЙКА ДОСТУПА
        $accessResults = null;
        if ($useRealGogs && $gogsService) {
            try {
                $accessResults = $this->setupGranularPublicRepositoryAccess($moduleId);
                Log::info('Автоматически настроен доступ к публичному репозиторию', [
                    'module_id' => $moduleId,
                    'repository_id' => $repository->id,
                    'users_added' => $accessResults['total_users'] ?? 0
                ]);
            } catch (\Exception $accessError) {
                Log::error('Ошибка автоматической настройки доступа: ' . $accessError->getMessage());
                // Не прерываем выполнение, продолжаем
            }
        }
        
        return [
            'success' => true,
            'repository' => $repository,
            'access_configured' => $accessResults ? true : false,
            'access_results' => $accessResults,
            'message' => 'Публичный репозиторий создан' . 
                        ($accessResults ? ' и доступ настроен' : ' (доступ не настроен - используется mock режим)'),
            'deleted_old' => $existingPublicRepo ? true : false,
            'owner_info' => [
                'id' => $ownerId,
                'name' => 'Администратор',
                'role' => 'Системный'
            ]
        ];
        
    } catch (\Exception $e) {
        Log::error('Ошибка создания публичного репозитория: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Найти или создать владельца для публичного репозитория (гарантирует число)
 */
private function findOrCreateRepositoryOwner(Module $module)
{
    try {
        // Пытаемся найти администратора
        $adminUser = \App\Models\User::where('email', 'like', '%admin%')
            ->orWhere('name', 'like', '%admin%')
            ->orWhere('login', 'admin')
            ->first();
        
        if ($adminUser) {
            // Ищем учетную запись администратора для этого мероприятия
            $adminAccount = EventAccount::where('user_id', $adminUser->id)
                ->when($module->event_id, function($query) use ($module) {
                    return $query->where('event_id', $module->event_id);
                })
                ->first();
            
            if ($adminAccount) {
                return $adminAccount->id;
            }
        }
        
        // Ищем главного эксперта мероприятия
        if ($module->event_id) {
            $chiefExpertRoleId = Role::where('name', 'Главный эксперт')->value('id');
            
            if ($chiefExpertRoleId) {
                $chiefExpert = EventAccount::where('event_id', $module->event_id)
                    ->where('role_id', $chiefExpertRoleId)
                    ->first();
                
                if ($chiefExpert) {
                    return $chiefExpert->id;
                }
            }
        }
        
        // Если ничего не нашли, берем первую запись из event_accounts
        $anyAccount = EventAccount::first();
        if ($anyAccount) {
            Log::warning('Используем первый попавшийся аккаунт как владельца публичного репозитория: ' . $anyAccount->id);
            return $anyAccount->id;
        }
        
        // Создаем системного владельца если вообще нет аккаунтов
        Log::info('Создаем системного владельца для публичного репозитория');
        $systemOwnerId = $this->createSystemOwner();
        return $systemOwnerId;
        
    } catch (\Exception $e) {
        Log::error('Ошибка при поиске владельца: ' . $e->getMessage());
        // Запасной вариант - всегда возвращаем 1
        return 1;
    }
}

/**
 * Создать системного владельца
 */
private function createSystemOwner()
{
    // Создаем системного пользователя
    $systemUser = \App\Models\User::firstOrCreate(
        ['email' => 'system_owner@exam.local'],
        [
            'name' => 'System Repository Owner',
            'password' => bcrypt(Str::random(32)),
            'login' => 'system_owner'
        ]
    );
    
    // Создаем системную роль если нет
    $systemRole = Role::firstOrCreate(
        ['name' => 'Системный'],
        [
            'description' => 'Системные учетные записи',
            'is_active' => true
        ]
    );
    
    // Создаем системную учетную запись
    $systemAccount = EventAccount::firstOrCreate(
        [
            'user_id' => $systemUser->id,
            'event_id' => 0
        ],
        [
            'login' => 'system_owner',
            'password' => bcrypt(Str::random(32)),
            'password_plain' => Str::random(32),
            'role_id' => $systemRole->id,
            'is_active' => true
        ]
    );
    
    return $systemAccount->id;
}

public function getPublicRepository($moduleId)
{
    try {
        // Ищем публичный репозиторий
        $repository = \App\Models\Repository::where('module_id', $moduleId)
            ->where(function($query) {
                $query->whereHas('type', function($q) {
                        $q->where('name', 'Публичный');
                    })
                    ->orWhere('is_public', true)
                    ->orWhereJsonContains('metadata->is_public', true);
            })
            ->with(['eventAccount.user', 'eventAccount.role'])
            ->first();
        
        if (!$repository) {
            throw new \Exception('Публичный репозиторий не найден');
        }
        
        // Формируем информацию о владельце
        $ownerInfo = null;
        if ($repository->eventAccount) {
            $ownerInfo = [
                'name' => $repository->eventAccount->user->name ?? 'Неизвестно',
                'role' => $repository->eventAccount->role->name ?? 'Неизвестно',
                'email' => $repository->eventAccount->user->email ?? null
            ];
        }
        
        // Возвращаем массив данных
        return [
            'id' => $repository->id,
            'name' => $repository->name,
            'url' => $repository->url,
            'description' => $repository->description,
            'clone_url' => $repository->clone_url,
            'is_active' => $repository->is_active,
            'is_public' => $repository->is_public,
            'metadata' => $repository->metadata,
            'owner' => $ownerInfo,
            'created_at' => $repository->created_at,
            'updated_at' => $repository->updated_at
        ];
        
    } catch (\Exception $e) {
        \Log::error('Ошибка получения публичного репозитория: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Настроить доступ к публичному репозиторию для всех участников
 */
public function setupPublicRepositoryAccess($moduleId)
{
    $module = Module::with(['event'])->findOrFail($moduleId);
    $useRealGogs = !config('services.gogs.mock', true);
    
    if (!$useRealGogs) {
        throw new \Exception('Требуется реальный Gogs для настройки доступа');
    }
    
    // 1. Находим публичный репозиторий
    $publicRepo = Repository::where('module_id', $moduleId)
        ->where(function($query) {
            $query->where('is_public', true)
                  ->orWhereJsonContains('metadata->is_public', true);
        })
        ->first();
    
    if (!$publicRepo) {
        throw new \Exception('Публичный репозиторий не найден');
    }
    
    // 2. Получаем всех участников мероприятия
    $participantRoleId = Role::where('name', 'Участник')->value('id');
    $participants = EventAccount::where('event_id', $module->event_id)
        ->where('role_id', $participantRoleId)
        ->whereNotNull('login')
        ->whereNotNull('password_plain')
        ->get();
    
    // 3. Получаем всех экспертов мероприятия
    $expertRoleIds = Role::whereIn('name', ['Эксперт', 'Главный эксперт', 'Технический эксперт'])
        ->pluck('id')
        ->toArray();
    
    $experts = EventAccount::where('event_id', $module->event_id)
        ->whereIn('role_id', $expertRoleIds)
        ->whereNotNull('login')
        ->whereNotNull('password_plain')
        ->get();
    
    // Объединяем всех пользователей
    $allUsers = $participants->merge($experts);
    
    $gogsService = new GogsService();
    $results = [
        'total_users' => $allUsers->count(),
        'added_collaborators' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    foreach ($allUsers as $userAccount) {
        try {
            // Добавляем как коллаборатора в репозиторий
            // Права: write - запись, read - чтение, admin - админ
            $collabResult = $gogsService->addCollaborator(
                'adminangelina',  // Владелец репозитория
                $publicRepo->name, // Имя репозитория
                $userAccount->login, // Логин пользователя
                'write'  // Права на запись
            );
            
            if ($collabResult['success']) {
                $results['added_collaborators']++;
                $results['details'][] = [
                    'success' => true,
                    'user_id' => $userAccount->user_id,
                    'login' => $userAccount->login,
                    'role' => $userAccount->role->name ?? 'Неизвестно',
                    'permission' => 'write'
                ];
                
                Log::info('Добавлен доступ к публичному репозиторию', [
                    'user' => $userAccount->login,
                    'repository' => $publicRepo->name,
                    'module_id' => $moduleId
                ]);
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'success' => false,
                    'user_id' => $userAccount->user_id,
                    'login' => $userAccount->login,
                    'error' => $collabResult['message']
                ];
            }
            
        } catch (\Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'success' => false,
                'user_id' => $userAccount->user_id,
                'login' => $userAccount->login,
                'error' => $e->getMessage()
            ];
            
            Log::error('Ошибка добавления доступа к публичному репозиторию', [
                'user' => $userAccount->login,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Обновляем метаданные репозитория
    $metadata = $publicRepo->metadata ?? [];
    $metadata['collaborators_added'] = true;
    $metadata['total_collaborators'] = $results['added_collaborators'];
    $metadata['collaborators_added_at'] = now()->toISOString();
    
    $publicRepo->update([
        'metadata' => $metadata
    ]);
    
    return $results;
}

/**
 * Пересоздать учетную запись эксперта в Gogs
 */
public function recreateExpertAccount($moduleId, $expertId)
{
    $module = Module::findOrFail($moduleId);
    $expertAccount = EventAccount::with(['user', 'role'])->findOrFail($expertId);
    $user = $expertAccount->user;
    
    if (!$user) {
        throw new \Exception('У эксперта нет привязанного пользователя');
    }
    
    $useRealGogs = !config('services.gogs.mock', true);
    $gogsService = $useRealGogs ? new GogsService() : null;
    
    if (!$useRealGogs || !$gogsService) {
        throw new \Exception('Режим реального Gogs не активирован');
    }
    
    // Генерируем логин если нет
    $username = $expertAccount->login;
    if (empty($username)) {
        $username = strtolower(preg_replace('/[^a-z0-9]/', '', $user->last_name . '_' . $user->first_name)) 
            . '_expert_' . $moduleId;
    }
    
    // Генерируем новый пароль
    $newPassword = Str::random(12);
    
    try {
        // 1. Удаляем старую учетную запись из Gogs
        if ($expertAccount->password_plain) {
            $deleteResult = $gogsService->deleteUser($username);
            
            if (!$deleteResult['success'] && !str_contains($deleteResult['message'], 'уже удален')) {
                Log::warning("Не удалось удалить старую учетную запись: {$deleteResult['message']}");
            }
        }
        
        // 2. Создаем новую учетную запись
        $userResult = $gogsService->createUserWithCredentials(
            $username,
            $newPassword,
            $user->full_name ?? $user->name,
            $user->email ?? ($username . '@exam.local')
        );
        
        // 3. Обновляем пароль в БД
        $expertAccount->update([
            'login' => $username,
            'password_plain' => $newPassword,
            'password' => bcrypt($newPassword)
        ]);
        
        Log::info("Пересоздана учетная запись эксперта", [
            'module_id' => $moduleId,
            'expert_id' => $expertId,
            'username' => $username,
            'role' => $expertAccount->role->name
        ]);
        
        return [
            'success' => true,
            'username' => $username,
            'password' => $newPassword,
            'expert_name' => $user->full_name ?? $user->name,
            'role' => $expertAccount->role->name,
            'message' => 'Учетная запись эксперта успешно пересоздана'
        ];
        
    } catch (\Exception $e) {
        Log::error('Ошибка пересоздания учетной записи эксперта: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Настроить гранулярный доступ к публичному репозиторию
 * с разными правами для разных ролей
 */
public function setupGranularPublicRepositoryAccess($moduleId)
{
    $module = Module::with(['event'])->findOrFail($moduleId);
    $useRealGogs = !config('services.gogs.mock', true);
    
    if (!$useRealGogs) {
        throw new \Exception('Требуется реальный Gogs для настройки доступа');
    }
    
    // 1. Находим публичный репозиторий
    $publicRepo = Repository::where('module_id', $moduleId)
        ->where(function($query) {
            $query->where('is_public', true)
                  ->orWhereJsonContains('metadata->is_public', true);
        })
        ->first();
    
    if (!$publicRepo) {
        throw new \Exception('Публичный репозиторий не найден');
    }
    
    $gogsService = new GogsService();
    
    // 2. Сначала очищаем старых коллабораторов (если есть)
    $this->cleanupOldCollaborators($gogsService, 'adminangelina', $publicRepo->name);
    
    // 3. Настраиваем права по ролям
    $results = [
        'repository_id' => $publicRepo->id,
        'repository_name' => $publicRepo->name,
        'owner' => 'adminangelina',
        'total_users' => 0,
        'by_role' => [],
        'details' => []
    ];
    
    // Права по ролям
    $rolePermissions = [
        1 => 'admin',      // Главный эксперт - полные права
        3 => 'admin',      // Технический эксперт - полные права  
        5 => 'admin',      // Администратор - полные права
        2 => 'read',       // Эксперт - только чтение
        4 => 'read',       // Участник - только чтение
        6 => 'read'        // Наблюдатель - только чтение
    ];
    
    // 4. Получаем пользователей по ролям
    foreach ($rolePermissions as $roleId => $permission) {
        $users = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $roleId)
            ->whereNotNull('login')
            ->whereNotNull('password_plain')
            ->with(['user', 'role'])
            ->get();
        
        $roleResults = [
            'role_id' => $roleId,
            'role_name' => Role::find($roleId)->name ?? 'Неизвестно',
            'permission' => $permission,
            'total' => $users->count(),
            'successful' => 0,
            'failed' => 0,
            'users' => []
        ];
        
        foreach ($users as $userAccount) {
            try {
                // Добавляем как коллаборатора с соответствующими правами
                $collabResult = $gogsService->addCollaborator(
                    'adminangelina',
                    $publicRepo->name,
                    $userAccount->login,
                    $permission
                );
                
                if ($collabResult['success']) {
                    $roleResults['successful']++;
                    $roleResults['users'][] = [
                        'success' => true,
                        'user_id' => $userAccount->user_id,
                        'login' => $userAccount->login,
                        'name' => $userAccount->user->name ?? 'Неизвестно',
                        'email' => $userAccount->user->email ?? null
                    ];
                    
                    Log::info('Настроены права доступа к публичному репозиторию', [
                        'user' => $userAccount->login,
                        'role' => $userAccount->role->name,
                        'permission' => $permission,
                        'repository' => $publicRepo->name
                    ]);
                } else {
                    $roleResults['failed']++;
                    $roleResults['users'][] = [
                        'success' => false,
                        'user_id' => $userAccount->user_id,
                        'login' => $userAccount->login,
                        'error' => $collabResult['message']
                    ];
                }
                
            } catch (\Exception $e) {
                $roleResults['failed']++;
                $roleResults['users'][] = [
                    'success' => false,
                    'user_id' => $userAccount->user_id,
                    'login' => $userAccount->login,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Ошибка настройки прав доступа', [
                    'user' => $userAccount->login,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $results['total_users'] += $users->count();
        $results['by_role'][$roleId] = $roleResults;
    }
    
    // 5. Обновляем метаданные репозитория
    $metadata = $publicRepo->metadata ?? [];
    $metadata['access_configured'] = true;
    $metadata['configured_at'] = now()->toISOString();
    $metadata['role_permissions'] = $rolePermissions;
    $metadata['access_summary'] = [
        'total_users' => $results['total_users'],
        'admin_users' => ($results['by_role'][1]['successful'] ?? 0) + 
                         ($results['by_role'][3]['successful'] ?? 0) + 
                         ($results['by_role'][5]['successful'] ?? 0),
        'readonly_users' => ($results['by_role'][2]['successful'] ?? 0) + 
                            ($results['by_role'][4]['successful'] ?? 0) + 
                            ($results['by_role'][6]['successful'] ?? 0)
    ];
    
    $publicRepo->update([
        'metadata' => $metadata
    ]);
    
    return $results;
}

/**
 * Проверить текущие права доступа к публичному репозиторию
 */
public function checkPublicRepositoryAccess($moduleId)
{
    $publicRepo = Repository::where('module_id', $moduleId)
        ->where(function($query) {
            $query->where('is_public', true)
                  ->orWhereJsonContains('metadata->is_public', true);
        })
        ->first();
    
    if (!$publicRepo) {
        throw new \Exception('Публичный репозиторий не найден');
    }
    
    $gogsService = new GogsService();
    
    // Получаем текущих коллабораторов
    $collaboratorsResult = $gogsService->getRepositoryCollaborators(
        'adminangelina',
        $publicRepo->name
    );
    
    if (!$collaboratorsResult['success']) {
        throw new \Exception('Не удалось получить список коллабораторов');
    }
    
    // Анализируем права
    $accessAnalysis = [
        'repository' => [
            'id' => $publicRepo->id,
            'name' => $publicRepo->name,
            'owner' => 'adminangelina'
        ],
        'collaborators' => [],
        'summary' => [
            'total' => 0,
            'by_permission' => [
                'admin' => 0,
                'write' => 0,
                'read' => 0
            ]
        ]
    ];
    
    foreach ($collaboratorsResult['data'] as $collaborator) {
        if ($collaborator['login'] === 'adminangelina') {
            continue; // Пропускаем владельца
        }
        
        // Получаем права пользователя
        $permissionResult = $gogsService->getUserRepositoryPermission(
            'adminangelina',
            $publicRepo->name,
            $collaborator['login']
        );
        
        $permission = $permissionResult['permission'] ?? 'unknown';
        
        // Находим пользователя в системе
        $userAccount = EventAccount::where('login', $collaborator['login'])
            ->with(['user', 'role'])
            ->first();
        
        $accessAnalysis['collaborators'][] = [
            'login' => $collaborator['login'],
            'permission' => $permission,
            'user' => $userAccount ? [
                'id' => $userAccount->user_id,
                'name' => $userAccount->user->name ?? 'Неизвестно',
                'role' => $userAccount->role->name ?? 'Неизвестно',
                'role_id' => $userAccount->role_id
            ] : null
        ];
        
        $accessAnalysis['summary']['total']++;
        $accessAnalysis['summary']['by_permission'][$permission] = 
            ($accessAnalysis['summary']['by_permission'][$permission] ?? 0) + 1;
    }
    
    return $accessAnalysis;
}

/**
 * Очистить старых коллабораторов репозитория
 */
private function cleanupOldCollaborators($gogsService, $owner, $repo)
{
    try {
        // Получаем текущих коллабораторов
        $collaborators = $gogsService->getRepositoryCollaborators($owner, $repo);
        
        if ($collaborators['success'] && !empty($collaborators['data'])) {
            // Удаляем всех коллабораторов кроме владельца
            foreach ($collaborators['data'] as $collaborator) {
                if ($collaborator['login'] !== $owner) {
                    $gogsService->removeCollaborator($owner, $repo, $collaborator['login']);
                }
            }
        }
        
        Log::info('Очищены старые коллабораторы репозитория', [
            'owner' => $owner,
            'repo' => $repo
        ]);
        
    } catch (\Exception $e) {
        Log::warning('Не удалось очистить старых коллабораторов: ' . $e->getMessage());
    }
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