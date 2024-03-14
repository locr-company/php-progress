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
    public const DEFAULT_TO_STRING_FORMAT = 'progress => ${Counter}/${TotalCount} (${PercentageCompleted}%)' .
        '; elapsed: ${ElapsedTime}' .
        '; ete: ${EstimatedTimeEnroute}' .
        '; eta: ${EstimatedTimeOfArrival}';

    private int $counter = 0;
    /**
     * @var array<string, callable(Progress $progress): void>
     */
    private array $events = [];
    private \DateTimeImmutable $startTime;

    /**
     * the constructor
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress(totalCount: 1_000);
     * print $progress->TotalCount; // 1000
     * ```
     */
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

    /**
     * Calculate the estimated time enroute
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress(totalCount: 1_000);
     * $progress->setCounter(200);
     * sleep(1);
     * print $progress->calculateEstimatedTimeEnroute()->format('%H:%I:%S'); // 00:00:04
     * ```
     */
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

    /**
     * Calculate the estimated time of arrival
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress(totalCount: 1_000);
     * $progress->incrementCounter();
     * sleep(1);
     *
     * $eta = $progress->calculateEstimatedTimeOfArrival();
     * print $eta->format('Y-m-d H:i:s'); // 2021-10-10 20:00:01
     * ```
     */
    public function calculateEstimatedTimeOfArrival(): ?\DateTimeImmutable
    {
        $ete = $this->calculateEstimatedTimeEnroute();
        if (is_null($ete)) {
            return null;
        }

        return (new \DateTimeImmutable())->add($ete);
    }

    /**
     * Increment the counter and trigger the change event if it is set
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress();
     * $progress->incrementCounter();
     * print $progress->Counter; // 1
     * ```
     */
    public function incrementCounter(): void
    {
        $this->counter++;

        if (isset($this->events[ProgressEvent::Change->value])) {
            $this->events[ProgressEvent::Change->value]($this);
        }
    }

    /**
     * Register a callback for an event
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     * use Locr\Lib\ProgressEvent;
     *
     * $progress = new Progress();
     * $progress->on(ProgressEvent::Change, function (Progress $progress) {
     *    print $progress->Counter; // 1
     * });
     * $progress->incrementCounter();
     * ```
     *
     * @param callable(Progress $progress): void $callback
     */
    public function on(ProgressEvent $event, callable $callback): void
    {
        $this->events[$event->value] = $callback;
    }

    /**
     * Set the counter and trigger the change event if it is set
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress();
     * $progress->setCounter(10);
     * print $progress->Counter; // 10
     * ```
     */
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

    /**
     * Return a formatted string
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress(totalCount: 1_000);
     * sleep(1);
     * $progress->incrementCounter();
     * // progress => 1/1000 (0.10%); elapsed: 00:00:01; ete: 00:16:39; eta: 2021-10-10 20:00:01
     * print $progress->toFormattedString();
     * ```
     */
    public function toFormattedString(string $format = self::DEFAULT_TO_STRING_FORMAT): string
    {
        $replacements = [
            '${Counter}' => $this->counter,
            '${ElapsedTime}' => $this->ElapsedTime->format('%H:%I:%S'),
            '${EstimatedTimeEnroute}' => $this->calculateEstimatedTimeEnroute()?->format('%H:%I:%S') ?? 'N/A',
            '${EstimatedTimeOfArrival}' => $this->calculateEstimatedTimeOfArrival()?->format('Y-m-d H:i:s') ?? 'N/A',
            '${PercentageCompleted}' => !is_null($this->PercentageCompleted) ?
                sprintf("%.2f", $this->PercentageCompleted) : 'N/A',
            '${TotalCount}' => $this->totalCount ?? '-',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }
}
