<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Database;
use App\Models\Module;
use App\Services\DatabaseService;

class DatabaseController extends Controller
{
    protected $databaseService;
    
    public function __construct()
    {
        $this->databaseService = new DatabaseService();
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Database::with(['server', 'type', 'eventAccount.user', 'module', 'status'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $database = Database::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => $request->password,
            'server_id' => $request->server_id,
            'type_id' => $request->type_id,
            'event_account_id' => $request->event_account_id,
            'module_id' => $request->module_id,
            'status_id' => $request->status_id,
            'is_active' => $request->is_active ?? true,
            'is_public' => $request->is_public ?? false,
            'has_demo_data' => false,
            'is_empty' => true
        ]);
        
        return response()->json($database, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $database = Database::with(['server', 'type', 'eventAccount.user', 'module', 'status'])->find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        return $database;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $database = Database::find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        $database->update($request->only([
            'name', 'username', 'password', 'is_active', 'is_public', 'status_id'
        ]));
        
        return $database;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $database = Database::find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        $database->delete();
        return response()->noContent();
    }
    
    /**
     * Проверить подключение к PostgreSQL серверу
     */
    public function testConnection()
    {
        try {
            $result = $this->databaseService->testPostgresConnection();
            
            return response()->json([
                'success' => true,
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
     * Создать БД для всех участников модуля
     */
    /**
 * Создать БД для всех участников модуля
 */
public function createForModule(Request $request, $moduleId)
{
    try {
        // 1. Находим модуль
        $module = \App\Models\Module::with('event')->find($moduleId);
        if (!$module) {
            return response()->json([
                'success' => false, 
                'message' => 'Module not found'
            ], 404);
        }
        
        // 2. Находим участников
        $participantRole = \App\Models\Role::where('name', 'Участник')->first();
        $participants = \App\Models\EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRole->id)
            ->get();
        
        if ($participants->isEmpty()) {
            return response()->json([
                'success' => false, 
                'message' => 'No participants'
            ], 400);
        }
        
        // 3. Подключение к PostgreSQL
        $pdo = new \PDO(
            "pgsql:host=" . env('DB_HOST', 'localhost') . ";port=" . env('DB_PORT', 5432),
            env('DB_USERNAME', 'postgres'),
            env('DB_PASSWORD', '061241angel'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        // 4. Для каждого участника создаем БД
        foreach ($participants as $participant) {
            try {
                $username = $participant->login;
                $password = $participant->password_plain;
                
                // Генерируем КОРОТКОЕ имя БД
                $dbName = $this->generateShortDbName($module, $participant);
                
                // ШАГ 1: Удаляем ВСЕ базы данных пользователя
                $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datdba = (SELECT oid FROM pg_roles WHERE rolname = '{$username}')");
                $userDatabases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                
                foreach ($userDatabases as $oldDb) {
                    // Завершаем все соединения
                    $pdo->exec("
                        SELECT pg_terminate_backend(pid) 
                        FROM pg_stat_activity 
                        WHERE datname = '{$oldDb}' 
                        AND pid <> pg_backend_pid()
                    ");
                    // Удаляем БД
                    $pdo->exec("DROP DATABASE IF EXISTS \"{$oldDb}\"");
                }
                
                // ШАГ 2: Удаляем пользователя (теперь можно, т.к. нет БД)
                $pdo->exec("DROP USER IF EXISTS \"{$username}\"");
                
                // ШАГ 3: Создаем нового пользователя
                $escapedPassword = str_replace("'", "''", $password);
                $pdo->exec("CREATE USER \"{$username}\" WITH PASSWORD '{$escapedPassword}'");
                
                // ШАГ 4: Создаем новую БД
                $pdo->exec("CREATE DATABASE \"{$dbName}\" OWNER = \"{$username}\" ENCODING = 'UTF8' TEMPLATE = template0");
                
                // ШАГ 5: Проверяем подключение
                $userPdo = new \PDO(
                    "pgsql:host=localhost;port=5432;dbname={$dbName}",
                    $username,
                    $password,
                    [\PDO::ATTR_TIMEOUT => 2, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                
                $stmt = $userPdo->query("SELECT current_database()");
                $currentDb = $stmt->fetchColumn();
                
                // ШАГ 6: Настраиваем права
                $userPdo->exec("
    -- 1. Личная схема
    CREATE SCHEMA IF NOT EXISTS \"user_{$username}\";
    
    -- 2. Все права на свою схему
    GRANT ALL ON SCHEMA \"user_{$username}\" TO \"{$username}\";
    
    -- 3. Только использование публичной схемы
    GRANT USAGE ON SCHEMA public TO \"{$username}\";
    
    -- 4. Запрет создания в публичной
    REVOKE CREATE ON SCHEMA public FROM \"{$username}\";
    
    -- 5. Блокировка системных схем
    REVOKE ALL ON SCHEMA pg_catalog FROM \"{$username}\";
    REVOKE ALL ON SCHEMA information_schema FROM \"{$username}\";
    
    -- 6. Права по умолчанию
    ALTER DEFAULT PRIVILEGES IN SCHEMA \"user_{$username}\"
    GRANT ALL ON TABLES TO \"{$username}\";
    
    -- 7. Отключаем PUBLIC права
    REVOKE ALL ON DATABASE \"{$dbName}\" FROM PUBLIC;
    REVOKE ALL ON SCHEMA public FROM PUBLIC;
    -- 8. Запрещаем доступ к другим БД (если есть права)
    -- REVOKE CONNECT ON DATABASE postgres FROM \"{$username}\";
    
    -- 9. Только SELECT на public (если нужны справочники)
    -- GRANT SELECT ON ALL TABLES IN SCHEMA public TO \"{$username}\";
    
    -- 10. Запрещаем изменять права
    REVOKE ALL ON SCHEMA \"user_{$username}\" FROM PUBLIC;
    
    -- 11. Только владелец может изменять свои объекты
    ALTER SCHEMA \"user_{$username}\" OWNER TO \"{$username}\";
    
    -- 12. Если нужны временные таблицы в public:
    GRANT TEMPORARY ON DATABASE \"{$dbName}\" TO \"{$username}\";
");
                $userPdo = null;
                
                // ШАГ 7: Удаляем старую запись если есть
                \App\Models\Database::where('module_id', $moduleId)
                    ->where('event_account_id', $participant->id)
                    ->delete();
                
                // ШАГ 8: Сохраняем в нашу систему
                $database = \App\Models\Database::create([
                    'name' => $dbName,
                    'username' => $username,
                    'password' => $password,
                    'event_account_id' => $participant->id,
                    'module_id' => $moduleId,
                    'is_active' => true,
                    'is_public' => false,
                    'has_demo_data' => false,
                    'is_empty' => true
                ]);
                
                $results[] = [
                    'success' => true,
                    'database_id' => $database->id,
                    'database_name' => $dbName,
                    'username' => $username,
                    'password' => $password,
                    'participant_id' => $participant->id,
                    'participant_login' => $participant->login,
                    'connected_to' => $currentDb,
                    'old_databases_deleted' => count($userDatabases),
                    'connection_string' => "psql -h localhost -p 5432 -U {$username} -d {$dbName}",
                    'pgadmin_url' => "postgresql://{$username}:{$password}@localhost:5432/{$dbName}"
                ];
                
                $successCount++;
                
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'participant_id' => $participant->id,
                    'participant_login' => $participant->login
                ];
                
                $errorCount++;
            }
        }
        
        // 5. Формируем ответ
        $response = [
            'success' => true,
            'message' => "Создано {$successCount} баз данных, ошибок: {$errorCount}",
            'summary' => [
                'total' => count($participants),
                'successful' => $successCount,
                'failed' => $errorCount
            ],
            'results' => $results
        ];
        
        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Генерация короткого имени БД
 */
private function generateShortDbName($module, $participant)
{
    // Вариант 1: Очень короткий (модуль + ID участника)
    // return "m{$module->id}_u{$participant->id}";
    
    // Вариант 2: С префиксом exam (рекомендую)
    // return "exam_m{$module->id}_u{$participant->id}";
    
    // Вариант 3: С событием и участником
    $eventShort = substr(str_replace(' ', '', $module->event->name ?? 'event'), 0, 10);
    $moduleShort = substr(str_replace(' ', '', $module->name), 0, 10);
    
    // Убираем русские буквы и спецсимволы
    $eventShort = preg_replace('/[^a-z0-9]/i', '', $eventShort);
    $moduleShort = preg_replace('/[^a-z0-9]/i', '', $moduleShort);
    
    if (empty($eventShort)) $eventShort = 'e' . $module->event_id;
    if (empty($moduleShort)) $moduleShort = 'm' . $module->id;
    
    // Вариант 4: Самый простой и короткий
    return strtolower("db_{$moduleShort}_{$participant->login}");
    
    // Вариант 5: С временной меткой (уникально)
    // return "db_" . $module->id . "_" . $participant->id . "_" . date('mdHi');
}

    
    /**
     * Получить все БД для модуля
     */
    public function getByModule($moduleId)
{
    try {
        $databases = Database::with([
            'eventAccount.user', 
            'status'  // Добавьте это!
        ])
            ->where('module_id', $moduleId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $databases,
            'total' => $databases->count()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Удалить реальную БД из PostgreSQL
     */
    public function dropRealDatabase($id)
    {
        try {
            $database = Database::findOrFail($id);
            $result = $this->databaseService->dropDatabase($database->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Real database dropped successfully',
                'database_name' => $database->name
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}