<?php

declare(strict_types=1);

namespace Checkend;

/**
 * Configuration for the Checkend SDK.
 */
class Configuration
{
    public const DEFAULT_ENDPOINT = 'https://app.checkend.com';
    public const DEFAULT_TIMEOUT = 15;
    public const DEFAULT_OPEN_TIMEOUT = 5;
    public const DEFAULT_MAX_QUEUE_SIZE = 1000;

    public const DEFAULT_FILTER_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'secret_key',
        'api_key',
        'apikey',
        'access_token',
        'auth_token',
        'authorization',
        'token',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'ssn',
        'social_security',
    ];

    /**
     * Default exceptions to ignore (common framework exceptions).
     * These are typically 404s, CSRF errors, validation errors, etc.
     */
    public const DEFAULT_IGNORED_EXCEPTIONS = [
        // Symfony/Laravel HTTP exceptions
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
        'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException',
        'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException',
        // Laravel specific
        'Illuminate\Session\TokenMismatchException',
        'Illuminate\Auth\AuthenticationException',
        'Illuminate\Auth\Access\AuthorizationException',
        'Illuminate\Database\Eloquent\ModelNotFoundException',
        'Illuminate\Validation\ValidationException',
        'Illuminate\Http\Exceptions\ThrottleRequestsException',
    ];

    // Core settings
    public string $apiKey;
    public string $endpoint;
    public string $environment;
    public bool $enabled;
    public bool $asyncSend;
    public int $maxQueueSize;
    public bool $debug;

    // HTTP settings
    public int $timeout;
    public int $openTimeout;
    public ?string $proxy;
    public bool $sslVerify;
    public ?string $sslCaPath;

    // Data control flags
    public bool $sendRequestData;
    public bool $sendSessionData;
    public bool $sendEnvironment;
    public bool $sendUserData;

    // App metadata
    public ?string $appName;
    public ?string $revision;
    public ?string $rootPath;

    /** @var string[] */
    public array $filterKeys;

    /** @var array<string|class-string> */
    public array $ignoredExceptions;

    /** @var callable[] */
    public array $beforeNotify;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        // API key
        $this->apiKey = $options['api_key'] ?? $_ENV['CHECKEND_API_KEY'] ?? getenv('CHECKEND_API_KEY') ?: '';

        // Endpoint
        $this->endpoint = $options['endpoint']
            ?? $_ENV['CHECKEND_ENDPOINT']
            ?? getenv('CHECKEND_ENDPOINT')
            ?: self::DEFAULT_ENDPOINT;

        // Environment
        $this->environment = $options['environment']
            ?? $_ENV['CHECKEND_ENVIRONMENT']
            ?? getenv('CHECKEND_ENVIRONMENT')
            ?: $this->detectEnvironment();

        // Enabled
        if (isset($options['enabled'])) {
            $this->enabled = (bool) $options['enabled'];
        } else {
            $this->enabled = in_array($this->environment, ['production', 'staging'], true);
        }

        // Async send
        $this->asyncSend = $options['async_send'] ?? true;

        // Max queue size
        $this->maxQueueSize = $options['max_queue_size'] ?? self::DEFAULT_MAX_QUEUE_SIZE;

        // Timeouts
        $this->timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->openTimeout = $options['open_timeout'] ?? self::DEFAULT_OPEN_TIMEOUT;

        // Proxy support
        $this->proxy = $options['proxy'] ?? $_ENV['CHECKEND_PROXY'] ?? getenv('CHECKEND_PROXY') ?: null;

        // SSL configuration
        $this->sslVerify = $options['ssl_verify'] ?? true;
        $this->sslCaPath = $options['ssl_ca_path'] ?? null;

        // Data control flags
        $this->sendRequestData = $options['send_request_data'] ?? true;
        $this->sendSessionData = $options['send_session_data'] ?? true;
        $this->sendEnvironment = $options['send_environment'] ?? false;
        $this->sendUserData = $options['send_user_data'] ?? true;

        // App metadata
        $this->appName = $options['app_name'] ?? null;
        $this->revision = $options['revision'] ?? null;
        $this->rootPath = $options['root_path'] ?? null;

        // Filter keys
        $this->filterKeys = self::DEFAULT_FILTER_KEYS;
        if (isset($options['filter_keys']) && is_array($options['filter_keys'])) {
            $this->filterKeys = array_merge($this->filterKeys, $options['filter_keys']);
        }

        // Ignored exceptions - merge defaults with user-provided
        $this->ignoredExceptions = self::DEFAULT_IGNORED_EXCEPTIONS;
        if (isset($options['ignored_exceptions']) && is_array($options['ignored_exceptions'])) {
            $this->ignoredExceptions = array_merge($this->ignoredExceptions, $options['ignored_exceptions']);
        }

        // Allow disabling default ignored exceptions
        if (isset($options['disable_default_ignored_exceptions']) && $options['disable_default_ignored_exceptions']) {
            $this->ignoredExceptions = $options['ignored_exceptions'] ?? [];
        }

        // Before notify callbacks
        $this->beforeNotify = $options['before_notify'] ?? [];

        // Debug
        $this->debug = $options['debug'] ?? $this->isDebugFromEnv();
    }

    /**
     * Log a message.
     */
    public function log(string $level, string $message): void
    {
        if (!$this->debug && $level === 'debug') {
            return;
        }

        error_log("[Checkend] [{$level}] {$message}");
    }

    /**
     * Validate the configuration.
     *
     * @return string[]
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->apiKey)) {
            $errors[] = 'api_key is required';
        }

        return $errors;
    }

    /**
     * Check if the configuration is valid.
     */
    public function isValid(): bool
    {
        return count($this->validate()) === 0;
    }

    private function detectEnvironment(): string
    {
        $envVars = [
            'APP_ENV',
            'ENVIRONMENT',
            'ENV',
            'PHP_ENV',
        ];

        foreach ($envVars as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }

        return 'development';
    }

    private function isDebugFromEnv(): bool
    {
        $debug = $_ENV['CHECKEND_DEBUG'] ?? getenv('CHECKEND_DEBUG');
        return in_array(strtolower((string) $debug), ['true', '1', 'yes'], true);
    }
}
