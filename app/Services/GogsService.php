<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GogsService
{
    protected $baseUrl;
    protected $token;
    protected $headers;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gogs.url'), '/');
        $this->token = config('services.gogs.api_token');
        
        if (empty($this->token)) {
            throw new \Exception('Gogs API token не настроен. Проверьте .env файл.');
        }
        
        if (empty($this->baseUrl) || $this->baseUrl === 'http://localhost:3000') {
            throw new \Exception('Gogs URL не настроен в .env');
        }
        
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'token ' . $this->token,
        ];
    }

    /**
     * Проверка подключения к Gogs
     */
    public function testConnection()
    {
        try {
        $response = Http::withHeaders($this->headers)
            ->timeout(10)
            ->get($this->baseUrl . '/api/v1/user');
            
        if ($response->successful()) {
            return [
                'success' => true,
                'status' => 'connected',
                'user' => $response->json()['username'] ?? 'unknown',
                'url' => $this->baseUrl, // ← ДОБАВЬТЕ ЭТО!
                'message' => '✅ Gogs сервер доступен'
            ];
        }
        
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Gogs вернул ошибку: ' . $response->status(),
            'url' => $this->baseUrl // ← И ЗДЕСЬ!
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Не удалось подключиться к Gogs: ' . $e->getMessage(),
            'url' => $this->baseUrl // ← И ЗДЕСЬ!
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
    public function getRepository($owner, $repoName)
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get($this->baseUrl . '/api/v1/repos/' . $owner . '/' . $repoName);
                
            if ($response->successful()) {
                $repoData = $response->json();
                $repoData['clone_url_http'] = $this->baseUrl . '/' . $repoData['full_name'] . '.git';
                $repoData['html_url'] = $this->baseUrl . '/' . $repoData['full_name'];
                
                return [
                    'success' => true,
                    'repository' => $repoData
                ];
            }
            
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => 'Репозиторий не найден'
            ];
            
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
                ->get($this->baseUrl . '/api/v1/users/' . $username . '/repos');
                
            if ($response->successful()) {
                $repos = $response->json();
                
                // Добавляем HTTP ссылки
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
}