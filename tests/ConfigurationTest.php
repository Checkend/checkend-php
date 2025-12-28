<?php

declare(strict_types=1);

namespace Checkend\Tests;

use Checkend\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testApiKeyFromParameter(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals('test-key', $config->apiKey);
    }

    public function testDefaultEndpoint(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals('https://app.checkend.com', $config->endpoint);
    }

    public function testCustomEndpoint(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'endpoint' => 'https://custom.example.com',
        ]);
        $this->assertEquals('https://custom.example.com', $config->endpoint);
    }

    public function testDefaultEnvironmentIsDevelopment(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals('development', $config->environment);
    }

    public function testEnvironmentFromParameter(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'environment' => 'staging',
        ]);
        $this->assertEquals('staging', $config->environment);
    }

    public function testEnabledDefaultInDevelopment(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertFalse($config->enabled);
    }

    public function testEnabledExplicitOverride(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'enabled' => true,
        ]);
        $this->assertTrue($config->enabled);
    }

    public function testDefaultFilterKeys(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertContains('password', $config->filterKeys);
        $this->assertContains('secret', $config->filterKeys);
        $this->assertContains('api_key', $config->filterKeys);
    }

    public function testCustomFilterKeysExtendDefaults(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'filter_keys' => ['custom_key'],
        ]);
        $this->assertContains('password', $config->filterKeys);
        $this->assertContains('custom_key', $config->filterKeys);
    }

    public function testDefaultAsyncSend(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertTrue($config->asyncSend);
    }

    public function testAsyncSendDisabled(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'async_send' => false,
        ]);
        $this->assertFalse($config->asyncSend);
    }

    public function testDefaultTimeout(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals(15, $config->timeout);
    }

    public function testCustomTimeout(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'timeout' => 30,
        ]);
        $this->assertEquals(30, $config->timeout);
    }

    public function testDefaultMaxQueueSize(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals(1000, $config->maxQueueSize);
    }

    public function testValidationMissingApiKey(): void
    {
        $config = new Configuration([]);
        $errors = $config->validate();
        $this->assertContains('api_key is required', $errors);
        $this->assertFalse($config->isValid());
    }

    public function testValidationWithApiKey(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $errors = $config->validate();
        $this->assertEmpty($errors);
        $this->assertTrue($config->isValid());
    }

    public function testBeforeNotifyDefaultEmpty(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals([], $config->beforeNotify);
    }

    public function testBeforeNotifyWithCallbacks(): void
    {
        $callback = function ($notice) {
            return true;
        };
        $config = new Configuration([
            'api_key' => 'test-key',
            'before_notify' => [$callback],
        ]);
        $this->assertCount(1, $config->beforeNotify);
    }

    // ======= NEW TESTS FOR ADDED FEATURES =======

    public function testDefaultProxyIsNull(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertNull($config->proxy);
    }

    public function testCustomProxy(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'proxy' => 'http://proxy.example.com:8080',
        ]);
        $this->assertEquals('http://proxy.example.com:8080', $config->proxy);
    }

    public function testDefaultSslVerifyIsTrue(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertTrue($config->sslVerify);
    }

    public function testSslVerifyDisabled(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'ssl_verify' => false,
        ]);
        $this->assertFalse($config->sslVerify);
    }

    public function testDefaultSslCaPathIsNull(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertNull($config->sslCaPath);
    }

    public function testCustomSslCaPath(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'ssl_ca_path' => '/path/to/ca.pem',
        ]);
        $this->assertEquals('/path/to/ca.pem', $config->sslCaPath);
    }

    public function testDefaultOpenTimeout(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertEquals(5, $config->openTimeout);
    }

    public function testCustomOpenTimeout(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'open_timeout' => 10,
        ]);
        $this->assertEquals(10, $config->openTimeout);
    }

    public function testDefaultSendRequestDataIsTrue(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertTrue($config->sendRequestData);
    }

    public function testSendRequestDataDisabled(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'send_request_data' => false,
        ]);
        $this->assertFalse($config->sendRequestData);
    }

    public function testDefaultSendSessionDataIsTrue(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertTrue($config->sendSessionData);
    }

    public function testDefaultSendEnvironmentIsFalse(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertFalse($config->sendEnvironment);
    }

    public function testSendEnvironmentEnabled(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'send_environment' => true,
        ]);
        $this->assertTrue($config->sendEnvironment);
    }

    public function testDefaultSendUserDataIsTrue(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertTrue($config->sendUserData);
    }

    public function testSendUserDataDisabled(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'send_user_data' => false,
        ]);
        $this->assertFalse($config->sendUserData);
    }

    public function testDefaultAppNameIsNull(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertNull($config->appName);
    }

    public function testCustomAppName(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'app_name' => 'MyApp',
        ]);
        $this->assertEquals('MyApp', $config->appName);
    }

    public function testDefaultRevisionIsNull(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertNull($config->revision);
    }

    public function testCustomRevision(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'revision' => 'v1.2.3',
        ]);
        $this->assertEquals('v1.2.3', $config->revision);
    }

    public function testDefaultRootPathIsNull(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertNull($config->rootPath);
    }

    public function testCustomRootPath(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'root_path' => '/var/www/myapp',
        ]);
        $this->assertEquals('/var/www/myapp', $config->rootPath);
    }

    public function testDefaultIgnoredExceptionsIncludesLaravelExceptions(): void
    {
        $config = new Configuration(['api_key' => 'test-key']);
        $this->assertContains(
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            $config->ignoredExceptions,
        );
        $this->assertContains(
            'Illuminate\Database\Eloquent\ModelNotFoundException',
            $config->ignoredExceptions,
        );
        $this->assertContains(
            'Illuminate\Session\TokenMismatchException',
            $config->ignoredExceptions,
        );
    }

    public function testCustomIgnoredExceptionsExtendDefaults(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'ignored_exceptions' => ['App\Exceptions\CustomException'],
        ]);
        $this->assertContains(
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            $config->ignoredExceptions,
        );
        $this->assertContains('App\Exceptions\CustomException', $config->ignoredExceptions);
    }

    public function testDisableDefaultIgnoredExceptions(): void
    {
        $config = new Configuration([
            'api_key' => 'test-key',
            'disable_default_ignored_exceptions' => true,
            'ignored_exceptions' => ['App\Exceptions\CustomException'],
        ]);
        $this->assertNotContains(
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            $config->ignoredExceptions,
        );
        $this->assertContains('App\Exceptions\CustomException', $config->ignoredExceptions);
    }
}
