# Checkend PHP SDK

PHP SDK for [Checkend](https://checkend.com) error monitoring. Zero dependencies, works with any PHP application.

## Features

- **Zero dependencies** - Uses only PHP standard library
- **Framework integrations** - Laravel, generic PHP error handler
- **Automatic context** - Request, user, and custom context tracking
- **Sensitive data filtering** - Automatic scrubbing of passwords, tokens, etc.
- **Testing utilities** - Capture errors in tests without sending

## Requirements

- PHP 8.1+
- No external dependencies

## Installation

```bash
composer require checkend/checkend
```

## Quick Start

```php
<?php

use Checkend\Checkend;

// Configure the SDK
Checkend::configure(['api_key' => 'your-api-key']);

// Report an error
try {
    doSomething();
} catch (Exception $e) {
    Checkend::notify($e);
}
```

## Configuration

```php
<?php

use Checkend\Checkend;

Checkend::configure([
    'api_key' => 'your-api-key',              // Required
    'endpoint' => 'https://app.checkend.com',  // Optional: Custom endpoint
    'environment' => 'production',             // Optional: Auto-detected
    'enabled' => true,                         // Optional: Enable/disable
    'async_send' => true,                      // Optional: Queue sending (default: true)
    'timeout' => 15,                           // Optional: HTTP timeout in seconds
    'filter_keys' => ['custom_secret'],        // Optional: Additional keys to filter
    'ignored_exceptions' => [NotFoundException::class], // Optional: Exceptions to ignore
    'debug' => false,                          // Optional: Enable debug logging
]);
```

### Environment Variables

```bash
CHECKEND_API_KEY=your-api-key
CHECKEND_ENDPOINT=https://your-server.com
CHECKEND_ENVIRONMENT=production
CHECKEND_DEBUG=true
```

## Manual Error Reporting

```php
<?php

use Checkend\Checkend;

// Basic error reporting
try {
    riskyOperation();
} catch (Exception $e) {
    Checkend::notify($e);
}

// With additional context
try {
    processOrder($orderId);
} catch (Exception $e) {
    Checkend::notify($e, [
        'context' => ['order_id' => $orderId],
        'user' => ['id' => $user->id, 'email' => $user->email],
        'tags' => ['orders', 'critical'],
        'fingerprint' => 'order-processing-error',
    ]);
}

// Synchronous sending (blocks until sent)
$response = Checkend::notifySync($e);
echo "Notice ID: " . $response['id'];
```

## Context & User Tracking

```php
<?php

use Checkend\Checkend;

// Set context for all errors in this request
Checkend::setContext([
    'order_id' => 12345,
    'feature_flag' => 'new-checkout',
]);

// Set user information
Checkend::setUser([
    'id' => $user->id,
    'email' => $user->email,
    'name' => $user->name,
]);

// Set request information
Checkend::setRequest([
    'url' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
]);

// Clear all context (call at end of request)
Checkend::clear();
```

## Framework Integrations

### Laravel

Add the exception handler integration to your `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Checkend\Checkend;
use Checkend\Integrations\LaravelExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        // Configure Checkend
        Checkend::configure(['api_key' => env('CHECKEND_API_KEY')]);

        $this->reportable(function (Throwable $e) {
            LaravelExceptionHandler::reportWithContext($e, [
                'user' => auth()->user()?->only(['id', 'email']),
            ]);
        });
    }
}
```

### Generic PHP

Use the generic error handler for any PHP application:

```php
<?php

use Checkend\Checkend;
use Checkend\Integrations\GenericErrorHandler;

// Configure and register
Checkend::configure(['api_key' => 'your-api-key']);
GenericErrorHandler::register();

// Your application code...
// All errors and uncaught exceptions will be reported automatically
```

## Testing

Use the `Testing` class to capture errors without sending them:

```php
<?php

use Checkend\Checkend;
use Checkend\Testing;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        Testing::setup();
        Checkend::configure(['api_key' => 'test-key', 'enabled' => true]);
    }

    protected function tearDown(): void
    {
        Checkend::reset();
        Testing::teardown();
    }

    public function testErrorReporting(): void
    {
        try {
            throw new \Exception('Test error');
        } catch (\Exception $e) {
            Checkend::notify($e);
        }

        $this->assertTrue(Testing::hasNotices());
        $this->assertEquals(1, Testing::noticeCount());

        $notice = Testing::lastNotice();
        $this->assertEquals('Exception', $notice->errorClass);
    }
}
```

## Filtering Sensitive Data

By default, these keys are filtered: `password`, `secret`, `token`, `api_key`, `authorization`, `credit_card`, `cvv`, `ssn`, etc.

Add custom keys:

```php
Checkend::configure([
    'api_key' => 'your-api-key',
    'filter_keys' => ['custom_secret', 'internal_token'],
]);
```

Filtered values appear as `[FILTERED]` in the dashboard.

## Ignoring Exceptions

```php
Checkend::configure([
    'api_key' => 'your-api-key',
    'ignored_exceptions' => [
        NotFoundException::class,
        ValidationException::class,
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
    ],
]);
```

## Before Notify Callbacks

```php
Checkend::configure([
    'api_key' => 'your-api-key',
    'before_notify' => [
        function ($notice) {
            $notice->context['server'] = gethostname();
            return true; // Continue sending
        },
        function ($notice) {
            if (str_contains($notice->message, 'ignore-me')) {
                return false; // Skip sending
            }
            return true;
        },
    ],
]);
```

## Shutdown Handling

The SDK automatically:
- Flushes pending notices on script shutdown
- Captures fatal errors (E_ERROR, E_PARSE, etc.)

For manual control:

```php
// Flush pending notices
Checkend::flush();

// Stop the worker
Checkend::stop();
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Or with PHPUnit directly
./vendor/bin/phpunit
```

## License

MIT License - see [LICENSE](LICENSE) for details.
