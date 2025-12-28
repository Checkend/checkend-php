<?php

declare(strict_types=1);

namespace Checkend\Filters;

/**
 * Filter for sanitizing sensitive data.
 */
class SanitizeFilter
{
    public const FILTERED_VALUE = '[FILTERED]';
    private const MAX_DEPTH = 10;
    private const MAX_STRING_LENGTH = 10000;

    /** @var string[] */
    private array $filterKeys;

    /**
     * @param string[] $filterKeys
     */
    public function __construct(array $filterKeys)
    {
        $this->filterKeys = array_map('strtolower', $filterKeys);
    }

    /**
     * Recursively filter sensitive data from an array.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filter(array $data): array
    {
        return $this->filterArray($data, 0);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterArray(array $data, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['_truncated' => '[MAX DEPTH EXCEEDED]'];
        }

        $result = [];
        foreach ($data as $key => $value) {
            if ($this->shouldFilter((string) $key)) {
                $result[$key] = self::FILTERED_VALUE;
            } else {
                $result[$key] = $this->filterValue($value, $depth + 1);
            }
        }

        return $result;
    }

    private function filterValue(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[MAX DEPTH EXCEEDED]';
        }

        if (is_array($value)) {
            // Check if it's an associative array or indexed array
            if ($this->isAssociativeArray($value)) {
                return $this->filterArray($value, $depth);
            }
            return $this->filterIndexedArray($value, $depth);
        }

        if (is_string($value)) {
            return $this->truncateString($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->truncateString((string) $value);
            }
            return '[OBJECT: ' . get_class($value) . ']';
        }

        return '[UNKNOWN TYPE]';
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int, mixed>
     */
    private function filterIndexedArray(array $data, int $depth): array
    {
        $result = [];
        foreach ($data as $item) {
            $result[] = $this->filterValue($item, $depth + 1);
        }
        return $result;
    }

    private function shouldFilter(string $key): bool
    {
        $keyLower = strtolower($key);

        foreach ($this->filterKeys as $filterKey) {
            if (str_contains($keyLower, $filterKey)) {
                return true;
            }
        }

        return false;
    }

    private function truncateString(string $value): string
    {
        if (strlen($value) > self::MAX_STRING_LENGTH) {
            return substr($value, 0, self::MAX_STRING_LENGTH) . '...';
        }
        return $value;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
