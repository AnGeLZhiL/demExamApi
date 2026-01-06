<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Database;
use App\Models\Module;
use App\Services\DatabaseService;
use App\Models\EventAccount;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class DatabaseController extends Controller
{
    protected $databaseService;
    
    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
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
     * Универсальный метод: создает/обновляет БД для всех участников
     * Заменяет старый createForModule
     */
    public function createForModule(Request $request, $moduleId)
    {
        try {
            $module = Module::with('event')->findOrFail($moduleId);
            
            // Находим всех участников
            $participantRole = Role::where('name', 'Участник')->first();
            if (!$participantRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role "Участник" not found'
                ], 400);
            }
            
            $participants = EventAccount::where('event_id', $module->event_id)
                ->where('role_id', $participantRole->id)
                ->with('user')
                ->get();
            
            if ($participants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No participants found'
                ], 400);
            }
            
            // Находим существующие БД
            $existingDatabases = Database::where('module_id', $moduleId)
                ->whereIn('event_account_id', $participants->pluck('id'))
                ->get()
                ->keyBy('event_account_id');
            
            $results = [];
            $createdCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            
            foreach ($participants as $participant) {
                try {
                    $action = 'created';
                    $participantName = $participant->user->name ?? $participant->login;
                    
                    // Проверяем, есть ли уже БД для этого участника
                    $existingDatabase = $existingDatabases[$participant->id] ?? null;
                    
                    if ($existingDatabase) {
                        $action = 'recreated';
                        $updatedCount++;
                        
                        \Log::info("Recreating DB for {$participantName}, old DB: {$existingDatabase->name}");
                    } else {
                        $createdCount++;
                        \Log::info("Creating new DB for {$participantName}");
                    }
                    
                    // Используем метод для создания/пересоздания
                    $result = $this->databaseService->recreateDatabaseForParticipant(
                        $moduleId, 
                        $participant->id
                    );
                    
                    $results[] = [
                        'success' => true,
                        'participant_id' => $participant->id,
                        'participant_login' => $participant->login,
                        'participant_name' => $participantName,
                        'database_name' => $result['database']->name,
                        'username' => $participant->login,
                        'action' => $action,
                        'old_database' => $existingDatabase ? $existingDatabase->name : null,
                        'message' => $action === 'created' ? 
                            'Database created successfully' : 
                            'Database recreated successfully'
                    ];
                    
                    // Небольшая пауза между созданиями
                    usleep(100000);
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'participant_id' => $participant->id,
                        'participant_login' => $participant->login,
                        'error' => $e->getMessage(),
                        'database_name' => 'none'
                    ];
                    $errorCount++;
                    
                    \Log::error("Failed for {$participant->login}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => $errorCount === 0,
                'message' => "✅ Базы данных синхронизированы!",
                'details' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'failed' => $errorCount,
                    'total_participants' => count($participants)
                ],
                'summary' => [
                    'total' => count($participants),
                    'successful' => $createdCount + $updatedCount,
                    'failed' => $errorCount,
                    'created' => $createdCount,
                    'updated' => $updatedCount
                ],
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Error in createForModule: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Удалить ВСЕ базы данных модуля
 */
public function dropAllDatabases($moduleId)
{
    try {
        $module = Module::findOrFail($moduleId);
        
        // Находим все БД модуля
        $databases = Database::where('module_id', $moduleId)->get();
        
        if ($databases->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Нет баз данных для удаления',
                'deleted_count' => 0
            ]);
        }
        
        $deletedCount = 0;
        $errors = [];
        
        foreach ($databases as $database) {
            try {
                // 1. Удаляем реальную БД из PostgreSQL
                $this->dropDatabaseFromPostgres($database->name);
                
                // 2. Удаляем запись из нашей системы
                $database->delete();
                
                $deletedCount++;
                
                // Небольшая пауза между удалениями
                usleep(100000); // 0.1 секунды
                
            } catch (\Exception $e) {
                $errors[] = [
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'error' => $e->getMessage()
                ];
                \Log::error("Error dropping database {$database->name}: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => $deletedCount > 0 && empty($errors),
            'message' => "Удалено {$deletedCount} баз данных" . 
                        (count($errors) > 0 ? ", ошибок: " . count($errors) : ""),
            'deleted_count' => $deletedCount,
            'error_count' => count($errors),
            'errors' => $errors,
            'details' => [
                'total_found' => $databases->count(),
                'successfully_deleted' => $deletedCount,
                'failed' => count($errors)
            ]
        ]);
        
    } catch (\Exception $e) {
        \Log::error("Error in dropAllDatabases: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Вспомогательный метод: удалить БД из PostgreSQL
 */
private function dropDatabaseFromPostgres($dbName)
{
    $pdo = $this->databaseService->getPdo();
    
    try {
        // 1. Проверяем существование БД
        $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            \Log::info("Database {$dbName} does not exist in PostgreSQL");
            return;
        }
        
        // 2. Завершаем все активные соединения
        \Log::info("Terminating connections to database {$dbName}");
        
        $terminateSql = "
            SELECT pg_terminate_backend(pid)
            FROM pg_stat_activity
            WHERE datname = '{$dbName}'
            AND pid <> pg_backend_pid()
        ";
        
        $pdo->exec($terminateSql);
        
        // 3. Небольшая пауза для завершения процессов
        sleep(1);
        
        // 4. Удаляем БД
        \Log::info("Dropping database {$dbName} from PostgreSQL");
        $pdo->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
        
        \Log::info("Database {$dbName} dropped from PostgreSQL successfully");
        
    } catch (\Exception $e) {
        \Log::error("Error dropping database {$dbName} from PostgreSQL: " . $e->getMessage());
        throw $e;
    }
}

    /**
     * Пересоздать БД для конкретного участника
     * Удаляет старую и создает новую
     */
    public function recreateForParticipant(Request $request, $moduleId)
    {
        try {
            $eventAccountId = $request->input('event_account_id');
            
            if (!$eventAccountId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'event_account_id required'
                ], 400);
            }
            
            // Используем метод из сервиса
            $result = $this->databaseService->recreateDatabaseForParticipant($moduleId, $eventAccountId);
            
            return response()->json([
                'success' => true,
                'message' => 'Database recreated successfully',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Error recreating database: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Пересоздать БД для ВСЕХ участников модуля
     */
    public function recreateForAllParticipants(Request $request, $moduleId)
    {
        try {
            $module = Module::with('event')->find($moduleId);
            if (!$module) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Module not found'
                ], 404);
            }
            
            // Используем метод из сервиса
            $result = $this->databaseService->recreateForAllParticipants($moduleId);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            \Log::error("Error recreating all databases: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить БД участника (только удаление)
     */
    public function dropDatabase($id)
    {
        try {
            $database = Database::findOrFail($id);
            
            // Используем PDO из сервиса
            $pdo = $this->databaseService->getPdo();
            
            // Завершаем все соединения
            $pdo->exec("
                SELECT pg_terminate_backend(pid)
                FROM pg_stat_activity
                WHERE datname = '{$database->name}'
                AND pid <> pg_backend_pid()
            ");
            
            sleep(1); // Пауза для завершения
            
            // Удаляем БД
            $pdo->exec("DROP DATABASE IF EXISTS \"{$database->name}\"");
            
            // Удаляем запись
            $database->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Database dropped successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить все БД для модуля
     */
    public function getByModule($moduleId)
    {
        try {
            $databases = Database::with([
                'eventAccount.user', 
                'status'
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
 * Блокировка/разблокировка БД (только чтение)
 */
// public function toggleDatabaseLock(Request $request, $databaseId)
// {
//     try {
//         \Log::info("Toggle database lock called for DB: {$databaseId}", $request->all());
        
//         $database = Database::find($databaseId);
        
//         if (!$database) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'База данных не найдена'
//             ], 404);
//         }
        
//         $action = $request->input('action'); // 'lock' или 'unlock'
//         $lockReason = $request->input('reason', 'Административная блокировка');
        
//         if ($action === 'lock') {
//             // ВЫЗЫВАЕМ РЕАЛЬНУЮ БЛОКИРОВКУ
//             return $this->lockDatabaseReadOnly($database, $lockReason);
            
//         } elseif ($action === 'unlock') {
//             // ВЫЗЫВАЕМ РЕАЛЬНУЮ РАЗБЛОКИРОВКУ
//             return $this->unlockDatabase($database);
            
//         } else {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Неизвестное действие. Используйте "lock" или "unlock"'
//             ], 400);
//         }
        
//     } catch (\Exception $e) {
//         \Log::error("Error in toggleDatabaseLock: " . $e->getMessage());
//         \Log::error($e->getTraceAsString());
        
//         return response()->json([
//             'success' => false,
//             'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage(),
//             'trace' => config('app.debug') ? $e->getTraceAsString() : null
//         ], 500);
//     }
// }

//     /**
//      * Блокировка БД (только чтение) - БЕЗ смены пароля
//      */
//     private function lockDatabaseReadOnly(Database $database, $reason)
// {
//     $pdo = $this->databaseService->getPdo();
    
//     try {
//         // 1. Завершаем все активные соединения пользователя
//         $terminateSql = "
//             SELECT pg_terminate_backend(pid)
//             FROM pg_stat_activity
//             WHERE usename = '{$database->username}'
//             AND pid <> pg_backend_pid()
//         ";
//         $pdo->exec($terminateSql);
        
//         // 2. Временный пароль для блокировки (опционально)
//         $lockPassword = bin2hex(random_bytes(8));
//         $escapedPassword = str_replace("'", "''", $lockPassword);
//         $pdo->exec("ALTER USER \"{$database->username}\" WITH PASSWORD '{$escapedPassword}'");
        
//         // 3. Отключаем все права в БД
//         $this->setDatabaseReadOnly($database->name, $database->username);
        
//         // 4. Обновляем запись
//         $database->update([
//             'is_active' => false,
//             'password' => $lockPassword, // Сохраняем временный пароль
//             'metadata' => array_merge($database->metadata ?? [], [
//                 'locked_at' => now()->toISOString(),
//                 'locked_by' => auth()->id(),
//                 'lock_reason' => $reason,
//                 'lock_type' => 'read_only',
//                 'original_password' => $database->password, // Сохраняем для разблокировки
//                 'was_active' => $database->is_active
//             ])
//         ]);
        
//         return response()->json([
//             'success' => true,
//             'message' => 'БД заблокирована (режим "только чтение")',
//             'data' => [
//                 'database_id' => $database->id,
//                 'database_name' => $database->name,
//                 'username' => $database->username,
//                 'is_locked' => true,
//                 'lock_reason' => $reason,
//                 'locked_at' => now()->toISOString(),
//                 'note' => 'Пользователь НЕ МОЖЕТ подключаться с новым паролем'
//             ]
//         ]);
        
//     } catch (\Exception $e) {
//         throw new \Exception("Ошибка блокировки БД: " . $e->getMessage());
//     }
// }
/**
 * Блокировка/разблокировка БД (рабочая версия)
 */
public function toggleDatabaseLock(Request $request, $databaseId)
{
    try {
        \Log::info("=== TOGGLE LOCK REQUEST ===");
        \Log::info("Database ID: {$databaseId}");
        \Log::info("Action: " . $request->input('action'));
        \Log::info("Reason: " . $request->input('reason'));
        \Log::info("Full request: ", $request->all());
        
        $database = Database::findOrFail($databaseId);
        $action = $request->input('action');
        $reason = $request->input('reason', 'Административная блокировка');
        
        \Log::info("DB: {$database->name}, User: {$database->username}, Action: {$action}");
        
        $pdo = $this->databaseService->getPdo();
        
        if ($action === 'lock') {
            // БЛОКИРОВКА (РЕЖИМ "ТОЛЬКО ЧТЕНИЕ")
            
            // 1. Завершаем ВСЕ активные соединения пользователя
            $terminateSql = "
                SELECT pg_terminate_backend(pid)
                FROM pg_stat_activity
                WHERE usename = '{$database->username}'
                AND datname = '{$database->name}'
                AND pid <> pg_backend_pid()
            ";
            \Log::info("Terminating user connections: {$terminateSql}");
            $pdo->exec($terminateSql);
            sleep(1); // Ждем завершения
            
            // 2. Меняем владельца БД на postgres (чтобы убрать автоматические права)
            $pdo->exec("ALTER DATABASE \"{$database->name}\" OWNER TO \"postgres\"");
            
            // 3. Отключаем ВСЕ права, кроме CONNECT и SELECT
            // 3.1. Отключаем CREATE на уровне БД
            $pdo->exec("REVOKE CREATE ON DATABASE \"{$database->name}\" FROM \"{$database->username}\"");
            
            // 3.2. Отключаем TEMP (создание временных таблиц)
            $pdo->exec("REVOKE TEMPORARY ON DATABASE \"{$database->name}\" FROM \"{$database->username}\"");
            
            // 4. Подключаемся к самой БД для настройки схемы
            try {
                $dbPdo = $this->databaseService->createPdoConnection(
                    $database->name, 
                    env('DB_USERNAME'), 
                    env('DB_PASSWORD')
                );
                
                // 4.1. Отключаем CREATE в схеме public
                $dbPdo->exec("REVOKE CREATE ON SCHEMA public FROM \"{$database->username}\"");
                
                // 4.2. Отключаем права на запись в существующих таблицах
                $dbPdo->exec("REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public FROM \"{$database->username}\"");
                
                // 4.3. Отключаем права на запись в последовательностях
                $dbPdo->exec("REVOKE UPDATE ON ALL SEQUENCES IN SCHEMA public FROM \"{$database->username}\"");
                
                // 4.4. Оставляем только SELECT (чтение)
                $dbPdo->exec("GRANT SELECT ON ALL TABLES IN SCHEMA public TO \"{$database->username}\"");
                
                // 4.5. Оставляем USAGE на последовательностях (для чтения currval)
                $dbPdo->exec("GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO \"{$database->username}\"");
                
                \Log::info("Schema permissions set to read-only");
                
            } catch (\Exception $e) {
                \Log::warning("Could not set schema permissions: " . $e->getMessage());
            }
            
            // 5. Обновляем статус
            $database->update([
                'is_active' => false,
                'metadata' => array_merge($database->metadata ?? [], [
                    'locked_at' => now()->toISOString(),
                    'lock_reason' => $reason,
                    'lock_type' => 'read_only',
                    'previous_owner' => $database->username,
                    'current_owner' => 'postgres'
                ])
            ]);
            
            \Log::info("Database locked in read-only mode");
            
            return response()->json([
                'success' => true,
                'message' => 'БД переведена в режим "только чтение"',
                'data' => [
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'username' => $database->username,
                    'can_create' => false,
                    'can_connect' => true, // Подключение разрешено!
                    'can_select' => true,  // Чтение разрешено!
                    'lock_type' => 'read_only',
                    'locked_at' => now()->toISOString()
                ]
            ]);
            
        } elseif ($action === 'unlock') {
            // РАЗБЛОКИРОВКА (ПОЛНЫЙ ДОСТУП)
            
            // 1. Возвращаем владельца БД
            $pdo->exec("ALTER DATABASE \"{$database->name}\" OWNER TO \"{$database->username}\"");
            
            // 2. Возвращаем полные права на БД
            $pdo->exec("GRANT CREATE, TEMPORARY ON DATABASE \"{$database->name}\" TO \"{$database->username}\"");
            
            // 3. Возвращаем полные права в схеме
            try {
                $dbPdo = $this->databaseService->createPdoConnection(
                    $database->name, 
                    env('DB_USERNAME'), 
                    env('DB_PASSWORD')
                );
                
                // Возвращаем все права
                $dbPdo->exec("GRANT ALL ON SCHEMA public TO \"{$database->username}\"");
                $dbPdo->exec("GRANT ALL ON ALL TABLES IN SCHEMA public TO \"{$database->username}\"");
                $dbPdo->exec("GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO \"{$database->username}\"");
                
                \Log::info("Full permissions restored");
                
            } catch (\Exception $e) {
                \Log::warning("Could not restore schema permissions: " . $e->getMessage());
            }
            
            // 4. Обновляем статус
            $database->update([
                'is_active' => true,
                'metadata' => array_merge($database->metadata ?? [], [
                    'unlocked_at' => now()->toISOString(),
                    'owner' => $database->username
                ])
            ]);
            
            \Log::info("Database unlocked with full access");
            
            return response()->json([
                'success' => true,
                'message' => 'БД разблокирована (полный доступ)',
                'data' => [
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'username' => $database->username,
                    'can_create' => true,
                    'can_connect' => true,
                    'can_select' => true,
                    'unlocked_at' => now()->toISOString()
                ]
            ]);
        }
        
    } catch (\Exception $e) {
        \Log::error("Toggle lock error: " . $e->getMessage());
        \Log::error($e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Ошибка: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Проверить реальное состояние блокировки
 */
public function checkLockStatus($databaseId)
{
    try {
        $database = Database::findOrFail($databaseId);
        $pdo = $this->databaseService->getPdo();
        
        // Проверяем права в PostgreSQL
        $sql = "
            SELECT 
                has_database_privilege('{$database->username}', '{$database->name}', 'CREATE') as can_create,
                has_database_privilege('{$database->username}', '{$database->name}', 'CONNECT') as can_connect,
                has_database_privilege('{$database->username}', '{$database->name}', 'TEMPORARY') as can_temp
        ";
        
        $stmt = $pdo->query($sql);
        $privileges = $stmt->fetch();
        
        return response()->json([
            'success' => true,
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'username' => $database->username,
                'is_active_in_app' => $database->is_active
            ],
            'postgres_privileges' => $privileges,
            'is_really_locked' => !($privileges['can_create'] === 't' || $privileges['can_create'] === true),
            'status' => $privileges['can_create'] ? 
                ($database->is_active ? '✅ Активна' : '⚠️ Несоответствие: активна в PG, но заблокирована в приложении') :
                (!$database->is_active ? '🔒 Заблокирована' : '⚠️ Несоответствие: заблокирована в PG, но активна в приложении')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Диагностика подключения и прав
 */
public function diagnoseDatabase($databaseId)
{
    try {
        $database = Database::findOrFail($databaseId);
        
        $pdo = $this->databaseService->getPdo();
        
        // 1. Проверка подключения к PostgreSQL
        $version = $pdo->query("SELECT version()")->fetchColumn();
        
        // 2. Проверка существования пользователя
        $userStmt = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '{$database->username}'");
        $userExists = $userStmt->fetchColumn();
        
        // 3. Проверка существования БД
        $dbStmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$database->name}'");
        $dbExists = $dbStmt->fetchColumn();
        
        // 4. Проверка прав
        $privileges = [];
        if ($userExists && $dbExists) {
            $privStmt = $pdo->query("
                SELECT 
                    has_database_privilege('{$database->username}', '{$database->name}', 'CREATE') as can_create,
                    has_database_privilege('{$database->username}', '{$database->name}', 'CONNECT') as can_connect,
                    has_database_privilege('{$database->username}', '{$database->name}', 'TEMPORARY') as can_temp
            ");
            $privileges = $privStmt->fetch();
        }
        
        // 5. Проверка владельца БД
        $ownerStmt = $pdo->query("
            SELECT pg_catalog.pg_get_userbyid(datdba) as owner 
            FROM pg_database 
            WHERE datname = '{$database->name}'
        ");
        $owner = $ownerStmt->fetchColumn();
        
        return response()->json([
            'success' => true,
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'username' => $database->username,
                'is_active' => $database->is_active,
                'exists_in_postgres' => (bool)$dbExists,
                'user_exists_in_postgres' => (bool)$userExists
            ],
            'postgres' => [
                'version' => $version,
                'db_owner' => $owner,
                'privileges' => $privileges
            ],
            'connection_test' => [
                'can_connect' => true,
                'can_query' => true
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => $e->getMessage()
        ], 500);
    }
}

public function verifyDatabaseLock($databaseId)
{
    $database = Database::findOrFail($databaseId);
    
    try {
        $pdo = $this->databaseService->getPdo();
        
        // Проверяем может ли пользователь создавать таблицы
        $stmt = $pdo->query("
            SELECT 
                has_database_privilege('{$database->username}', '{$database->name}', 'CREATE') as can_create,
                has_database_privilege('{$database->username}', '{$database->name}', 'TEMPORARY') as can_temp,
                has_database_privilege('{$database->username}', '{$database->name}', 'CONNECT') as can_connect,
                rolvaliduntil as valid_until
            FROM pg_roles 
            WHERE rolname = '{$database->username}'
        ");
        
        $privileges = $stmt->fetch();
        
        return response()->json([
            'success' => true,
            'is_locked' => !$privileges['can_create'] || $privileges['valid_until'] < now(),
            'privileges' => $privileges,
            'database' => $database->name,
            'username' => $database->username
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Установить режим "только чтение" для БД
 */
private function setDatabaseReadOnly($dbName, $username)
{
    try {
        \Log::info("Setting database {$dbName} to read-only for user {$username}");
        
        // 1. Сначала меняем владельца БД на postgres
        $adminPdo = $this->databaseService->createPdoConnection('postgres', env('DB_USERNAME'), env('DB_PASSWORD'));
        $adminPdo->exec("ALTER DATABASE \"{$dbName}\" OWNER TO \"postgres\"");
        \Log::info("Changed owner of {$dbName} to postgres");
        
        // 2. Подключаемся к самой БД как администратор
        $dbPdo = $this->databaseService->createPdoConnection(
            $dbName, 
            env('DB_USERNAME'), 
            env('DB_PASSWORD')
        );
        
        // 3. Отключаем ВСЕ права пользователя в схеме public
        $revokeSql = "REVOKE ALL ON SCHEMA public FROM \"{$username}\"";
        $dbPdo->exec($revokeSql);
        \Log::info("Revoked all privileges from {$username} on public schema");
        
        // 4. Отключаем права на все таблицы
        $dbPdo->exec("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM \"{$username}\"");
        
        // 5. Отключаем права на все последовательности
        $dbPdo->exec("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM \"{$username}\"");
        
        // 6. Отключаем права на все функции
        $dbPdo->exec("REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM \"{$username}\"");
        
        // 7. Запрещаем создание объектов
        $dbPdo->exec("REVOKE CREATE ON SCHEMA public FROM \"{$username}\"");
        
        // 8. Устанавливаем права по умолчанию (запрет на создание)
        $dbPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM \"{$username}\"");
        
        // 9. Даем только права на чтение существующих таблиц
        $dbPdo->exec("GRANT SELECT ON ALL TABLES IN SCHEMA public TO \"{$username}\"");
        $dbPdo->exec("GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO \"{$username}\"");
        
        // 10. Устанавливаем срок действия пароля пользователя
        $adminPdo->exec("ALTER USER \"{$username}\" VALID UNTIL '1970-01-01'");
        
        \Log::info("Successfully set database {$dbName} to read-only for user {$username}");
        
    } catch (\Exception $e) {
        \Log::error("Error setting database read-only for {$username} in {$dbName}: " . $e->getMessage());
        throw new \Exception("Ошибка установки режима 'только чтение': " . $e->getMessage());
    }
}

    /**
     * Установка прав только для чтения
     */
    private function setReadOnlyPermissions($dbName, $username)
    {
        // Подключаемся к БД от имени суперпользователя
        $adminPdo = $this->databaseService->getPdo();
        
        try {
            // Переключаемся на целевую БД
            $adminPdo->exec("\\c {$dbName}");
            
            // 1. Отзываем права на создание объектов
            $adminPdo->exec("REVOKE CREATE ON SCHEMA public FROM \"{$username}\"");
            
            // 2. Отзываем права на запись для существующих таблиц
            $adminPdo->exec("REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public FROM \"{$username}\"");
            
            // 3. Отзываем права на выполнение DDL команд
            $adminPdo->exec("REVOKE CREATE, USAGE ON ALL SEQUENCES IN SCHEMA public FROM \"{$username}\"");
            
            // 4. Запрещаем создание новых таблиц
            $adminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM \"{$username}\"");
            
            // 5. Запрещаем создание функций
            $adminPdo->exec("REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA public FROM \"{$username}\"");
            
            // 6. Блокируем личную схему пользователя
            $adminPdo->exec("REVOKE ALL ON SCHEMA \"user_{$username}\" FROM \"{$username}\"");
            
            // 7. Даем права только на чтение
            $adminPdo->exec("GRANT SELECT ON ALL TABLES IN SCHEMA public TO \"{$username}\"");
            $adminPdo->exec("GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO \"{$username}\"");
            
            Log::info("Database {$dbName} set to read-only for user {$username}");
            
        } catch (\Exception $e) {
            Log::warning("Error setting read-only permissions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Разблокировка БД (возвращаем полные права)
     */
    private function unlockDatabase(Database $database)
{
    $pdo = $this->databaseService->getPdo();
    
    try {
        // Восстанавливаем оригинальный пароль
        $originalPassword = $database->metadata['original_password'] ?? $database->password;
        
        if (empty($originalPassword)) {
            throw new \Exception('Не найден оригинальный пароль для восстановления');
        }
        
        $escapedPassword = str_replace("'", "''", $originalPassword);
        $pdo->exec("ALTER USER \"{$database->username}\" WITH PASSWORD '{$escapedPassword}'");
        
        // Восстанавливаем полные права
        $this->restoreFullAccess($database->name, $database->username);
        
        // Возвращаем владение БД пользователю
        $pdo->exec("ALTER DATABASE \"{$database->name}\" OWNER TO \"{$database->username}\"");
        
        // Обновляем запись
        $wasActive = $database->metadata['was_active'] ?? true;
        
        $database->update([
            'is_active' => $wasActive,
            'password' => $originalPassword,
            'metadata' => array_merge($database->metadata ?? [], [
                'unlocked_at' => now()->toISOString(),
                'unlocked_by' => auth()->id(),
                'was_active' => null,
                'original_password' => null
            ])
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'БД разблокирована (полный доступ восстановлен)',
            'data' => [
                'database_id' => $database->id,
                'database_name' => $database->name,
                'username' => $database->username,
                'is_locked' => false,
                'unlocked_at' => now()->toISOString(),
                'note' => 'Пользователь может подключаться с оригинальным паролем'
            ]
        ]);
        
    } catch (\Exception $e) {
        throw new \Exception("Ошибка разблокировки БД: " . $e->getMessage());
    }
}

/**
 * Восстановление полного доступа
 */
private function restoreFullAccess($dbName, $username)
{
    $pdo = $this->databaseService->getPdo();
    
    try {
        // 1. Даем полные права на БД
        $pdo->exec("GRANT ALL ON DATABASE \"{$dbName}\" TO \"{$username}\"");
        
        // 2. Подключаемся к БД
        $dbPdo = $this->databaseService->createPdoConnection($dbName, env('DB_USERNAME'), env('DB_PASSWORD'));
        
        // 3. Возвращаем все права в схеме public
        $dbPdo->exec("GRANT ALL ON SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbPdo->exec("GRANT ALL ON ALL TABLES IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbPdo->exec("GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbPdo->exec("GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        
        // 4. Права по умолчанию
        $dbPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO \"{$username}\" WITH GRANT OPTION");
        $dbPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO \"{$username}\" WITH GRANT OPTION");
        $dbPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO \"{$username}\" WITH GRANT OPTION");
        
        // 5. Возвращаем личную схему
        $dbPdo->exec("GRANT ALL ON SCHEMA \"private_{$username}\" TO \"{$username}\"");
        
        \Log::info("Full access restored for {$username} in {$dbName}");
        
    } catch (\Exception $e) {
        \Log::error("Error restoring full access: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Проверить реальные права пользователя в PostgreSQL
 */
public function checkRealPermissions($databaseId)
{
    try {
        $database = Database::findOrFail($databaseId);
        $pdo = $this->databaseService->getPdo();
        
        // 1. Проверяем права на уровне БД
        $stmt = $pdo->query("
            SELECT 
                has_database_privilege('{$database->username}', '{$database->name}', 'CREATE') as can_create,
                has_database_privilege('{$database->username}', '{$database->name}', 'CONNECT') as can_connect,
                has_database_privilege('{$database->username}', '{$database->name}', 'TEMPORARY') as can_temp
        ");
        $dbPrivileges = $stmt->fetch();
        
        // 2. Проверяем права на уровне схемы (если можем подключиться к БД)
        $schemaPrivileges = [];
        try {
            $dbPdo = $this->databaseService->createPdoConnection(
                $database->name, 
                env('DB_USERNAME'), 
                env('DB_PASSWORD')
            );
            
            $stmt = $dbPdo->query("
                SELECT 
                    has_schema_privilege('{$database->username}', 'public', 'CREATE') as can_create_in_public,
                    has_schema_privilege('{$database->username}', 'public', 'USAGE') as can_use_public
            ");
            $schemaPrivileges = $stmt->fetch();
        } catch (\Exception $e) {
            \Log::warning("Cannot check schema privileges: " . $e->getMessage());
        }
        
        // 3. Проверяем владельца БД
        $stmt = $pdo->query("
            SELECT pg_catalog.pg_get_userbyid(datdba) as owner 
            FROM pg_database 
            WHERE datname = '{$database->name}'
        ");
        $owner = $stmt->fetchColumn();
        
        return response()->json([
            'success' => true,
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'username' => $database->username,
                'is_active_in_app' => $database->is_active
            ],
            'postgres_status' => [
                'owner' => $owner,
                'is_owner' => $owner === $database->username,
                'database_privileges' => $dbPrivileges,
                'schema_privileges' => $schemaPrivileges,
                'is_really_locked' => !$dbPrivileges['can_create'] || $owner !== $database->username
            ],
            'summary' => $database->is_active ? 
                ($dbPrivileges['can_create'] ? '✅ Активна (может создавать)' : '⚠️ Несоответствие: активна в app, но нет прав в PG') :
                (!$dbPrivileges['can_create'] ? '🔒 Заблокирована (не может создавать)' : '⚠️ Несоответствие: заблокирована в app, но есть права в PG')
        ]);
        
    } catch (\Exception $e) {
        \Log::error("Error checking real permissions: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Восстановление полных прав
     */
    private function restoreFullPermissions($dbName, $username)
    {
        $adminPdo = $this->databaseService->getPdo();
        
        try {
            // Переключаемся на целевую БД
            $adminPdo->exec("\\c {$dbName}");
            
            // 1. Возвращаем права на создание объектов
            $adminPdo->exec("GRANT CREATE ON SCHEMA public TO \"{$username}\"");
            
            // 2. Возвращаем права на запись
            $adminPdo->exec("GRANT INSERT, UPDATE, DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public TO \"{$username}\"");
            
            // 3. Возвращаем права на последовательности
            $adminPdo->exec("GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO \"{$username}\"");
            
            // 4. Возвращаем права по умолчанию для новых таблиц
            $adminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO \"{$username}\"");
            $adminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO \"{$username}\"");
            
            // 5. Возвращаем права на функции
            $adminPdo->exec("GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO \"{$username}\"");
            
            // 6. Возвращаем личную схему
            $adminPdo->exec("GRANT ALL ON SCHEMA \"user_{$username}\" TO \"{$username}\"");
            
            Log::info("Database {$dbName} full permissions restored for user {$username}");
            
        } catch (\Exception $e) {
            Log::warning("Error restoring permissions: " . $e->getMessage());
            throw $e;
        }
    }
}