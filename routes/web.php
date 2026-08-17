<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(env('SWAGGER_UI_URL', 'http://localhost:8081')));
