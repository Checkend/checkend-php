<?php

declare(strict_types=1);

namespace Checkend;

use Throwable;

/**
 * Builds Notice objects from exceptions.
 */
class NoticeBuilder
{
    private const MAX_BACKTRACE_LINES = 100;
    private const MAX_MESSAGE_LENGTH = 10000;

    private Configuration $configuration;
    private Filters\SanitizeFilter $sanitizeFilter;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->sanitizeFilter = new Filters\SanitizeFilter($configuration->filterKeys);
    }

    /**
     * Build a Notice from an exception.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $user
     * @param array<string, mixed> $request
     * @param string[] $tags
     */
    public function build(
        Throwable $exception,
        array $context = [],
        array $user = [],
        array $request = [],
        ?string $fingerprint = null,
        array $tags = [],
    ): Notice {
        $errorClass = $this->extractClassName($exception);
        $message = $this->extractMessage($exception);
        $backtrace = $this->extractBacktrace($exception);

        // Sanitize context data
        $sanitizedContext = $this->sanitizeFilter->filter($context);

        // Add environment variables if configured
        if ($this->configuration->sendEnvironment) {
            $sanitizedContext['environment_variables'] = $this->sanitizeFilter->filter($_ENV);
        }

        // Handle user data based on configuration
        $sanitizedUser = [];
        if ($this->configuration->sendUserData && !empty($user)) {
            $sanitizedUser = $this->sanitizeFilter->filter($user);
        }

        // Handle request data based on configuration
        $sanitizedRequest = [];
        if ($this->configuration->sendRequestData && !empty($request)) {
            $sanitizedRequest = $this->sanitizeFilter->filter($request);
        }

        return new Notice(
            $errorClass,
            $message,
            $backtrace,
            $fingerprint,
            $tags,
            $sanitizedContext,
            $sanitizedRequest,
            $sanitizedUser,
            $this->configuration->environment,
            null,
            $this->buildNotifier(),
        );
    }

    private function extractClassName(Throwable $exception): string
    {
        return get_class($exception);
    }

    private function extractMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '...';
        }
        return $message;
    }

    /**
     * @return string[]
     */
    private function extractBacktrace(Throwable $exception): array
    {
        $backtrace = [];

        // Add the exception location first
        $file = $this->cleanPath($exception->getFile());
        $backtrace[] = sprintf('%s:%d', $file, $exception->getLine());

        // Add the stack trace
        $trace = $exception->getTrace();
        $count = 0;

        foreach ($trace as $frame) {
            if ($count >= self::MAX_BACKTRACE_LINES - 1) {
                break;
            }

            $file = isset($frame['file']) ? $this->cleanPath($frame['file']) : '[internal function]';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'];
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $functionName = $class . $type . $function;
            $backtrace[] = sprintf('%s:%d in %s', $file, $line, $functionName);
            $count++;
        }

        return $backtrace;
    }

    /**
     * Clean a file path by replacing the root path with [PROJECT_ROOT].
     */
    private function cleanPath(string $path): string
    {
        $rootPath = $this->configuration->rootPath;
        if ($rootPath !== null && str_starts_with($path, $rootPath)) {
            return '[PROJECT_ROOT]' . substr($path, strlen($rootPath));
        }
        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function buildNotifier(): array
    {
        $notifier = [
            'name' => 'checkend-php',
            'version' => Checkend::VERSION,
            'language' => 'php',
            'language_version' => PHP_VERSION,
        ];

        // Add app metadata if available
        if ($this->configuration->appName !== null) {
            $notifier['app_name'] = $this->configuration->appName;
        }

        if ($this->configuration->revision !== null) {
            $notifier['revision'] = $this->configuration->revision;
        }

        return $notifier;
    }
}
