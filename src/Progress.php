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

    public function calculateEstimatedTimeEnroute(): ?\DateInterval
    {
        if ($this->totalCount === null || $this->counter === 0) {
            return null;
        }

        $hoursRemaining = 0;
        $minutesRemaining = 0;
        $secondsRemaining = (int)($this->ElapsedTime->s / $this->counter * ($this->totalCount - $this->counter));
        if ($secondsRemaining >= 60) {
            $minutesRemaining = floor($secondsRemaining / 60);
            $secondsRemaining %= 60;
            if ($minutesRemaining >= 60) {
                $hoursRemaining = floor($minutesRemaining / 60);
                $minutesRemaining %= 60;
            }
        }

        return new \DateInterval("PT{$hoursRemaining}H{$minutesRemaining}M{$secondsRemaining}S");
    }

    public function calculateEstimatedTimeOfArrival(): ?\DateTimeImmutable
    {
        if ($this->totalCount === null) {
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
