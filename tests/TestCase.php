<?php

namespace Spatie\GuzzleRateLimiterMiddleware\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Spatie\GuzzleRateLimiterMiddleware\InMemoryStore;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    /** @var \Spatie\GuzzleRateLimiterMiddleware\Tests\TestDeferrer */
    protected $deferrer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deferrer = new TestDeferrer();
    }

    public function createRateLimiter(int|float $limit, string $timeFrame): RateLimiter
    {
        return new RateLimiter($limit, $timeFrame, new InMemoryStore(), $this->deferrer);
    }
}
