<?php

namespace App\Http\Controllers;

use App\Services\RepositoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExpertController extends Controller
{
    protected $repositoryService;
    
    public function __construct(RepositoryService $repositoryService)
    {
        $this->repositoryService = $repositoryService;
    }
    
    /**
     * Получить список экспертов модуля
     */
    public function getModuleExperts($moduleId)
    {
        try {
            $module = \App\Models\Module::findOrFail($moduleId);
            
            if (!$module->event_id) {
                throw new \Exception('Модуль не привязан к мероприятию');
            }
            
            // Роли экспертов
            $expertRoleNames = ['Эксперт', 'Главный эксперт', 'Технический эксперт'];
            
            $experts = \App\Models\EventAccount::where('event_id', $module->event_id)
                ->whereHas('role', function($query) use ($expertRoleNames) {
                    $query->whereIn('name', $expertRoleNames);
                })
                ->with(['user', 'role'])
                ->get()
                ->map(function($account) {
                    $user = $account->user;
                    
                    // Формируем ФИО
                    $fullName = '';
                    if ($user->last_name || $user->first_name || $user->middle_name) {
                        $fullName = trim($user->last_name . ' ' . $user->first_name . ' ' . $user->middle_name);
                    } else {
                        $fullName = $user->name ?? 'Неизвестно';
                    }
                    
                    return [
                        'id' => $account->id,
                        'user_id' => $user->id,
                        'name' => $fullName,
                        'login' => $account->login,
                        'role' => $account->role->name,
                        'email' => $user->email,
                        'has_gogs_account' => !empty($account->password_plain),
                        'password_plain' => $account->password_plain ? '********' : null
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $experts
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка получения экспертов модуля: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Создать учетные записи экспертов для модуля
     */
    public function createExpertAccounts($moduleId)
    {
        try {
            $results = $this->repositoryService->createExpertAccounts($moduleId);
            
            return response()->json([
                'success' => true,
                'message' => "Создано {$results['successful']} учетных записей экспертов",
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка создания учетных записей экспертов: ' . $e->getMessage());
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
            // Ищем публичный репозиторий по типу или по метаданным
            $repository = \App\Models\Repository::where('module_id', $moduleId)
                ->where(function($query) {
                    $query->whereHas('type', function($q) {
                            $q->where('name', 'Публичный');
                        })
                        ->orWhereJsonContains('metadata->is_public', true);
                })
                ->first();
            
            if (!$repository) {
                return response()->json([
                    'success' => false,
                    'message' => 'Публичный репозиторий не найден'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $repository
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Пересоздать учетную запись эксперта
     */
    public function recreateExpertAccount($moduleId, $expertId)
    {
        try {
            $result = $this->repositoryService->recreateExpertAccount($moduleId, $expertId);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка пересоздания учетной записи эксперта: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}