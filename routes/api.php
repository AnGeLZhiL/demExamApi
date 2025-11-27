<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\EventAccountController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\RepositoryController;

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

//User
Route::apiResource('users', UserController::class);
Route::get('/users/{id}/event-accounts', [UserController::class, 'getEventAccounts']); //получить учетные записи заданного пользователя

//EventAccount
Route::apiResource('event-accounts', EventAccountController::class);

//Event
Route::apiResource('events', EventController::class);
Route::get('/events/{id}/modules', [EventController::class, 'getModules']); //получить модули заданного мероприятия
Route::get('/events/{id}/users', [EventController::class, 'getUsers']); //получить пользователей заданного мероприятия
Route::get('/events/{id}/event-accounts', [EventController::class, 'getEventAccounts']); //получить учетные записи с фильтрацией

//Module
Route::apiResource('modules', ModuleController::class);

//Server
Route::apiResource('servers', ServerController::class);

//Repository
Route::apiResource('repositories', RepositoryController::class);

