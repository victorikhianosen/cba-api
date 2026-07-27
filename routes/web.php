<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'responseCode' => '200',
        'message' => 'The endpoint is reachable. Thank You.',
    ], 200);
});