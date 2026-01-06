<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Repository;
use App\Services\GogsService;
use App\Services\RepositoryService;


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
}
