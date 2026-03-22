<?php

use Illuminate\Support\Facades\Route;

// Setup Wizard SPA
Route::view('/setup', 'setup');

// Main SPA (catch-all for React Router)
Route::view('/{any?}', 'app')->where('any', '.*');
