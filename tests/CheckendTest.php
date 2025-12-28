<?php

declare(strict_types=1);

namespace Checkend\Tests;

use Checkend\Checkend;
use Checkend\Testing;
use Exception;
use PHPUnit\Framework\TestCase;

class CheckendTest extends TestCase
{
    protected function setUp(): void
    {
        Checkend::reset();
        Testing::setup();
    }

    protected function tearDown(): void
    {
        Checkend::reset();
        Testing::teardown();
    }

    public function testConfigureReturnsConfiguration(): void
    {
        $config = Checkend::configure(['api_key' => 'test-key', 'enabled' => true]);

        $this->assertEquals('test-key', $config->apiKey);
        $this->assertTrue($config->enabled);
    }

    public function testNotifyCapturesException(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);

        try {
            throw new Exception('Test error');
        } catch (Exception $e) {
            Checkend::notify($e);
        }

        $this->assertTrue(Testing::hasNotices());
        $this->assertEquals(1, Testing::noticeCount());

        $notice = Testing::lastNotice();
        $this->assertEquals('Exception', $notice->errorClass);
        $this->assertEquals('Test error', $notice->message);
    }

    public function testNotifyWithContext(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e, ['context' => ['order_id' => 123]]);
        }

        $notice = Testing::lastNotice();
        $this->assertEquals(123, $notice->context['order_id']);
    }

    public function testNotifyWithUser(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e, ['user' => ['id' => 'user-1', 'email' => 'test@example.com']]);
        }

        $notice = Testing::lastNotice();
        $this->assertEquals('user-1', $notice->user['id']);
        $this->assertEquals('test@example.com', $notice->user['email']);
    }

    public function testNotifyWithTags(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e, ['tags' => ['critical', 'backend']]);
        }

        $notice = Testing::lastNotice();
        $this->assertEquals(['critical', 'backend'], $notice->tags);
    }

    public function testNotifyWithFingerprint(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e, ['fingerprint' => 'custom-fingerprint']);
        }

        $notice = Testing::lastNotice();
        $this->assertEquals('custom-fingerprint', $notice->fingerprint);
    }

    public function testNotifyDisabledDoesNotCapture(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => false]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e);
        }

        $this->assertFalse(Testing::hasNotices());
    }

    public function testNotifyIgnoresConfiguredExceptions(): void
    {
        Checkend::configure([
            'api_key' => 'test-key',
            'enabled' => true,
            'async_send' => false,
            'ignored_exceptions' => ['Exception'],
        ]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e);
        }

        $this->assertFalse(Testing::hasNotices());
    }

    public function testSetAndGetContext(): void
    {
        Checkend::setContext(['key1' => 'value1']);
        Checkend::setContext(['key2' => 'value2']);

        $context = Checkend::getContext();
        $this->assertEquals('value1', $context['key1']);
        $this->assertEquals('value2', $context['key2']);
    }

    public function testSetAndGetUser(): void
    {
        Checkend::setUser(['id' => 'user-1', 'email' => 'test@example.com']);

        $user = Checkend::getUser();
        $this->assertEquals('user-1', $user['id']);
        $this->assertEquals('test@example.com', $user['email']);
    }

    public function testSetAndGetRequest(): void
    {
        Checkend::setRequest(['url' => 'https://example.com', 'method' => 'POST']);

        $request = Checkend::getRequest();
        $this->assertEquals('https://example.com', $request['url']);
        $this->assertEquals('POST', $request['method']);
    }

    public function testClearResetsContext(): void
    {
        Checkend::setContext(['key' => 'value']);
        Checkend::setUser(['id' => 'user-1']);
        Checkend::setRequest(['url' => 'https://example.com']);

        Checkend::clear();

        $this->assertEquals([], Checkend::getContext());
        $this->assertEquals([], Checkend::getUser());
        $this->assertEquals([], Checkend::getRequest());
    }

    public function testContextMergedIntoNotice(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true, 'async_send' => false]);
        Checkend::setContext(['global_key' => 'global_value']);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e, ['context' => ['local_key' => 'local_value']]);
        }

        $notice = Testing::lastNotice();
        $this->assertEquals('global_value', $notice->context['global_key']);
        $this->assertEquals('local_value', $notice->context['local_key']);
    }

    public function testBeforeNotifyCallback(): void
    {
        $called = false;

        Checkend::configure([
            'api_key' => 'test-key',
            'enabled' => true,
            'async_send' => false,
            'before_notify' => [
                function ($notice) use (&$called) {
                    $called = true;
                    return true;
                },
            ],
        ]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e);
        }

        $this->assertTrue($called);
        $this->assertTrue(Testing::hasNotices());
    }

    public function testBeforeNotifyCanSkipNotice(): void
    {
        Checkend::configure([
            'api_key' => 'test-key',
            'enabled' => true,
            'async_send' => false,
            'before_notify' => [
                function ($notice) {
                    return false; // Skip sending
                },
            ],
        ]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            Checkend::notify($e);
        }

        $this->assertFalse(Testing::hasNotices());
    }

    public function testNotifySyncReturnsResponse(): void
    {
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true]);

        try {
            throw new Exception('Test');
        } catch (Exception $e) {
            $response = Checkend::notifySync($e);
        }

        $this->assertNotNull($response);
        $this->assertEquals(0, $response['id']);
        $this->assertTrue(Testing::hasNotices());
    }
}
