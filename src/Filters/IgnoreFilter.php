<?php

declare(strict_types=1);

namespace Checkend\Filters;

use ReflectionClass;
use Throwable;

/**
 * Filter for ignoring certain exceptions.
 */
class IgnoreFilter
{
    /** @var array<string|class-string> */
    private array $patterns;

    /**
     * @param array<string|class-string> $patterns
     */
    public function __construct(array $patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * Check if an exception should be ignored.
     */
    public function shouldIgnore(Throwable $exception): bool
    {
        $exceptionClass = get_class($exception);

        foreach ($this->patterns as $pattern) {
            // Class name or parent class match
            if (is_string($pattern)) {
                // Exact class match
                if ($exceptionClass === $pattern) {
                    return true;
                }

                // Check if it's a subclass
                if (is_a($exception, $pattern)) {
                    return true;
                }

                // Check short class name
                $shortName = (new ReflectionClass($exception))->getShortName();
                if ($shortName === $pattern) {
                    return true;
                }

                // Regex match
                if ($this->matchesRegex($exceptionClass, $pattern)) {
                    return true;
                }
                if ($this->matchesRegex($shortName, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matchesRegex(string $name, string $pattern): bool
    {
        // Only try regex if pattern looks like a regex
        if (strpos($pattern, '*') !== false || strpos($pattern, '|') !== false) {
            $regexPattern = '/^' . str_replace(['\\*', '\\|'], ['.*', '|'], preg_quote($pattern, '/')) . '$/';
            return (bool) preg_match($regexPattern, $name);
        }

        return false;
    }
}
