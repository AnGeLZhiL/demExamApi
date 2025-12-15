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

    //Module
    Route::apiResource('modules', ModuleController::class);

    //Server
    Route::apiResource('servers', ServerController::class);

    //Repository
    Route::apiResource('repositories', RepositoryController::class);

    //Database
    Route::apiResource('databases', DatabaseController::class);

    //File
    Route::apiResource('files', FileController::class);

    //Role
    Route::apiResource('roles', RoleController::class);
});

