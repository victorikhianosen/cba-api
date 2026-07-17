<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    use ApiResponse;

    public function render(Throwable $e): JsonResponse
    {
        $statusCode   = 500;
        $responseCode = '500';
        $errors       = [];
        $message      = 'System busy';

        if ($e instanceof ValidationException) {
            $statusCode = 422; $responseCode = '422';
            $message = 'Validation error.'; $errors = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $statusCode = 401; $responseCode = '401'; $message = 'Unauthenticated.';
        } elseif ($e instanceof AuthorizationException) {
            $statusCode = 403; $responseCode = '403'; $message = 'Access denied.';
        } elseif ($e instanceof ModelNotFoundException) {
            $statusCode = 404; $responseCode = '404';
            $message = class_basename($e->getModel() ?? 'Resource') . ' not found.';
        } elseif ($e instanceof NotFoundHttpException) {
            $statusCode = 404; $responseCode = '404'; $message = 'Route not found.';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $statusCode = 405; $responseCode = '405'; $message = 'Method not allowed.';
        } elseif ($e instanceof TooManyRequestsHttpException) {
            $statusCode = 429; $responseCode = '429'; $message = 'Too many requests.';
        } elseif ($e instanceof InvalidArgumentException) {
            $statusCode = 422; $responseCode = '422'; $message = $e->getMessage();
        } elseif ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
            $responseCode = (string) $statusCode;
            $message = $e->getMessage() ?: 'HTTP error.';
        } else {
            $message = config('app.debug')
                ? $e->getMessage()
                : 'We couldn’t process your request at the moment. Please try again shortly.';
        }

        return $this->error($message, $responseCode, $errors, $statusCode);
    }
}