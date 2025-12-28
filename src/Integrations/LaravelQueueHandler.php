<?php

declare(strict_types=1);

namespace Checkend\Integrations;

use Checkend\Checkend;
use Checkend\Filters\SanitizeFilter;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Laravel Queue integration for capturing job failures.
 *
 * This handler listens for Laravel Queue events and reports job failures
 * to Checkend with context about the job.
 *
 * Usage:
 *
 *     // In a service provider boot method:
 *     LaravelQueueHandler::register();
 *
 *     // Or it will be automatically registered by LaravelServiceProvider
 */
class LaravelQueueHandler
{
    private static bool $registered = false;

    /**
     * Register queue event listeners.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        // Check if Laravel Queue classes exist
        if (!class_exists(\Illuminate\Queue\Events\JobProcessing::class)) {
            return;
        }

        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = app('events');

        // Listen for job processing (to set context)
        $events->listen(
            \Illuminate\Queue\Events\JobProcessing::class,
            [self::class, 'onJobProcessing'],
        );

        // Listen for job processed (to clear context)
        $events->listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            [self::class, 'onJobProcessed'],
        );

        // Listen for job failures
        $events->listen(
            \Illuminate\Queue\Events\JobFailed::class,
            [self::class, 'onJobFailed'],
        );

        // Listen for job exceptions (before retry)
        $events->listen(
            \Illuminate\Queue\Events\JobExceptionOccurred::class,
            [self::class, 'onJobExceptionOccurred'],
        );

        self::$registered = true;
    }

    /**
     * Handle job processing event - set job context.
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     */
    public static function onJobProcessing(object $event): void
    {
        $jobContext = self::extractJobContext($event);
        Checkend::setContext(['queue' => $jobContext]);
    }

    /**
     * Handle job processed event - clear context.
     *
     * @param \Illuminate\Queue\Events\JobProcessed $event
     */
    public static function onJobProcessed(object $event): void
    {
        Checkend::clear();
    }

    /**
     * Handle job failed event - report the exception.
     *
     * @param \Illuminate\Queue\Events\JobFailed $event
     */
    public static function onJobFailed(object $event): void
    {
        /** @var \Illuminate\Queue\Events\JobFailed $event */
        $jobContext = self::extractJobContext($event);

        Checkend::notify($event->exception, [
            'tags' => ['queue', $event->job->getQueue() ?? 'default'],
            'context' => [
                'queue' => $jobContext,
            ],
        ]);

        // Flush immediately to ensure the error is reported
        // (queue workers are long-running processes)
        Checkend::flush();

        // Clear context after reporting
        Checkend::clear();
    }

    /**
     * Handle job exception event - report exceptions that may be retried.
     *
     * This event fires when a job throws an exception but may still be retried.
     * We only report if this is the final attempt or if the job won't be retried.
     *
     * @param \Illuminate\Queue\Events\JobExceptionOccurred $event
     */
    public static function onJobExceptionOccurred(object $event): void
    {
        /** @var \Illuminate\Queue\Events\JobExceptionOccurred $event */
        $job = $event->job;

        // Check if this is the final attempt
        $attempts = $job->attempts();
        $maxTries = $job->maxTries() ?? 1;

        // Only report if this is the final attempt
        // JobFailed event handles the case when the job actually fails
        if ($attempts < $maxTries) {
            return;
        }

        // Context will be cleared in onJobFailed, but if job won't fail
        // (e.g., it's being released), we should report here
        $jobContext = self::extractJobContext($event);

        Checkend::notify($event->exception, [
            'tags' => ['queue', $job->getQueue() ?? 'default', 'final_attempt'],
            'context' => [
                'queue' => $jobContext,
            ],
        ]);

        Checkend::flush();
    }

    /**
     * Extract job context from a queue event.
     *
     * @return array<string, mixed>
     */
    private static function extractJobContext(object $event): array
    {
        $job = $event->job;

        $context = [
            'connection' => $event->connectionName ?? 'unknown',
            'queue' => $job->getQueue() ?? 'default',
            'job_class' => $job->resolveName(),
            'job_id' => $job->getJobId(),
            'attempts' => $job->attempts(),
        ];

        // Try to get max tries
        $maxTries = $job->maxTries();
        if ($maxTries !== null) {
            $context['max_tries'] = $maxTries;
        }

        // Try to get job payload for additional context
        $payload = $job->payload();
        if (is_array($payload)) {
            // Add UUID if available
            if (isset($payload['uuid'])) {
                $context['uuid'] = $payload['uuid'];
            }

            // Add display name if different from job class
            if (isset($payload['displayName']) && $payload['displayName'] !== $context['job_class']) {
                $context['display_name'] = $payload['displayName'];
            }

            // Add sanitized job data (from command if available)
            if (isset($payload['data']['command'])) {
                $context['job_data'] = self::extractJobData($payload['data']['command']);
            }
        }

        return $context;
    }

    /**
     * Extract and sanitize job data from serialized command.
     *
     * @return array<string, mixed>|null
     */
    private static function extractJobData(string $serializedCommand): ?array
    {
        try {
            $command = unserialize($serializedCommand);
            if (!is_object($command)) {
                return null;
            }

            // Get public properties
            $reflection = new ReflectionClass($command);
            $data = [];

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $name = $property->getName();
                $value = $property->getValue($command);

                // Skip complex objects, just get scalar values and simple arrays
                if (is_scalar($value) || is_null($value)) {
                    $data[$name] = $value;
                } elseif (is_array($value)) {
                    $data[$name] = self::simplifyArray($value);
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $data[$name] = (string) $value;
                } elseif (is_object($value)) {
                    $data[$name] = '[' . get_class($value) . ']';
                }
            }

            if (empty($data)) {
                return null;
            }

            // Sanitize the data
            $configuration = Checkend::getConfiguration();
            if ($configuration !== null) {
                $filter = new SanitizeFilter($configuration->filterKeys);
                $data = $filter->filter($data);
            }

            return $data;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Simplify an array for context (remove complex nested structures).
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private static function simplifyArray(array $array, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['[truncated]'];
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $result[$key] = $value;
            } elseif (is_array($value)) {
                $result[$key] = self::simplifyArray($value, $depth + 1);
            } elseif (is_object($value)) {
                $result[$key] = '[' . get_class($value) . ']';
            }
        }
        return $result;
    }
}
