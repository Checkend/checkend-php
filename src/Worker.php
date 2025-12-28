<?php

declare(strict_types=1);

namespace Checkend;

use Exception;

/**
 * Worker for queuing and sending notices.
 *
 * Note: PHP doesn't have native async/threading like other languages.
 * This worker queues notices and sends them at shutdown or when flushed.
 */
class Worker
{
    private Configuration $configuration;
    private Client $client;
    /** @var Notice[] */
    private array $queue = [];

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->client = new Client($configuration);

        // Register shutdown handler to flush queue
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Add a notice to the queue.
     */
    public function push(Notice $notice): bool
    {
        if (count($this->queue) >= $this->configuration->maxQueueSize) {
            $this->configuration->log('warning', 'Queue full, notice dropped');
            return false;
        }

        $this->queue[] = $notice;
        return true;
    }

    /**
     * Send all queued notices.
     */
    public function flush(): void
    {
        while (!empty($this->queue)) {
            $notice = array_shift($this->queue);
            $this->sendWithRetry($notice);
        }
    }

    /**
     * Stop the worker (just flushes in PHP).
     */
    public function stop(): void
    {
        $this->flush();
    }

    private function sendWithRetry(Notice $notice, int $maxRetries = 3): void
    {
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $this->client->send($notice);
                return;
            } catch (Exception $e) {
                if ($attempt < $maxRetries - 1) {
                    $delay = (int) pow(2, $attempt) * 100000; // microseconds
                    usleep($delay);
                } else {
                    $this->configuration->log(
                        'error',
                        "Failed to send notice after {$maxRetries} attempts: " . $e->getMessage(),
                    );
                }
            }
        }
    }
}
