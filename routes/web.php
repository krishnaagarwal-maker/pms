<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/notifications/read-all', [DashboardController::class, 'markAllAsRead'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.read-all');

Route::get('/debug-email', function () {
    try {
        Mail::raw('Testing direct SMTP connection.', function ($message) {
            $message->to('krishnaagarwal067466@gmail.com') // Send to yourself first
                    ->subject('Laravel Debug Mail');
        });
        
        return "Laravel says the email sent successfully! Check your Gmail Sent folder now.";
        
    } catch (\Exception $e) {
        return "SMTP Error Caught: " . $e->getMessage();
    }
});

// Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });


require __DIR__.'/auth.php';
