<?php

declare(strict_types=1);

namespace Locr\Lib;

/**
 * @property-read int $Counter
 * @property-read \DateInterval $ElapsedTime
 * @property-read ?float $PercentageCompleted
 * @property-read \DateTimeImmutable $StartTime
 * @property-read ?int $TotalCount
 */
class Progress
{
    private int $counter = 0;
    private \DateTimeImmutable $startTime;

    public function __construct(private ?int $totalCount = null)
    {
        $this->startTime = new \DateTimeImmutable();
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'Counter' => $this->counter,
            'ElapsedTime' => (new \DateTimeImmutable())->diff($this->startTime),
            'PercentageCompleted' => $this->totalCount === null ? null : $this->counter / $this->totalCount * 100,
            'StartTime' => $this->startTime,
            'TotalCount' => $this->totalCount,
            default => throw new \InvalidArgumentException("Property $name does not exist")
        };
    }

    public function incrementCounter(): void
    {
        $this->counter++;
    }

    public function setCounter(int $counter): void
    {
        if ($counter < 0) {
            throw new \InvalidArgumentException('Counter must be greater than or equal to 0');
        }
        $this->counter = $counter;
    }
}
