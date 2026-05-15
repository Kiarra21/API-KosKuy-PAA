<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\FacilityController;
use Illuminate\Support\Facades\Route;

 

Route::prefix('auth')->group(function () {
	Route::post('/register', [AuthController::class, 'register']);
	Route::post('/login', [AuthController::class, 'login']);
	Route::middleware('auth:api')->group(function () {
		Route::get('/me', [AuthController::class, 'me']);
		Route::post('/logout', [AuthController::class, 'logout']);
		Route::post('/refresh', [AuthController::class, 'refresh']);
	});
});

Route::middleware('auth:api')->group(function () {
	Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
	Route::apiResource('facilities', FacilityController::class)->only(['index', 'show']);

	Route::get('branches/{branch}/facilities', [\App\Http\Controllers\Api\BranchFacilityController::class, 'index']);

	Route::middleware('role:pemilik_kos')->group(function () {
		Route::apiResource('branches', BranchController::class)->only(['store', 'update', 'destroy']);
		Route::apiResource('facilities', FacilityController::class)->only(['store', 'update', 'destroy']);
		Route::post('branches/{branch}/facilities', [\App\Http\Controllers\Api\BranchFacilityController::class, 'store']);
		Route::put('branches/{branch}/facilities', [\App\Http\Controllers\Api\BranchFacilityController::class, 'update']);
		Route::delete('branches/{branch}/facilities', [\App\Http\Controllers\Api\BranchFacilityController::class, 'destroy']);
	});
});
