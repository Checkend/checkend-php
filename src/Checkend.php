<?php

declare(strict_types=1);

namespace Checkend;

use ErrorException;
use Throwable;

/**
 * Main entry point for the Checkend SDK.
 *
 * Usage:
 *     Checkend::configure(['api_key' => 'your-api-key']);
 *     Checkend::notify($exception);
 */
class Checkend
{
    public const VERSION = '0.1.0';

    private static ?Configuration $configuration = null;
    private static ?Worker $worker = null;
    private static bool $initialized = false;

    /** @var array<string, mixed> */
    private static array $context = [];

    /** @var array<string, mixed> */
    private static array $user = [];

    /** @var array<string, mixed> */
    private static array $request = [];

    /**
     * Configure the Checkend SDK.
     *
     * @param array<string, mixed> $options
     */
    public static function configure(array $options): Configuration
    {
        self::$configuration = new Configuration($options);

        if (self::$configuration->asyncSend && self::$configuration->enabled) {
            self::$worker = new Worker(self::$configuration);
        }

        self::$initialized = true;

        // Register shutdown handler
        register_shutdown_function([self::class, 'shutdown']);

        return self::$configuration;
    }

    /**
     * Get the current configuration.
     */
    public static function getConfiguration(): ?Configuration
    {
        return self::$configuration;
    }

    /**
     * Report an exception to Checkend.
     *
     * @param array<string, mixed> $options
     */
    public static function notify(Throwable $exception, array $options = []): ?int
    {
        if (!self::$initialized || !self::$configuration || !self::$configuration->enabled) {
            return null;
        }

        // Check if exception should be ignored
        if (self::shouldIgnore($exception)) {
            return null;
        }

        // Build notice
        $notice = self::buildNotice($exception, $options);

        // Run before notify callbacks
        if (!self::runBeforeNotify($notice)) {
            return null;
        }

        // Handle testing mode
        if (Testing::isEnabled()) {
            Testing::addNotice($notice);
            return null;
        }

        // Send asynchronously or synchronously
        if (self::$configuration->asyncSend && self::$worker) {
            self::$worker->push($notice);
            return null;
        }

        $client = new Client(self::$configuration);
        $response = $client->send($notice);

        return $response['id'] ?? null;
    }

    /**
     * Report an exception to Checkend synchronously.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public static function notifySync(Throwable $exception, array $options = []): ?array
    {
        if (!self::$initialized || !self::$configuration || !self::$configuration->enabled) {
            return null;
        }

        // Check if exception should be ignored
        if (self::shouldIgnore($exception)) {
            return null;
        }

        // Build notice
        $notice = self::buildNotice($exception, $options);

        // Run before notify callbacks
        if (!self::runBeforeNotify($notice)) {
            return null;
        }

        // Handle testing mode
        if (Testing::isEnabled()) {
            Testing::addNotice($notice);
            return ['id' => 0, 'problem_id' => 0];
        }

        $client = new Client(self::$configuration);
        return $client->send($notice);
    }

    /**
     * Set context data for errors.
     *
     * @param array<string, mixed> $context
     */
    public static function setContext(array $context): void
    {
        self::$context = array_merge(self::$context, $context);
    }

    /**
     * Get the current context data.
     *
     * @return array<string, mixed>
     */
    public static function getContext(): array
    {
        return self::$context;
    }

    /**
     * Set user information.
     *
     * @param array<string, mixed> $user
     */
    public static function setUser(array $user): void
    {
        self::$user = $user;
    }

    /**
     * Get the current user information.
     *
     * @return array<string, mixed>
     */
    public static function getUser(): array
    {
        return self::$user;
    }

    /**
     * Set request information.
     *
     * @param array<string, mixed> $request
     */
    public static function setRequest(array $request): void
    {
        self::$request = $request;
    }

    /**
     * Get the current request information.
     *
     * @return array<string, mixed>
     */
    public static function getRequest(): array
    {
        return self::$request;
    }

    /**
     * Clear all context, user, and request data.
     */
    public static function clear(): void
    {
        self::$context = [];
        self::$user = [];
        self::$request = [];
    }

    /**
     * Flush pending notices.
     */
    public static function flush(): void
    {
        if (self::$worker) {
            self::$worker->flush();
        }
    }

    /**
     * Stop the worker.
     */
    public static function stop(): void
    {
        if (self::$worker) {
            self::$worker->stop();
            self::$worker = null;
        }
    }

    /**
     * Reset all state (useful for testing).
     */
    public static function reset(): void
    {
        self::stop();
        self::$configuration = null;
        self::$initialized = false;
        self::clear();
        Testing::teardown();
    }

    /**
     * Shutdown handler.
     */
    public static function shutdown(): void
    {
        // Check for fatal errors
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line'],
            );
            self::notify($exception);
        }

        self::flush();
    }

    private static function shouldIgnore(Throwable $exception): bool
    {
        if (!self::$configuration) {
            return false;
        }

        $filter = new Filters\IgnoreFilter(self::$configuration->ignoredExceptions);
        return $filter->shouldIgnore($exception);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildNotice(Throwable $exception, array $options): Notice
    {
        // Merge context
        $mergedContext = self::$context;
        if (isset($options['context']) && is_array($options['context'])) {
            $mergedContext = array_merge($mergedContext, $options['context']);
        }

        // Merge user
        $mergedUser = self::$user;
        if (isset($options['user']) && is_array($options['user'])) {
            $mergedUser = $options['user'];
        }

        // Merge request
        $mergedRequest = self::$request;
        if (isset($options['request']) && is_array($options['request'])) {
            $mergedRequest = array_merge($mergedRequest, $options['request']);
        }

        $builder = new NoticeBuilder(self::$configuration);
        return $builder->build(
            $exception,
            $mergedContext,
            $mergedUser,
            $mergedRequest,
            $options['fingerprint'] ?? null,
            $options['tags'] ?? [],
        );
    }

    private static function runBeforeNotify(Notice $notice): bool
    {
        if (!self::$configuration || empty(self::$configuration->beforeNotify)) {
            return true;
        }

        foreach (self::$configuration->beforeNotify as $callback) {
            try {
                $result = $callback($notice);
                if ($result === false) {
                    return false;
                }
            } catch (Throwable) {
                // Ignore callback errors
            }
        }

        return true;
    }
}
