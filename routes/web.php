<?php

use Illuminate\Support\Facades\Route;

// Setup Wizard SPA
Route::view('/setup', 'setup');

// Main SPA (catch-all for React Router — excludes admin, api, docs, livewire, sanctum, filament)
Route::view('/{any?}', 'app')->where('any', '^(?!admin|api|docs|livewire|filament|sanctum|storage|up).*$');
