<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HarvestController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SeedTypeController;
use App\Http\Controllers\ShopItemController;
use App\Http\Controllers\TreeCareController;
use App\Http\Controllers\TreeController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => new UserResource($request->user()));

    Route::get('/trees', [TreeController::class, 'index']);
    Route::post('/trees', [TreeController::class, 'store']);
    Route::get('/seed-types', [SeedTypeController::class, 'index']);
    Route::get('/trees/{tree}', [TreeController::class, 'show']);
    Route::post('/trees/{tree}/care', TreeCareController::class);
    Route::post('/trees/{tree}/harvest', HarvestController::class);

    Route::get('/shop/items', [ShopItemController::class, 'index']);
    Route::post('/shop/purchases', [PurchaseController::class, 'store']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/{inventoryItem}/use', [InventoryController::class, 'useItem']);
});
