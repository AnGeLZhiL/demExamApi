<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\UserController;
// use App\Http\Controllers\EventController;
// use App\Http\Controllers\ModuleController;
// use App\Http\Controllers\EventAccountController;
// use App\Http\Controllers\ServerController;
// use App\Http\Controllers\RepositoryController;
// use App\Http\Controllers\DatabaseController;
// use App\Http\Controllers\FileController;
// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\StatusController;
// use App\Http\Controllers\RoleController;
// use App\Http\Controllers\TypeController;
// use App\Http\Controllers\ContextController;
// use App\Http\Controllers\ModuleRepositoryController;
// use App\Http\Controllers\GroupController;
// use App\Http\Controllers\UniversityParserController;
// use App\Http\Controllers\ExpertController;


// /*
// |--------------------------------------------------------------------------
// | API Routes
// |--------------------------------------------------------------------------
// |
// | Here is where you can register API routes for your application. These
// | routes are loaded by the RouteServiceProvider and all of them will
// | be assigned to the "api" middleware group. Make something great!
// |
// */

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::post('/login', [AuthController::class, 'login']);
// Route::get('/databases/{database}/diagnose', [DatabaseController::class, 'diagnoseDatabase']);
// Route::get('/databases/{database}/check-lock', [DatabaseController::class, 'checkLockStatus']);

// Route::middleware('auth:sanctum')->group(function () {

//     // Authentication
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', [AuthController::class, 'user']);

//     // Context
//     Route::apiResource('contexts', ContextController::class);
    
//     //Event
//     Route::apiResource('events', EventController::class);
//     Route::get('/events/{id}/modules', [EventController::class, 'getModules']); //получить модули заданного мероприятия
//     Route::get('/events/{id}/users', [EventController::class, 'getUsers']); //получить пользователей заданного мероприятия
//     Route::get('/events/{id}/event-accounts', [EventController::class, 'getEventAccounts']); //получить учетные записи с фильтрацией
    
//     //Status
//     Route::apiResource('statuses', StatusController::class);
//     Route::get('/statuses/context/{contextName}', [StatusController::class, 'getByContext']);

//     // Type
//     Route::apiResource('types', TypeController::class);
//     Route::get('/types/context/{contextName}', [TypeController::class, 'getByContext']);

//     //User
//     Route::get('/users/by-group/{groupId}', [UserController::class, 'getByGroup']);
//     Route::get('/users/by-group', [UserController::class, 'getByGroup']);
//     Route::get('/groups-with-users', [UserController::class, 'getGroupsWithUsers']);
//     Route::get('/users/{id}/event-accounts', [UserController::class, 'getEventAccounts']); //получить учетные записи заданного пользователя
//     Route::get('/users', [UserController::class, 'index']);
//     Route::apiResource('users', UserController::class);

//     //EventAccount
//     Route::apiResource('event-accounts', EventAccountController::class);
//     Route::get('events/{eventId}/event-accounts', [EventAccountController::class, 'getEventAccounts']);
//     Route::put('events/{eventId}/users/{userId}/seat', [EventAccountController::class, 'updateSeat']);
//     Route::post('/system-accounts', [EventAccountController::class, 'storeSystemAccount']);
//     Route::delete('/users/{userId}/system-accounts', [EventAccountController::class, 'destroySystemAccounts']);
//     Route::post('/system-accounts/{id}/generate-password', [EventAccountController::class, 'generatePassword']);
//     Route::put('/users/{userId}/system-accounts', [EventAccountController::class, 'updateSystemAccount']);


//     //Module
//     Route::apiResource('modules', ModuleController::class);

//     //Server
//     Route::apiResource('servers', ServerController::class);

//     // Gogs
//     Route::get('/modules/gogs/test-connection', [RepositoryController::class, 'testGogsConnection']);

//     // Модули + репозитории
//     Route::prefix('modules')->group(function () {
//         Route::get('/{moduleId}/repositories', [RepositoryController::class, 'getByModule']);
//         Route::post('/{moduleId}/repositories/create-all', [RepositoryController::class, 'createForModule']);
//         Route::post('/{moduleId}/repositories/smart-action', [RepositoryController::class, 'smartAction']);
//         Route::post('/{moduleId}/repositories/recreate-for-participant', [RepositoryController::class, 'recreateForParticipant']);
//         Route::post('/{moduleId}/repositories/recreate-all', [RepositoryController::class, 'recreateAll']);
//         Route::delete('/{moduleId}/repositories/delete-from-gogs', [RepositoryController::class, 'deleteAllFromGogs']);
//         Route::post('/{moduleId}/repositories/single', [RepositoryController::class, 'createSingleRepository']);
//         Route::delete('/{moduleId}/repositories/delete-all', [RepositoryController::class, 'deleteAll']);
//         Route::delete('/{moduleId}/repositories/{repositoryId}/delete', [RepositoryController::class, 'deleteSingle']);
//     });

//     // Эксперты и публичные репозитории
//     Route::prefix('modules')->group(function () {
//         Route::get('/{moduleId}/experts', [ExpertController::class, 'getModuleExperts']);
//         Route::post('/{moduleId}/experts/create-accounts', [ExpertController::class, 'createExpertAccounts']);
//         // Route::post('/{moduleId}/public-repository', [ExpertController::class, 'createPublicRepository']);
//         // Route::get('/{moduleId}/public-repository', [ExpertController::class, 'getPublicRepository']);
//         Route::post('/{moduleId}/experts/{expertId}/recreate-account', [ExpertController::class, 'recreateExpertAccount']);
//         Route::get('/{moduleId}/public-repository', [RepositoryController::class, 'getPublicRepository']);
//         Route::post('/{moduleId}/public-repository', [RepositoryController::class, 'createPublicRepository']);
//     });

//     //Repository CRUD (это для репозиториев как ресурсов)
//     Route::apiResource('repositories', RepositoryController::class);


//     //Database
//     Route::get('/databases/test-connection', [DatabaseController::class, 'testConnection']);
//     // Универсальный маршрут для создания/обновления БД
//     Route::post('/modules/{module}/databases/create-for-participants', [DatabaseController::class, 'createForModule']);
//     Route::post('/modules/{module}/databases/sync', [DatabaseController::class, 'createForModule']);
//     // Создание/пересоздание для одного участника
//     Route::post('/modules/{module}/databases/recreate-for-participant', [DatabaseController::class, 'recreateForParticipant']);

//     // Старый маршрут для пересоздания (если нужен)
//     Route::post('/modules/{module}/databases/recreate-for-all', [DatabaseController::class, 'recreateForAllParticipants']);
    
//     // Удаление БД
//     Route::delete('/databases/{id}/drop', [DatabaseController::class, 'dropDatabase']);
    
//     // Получение БД модуля
//     Route::get('/modules/{module}/databases', [DatabaseController::class, 'getByModule']);
    
//     // CRUD для Database
//     Route::apiResource('databases', DatabaseController::class);

//     Route::delete('/modules/{module}/databases/drop-all', [DatabaseController::class, 'dropAllDatabases']);

//     Route::post('/databases/{database}/toggle-lock', [DatabaseController::class, 'toggleDatabaseLock']);

//     Route::get('/databases/{database}/check-permissions', [DatabaseController::class, 'checkRealPermissions']);
//     Route::get('/databases/{database}/verify-lock', [DatabaseController::class, 'verifyDatabaseLock']);

//     //Group
//     Route::apiResource('groups', GroupController::class);

//     //File
//     Route::apiResource('files', FileController::class);

//     //Role
//     Route::apiResource('roles', RoleController::class);

//     // Маршруты для парсинга данных университета
//     Route::get('/university/groups/search', [UniversityParserController::class, 'searchGroups']);
// });

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\UserController;
// use App\Http\Controllers\EventController;
// use App\Http\Controllers\ModuleController;
// use App\Http\Controllers\EventAccountController;
// use App\Http\Controllers\ServerController;
// use App\Http\Controllers\RepositoryController;
// use App\Http\Controllers\DatabaseController;
// use App\Http\Controllers\FileController;
// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\StatusController;
// use App\Http\Controllers\RoleController;
// use App\Http\Controllers\TypeController;
// use App\Http\Controllers\ContextController;
// use App\Http\Controllers\ExpertController;
// use App\Http\Controllers\GroupController;

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// // Аутентификация
// Route::post('/login', [AuthController::class, 'login']);

// // Открытые маршруты (без аутентификации)
// Route::get('/databases/{database}/diagnose', [DatabaseController::class, 'diagnoseDatabase']);
// Route::get('/databases/{database}/check-lock', [DatabaseController::class, 'checkLockStatus']);

// // 🔥 ВАЖНО: Публичные репозитории делаем ДОСТУПНЫМИ без аутентификации
// Route::get('/modules/{moduleId}/public-repository', [RepositoryController::class, 'getPublicRepository']);
// Route::post('/modules/{moduleId}/public-repository', [RepositoryController::class, 'createPublicRepository']);
// Route::post('/modules/{moduleId}/public-repository/setup-access', [RepositoryController::class, 'setupPublicRepositoryAccess']);

// // Настройка доступа к публичному репозиторию
// Route::post('/modules/{moduleId}/public-repository/setup-granular-access', [RepositoryController::class, 'setupGranularAccess']);
// Route::get('/modules/{moduleId}/public-repository/check-access', [RepositoryController::class, 'checkAccess']);


// // Защищенные маршруты (требуют аутентификации)
// Route::middleware('auth:sanctum')->group(function () {
//     // Аутентификация
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', [AuthController::class, 'user']);

//     // Context
//     Route::apiResource('contexts', ContextController::class);
    
//     // Event
//     Route::apiResource('events', EventController::class);
//     Route::get('/events/{id}/modules', [EventController::class, 'getModules']);
//     Route::get('/events/{id}/users', [EventController::class, 'getUsers']);
//     Route::get('/events/{id}/event-accounts', [EventController::class, 'getEventAccounts']);
    
//     // Status
//     Route::apiResource('statuses', StatusController::class);
//     Route::get('/statuses/context/{contextName}', [StatusController::class, 'getByContext']);

//     // Type
//     Route::apiResource('types', TypeController::class);
//     Route::get('/types/context/{contextName}', [TypeController::class, 'getByContext']);

//     // User
//     Route::get('/users/by-group/{groupId}', [UserController::class, 'getByGroup']);
//     Route::get('/users/by-group', [UserController::class, 'getByGroup']);
//     Route::get('/groups-with-users', [UserController::class, 'getGroupsWithUsers']);
//     Route::get('/users/{id}/event-accounts', [UserController::class, 'getEventAccounts']);
//     Route::get('/users', [UserController::class, 'index']);
//     Route::apiResource('users', UserController::class);

//     // EventAccount
//     Route::apiResource('event-accounts', EventAccountController::class);
//     Route::get('events/{eventId}/event-accounts', [EventAccountController::class, 'getEventAccounts']);
//     Route::put('events/{eventId}/users/{userId}/seat', [EventAccountController::class, 'updateSeat']);
//     Route::post('/system-accounts', [EventAccountController::class, 'storeSystemAccount']);
//     Route::delete('/users/{userId}/system-accounts', [EventAccountController::class, 'destroySystemAccounts']);
//     Route::post('/system-accounts/{id}/generate-password', [EventAccountController::class, 'generatePassword']);
//     Route::put('/users/{userId}/system-accounts', [EventAccountController::class, 'updateSystemAccount']);

//     // Module
//     Route::apiResource('modules', ModuleController::class);

//     // Server
//     Route::apiResource('servers', ServerController::class);

//     // Gogs
//     Route::get('/modules/gogs/test-connection', [RepositoryController::class, 'testGogsConnection']);

//     // Модули + репозитории
//     Route::prefix('modules')->group(function () {
//         Route::get('/{moduleId}/repositories', [RepositoryController::class, 'getByModule']);
//         Route::post('/{moduleId}/repositories/create-all', [RepositoryController::class, 'createForModule']);
//         Route::post('/{moduleId}/repositories/smart-action', [RepositoryController::class, 'smartAction']);
//         Route::post('/{moduleId}/repositories/recreate-for-participant', [RepositoryController::class, 'recreateForParticipant']);
//         Route::post('/{moduleId}/repositories/recreate-all', [RepositoryController::class, 'recreateAll']);
//         Route::delete('/{moduleId}/repositories/delete-from-gogs', [RepositoryController::class, 'deleteAllFromGogs']);
//         Route::post('/{moduleId}/repositories/single', [RepositoryController::class, 'createSingleRepository']);
//         Route::delete('/{moduleId}/repositories/delete-all', [RepositoryController::class, 'deleteAll']);
//         Route::delete('/{moduleId}/repositories/{repositoryId}/delete', [RepositoryController::class, 'deleteSingle']);
//         Route::post('/{moduleId}/repositories/bulk-toggle', [RepositoryController::class, 'bulkToggleRepositories']);
        
        
//         // 🔥 ВАЖНО: УБРАТЬ дублирование публичного репозитория отсюда
//         // Route::get('/{moduleId}/public-repository', [RepositoryController::class, 'getPublicRepository']);
//         // Route::post('/{moduleId}/public-repository', [RepositoryController::class, 'createPublicRepository']);
//     });

//     Route::post('/repositories/{repositoryId}/toggle', [RepositoryController::class, 'toggleRepository']);

//     // Эксперты
//     Route::prefix('modules')->group(function () {
//         Route::get('/{moduleId}/experts', [ExpertController::class, 'getModuleExperts']);
//         Route::post('/{moduleId}/experts/create-accounts', [ExpertController::class, 'createExpertAccounts']);
//         Route::post('/{moduleId}/experts/{expertId}/recreate-account', [ExpertController::class, 'recreateExpertAccount']);
//     });

//     // Repository CRUD
//     Route::apiResource('repositories', RepositoryController::class);

//     // Database
//     Route::get('/databases/test-connection', [DatabaseController::class, 'testConnection']);
//     Route::post('/modules/{module}/databases/create-for-participants', [DatabaseController::class, 'createForModule']);
//     Route::post('/modules/{module}/databases/sync', [DatabaseController::class, 'createForModule']);
//     Route::post('/modules/{module}/databases/recreate-for-participant', [DatabaseController::class, 'recreateForParticipant']);
//     Route::post('/modules/{module}/databases/recreate-for-all', [DatabaseController::class, 'recreateForAllParticipants']);
//     Route::delete('/databases/{id}/drop', [DatabaseController::class, 'dropDatabase']);
//     Route::get('/modules/{module}/databases', [DatabaseController::class, 'getByModule']);
//     Route::apiResource('databases', DatabaseController::class);
//     Route::delete('/modules/{module}/databases/drop-all', [DatabaseController::class, 'dropAllDatabases']);
//     Route::post('/databases/{database}/toggle-lock', [DatabaseController::class, 'toggleDatabaseLock']);
//     Route::get('/databases/{database}/check-permissions', [DatabaseController::class, 'checkRealPermissions']);
//     Route::get('/databases/{database}/verify-lock', [DatabaseController::class, 'verifyDatabaseLock']);

//     // Group
//     Route::apiResource('groups', GroupController::class);

//     // File
//     Route::apiResource('files', FileController::class);

//     // Role
//     Route::apiResource('roles', RoleController::class);

    
// });

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\EventAccountController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\ModuleRepositoryController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\UniversityParserController;
use App\Http\Controllers\ExpertController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Аутентификация
Route::post('/login', [AuthController::class, 'login']);

// Открытые маршруты (без аутентификации)
Route::get('/databases/{database}/diagnose', [DatabaseController::class, 'diagnoseDatabase']);
Route::get('/databases/{database}/check-lock', [DatabaseController::class, 'checkLockStatus']);

// 🔥 ВАЖНО: Публичные репозитории делаем ДОСТУПНЫМИ без аутентификации
Route::get('/modules/{moduleId}/public-repository', [RepositoryController::class, 'getPublicRepository']);
Route::post('/modules/{moduleId}/public-repository', [RepositoryController::class, 'createPublicRepository']);
Route::post('/modules/{moduleId}/public-repository/setup-access', [RepositoryController::class, 'setupPublicRepositoryAccess']);

// Настройка доступа к публичному репозиторию
Route::post('/modules/{moduleId}/public-repository/setup-granular-access', [RepositoryController::class, 'setupGranularAccess']);
Route::get('/modules/{moduleId}/public-repository/check-access', [RepositoryController::class, 'checkAccess']);

// Маршруты для парсинга данных университета
Route::get('/university/groups/search', [UniversityParserController::class, 'searchGroups']);


Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Context
    Route::apiResource('contexts', ContextController::class);
    
    //Event
    Route::get('/events/{id}/modules', [EventController::class, 'getModules']);
    Route::get('/events/{id}/users', [EventController::class, 'getUsers']);
    Route::get('/events/{id}/event-accounts', [EventController::class, 'getEventAccounts']);
    Route::apiResource('events', EventController::class);
    
    //Status
    Route::apiResource('statuses', StatusController::class);
    Route::get('/statuses/context/{contextName}', [StatusController::class, 'getByContext']);

    // Type
    Route::apiResource('types', TypeController::class);
    Route::get('/types/context/{contextName}', [TypeController::class, 'getByContext']);

    //User
    Route::get('/users/by-group/{groupId}', [UserController::class, 'getByGroup']);
    Route::get('/users/by-group', [UserController::class, 'getByGroup']);
    Route::get('/groups-with-users', [UserController::class, 'getGroupsWithUsers']);
    Route::get('/users/{id}/event-accounts', [UserController::class, 'getEventAccounts']); //получить учетные записи заданного пользователя
    Route::get('/users', [UserController::class, 'index']);
    Route::apiResource('users', UserController::class);

    //EventAccount
    Route::apiResource('event-accounts', EventAccountController::class);
    Route::get('events/{eventId}/event-accounts', [EventAccountController::class, 'getEventAccounts']);
    Route::put('events/{eventId}/users/{userId}/seat', [EventAccountController::class, 'updateSeat']);
    Route::post('/system-accounts', [EventAccountController::class, 'storeSystemAccount']);
    Route::delete('/users/{userId}/system-accounts', [EventAccountController::class, 'destroySystemAccounts']);
    Route::post('/system-accounts/{id}/generate-password', [EventAccountController::class, 'generatePassword']);
    Route::put('/users/{userId}/system-accounts', [EventAccountController::class, 'updateSystemAccount']);


    //Module
    Route::apiResource('modules', ModuleController::class);

    //Server
    Route::apiResource('servers', ServerController::class);

    // Gogs
    Route::get('/modules/gogs/test-connection', [RepositoryController::class, 'testGogsConnection']);

    // Модули + репозитории
    Route::prefix('modules')->group(function () {
        Route::get('/{moduleId}/repositories', [RepositoryController::class, 'getByModule']);
        Route::post('/{moduleId}/repositories/create-all', [RepositoryController::class, 'createForModule']);
        Route::post('/{moduleId}/repositories/smart-action', [RepositoryController::class, 'smartAction']);
        Route::post('/{moduleId}/repositories/recreate-for-participant', [RepositoryController::class, 'recreateForParticipant']);
        Route::post('/{moduleId}/repositories/recreate-all', [RepositoryController::class, 'recreateAll']);
        Route::delete('/{moduleId}/repositories/delete-from-gogs', [RepositoryController::class, 'deleteAllFromGogs']);
        Route::post('/{moduleId}/repositories/single', [RepositoryController::class, 'createSingleRepository']);
        Route::delete('/{moduleId}/repositories/delete-all', [RepositoryController::class, 'deleteAll']);
        Route::delete('/{moduleId}/repositories/{repositoryId}/delete', [RepositoryController::class, 'deleteSingle']);
        Route::post('/{moduleId}/repositories/bulk-toggle', [RepositoryController::class, 'bulkToggleRepositories']);
    });

    Route::post('/repositories/{repositoryId}/toggle', [RepositoryController::class, 'toggleRepository']);

    // Эксперты и публичные репозитории
    Route::prefix('modules')->group(function () {
        Route::get('/{moduleId}/experts', [ExpertController::class, 'getModuleExperts']);
        Route::post('/{moduleId}/experts/create-accounts', [ExpertController::class, 'createExpertAccounts']);
        Route::post('/{moduleId}/experts/{expertId}/recreate-account', [ExpertController::class, 'recreateExpertAccount']);
    });

    //Repository CRUD (это для репозиториев как ресурсов)
    Route::apiResource('repositories', RepositoryController::class);


    //Database
    Route::get('/databases/test-connection', [DatabaseController::class, 'testConnection']);
    // Универсальный маршрут для создания/обновления БД
    Route::post('/modules/{module}/databases/create-for-participants', [DatabaseController::class, 'createForModule']);
    Route::post('/modules/{module}/databases/sync', [DatabaseController::class, 'createForModule']);
    // Создание/пересоздание для одного участника
    Route::post('/modules/{module}/databases/recreate-for-participant', [DatabaseController::class, 'recreateForParticipant']);

    // Старый маршрут для пересоздания (если нужен)
    Route::post('/modules/{module}/databases/recreate-for-all', [DatabaseController::class, 'recreateForAllParticipants']);
    
    // Удаление БД
    Route::delete('/databases/{id}/drop', [DatabaseController::class, 'dropDatabase']);
    
    // Получение БД модуля
    Route::get('/modules/{module}/databases', [DatabaseController::class, 'getByModule']);
    
    // CRUD для Database
    Route::apiResource('databases', DatabaseController::class);

    Route::delete('/modules/{module}/databases/drop-all', [DatabaseController::class, 'dropAllDatabases']);

    Route::post('/databases/{database}/toggle-lock', [DatabaseController::class, 'toggleDatabaseLock']);

    Route::get('/databases/{database}/check-permissions', [DatabaseController::class, 'checkRealPermissions']);
    Route::get('/databases/{database}/verify-lock', [DatabaseController::class, 'verifyDatabaseLock']);

    //Group
    Route::apiResource('groups', GroupController::class);

    //File
    Route::apiResource('files', FileController::class);

    //Role
    Route::apiResource('roles', RoleController::class);

    
});