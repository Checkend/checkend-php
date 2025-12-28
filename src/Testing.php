<?php

declare(strict_types=1);

namespace Checkend;

/**
 * Testing utilities for capturing notices without sending them.
 *
 * Usage:
 *     Testing::setup();
 *     // ... your test code ...
 *     $this->assertTrue(Testing::hasNotices());
 *     Testing::teardown();
 */
class Testing
{
    private static bool $enabled = false;
    /** @var Notice[] */
    private static array $notices = [];

    /**
     * Enable testing mode.
     */
    public static function setup(): void
    {
        self::$enabled = true;
        self::$notices = [];
    }

    /**
     * Disable testing mode and clear notices.
     */
    public static function teardown(): void
    {
        self::$enabled = false;
        self::$notices = [];
    }

    /**
     * Check if testing mode is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Get all captured notices.
     *
     * @return Notice[]
     */
    public static function notices(): array
    {
        return self::$notices;
    }

    /**
     * Get the last captured notice.
     */
    public static function lastNotice(): ?Notice
    {
        if (empty(self::$notices)) {
            return null;
        }
        return self::$notices[count(self::$notices) - 1];
    }

    /**
     * Get the first captured notice.
     */
    public static function firstNotice(): ?Notice
    {
        if (empty(self::$notices)) {
            return null;
        }
        return self::$notices[0];
    }

    /**
     * Get the number of captured notices.
     */
    public static function noticeCount(): int
    {
        return count(self::$notices);
    }

    /**
     * Check if any notices have been captured.
     */
    public static function hasNotices(): bool
    {
        return count(self::$notices) > 0;
    }

    /**
     * Clear all captured notices.
     */
    public static function clearNotices(): void
    {
        self::$notices = [];
    }

    /**
     * Add a notice (internal use only).
     *
     * @internal
     */
    public static function addNotice(Notice $notice): void
    {
        self::$notices[] = $notice;
    }
}
