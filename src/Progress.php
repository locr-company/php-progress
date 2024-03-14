<?php

declare(strict_types=1);

namespace Locr\Lib;

/**
 * @property-read int $Counter
 * @property-read \DateInterval $ElapsedTime
 * @property-read ?\DateTimeImmutable $EstimatedTimeOfArrival
 * @property-read ?\DateTimeInterval $EstimatedTimeEnroute
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
            'EstimatedTimeEnroute' => $this->calculateEstimatedTimeEnroute(),
            'EstimatedTimeOfArrival' => $this->calculateEstimatedTimeOfArrival(),
            'PercentageCompleted' => $this->totalCount === null ? null : $this->counter / $this->totalCount * 100,
            'StartTime' => $this->startTime,
            'TotalCount' => $this->totalCount,
            default => throw new \InvalidArgumentException("Property $name does not exist")
        };
    }

    private function calculateEstimatedTimeEnroute(): ?\DateInterval
    {
        if ($this->totalCount === null || $this->counter === 0) {
            return null;
        }

        $secondsRemaining = (int)($this->ElapsedTime->s / $this->counter * ($this->totalCount - $this->counter));
        return new \DateInterval("PT{$secondsRemaining}S");
    }

    private function calculateEstimatedTimeOfArrival(): ?\DateTimeImmutable
    {
        if ($this->totalCount === null || $this->counter === 0) {
            return null;
        }
        $ete = $this->calculateEstimatedTimeEnroute();
        if (is_null($ete)) {
            return null;
        }

        return (new \DateTimeImmutable())->add($ete);
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
