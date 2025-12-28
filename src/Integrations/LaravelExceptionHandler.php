<?php

declare(strict_types=1);

namespace Checkend\Integrations;

use Checkend\Checkend;
use Throwable;

/**
 * Laravel Exception Handler integration for Checkend.
 *
 * Usage in app/Exceptions/Handler.php:
 *
 *     use Checkend\Integrations\LaravelExceptionHandler;
 *
 *     public function report(Throwable $e): void
 *     {
 *         LaravelExceptionHandler::report($e);
 *         parent::report($e);
 *     }
 *
 * Or with Laravel 11+ context:
 *
 *     public function report(Throwable $e): void
 *     {
 *         LaravelExceptionHandler::reportWithContext($e, [
 *             'user' => auth()->user()?->only(['id', 'email']),
 *         ]);
 *         parent::report($e);
 *     }
 */
class LaravelExceptionHandler
{
    /**
     * Report an exception to Checkend.
     */
    public static function report(Throwable $exception): void
    {
        Checkend::notify($exception);
    }

    /**
     * Report an exception with additional context.
     *
     * @param array<string, mixed> $context
     */
    public static function reportWithContext(Throwable $exception, array $context = []): void
    {
        $options = [];

        if (isset($context['user'])) {
            $options['user'] = $context['user'];
            unset($context['user']);
        }

        if (isset($context['tags'])) {
            $options['tags'] = $context['tags'];
            unset($context['tags']);
        }

        if (isset($context['fingerprint'])) {
            $options['fingerprint'] = $context['fingerprint'];
            unset($context['fingerprint']);
        }

        if (!empty($context)) {
            $options['context'] = $context;
        }

        Checkend::notify($exception, $options);
    }

    /**
     * Set the authenticated user for error context.
     *
     * @param array<string, mixed> $user
     */
    public static function setUser(array $user): void
    {
        Checkend::setUser($user);
    }
}
