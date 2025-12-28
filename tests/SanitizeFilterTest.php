<?php

declare(strict_types=1);

namespace Checkend\Tests;

use Checkend\Filters\SanitizeFilter;
use PHPUnit\Framework\TestCase;

class SanitizeFilterTest extends TestCase
{
    private SanitizeFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new SanitizeFilter(['password', 'secret', 'token']);
    }

    public function testFilterSimpleArray(): void
    {
        $data = ['username' => 'john', 'password' => 'secret123'];
        $result = $this->filter->filter($data);

        $this->assertEquals('john', $result['username']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['password']);
    }

    public function testFilterNestedArray(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'credentials' => [
                    'password' => 'secret123',
                    'api_token' => 'abc123',
                ],
            ],
        ];
        $result = $this->filter->filter($data);

        $this->assertEquals('John', $result['user']['name']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['user']['credentials']['password']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['user']['credentials']['api_token']);
    }

    public function testFilterIndexedArray(): void
    {
        $data = [
            'users' => [
                ['name' => 'Alice', 'password' => 'pass1'],
                ['name' => 'Bob', 'password' => 'pass2'],
            ],
        ];
        $result = $this->filter->filter($data);

        $this->assertEquals('Alice', $result['users'][0]['name']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['users'][0]['password']);
        $this->assertEquals('Bob', $result['users'][1]['name']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['users'][1]['password']);
    }

    public function testFilterCaseInsensitive(): void
    {
        $data = [
            'PASSWORD' => 'value1',
            'Password' => 'value2',
            'password' => 'value3',
        ];
        $result = $this->filter->filter($data);

        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['PASSWORD']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['Password']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['password']);
    }

    public function testFilterPartialMatch(): void
    {
        $data = [
            'user_password' => 'secret',
            'password_hash' => 'hash',
            'secret_key' => 'key',
        ];
        $result = $this->filter->filter($data);

        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['user_password']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['password_hash']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['secret_key']);
    }

    public function testFilterPreservesNonSensitiveData(): void
    {
        $data = [
            'id' => 123,
            'name' => 'Test',
            'active' => true,
            'value' => 3.14,
        ];
        $result = $this->filter->filter($data);

        $this->assertEquals(123, $result['id']);
        $this->assertEquals('Test', $result['name']);
        $this->assertTrue($result['active']);
        $this->assertEquals(3.14, $result['value']);
    }

    public function testFilterHandlesNull(): void
    {
        $data = ['key' => null, 'password' => null];
        $result = $this->filter->filter($data);

        $this->assertNull($result['key']);
        $this->assertEquals(SanitizeFilter::FILTERED_VALUE, $result['password']);
    }

    public function testFilterTruncatesLongStrings(): void
    {
        $longString = str_repeat('x', 15000);
        $data = ['message' => $longString];
        $result = $this->filter->filter($data);

        $this->assertEquals(10003, strlen($result['message'])); // 10000 + '...'
        $this->assertStringEndsWith('...', $result['message']);
    }

    public function testFilterHandlesDeepNesting(): void
    {
        $data = ['level' => 0];
        $current = &$data;
        for ($i = 0; $i < 15; $i++) {
            $current['nested'] = ['level' => $i + 1];
            $current = &$current['nested'];
        }

        // Should not throw, should handle max depth
        $result = $this->filter->filter($data);
        $this->assertEquals(0, $result['level']);
    }

    public function testFilterEmptyArray(): void
    {
        $result = $this->filter->filter([]);
        $this->assertEquals([], $result);
    }
}
