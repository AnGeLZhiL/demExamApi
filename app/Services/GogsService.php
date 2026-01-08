<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Repository;

class GogsService
{
    protected $baseUrl;
    protected $token;
    protected $headers;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gogs.url'), '/');
    
        // Пробуем разные варианты токена
        $token = config('services.gogs.api_token') 
                ?: config('services.gogs.token') 
                ?: env('GOGS_TOKEN');
        
        if (empty($token)) {
            throw new \Exception('Gogs API token не настроен. Проверьте .env файл (GOGS_TOKEN или GOGS_API_TOKEN)');
        }
        
        if (empty($this->baseUrl) || $this->baseUrl === 'http://localhost:3000') {
            throw new \Exception('Gogs URL не настроен в .env (GOGS_URL)');
        }
        
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'token ' . $token,
        ];
        
        Log::info("GogsService инициализирован: {$this->baseUrl}, токен установлен: " . substr($token, 0, 10) . "...");
    }

    /**
     * Проверка подключения к Gogs
     */
    public function testConnection()
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying() // ← ВАЖНО! Отключаем SSL проверку
                ->get($this->baseUrl . '/api/v1/user');
                
            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'connected',
                    'user' => $response->json()['username'] ?? 'unknown',
                    'url' => $this->baseUrl,
                    'message' => '✅ Gogs сервер доступен'
                ];
            }
            
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Gogs вернул ошибку: ' . $response->status(),
                'url' => $this->baseUrl
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Не удалось подключиться к Gogs: ' . $e->getMessage(),
                'url' => $this->baseUrl
            ];
        }
    }

    /**
     * Создать пользователя в Gogs
     */
    public function createUser($username, $fullName, $email = null)
    {
        if (!$email) {
            $email = $username . config('services.gogs.email_domain', '@exam.local');
        }
        
        $password = Str::random(12);
        
        $data = [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'password' => $password,
            'send_notify' => false,
        ];
        
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying() // ← И здесь
                ->post($this->baseUrl . '/api/v1/admin/users', $data);
                
            if ($response->successful()) {
                return [
                    'success' => true,
                    'user' => $response->json(),
                    'password' => $password,
                    'message' => 'Пользователь создан'
                ];
            }
            
            // Если пользователь уже существует
            if ($response->status() === 422) {
                return [
                    'success' => true,
                    'user' => ['username' => $username],
                    'password' => null,
                    'message' => 'Пользователь уже существует'
                ];
            }
            
            throw new \Exception('Ошибка создания пользователя: ' . $response->body());
            
        } catch (\Exception $e) {
            Log::error('Gogs create user error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Создать приватный репозиторий
     */
    public function createRepository($owner, $repoName, $description = null)
    {
        if (!$description) {
            $description = 'Экзаменационный репозиторий для ' . $repoName;
        }
        
        $data = [
            'name' => $repoName,
            'description' => $description,
            'private' => true,
            'auto_init' => false, // Важно! false работает стабильнее
        ];
        
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying() // ← И здесь
                ->post($this->baseUrl . '/api/v1/user/repos', $data);
                
            if ($response->successful()) {
                $repoData = $response->json();
                
                // Формируем HTTP ссылку для клонирования
                $cloneUrl = $this->baseUrl . '/' . $repoData['full_name'] . '.git';
                $webUrl = $this->baseUrl . '/' . $repoData['full_name'];
                
                return [
                    'success' => true,
                    'repository' => array_merge($repoData, [
                        'clone_url_http' => $cloneUrl,
                        'html_url' => $webUrl
                    ]),
                    'clone_url' => $cloneUrl,
                    'web_url' => $webUrl,
                    'message' => 'Репозиторий создан'
                ];
            }
            
            throw new \Exception('Ошибка создания репозитория: ' . $response->body());
            
        } catch (\Exception $e) {
            Log::error('Gogs create repository error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
 * Получить информацию о репозитории
 */
public function getRepository($owner, $repo)
{
    $url = "{$this->baseUrl}/api/v1/repos/{$owner}/{$repo}";
    
    try {
        $response = Http::withHeaders($this->headers)
            ->get($url);
        
        if ($response->successful()) {
            return [
                'success' => true,
                'repository' => $response->json(),
                'is_private' => $response->json()['private'] ?? true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Не удалось получить информацию о репозитории',
                'status' => $response->status()
            ];
        }
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

    /**
     * Получить список репозиториев пользователя
     */
    public function getUserRepositories($username)
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying() // ← ДОБАВЬТЕ
                ->get($this->baseUrl . '/api/v1/users/' . $username . '/repos');
                
            if ($response->successful()) {
                $repos = $response->json();
                
                foreach ($repos as &$repo) {
                    $repo['clone_url_http'] = $this->baseUrl . '/' . $repo['full_name'] . '.git';
                    $repo['html_url'] = $this->baseUrl . '/' . $repo['full_name'];
                }
                
                return [
                    'success' => true,
                    'repositories' => $repos,
                    'count' => count($repos)
                ];
            }
            
            return [
                'success' => false,
                'status' => $response->status()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создать репозиторий для конкретного пользователя
     * (репозиторий будет в аккаунте пользователя)
     */
    public function createRepositoryForUser($username, $repoName, $description = null)
    {
        if (!$description) {
            $description = 'Экзаменационный репозиторий для ' . $username;
        }
        
        $data = [
            'name' => $repoName,
            'description' => $description,
            'private' => true,
            'auto_init' => false,
        ];
        
        try {
            // Важно: API /admin/users/{username}/repos создает репозиторий В АККАУНТЕ пользователя
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying()
                ->post($this->baseUrl . '/api/v1/admin/users/' . $username . '/repos', $data);
                
            if ($response->successful()) {
                $repoData = $response->json();
                
                // Формируем URL
                $cloneUrl = $this->baseUrl . '/' . $repoData['full_name'] . '.git';
                $webUrl = $this->baseUrl . '/' . $repoData['full_name'];
                
                return [
                    'success' => true,
                    'repository' => array_merge($repoData, [
                        'clone_url_http' => $cloneUrl,
                        'html_url' => $webUrl
                    ]),
                    'clone_url' => $cloneUrl,
                    'web_url' => $webUrl,
                    'message' => 'Репозиторий создан в аккаунте пользователя'
                ];
            }
            
            // Если API вернул ошибку, логируем и пробуем создать под админом
            Log::warning("Не удалось создать репозиторий в аккаунте {$username}: " . $response->body());
            
            // Fallback: создаем под админом
            return $this->createRepository('adminangelina', $repoName, $description);
            
        } catch (\Exception $e) {
            Log::error("Ошибка создания репозитория в аккаунте {$username}: " . $e->getMessage());
            
            // Fallback: создаем под админом
            return $this->createRepository('adminangelina', $repoName, $description);
        }
    }

    /**
     * Создать пользователя с указанными учетными данными
     */
    public function createUserWithCredentials($username, $password, $fullName, $email = null)
    {
        if (!$email) {
            $email = $username . config('services.gogs.email_domain', '@exam.local');
        }
        
        $data = [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'password' => $password,
            'send_notify' => false,
        ];
        
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->withoutVerifying()
                ->post($this->baseUrl . '/api/v1/admin/users', $data);
                
            if ($response->successful()) {
                return [
                    'success' => true,
                    'user' => $response->json(),
                    'password' => $password,
                    'message' => 'Пользователь создан с указанными учетными данными'
                ];
            }
            
            // Если пользователь уже существует
            if ($response->status() === 422) {
                return [
                    'success' => true,
                    'user' => ['username' => $username],
                    'password' => $password,
                    'message' => 'Пользователь уже существует'
                ];
            }
            
            throw new \Exception('Ошибка создания пользователя: ' . $response->body());
            
        } catch (\Exception $e) {
            Log::error('Gogs create user with credentials error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
 * Удалить репозиторий из Gogs
 */
public function deleteRepository($owner, $repoName)
{
    try {
        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->withoutVerifying()
            ->delete($this->baseUrl . '/api/v1/repos/' . $owner . '/' . $repoName);
            
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Репозиторий удален из Gogs'
            ];
        }
        
        // Если репозиторий не найден (уже удален) - считаем успехом
        if ($response->status() === 404) {
            return [
                'success' => true,
                'message' => 'Репозиторий уже удален или не существует'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Ошибка удаления репозитория: ' . $response->body()
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка удаления репозитория: ' . $e->getMessage()
        ];
    }
}

/**
 * Удалить пользователя из Gogs
 */
public function deleteUser($username)
{
    try {
        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->withoutVerifying()
            ->delete($this->baseUrl . '/api/v1/admin/users/' . $username);
            
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Пользователь удален из Gogs'
            ];
        }
        
        // Если пользователь не найден - считаем успехом
        if ($response->status() === 404) {
            return [
                'success' => true,
                'message' => 'Пользователь уже удален или не существует'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Ошибка удаления пользователя: ' . $response->body()
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка удаления пользователя: ' . $e->getMessage()
        ];
    }
}

/**
 * Удалить ВСЕ репозитории модуля (через админский API)
 */
public function deleteAllModuleRepositories($moduleId)
{
    // Получаем все репозитории модуля
    $repositories = Repository::where('module_id', $moduleId)->get();
    
    $results = [
        'total' => $repositories->count(),
        'repositories_deleted' => 0,
        'users_deleted' => 0,
        'errors' => 0,
        'details' => []
    ];
    
    foreach ($repositories as $repo) {
        try {
            $metadata = $repo->metadata ?? [];
            $owner = $metadata['gogs_owner'] ?? null;
            $repoName = $metadata['gogs_repo_name'] ?? $repo->name;
            
            if (!$owner || !$repoName) {
                throw new \Exception('Нет данных о владельце репозитория');
            }
            
            // 1. Удаляем репозиторий
            $deleteRepoResult = $this->deleteRepository($owner, $repoName);
            
            if ($deleteRepoResult['success']) {
                $results['repositories_deleted']++;
                Log::info("Удален репозиторий: {$owner}/{$repoName}");
            } else {
                $results['errors']++;
                Log::warning("Не удалось удалить репозиторий: {$deleteRepoResult['message']}");
            }
            
            // 2. Удаляем пользователя (опционально - если хотите полную очистку)
            $deleteUserResult = $this->deleteUser($owner);
            
            if ($deleteUserResult['success']) {
                $results['users_deleted']++;
                Log::info("Удален пользователь: {$owner}");
            }
            
            $results['details'][] = [
                'repository_id' => $repo->id,
                'repository_name' => $repoName,
                'owner' => $owner,
                'repo_deleted' => $deleteRepoResult['success'],
                'user_deleted' => $deleteUserResult['success'],
                'errors' => $deleteRepoResult['success'] ? null : $deleteRepoResult['message']
            ];
            
        } catch (\Exception $e) {
            $results['errors']++;
            $results['details'][] = [
                'repository_id' => $repo->id,
                'repository_name' => $repo->name,
                'error' => $e->getMessage()
            ];
            Log::error("Ошибка удаления репозитория {$repo->id}: " . $e->getMessage());
        }
    }
    
    // 3. Удаляем записи из БД
    if ($results['repositories_deleted'] > 0) {
        Repository::where('module_id', $moduleId)->delete();
        Log::info("Удалены записи репозиториев из БД для модуля {$moduleId}");
    }
    
    return $results;
}

/**
 * Пересоздать репозитории модуля (удалить старые, создать новые)
 */
public function recreateModuleRepositories($moduleId)
{
    try {
        // 1. Сначала удаляем всё
        $deletionResult = $this->deleteAllModuleRepositories($moduleId);
        
        Log::info("Удаление завершено: " . json_encode($deletionResult));
        
        // 2. Создаем новые
        $repositoryService = new RepositoryService();
        $creationResult = $repositoryService->createRepositoriesForModule($moduleId);
        
        return [
            'success' => true,
            'deletion' => $deletionResult,
            'creation' => $creationResult,
            'message' => "Пересоздано {$creationResult['successful']} репозиториев"
        ];
        
    } catch (\Exception $e) {
        Log::error('Ошибка пересоздания репозиториев: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Добавить коллаборатора в репозиторий
 */
public function addCollaborator($owner, $repo, $collaborator, $permission = 'write')
{
    try {
        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->withoutVerifying()
            ->put($this->baseUrl . "/api/v1/repos/{$owner}/{$repo}/collaborators/{$collaborator}", [
                'permission' => $permission
            ]);
            
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Коллаборатор добавлен',
                'data' => $response->json()
            ];
        }
        
        // Если пользователь уже добавлен
        if ($response->status() === 409) {
            return [
                'success' => true,
                'message' => 'Коллаборатор уже добавлен'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Ошибка добавления коллаборатора: ' . $response->body()
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка добавления коллаборатора: ' . $e->getMessage()
        ];
    }
}

/**
 * Удалить коллаборатора из репозитория
 */
public function removeCollaborator($owner, $repo, $collaborator)
{
    try {
        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->withoutVerifying()
            ->delete($this->baseUrl . "/api/v1/repos/{$owner}/{$repo}/collaborators/{$collaborator}");
            
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Коллаборатор удален'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Ошибка удаления коллаборатора: ' . $response->body()
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка удаления коллаборатора: ' . $e->getMessage()
        ];
    }
}

/**
 * Получить права пользователя в репозитории
 */
public function getUserRepositoryPermission($owner, $repo, $username)
{
    $url = "{$this->baseUrl}/api/v1/repos/{$owner}/{$repo}/collaborators/{$username}";
    
    try {
        $response = Http::withHeaders($this->headers)
            ->get($url);
        
        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'permission' => $data['permission'] ?? 'unknown',
                'user' => $data['user'] ?? null,
                'data' => $data
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Не удалось получить права пользователя',
                'status' => $response->status(),
                'data' => $response->json()
            ];
        }
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Получить список коллабораторов репозитория
 */
public function getRepositoryCollaborators($owner, $repo)
{
    try {
        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->withoutVerifying()
            ->get($this->baseUrl . "/api/v1/repos/{$owner}/{$repo}/collaborators");
            
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json()
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Ошибка получения коллабораторов: ' . $response->body()
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка получения коллабораторов: ' . $e->getMessage()
        ];
    }
}

/**
 * Проверить существование пользователя в Gogs
 */
public function getUser($username)
{
    if (config('services.gogs.mock')) {
        return [
            'success' => true,
            'data' => [
                'id' => 1, 
                'login' => $username,
                'email' => $username . '@exam.local',
                'full_name' => $username
            ],
            'mock' => true
        ];
    }
    
    $url = "{$this->baseUrl}/api/v1/users/{$username}";
    
    try {
        $response = Http::withHeaders($this->headers)
            ->withOptions(['verify' => false])
            ->get($url);
        
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json()
            ];
        } else {
            // Если пользователь не найден, возвращаем базовые данные
            return [
                'success' => true, // все равно true, потому что будем использовать fallback
                'data' => [
                    'email' => $username . '@exam.local',
                    'full_name' => $username,
                    'login' => $username
                ],
                'not_found' => true
            ];
        }
        
    } catch (\Exception $e) {
        Log::error("Ошибка получения пользователя {$username}: " . $e->getMessage());
        return [
            'success' => true, // fallback
            'data' => [
                'email' => $username . '@exam.local',
                'full_name' => $username,
                'login' => $username
            ],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получить заголовки для API запросов
 */
protected  function getHeaders()
{
    $token = config('services.gogs.token');
    
    if (empty($token)) {
        throw new \Exception('Токен Gogs не настроен в конфигурации');
    }
    
    return [
        'Authorization' => 'token ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

/**
 * Обновить права коллаборатора
 */
public function updateCollaboratorPermission($owner, $repo, $username, $permission)
{
    $url = "{$this->baseUrl}/api/v1/repos/{$owner}/{$repo}/collaborators/{$username}";
    
    try {
        $response = Http::withHeaders($this->headers)
            ->put($url, [
                'permission' => $permission
            ]);
        
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Права обновлены',
                'data' => $response->json()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Не удалось обновить права',
                'status' => $response->status(),
                'data' => $response->json()
            ];
        }
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Изменить пароль пользователя в Gogs
 */
public function changeUserPassword($username, $newPassword)
{
    if (config('services.gogs.mock')) {
        Log::info("Mock: Пароль изменен для {$username} на {$newPassword}");
        return [
            'success' => true,
            'message' => 'Mock: Пароль изменен',
            'mock' => true
        ];
    }
    
    $url = "{$this->baseUrl}/api/v1/admin/users/{$username}";
    
    Log::info("🔄 Изменение пароля для {$username}");
    Log::info("📝 URL: {$url}");
    Log::info("🔑 Новый пароль (первые 5 символов): " . substr($newPassword, 0, 5) . "...");
    
    try {
        // 1. Сначала получаем информацию о пользователе
        $userInfo = $this->getUser($username);
        
        if (!$userInfo['success']) {
            Log::error("❌ Пользователь {$username} не найден в Gogs");
            return [
                'success' => false,
                'message' => 'Пользователь не найден в Gogs'
            ];
        }
        
        $userData = $userInfo['data'];
        $email = $userData['email'] ?? ($username . '@exam.local');
        
        // 2. Подготавливаем данные для обновления
        $updateData = [
            'password' => $newPassword,
            'email' => $email,
            'full_name' => $userData['full_name'] ?? $username,
        ];
        
        // 3. Меняем пароль
        $response = Http::withHeaders($this->headers)
            ->withOptions([
                'verify' => false,
                'timeout' => 30,
            ])
            ->patch($url, $updateData);
        
        Log::info("📊 Статус ответа: " . $response->status());
        Log::info("📦 Тело ответа: " . $response->body());
        
        if ($response->successful()) {
            Log::info("✅ Пароль успешно изменен для {$username}");
            return [
                'success' => true,
                'message' => 'Пароль успешно изменен',
                'status' => $response->status(),
                'data' => $response->json()
            ];
        } else {
            Log::error("❌ Не удалось изменить пароль: " . $response->body());
            
            // Если ошибка 422 - пробуем альтернативный способ
            if ($response->status() === 422) {
                return $this->changeUserPasswordAlternative($username, $newPassword, $userData);
            }
            
            return [
                'success' => false,
                'message' => 'Не удалось изменить пароль. Статус: ' . $response->status(),
                'status' => $response->status(),
                'data' => $response->json(),
                'body' => $response->body()
            ];
        }
        
    } catch (\Exception $e) {
        Log::error("❌ Ошибка при вызове API: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Ошибка при вызове API: ' . $e->getMessage()
        ];
    }
}

/**
 * Комплексная блокировка пользователя (пробуем все способы)
 */
public function blockUserCompletely($username, $lockPassword)
{
    $url = "{$this->baseUrl}/api/v1/admin/users/{$username}";
    
    $results = [];
    
    // 1. Сначала меняем пароль (основной способ)
    $results['password_changed'] = $this->changeUserPassword($username, $lockPassword);
    
    // 2. Пробуем изменить логин (самый надежный для сброса сессии)
    $temporaryLogin = 'locked_' . time() . '_' . $username;
    $results['login_changed'] = $this->changeUserAttribute($username, [
        'login_name' => $temporaryLogin
    ]);
    
    // 3. Пробуем изменить email
    $temporaryEmail = 'locked_' . time() . '_' . $username . '@exam.local';
    $results['email_changed'] = $this->changeUserAttribute($username, [
        'email' => $temporaryEmail
    ]);
    
    // 4. Пробуем заблокировать через разные флаги
    $blockFlags = [
        ['prohibit_login' => true],
        ['active' => false],
        ['is_active' => false],
        ['status' => 'inactive'],
        ['login_prohibited' => true],
        ['suspended' => true],
    ];
    
    foreach ($blockFlags as $flags) {
        $result = $this->changeUserAttribute($username, $flags);
        if ($result['success']) {
            $results['blocked_by_flags'] = [
                'success' => true,
                'flags' => $flags,
                'data' => $result
            ];
            break;
        }
    }
    
    // 5. Если ничего не сработало - пробуем полное обновление
    if (!isset($results['blocked_by_flags'])) {
        $fullUpdate = $this->changeUserAttribute($username, [
            'login_name' => $temporaryLogin,
            'email' => $temporaryEmail,
            'full_name' => '[ЗАБЛОКИРОВАНО] ' . ($username),
            'password' => $lockPassword,
            'send_notify' => false,
            'source_id' => 0,
        ]);
        
        $results['full_update'] = $fullUpdate;
    }
    
    return [
        'success' => true,
        'message' => 'Комплексная блокировка выполнена',
        'results' => $results,
        'temporary_credentials' => [
            'login' => $temporaryLogin,
            'email' => $temporaryEmail,
            'password' => $lockPassword
        ]
    ];
}
/**
 * Изменить атрибут пользователя (КОРРЕКТНАЯ версия)
 */
private function changeUserAttribute($username, $attributes)
{
    $url = "{$this->baseUrl}/api/v1/admin/users/{$username}";
    
    try {
        Log::info("Изменение атрибутов пользователя {$username}: " . json_encode($attributes));
        
        // 1. Сначала получаем ТЕКУЩИЕ данные пользователя
        $currentData = $this->getUser($username)['data'] ?? [];
        
        // 2. Объединяем с новыми атрибутами
        $fullData = array_merge([
            'email' => $currentData['email'] ?? ($username . '@exam.local'),
            'full_name' => $currentData['full_name'] ?? $username,
            'password' => 'temporary_password_' . Str::random(8), // временный пароль
            'send_notify' => false,
            'source_id' => 0,
        ], $attributes);
        
        Log::info("Полные данные для отправки: " . json_encode($fullData));
        
        // 3. Отправляем
        $response = Http::withHeaders($this->headers)
            ->withOptions(['verify' => false])
            ->patch($url, $fullData);
        
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Атрибуты изменены',
                'data' => $response->json()
            ];
        } else {
            Log::error("Ошибка изменения атрибутов: " . $response->body());
            return [
                'success' => false,
                'message' => 'Не удалось изменить: ' . $response->body(),
                'status' => $response->status(),
                'body' => $response->body()
            ];
        }
        
    } catch (\Exception $e) {
        Log::error("Исключение при изменении атрибутов: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Изменить логин пользователя (упрощенная версия)
 */
public function changeUserLoginSimple($oldUsername, $newLogin)
{
    // Формируем временный email на основе нового логина
    $temporaryEmail = $newLogin . '@exam.local';
    
    $data = [
        'login_name' => $newLogin,
        'email' => $temporaryEmail,
        'full_name' => '[LOCKED] ' . $oldUsername,
        'password' => 'LOCKED_' . Str::random(20),
        'send_notify' => false,
    ];
    
    $url = "{$this->baseUrl}/api/v1/admin/users/{$oldUsername}";
    
    try {
        Log::info("Изменение логина: {$oldUsername} → {$newLogin}");
        Log::info("Данные: " . json_encode($data));
        
        $response = Http::withHeaders($this->headers)
            ->withOptions(['verify' => false])
            ->patch($url, $data);
        
        if ($response->successful()) {
            Log::info("✅ Логин изменен: {$oldUsername} → {$newLogin}");
            return [
                'success' => true,
                'old_login' => $oldUsername,
                'new_login' => $newLogin,
                'data' => $response->json()
            ];
        } else {
            Log::error("❌ Ошибка: " . $response->body());
            
            // Если ошибка 422, пробуем с текущим email
            if ($response->status() === 422) {
                return $this->changeUserLoginWithCurrentEmail($oldUsername, $newLogin);
            }
            
            return [
                'success' => false,
                'message' => 'Не удалось изменить логин: ' . $response->body(),
                'status' => $response->status()
            ];
        }
        
    } catch (\Exception $e) {
        Log::error("Ошибка: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Изменить логин с текущим email
 */
private function changeUserLoginWithCurrentEmail($oldUsername, $newLogin)
{
    // Сначала получаем текущий email
    $userInfo = $this->getUser($oldUsername);
    $currentEmail = $userInfo['data']['email'] ?? ($oldUsername . '@exam.local');
    
    $data = [
        'login_name' => $newLogin,
        'email' => $currentEmail, // используем текущий email
        'full_name' => $userInfo['data']['full_name'] ?? $oldUsername,
        'password' => 'LOCKED_' . Str::random(20),
        'send_notify' => false,
        'source_id' => 0,
    ];
    
    $url = "{$this->baseUrl}/api/v1/admin/users/{$oldUsername}";
    
    try {
        Log::info("Попытка с текущим email: {$currentEmail}");
        
        $response = Http::withHeaders($this->headers)
            ->withOptions(['verify' => false])
            ->patch($url, $data);
        
        if ($response->successful()) {
            return [
                'success' => true,
                'old_login' => $oldUsername,
                'new_login' => $newLogin,
                'data' => $response->json()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Вторая попытка тоже не удалась: ' . $response->body()
            ];
        }
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
}