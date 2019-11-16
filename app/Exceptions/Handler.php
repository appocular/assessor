<?php

declare(strict_types=1);

namespace Appocular\Assessor\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array<string>
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     */
    public function report(Exception $e): void
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
    public function render($request, Exception $e): Response
    {
        $rendered = parent::render($request, $e);

        if ($e instanceof ValidationException) {
            return new Response($e->errors(), $rendered->getStatusCode());
        }

        if ($e instanceof ModelNotFoundException) {
            return new Response("", $rendered->getStatusCode(), ['Content-Type' => 'text/plain']);
        }

        return new Response($e->getMessage() . "\n", $rendered->getStatusCode(), ['Content-Type' => 'text/plain']);
    }
}
