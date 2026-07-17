<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'responseCode' => '200',
        'message' => 'The endpoint is reachable. Thank you for calling our API.',
    ], 200);
});