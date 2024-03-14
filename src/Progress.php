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
    /**
     * @var array<string, callable(Progress): void>
     */
    private array $events = [];
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

        $totalElapsedSeconds = $this->ElapsedTime->s +
            $this->ElapsedTime->i * 60 +
            $this->ElapsedTime->h * 3_600 +
            $this->ElapsedTime->d * 86_400 +
            $this->ElapsedTime->m * 2_592_000 +
            $this->ElapsedTime->y * 31_536_000;

        $hoursRemaining = 0;
        $minutesRemaining = 0;
        $secondsRemaining = ($totalElapsedSeconds / $this->counter * ($this->totalCount - $this->counter));
        if ($secondsRemaining >= 60) {
            $minutesRemaining = (int)floor($secondsRemaining / 60);
            $secondsRemaining = (int)$secondsRemaining % 60;
            if ($minutesRemaining >= 60) {
                $hoursRemaining = (int)floor($minutesRemaining / 60);
                $minutesRemaining = (int)$minutesRemaining % 60;
            }
        }
        $secondsRemaining = (int)$secondsRemaining;

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

        if (isset($this->events[ProgressEvent::Change->value])) {
            $this->events[ProgressEvent::Change->value]($this);
        }
    }

    /**
     * @param callable(Progress): void $callback
     */
    public function on(ProgressEvent $event, callable $callback): void
    {
        $this->events[$event->value] = $callback;
    }

    public function setCounter(int $counter): void
    {
        if ($counter < 0) {
            throw new \InvalidArgumentException('Counter must be greater than or equal to 0');
        }
        $this->counter = $counter;

        if (isset($this->events[ProgressEvent::Change->value])) {
            $this->events[ProgressEvent::Change->value]($this);
        }
    }
}
