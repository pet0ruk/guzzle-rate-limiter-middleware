<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

class RateLimiter
{
    const TIME_FRAME_MINUTE = 'minute';
    const TIME_FRAME_SECOND = 'second';

    /** @var int|float */
    protected $limit;

    /** @var string */
    protected $timeFrame;

    /** @var \Spatie\RateLimiter\Store */
    protected $store;

    /** @var \Spatie\GuzzleRateLimiterMiddleware\Deferrer */
    protected $deferrer;

    /**
     * @param int|float $limit
     */
    public function __construct(
        int|float $limit,
        string $timeFrame,
        Store $store,
        Deferrer $deferrer
    ) {
        if (!is_numeric($limit) || $limit <= 0) {
            throw new \InvalidArgumentException('Limit must be a positive number.');
        }
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->store = $store;
        $this->deferrer = $deferrer;
    }

    public function handle(callable $callback)
    {
        $delayUntilNextRequest = $this->delayUntilNextRequest();

        if ($delayUntilNextRequest > 0) {
            $this->deferrer->sleep($delayUntilNextRequest);
        }

        $this->store->push(
            $this->deferrer->getCurrentTime(),
            $this->limit
        );

        return $callback();
    }

    protected function delayUntilNextRequest(): int
    {
        // For float rates < 1, enforce a minimum interval between requests
        if ($this->timeFrame === self::TIME_FRAME_SECOND && $this->limit < 1) {
            $minIntervalMs = (int) round(1000 / $this->limit);
            $timestamps = $this->store->get();
            if (empty($timestamps)) {
                return 0;
            }
            $lastRequestTime = end($timestamps);
            $elapsed = $this->deferrer->getCurrentTime() - $lastRequestTime;
            if ($elapsed >= $minIntervalMs) {
                return 0;
            }
            return $minIntervalMs - $elapsed;
        }

        // Default logic for limit >= 1
        $currentTimeFrameStart = $this->deferrer->getCurrentTime() - $this->timeFrameLengthInMilliseconds();

        $requestsInCurrentTimeFrame = array_values(array_filter(
            $this->store->get(),
            function (int $timestamp) use ($currentTimeFrameStart) {
                return $timestamp >= $currentTimeFrameStart;
            }
        ));

        if (count($requestsInCurrentTimeFrame) < $this->limit) {
            return 0;
        }

        $oldestRequestStartTimeRelativeToCurrentTimeFrame =
            $this->deferrer->getCurrentTime() - $requestsInCurrentTimeFrame[0];

        return $this->timeFrameLengthInMilliseconds() - $oldestRequestStartTimeRelativeToCurrentTimeFrame;
    }

    protected function timeFrameLengthInMilliseconds(): int
    {
        if ($this->timeFrame === self::TIME_FRAME_MINUTE) {
            return 60 * 1000;
        }

        return 1000;
    }
}
