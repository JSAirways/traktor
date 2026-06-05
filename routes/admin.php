<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.approved'])->prefix('admin')->name('admin.')->group(function () {
    // Admin root - dashboard (default landing page)
    Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    
    // Dashboard routes
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/user/{user}', [App\Http\Controllers\Admin\DashboardController::class, 'showUser'])->name('dashboard.user');
    Route::get('/dashboard/users', [App\Http\Controllers\Admin\DashboardController::class, 'users'])->name('dashboard.users');
    Route::get('/api/dashboard/activity', [App\Http\Controllers\Admin\DashboardController::class, 'getActivityData'])->name('dashboard.activity');
    Route::get('/api/dashboard/content', [App\Http\Controllers\Admin\DashboardController::class, 'getContentData'])->name('dashboard.content');
    
    // User Management (Admin only - authorization checked in controller)
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::get('/users/pending/registrations', [App\Http\Controllers\Admin\UserController::class, 'pendingRegistrations'])->name('users.pending');
    Route::post('/users/{user}/approve', [App\Http\Controllers\Admin\UserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [App\Http\Controllers\Admin\UserController::class, 'reject'])->name('users.reject');
    Route::post('/users/bulk-approve', [App\Http\Controllers\Admin\UserController::class, 'bulkApprove'])->name('users.bulk-approve');
    Route::post('/users/bulk-reject', [App\Http\Controllers\Admin\UserController::class, 'bulkReject'])->name('users.bulk-reject');
    Route::post('/users/{user}/convert-to-parent', [App\Http\Controllers\Admin\UserController::class, 'convertToParent'])->name('users.convert-to-parent');
    
    // User Profile - allow users to edit themselves
    Route::get('/profile', [App\Http\Controllers\Admin\UserController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\Admin\UserController::class, 'updateProfile'])->name('profile.update');
    
    // Children Management (for parents)
    Route::resource('children', App\Http\Controllers\Admin\ChildrenController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    
    // Device Management (for parents)
    Route::get('/devices', [App\Http\Controllers\Admin\ParentDeviceController::class, 'index'])->name('parent-devices.index');
    Route::get('/devices/{device}', [App\Http\Controllers\Admin\ParentDeviceController::class, 'show'])->name('parent-devices.show');
    Route::put('/devices/{device}', [App\Http\Controllers\Admin\ParentDeviceController::class, 'update'])->name('parent-devices.update');
    Route::put('/devices/{device}/child-visibility', [App\Http\Controllers\Admin\ParentDeviceController::class, 'updateChildVisibility'])->name('parent-devices.child-visibility');
    Route::post('/devices/{device}/logout', [App\Http\Controllers\Admin\ParentDeviceController::class, 'logout'])->name('parent-devices.logout');
    Route::delete('/devices/{device}', [App\Http\Controllers\Admin\ParentDeviceController::class, 'destroy'])->name('parent-devices.destroy');
    
    // Device Management (Admin only - authorization checked in controller)
    Route::get('/admin/devices', [App\Http\Controllers\Admin\DeviceController::class, 'index'])->name('devices.index');
    Route::get('/admin/devices/{device}', [App\Http\Controllers\Admin\DeviceController::class, 'show'])->name('devices.show');
    Route::put('/admin/devices/{device}/child-visibility', [App\Http\Controllers\Admin\DeviceController::class, 'updateChildVisibility'])->name('devices.child-visibility');
    Route::post('/admin/devices/{device}/activate', [App\Http\Controllers\Admin\DeviceController::class, 'activate'])->name('devices.activate');
    Route::post('/admin/devices/{device}/deactivate', [App\Http\Controllers\Admin\DeviceController::class, 'deactivate'])->name('devices.deactivate');
    Route::delete('/admin/devices/{device}', [App\Http\Controllers\Admin\DeviceController::class, 'destroy'])->name('devices.destroy');
    
    // Settings (Admin only)
    // Authorization is handled in the controller methods
    Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [App\Http\Controllers\Admin\SettingController::class, 'clearCache'])->name('settings.clear-cache');
    
    // YouTube API Quota Monitoring (Admin only)
    Route::get('/quota/stats', [App\Http\Controllers\Admin\QuotaController::class, 'getQuotaStats'])->name('quota.stats');
    Route::post('/quota/clear-cache', [App\Http\Controllers\Admin\QuotaController::class, 'clearQuotaCache'])->name('quota.clear-cache');
    
    // Content Management (All authenticated users)
    Route::get('/content', [App\Http\Controllers\Admin\ContentController::class, 'index'])->name('content.index');
    Route::post('/content/reorder', [App\Http\Controllers\Admin\ContentController::class, 'reorder'])->name('content.reorder');
    Route::post('/content/reorder-channels', [App\Http\Controllers\Admin\ContentController::class, 'reorderChannels'])->name('content.reorder-channels');
    Route::post('/content/toggle-all-content-section', [App\Http\Controllers\Admin\ContentController::class, 'toggleAllContentSection'])->name('content.toggle-all-content-section');
    Route::post('/content/toggle-channel-visibility', [App\Http\Controllers\Admin\ContentController::class, 'toggleChannelVisibility'])->name('content.toggle-channel-visibility');
    Route::post('/content/delete-channel', [App\Http\Controllers\Admin\ContentController::class, 'deleteChannel'])->name('content.delete-channel');
    Route::post('/content/toggle-visibility', [App\Http\Controllers\Admin\ContentController::class, 'toggleVisibility'])->name('content.toggle-visibility');
    Route::post('/content/toggle-video-visibility', [App\Http\Controllers\Admin\ContentController::class, 'toggleVideoVisibility'])->name('content.toggle-video-visibility');
    Route::post('/content/delete', [App\Http\Controllers\Admin\ContentController::class, 'delete'])->name('content.delete');
    Route::post('/content/add-video', [App\Http\Controllers\Admin\ContentController::class, 'addVideo'])->name('content.add-video');
    Route::post('/content/bulk-delete', [App\Http\Controllers\Admin\ContentController::class, 'bulkDelete'])->name('content.bulk-delete');
    Route::post('/content/bulk-visibility', [App\Http\Controllers\Admin\ContentController::class, 'bulkVisibility'])->name('content.bulk-visibility');
    
    // Channel Import (All authenticated users)
    Route::post('/content/existing-ids', [App\Http\Controllers\Admin\ContentController::class, 'getExistingContentIds'])->name('content.existing-ids');
    Route::post('/content/fetch-channel', [App\Http\Controllers\Admin\ContentController::class, 'fetchChannelContent'])->name('content.fetch-channel');
    Route::post('/content/import-channel', [App\Http\Controllers\Admin\ContentController::class, 'importChannelContent'])->name('content.import-channel');
});

