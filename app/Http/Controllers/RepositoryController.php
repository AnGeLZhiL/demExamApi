<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Repository;
use App\Services\GogsService;
use App\Services\RepositoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class RepositoryController extends Controller
{
    protected $repositoryService;

    public function __construct(RepositoryService $repositoryService)
    {
        $this->repositoryService = $repositoryService;
    }

    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список репозиториев
     */
    public function index()
    {
        return Repository::with(['server', 'type', 'eventAccount.user', 'module'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание репозитория
     */
    public function store(Request $request)
    {
        $repository = Repository::create([
            'name' => $request->name,
            'url' => $request->url,
            'server_id' => $request->server_id,
            'type_id' => $request->type_id,
            'event_account_id' => $request->event_account_id,
            'module_id' => $request->module_id,
            'is_active' => $request->is_active ?? true,
            'is_public' => $request->is_public ?? false
        ]);
        
        return response()->json($repository, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить репозиторий по указаному id
     */
    public function show(string $id)
    {
        $repository = Repository::with(['server', 'type', 'eventAccount.user', 'module'])->find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        return $repository;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление репозитория 
     */
    public function update(Request $request, string $id)
    {
        $repository = Repository::find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        // Обновляем только is_active, статус будет автоматически меняться
        $repository->update($request->only([
            'is_active'
            // Не обновляем status здесь, он зависит от is_active
        ]));
        
        // Если меняем is_active, обновляем статус
        if ($request->has('is_active')) {
            $this->updateRepositoryStatusBasedOnActive($repository);
        }
        
        return response()->json([
            'success' => true,
            'repository' => $repository->load(['server', 'type', 'eventAccount.user', 'module', 'status'])
        ]);
    }

    /**
     * Обновить статус репозитория в зависимости от is_active
     */
    private function updateRepositoryStatusBasedOnActive(Repository $repository)
    {
        $activeStatusId = $this->getActiveStatusId();
        $inactiveStatusId = $this->getInactiveStatusId();
        
        if ($repository->is_active && $activeStatusId) {
            $repository->update(['status_id' => $activeStatusId]);
        } elseif (!$repository->is_active && $inactiveStatusId) {
            $repository->update(['status_id' => $inactiveStatusId]);
        }
    }

    /**
     * Получить ID статуса "Активен" для репозиториев
     */
    private function getActiveStatusId()
    {
        $repositoryContext = \App\Models\Context::where('name', 'repository')->first();
        
        if (!$repositoryContext) {
            return null;
        }
        
        $activeStatus = \App\Models\Status::where('name', 'Активен')
            ->where('context_id', $repositoryContext->id)
            ->first();
        
        return $activeStatus ? $activeStatus->id : null;
    }

    /**
     * Получить ID статуса "Отключен" для репозиториев
     */
    private function getInactiveStatusId()
    {
        $repositoryContext = \App\Models\Context::where('name', 'repository')->first();
        
        if (!$repositoryContext) {
            return null;
        }
        
        $inactiveStatus = \App\Models\Status::where('name', 'Отключен')
            ->where('context_id', $repositoryContext->id)
            ->first();
        
        return $inactiveStatus ? $inactiveStatus->id : null;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление репозитория
     */
    public function destroy(string $id)
    {
        $repository = Repository::find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        $repository->delete();
        return response()->noContent();
    }

    /**
     * Создать репозитории для всех участников модуля
     */
    public function createForModule($moduleId, Request $request)
    {
        try {
            $results = $this->repositoryService->createRepositoriesForModule($moduleId);
            
            return response()->json([
                'success' => true,
                'message' => "Создано {$results['successful']} репозиториев, ошибок: {$results['failed']}",
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить репозитории модуля
     */
    public function getByModule($moduleId)
    {
        try {
            $repositories = $this->repositoryService->getModuleRepositories($moduleId);
            
            return response()->json([
                'success' => true,
                'data' => $repositories
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Тест подключения к Gogs
     */
    public function testGogsConnection(Request $request)
    {
        $gogsService = new GogsService();
    
        try {
            $result = $gogsService->testConnection();
            
            return response()->json([
                'success' => $result['success'] ?? false,
                'status' => $result['status'] ?? 'error',
                'message' => $result['message'] ?? 'Unknown error',
                'user' => $result['user'] ?? null,
                'url' => $result['url'] ?? config('services.gogs.url', ''),
                'mock' => config('services.gogs.mock', true)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'url' => config('services.gogs.url', ''),
                'mock' => config('services.gogs.mock', true)
            ], 500);
        }
    }

    /**
     * Умное создание/пересоздание репозиториев
     */
    public function smartAction($moduleId, Request $request)
    {
        try {
            $recreate = $request->input('recreate', false);
            
            $results = $this->repositoryService->smartRepositoriesAction($moduleId, $recreate);
            
            $actionText = $recreate ? 'пересоздано' : 'создано';
            
            return response()->json([
                'success' => true,
                'message' => "{$actionText} {$results['successful']} репозиториев, ошибок: {$results['failed']}",
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Пересоздать репозиторий для одного участника
     */
    public function recreateForParticipant($moduleId, Request $request)
    {
        try {
            $eventAccountId = $request->input('event_account_id');
            
            if (!$eventAccountId) {
                throw new \Exception('Не указан ID учетной записи участника');
            }
            
            $result = $this->repositoryService->recreateRepositoryForParticipant($moduleId, $eventAccountId);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Репозиторий пересоздан',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Пересоздать ВСЕ репозитории модуля (удалить старые, создать новые)
     */
    public function recreateAll($moduleId)
    {
        try {
            $gogsService = new GogsService();
            $result = $gogsService->recreateModuleRepositories($moduleId);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить ВСЕ репозитории модуля (только из Gogs, записи остаются)
     */
    public function deleteAllFromGogs($moduleId)
    {
        try {
            $gogsService = new GogsService();
            $result = $gogsService->deleteAllModuleRepositories($moduleId);
            
            return response()->json([
                'success' => true,
                'message' => "Удалено {$result['repositories_deleted']} репозиториев из Gogs",
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать/пересоздать один репозиторий для участника
     */
    public function createSingleRepository($moduleId, Request $request)
    {
        try {
            $eventAccountId = $request->input('event_account_id');
            $recreate = $request->input('recreate', false);
            
            if (!$eventAccountId) {
                throw new \Exception('Не указан ID учетной записи участника');
            }
            
            $result = $this->repositoryService->createOrRecreateSingleRepository(
                $moduleId, 
                $eventAccountId, 
                $recreate
            );
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить ВСЕ репозитории модуля (из Gogs и БД)
     */
    public function deleteAll($moduleId)
    {
        try {
            $gogsService = new GogsService();
            
            // 1. Удаляем из Gogs
            $deletionResult = $gogsService->deleteAllModuleRepositories($moduleId);
            
            // 2. Удаляем записи из БД (если что-то осталось)
            $dbDeleted = Repository::where('module_id', $moduleId)->delete();
            
            Log::info("Удалены все репозитории модуля {$moduleId}", [
                'gogs_deletion' => $deletionResult,
                'db_deleted' => $dbDeleted
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Удалено {$deletionResult['repositories_deleted']} репозиториев из Gogs",
                'data' => [
                    'deletion' => $deletionResult,
                    'db_deleted' => $dbDeleted
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка удаления всех репозиториев: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить один репозиторий (из Gogs и БД)
     */
    public function deleteSingle($moduleId, $repositoryId, Request $request)
    {
        try {
            $eventAccountId = $request->input('event_account_id');
            
            if (!$eventAccountId) {
                throw new \Exception('Не указан ID учетной записи участника');
            }
            
            // Находим репозиторий
            $repository = Repository::where('module_id', $moduleId)
                ->where('id', $repositoryId)
                ->where('event_account_id', $eventAccountId)
                ->firstOrFail();
            
            $metadata = $repository->metadata ?? [];
            $owner = $metadata['gogs_owner'] ?? null;
            $repoName = $metadata['gogs_repo_name'] ?? $repository->name;
            
            $gogsService = new GogsService();
            $results = [
                'repository_deleted' => false,
                'user_deleted' => false,
                'db_deleted' => false
            ];
            
            // 1. Удаляем репозиторий из Gogs
            if ($owner && $repoName) {
                $repoDeleteResult = $gogsService->deleteRepository($owner, $repoName);
                $results['repository_deleted'] = $repoDeleteResult['success'];
                
                if ($repoDeleteResult['success']) {
                    Log::info("Удален репозиторий из Gogs: {$owner}/{$repoName}");
                } else {
                    Log::warning("Не удалось удалить репозиторий из Gogs: {$repoDeleteResult['message']}");
                }
            }
            
            // 2. Удаляем пользователя из Gogs
            if ($owner) {
                $userDeleteResult = $gogsService->deleteUser($owner);
                $results['user_deleted'] = $userDeleteResult['success'];
                
                if ($userDeleteResult['success']) {
                    Log::info("Удален пользователь из Gogs: {$owner}");
                }
            }
            
            // 3. Удаляем запись из БД
            $repository->delete();
            $results['db_deleted'] = true;
            
            Log::info("Удален репозиторий {$repositoryId} из БД");
            
            return response()->json([
                'success' => true,
                'message' => 'Репозиторий и пользователь успешно удалены',
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка удаления репозитория: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

 /**
 * Переключить активность одного репозитория
 */
public function toggleRepository($repositoryId, Request $request)
{
    try {
        $repository = Repository::with(['eventAccount.user', 'eventAccount.role'])
            ->findOrFail($repositoryId);
        
        $isActive = $request->input('is_active', !$repository->is_active);
        
        Log::info("🔄 Переключение репозитория {$repositoryId}: " . 
                 ($isActive ? 'разблокирован' : 'заблокирован'));
        
        // НИКАКИХ ИЗМЕНЕНИЙ В EVENT_ACCOUNTS!
        $eventAccount = $repository->eventAccount;
        $metadata = $repository->metadata ?? [];
        $username = $metadata['gogs_username'] ?? $eventAccount->login;
        
        // Только логи в Gogs
        $useRealGogs = !config('services.gogs.mock', true);
        $gogsService = $useRealGogs ? new GogsService() : null;
        
        if ($useRealGogs && $gogsService && $username) {
            try {
                if (!$isActive) {
                    // 🔒 БЛОКИРОВКА: только в Gogs
                    Log::info("🔒 Блокировка Gogs для: {$username}");
                    
                    // 1. Сохраняем оригинальные данные из event_accounts в метаданные
                    $originalPassword = $eventAccount->password_plain;
                    $originalFullName = $eventAccount->user->name ?? $username;
                    
                    // 2. Меняем пароль в Gogs на случайный
                    $lockPassword = 'LOCKED_' . Str::random(32);
                    $passwordResult = $gogsService->changeUserPassword($username, $lockPassword);
                    
                    // 3. Меняем имя в Gogs (добавляем [LOCKED])
                    $nameResult = $gogsService->changeUserAttribute(
                        $username,
                        ['full_name' => '[LOCKED] ' . $originalFullName]
                    );
                    
                    // 4. Сохраняем в метаданные репозитория
                    $metadata['lock_info'] = [
                        'original_password' => $originalPassword, // только для восстановления
                        'original_full_name' => $originalFullName,
                        'locked_at' => now()->toISOString(),
                        'lock_password' => $lockPassword,
                        'username' => $username,
                        'event_account_id' => $eventAccount->id,
                        'event_account_login' => $eventAccount->login, // оригинальный логин из БД
                    ];
                    
                    Log::info("✅ Gogs заблокирован. Event account не изменен.");
                    
                } else {
                    // 🔓 РАЗБЛОКИРОВКА: только в Gogs
                    Log::info("🔓 Разблокировка Gogs для: {$username}");
                    
                    $lockInfo = $metadata['lock_info'] ?? [];
                    
                    // 1. Восстанавливаем оригинальный пароль в Gogs
                    $restorePassword = $lockInfo['original_password'] ?? $eventAccount->password_plain;
                    
                    if ($restorePassword) {
                        $passwordResult = $gogsService->changeUserPassword($username, $restorePassword);
                    }
                    
                    // 2. Восстанавливаем оригинальное имя в Gogs (убираем [LOCKED])
                    $restoreFullName = $lockInfo['original_full_name'] ?? 
                                     ($eventAccount->user->name ?? $username);
                    
                    $nameResult = $gogsService->changeUserAttribute(
                        $username,
                        ['full_name' => $restoreFullName]
                    );
                    
                    // 3. Очищаем метаданные
                    unset($metadata['lock_info']);
                    
                    Log::info("✅ Gogs разблокирован. Event account не изменен.");
                }
                
            } catch (\Exception $e) {
                Log::error("❌ Ошибка Gogs: " . $e->getMessage());
            }
        }
        
        // Обновляем только статус репозитория
        $repositoryService = new RepositoryService();
        $statusId = $isActive 
            ? $repositoryService->getActiveStatusId() 
            : $repositoryService->getLockedStatusId();
        
        $repository->update([
            'is_active' => $isActive,
            'status_id' => $statusId,
            'metadata' => $metadata
        ]);
        
        // Возвращаем успех
        return response()->json([
            'success' => true,
            'message' => $isActive ? 'Репозиторий разблокирован' : 'Репозиторий заблокирован',
            'data' => [
                'id' => $repository->id,
                'name' => $repository->name,
                'is_active' => $isActive,
                'username' => $eventAccount->login, // оригинальный логин из event_accounts
                'db_unchanged' => true // подтверждение что БД не трогали
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error("❌ Ошибка: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Блокировать/разблокировать все репозитории модуля
 */

public function bulkToggleRepositories($moduleId, Request $request)
{
    try {
        $isActive = $request->input('is_active', false);
        
        Log::info("🔄 Массовое переключение репозиториев модуля {$moduleId}: " . 
                 ($isActive ? 'разблокировка' : 'блокировка'));
        
        $repositoryService = new RepositoryService();
        
        // Используем публичный метод если он есть
        if (method_exists($repositoryService, 'bulkToggleRepositories')) {
            $results = $repositoryService->bulkToggleRepositories($moduleId, $isActive);
        } else {
            // Или реализуем логику прямо здесь
            throw new \Exception('Метод bulkToggleRepositories не реализован в сервисе');
        }
        
        $actionText = $isActive ? 'разблокированы' : 'заблокированы';
        
        Log::info("✅ Массовая операция завершена: {$results['updated']} репозиториев {$actionText}");
        
        return response()->json([
            'success' => true,
            'message' => "{$results['updated']} репозиториев {$actionText}",
            'data' => $results
        ]);
        
    } catch (\Exception $e) {
        Log::error("❌ Ошибка массового переключения репозиториев модуля {$moduleId}: " . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Создать публичный репозиторий для модуля
     */
    public function createPublicRepository($moduleId)
    {
        try {
            $result = $this->repositoryService->createPublicRepository($moduleId);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка создания публичного репозитория: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить публичный репозиторий модуля
     */
    public function getPublicRepository($moduleId)
    {
        try {
        \Log::info("Запрос публичного репозитория для модуля {$moduleId}");
        
        // Ищем публичный репозиторий
        $repository = \App\Models\Repository::where('module_id', $moduleId)
            ->where(function($query) {
                $query->whereHas('type', function($q) {
                        $q->where('name', 'Публичный');
                    })
                    ->orWhereJsonContains('metadata->is_public', true);
            })
            ->with(['eventAccount.user', 'eventAccount.role'])
            ->first();
        
        \Log::info("Результат поиска: " . ($repository ? 'найден' : 'не найден'));
        
        if (!$repository) {
            \Log::info("Публичный репозиторий не найден для модуля {$moduleId}");
            return response()->json([
                'success' => false,
                'message' => 'Публичный репозиторий не найден',
                'data' => null
            ], 404);
        }
        
        \Log::info("Найден репозиторий ID: {$repository->id}, имя: {$repository->name}");
        
        // Формируем информацию о владельце
        $ownerInfo = null;
        if ($repository->eventAccount) {
            $ownerInfo = [
                'name' => $repository->eventAccount->user->name ?? 'Неизвестно',
                'role' => $repository->eventAccount->role->name ?? 'Неизвестно',
                'email' => $repository->eventAccount->user->email ?? null
            ];
        }
        
        $responseData = [
            'success' => true,
            'data' => [
                'id' => $repository->id,
                'name' => $repository->name,
                'url' => $repository->url,
                'description' => $repository->description,
                'clone_url' => $repository->clone_url,
                'is_active' => (bool)$repository->is_active,
                'metadata' => $repository->metadata ?? [],
                'owner' => $ownerInfo,
                'created_at' => $repository->created_at?->toISOString(),
                'updated_at' => $repository->updated_at?->toISOString()
            ],
            'message' => 'Публичный репозиторий найден'
        ];
        
        \Log::info("Отправляем ответ: " . json_encode($responseData));
        
        return response()->json($responseData);
        
    } catch (\Exception $e) {
        \Log::error('Ошибка получения публичного репозитория: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage(),
            'data' => null
        ], 500);
    }
    }

    /**
 * Настроить доступ к публичному репозиторию для всех участников
 */
public function setupPublicRepositoryAccess($moduleId)
{
    try {
        $results = $this->repositoryService->setupPublicRepositoryAccess($moduleId);
        
        return response()->json([
            'success' => true,
            'message' => "Добавлен доступ для {$results['added_collaborators']} пользователей",
            'data' => $results
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Настроить гранулярный доступ к публичному репозиторию
 */
public function setupGranularAccess($moduleId)
{
    try {
        $results = $this->repositoryService->setupGranularPublicRepositoryAccess($moduleId);
        
        return response()->json([
            'success' => true,
            'message' => 'Права доступа успешно настроены',
            'data' => $results
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Проверить текущие права доступа
 */
public function checkAccess($moduleId)
{
    try {
        $analysis = $this->repositoryService->checkPublicRepositoryAccess($moduleId);
        
        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}
