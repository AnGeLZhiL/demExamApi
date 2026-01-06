<?php

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
use App\Http\Controllers\GogsController;
use App\Http\Controllers\ModuleRepositoryController;
use App\Http\Controllers\GroupController;


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

Route::post('/login', [AuthController::class, 'login']);
Route::get('/databases/{database}/diagnose', [DatabaseController::class, 'diagnoseDatabase']);
Route::get('/databases/{database}/check-lock', [DatabaseController::class, 'checkLockStatus']);

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Context
    Route::apiResource('contexts', ContextController::class);
    
    //Event
    Route::apiResource('events', EventController::class);
    Route::get('/events/{id}/modules', [EventController::class, 'getModules']); //получить модули заданного мероприятия
    Route::get('/events/{id}/users', [EventController::class, 'getUsers']); //получить пользователей заданного мероприятия
    Route::get('/events/{id}/event-accounts', [EventController::class, 'getEventAccounts']); //получить учетные записи с фильтрацией
    
    //Status
    Route::apiResource('statuses', StatusController::class);
    Route::get('/statuses/context/{contextName}', [StatusController::class, 'getByContext']);

    // Type
    Route::apiResource('types', TypeController::class);
    Route::get('/types/context/{contextName}', [TypeController::class, 'getByContext']);

    //User
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

    //Repository
    Route::prefix('repositories')->group(function () {
        // Сначала специальные маршруты
        Route::get('/test-gogs', [RepositoryController::class, 'testGogsConnection']); // ИЗМЕНИТЕ НА GET
        
        // Потом CRUD маршруты
        Route::get('/', [RepositoryController::class, 'index']);
        Route::post('/', [RepositoryController::class, 'store']);
        Route::get('/{id}', [RepositoryController::class, 'show']);
        Route::put('/{id}', [RepositoryController::class, 'update']);
        Route::delete('/{id}', [RepositoryController::class, 'destroy']);
    });

    // Модули + репозитории
    Route::prefix('modules')->group(function () {
        Route::get('/{moduleId}/repositories', [RepositoryController::class, 'getByModule']);
        Route::post('/{moduleId}/repositories/create-all', [RepositoryController::class, 'createForModule']);
    });


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