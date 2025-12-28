<?php

declare(strict_types=1);

namespace Checkend\Tests;

use Checkend\Filters\IgnoreFilter;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IgnoreFilterTest extends TestCase
{
    public function testIgnoreByClassName(): void
    {
        $filter = new IgnoreFilter([Exception::class]);

        $this->assertTrue($filter->shouldIgnore(new Exception('test')));
        // Note: RuntimeException extends Exception, so it's also ignored (tested in testIgnoreByParentClass)
    }

    public function testIgnoreByShortName(): void
    {
        $filter = new IgnoreFilter(['Exception']);

        $this->assertTrue($filter->shouldIgnore(new Exception('test')));
    }

    public function testIgnoreByParentClass(): void
    {
        $filter = new IgnoreFilter([Exception::class]);

        // RuntimeException extends Exception
        $this->assertTrue($filter->shouldIgnore(new RuntimeException('test')));
    }

    public function testIgnoreMultiplePatterns(): void
    {
        $filter = new IgnoreFilter([Exception::class, InvalidArgumentException::class]);

        $this->assertTrue($filter->shouldIgnore(new Exception('test')));
        $this->assertTrue($filter->shouldIgnore(new InvalidArgumentException('test')));
    }

    public function testEmptyIgnoreList(): void
    {
        $filter = new IgnoreFilter([]);

        $this->assertFalse($filter->shouldIgnore(new Exception('test')));
    }

    public function testIgnoreByWildcardPattern(): void
    {
        $filter = new IgnoreFilter(['*Exception']);

        $this->assertTrue($filter->shouldIgnore(new Exception('test')));
        $this->assertTrue($filter->shouldIgnore(new RuntimeException('test')));
    }
}
