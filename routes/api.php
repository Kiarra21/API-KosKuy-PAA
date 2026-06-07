<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchFacilityController;
use App\Http\Controllers\Api\BranchPhotoController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomPhotoController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\RoomTypeFacilityController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

 

Route::prefix('auth')->group(function () {
	Route::post('/register', [AuthController::class, 'register']);
	Route::post('/login', [AuthController::class, 'login']);
	Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
	Route::post('/reset-password', [AuthController::class, 'resetPassword']);
	Route::middleware('auth:api')->group(function () {
		Route::get('/me', [AuthController::class, 'me']);
		Route::post('/logout', [AuthController::class, 'logout']);
		Route::post('/refresh', [AuthController::class, 'refresh']);
	});
});

Route::middleware('auth:api')->group(function () {
	Route::get('profile', [ProfileController::class, 'show']);
	Route::put('profile', [ProfileController::class, 'update']);
	Route::put('profile/password', [ProfileController::class, 'updatePassword']);
	Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);

	Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
	Route::apiResource('facilities', FacilityController::class)->only(['index', 'show']);
	Route::apiResource('room-types', RoomTypeController::class)->only(['index', 'show']);
	Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);

	Route::get('branches/{branch}/facilities', [BranchFacilityController::class, 'index']);
	Route::get('branches/{branch}/photos', [BranchPhotoController::class, 'index']);
	Route::get('branches/{branch}/reviews', [ReviewController::class, 'branchReviews']);
	Route::get('room-types/{roomType}/facilities', [RoomTypeFacilityController::class, 'index']);
	Route::get('room-types/{roomType}/photos', [RoomPhotoController::class, 'index']);

	Route::apiResource('reviews', ReviewController::class);

	Route::middleware('role:admin,pemilik_kos')->group(function () {
		Route::apiResource('room-types', RoomTypeController::class)->only(['store', 'update', 'destroy']);
		Route::apiResource('rooms', RoomController::class)->only(['store', 'update', 'destroy']);
		Route::post('room-types/{roomType}/facilities', [RoomTypeFacilityController::class, 'store']);
		Route::put('room-types/{roomType}/facilities', [RoomTypeFacilityController::class, 'update']);
		Route::delete('room-types/{roomType}/facilities', [RoomTypeFacilityController::class, 'destroy']);
		Route::post('room-types/{roomType}/photos', [RoomPhotoController::class, 'store']);
		Route::put('room-photos/{roomPhoto}', [RoomPhotoController::class, 'update']);
		Route::delete('room-photos/{roomPhoto}', [RoomPhotoController::class, 'destroy']);
		Route::put('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility']);
	});

	Route::middleware('role:pemilik_kos')->group(function () {
		Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);
		Route::put('customers/{customer}/status', [CustomerController::class, 'updateStatus']);
		Route::apiResource('users', UserController::class);
		Route::apiResource('branches', BranchController::class)->only(['store', 'update', 'destroy']);
		Route::get('branches/{branch}/admins', [BranchController::class, 'admins']);
		Route::get('branches/{branch}/admins/available', [BranchController::class, 'availableAdmins']);
		Route::post('branches/{branch}/admins/{user}', [BranchController::class, 'attachAdmin']);
		Route::delete('branches/{branch}/admins/{user}', [BranchController::class, 'detachAdmin']);
		Route::apiResource('facilities', FacilityController::class)->only(['store', 'update', 'destroy']);
		Route::post('branches/{branch}/facilities', [BranchFacilityController::class, 'store']);
		Route::put('branches/{branch}/facilities', [BranchFacilityController::class, 'update']);
		Route::delete('branches/{branch}/facilities', [BranchFacilityController::class, 'destroy']);
		Route::post('branches/{branch}/photos', [BranchPhotoController::class, 'store']);
		Route::put('branch-photos/{branchPhoto}', [BranchPhotoController::class, 'update']);
		Route::delete('branch-photos/{branchPhoto}', [BranchPhotoController::class, 'destroy']);
	});
});
