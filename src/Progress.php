<?php

declare(strict_types=1);

namespace Locr\Lib;

/**
 * @property-read int $Counter
 * @property-read \DateInterval $ElapsedTime
 * @property-read null $Null
 * @property-read ?float $PercentageCompleted
 * @property-read \DateTimeInterface $StartTime
 * @property-read ?int $TotalCount
 */
class Progress
{
    private const MILLISECONDS_PER_SECOND = 1_000;
    private const SECONDS_PER_MINUTE = 60;
    private const SECONDS_PER_HOUR = 3_600;
    private const SECONDS_PER_DAY = 86_400;
    private const SECONDS_PER_MONTH = 2_592_000;
    private const SECONDS_PER_YEAR = 31_536_000;
    private const MINUTES_PER_HOUR = 60;
    private const HOURS_PER_DAY = 24;
    private const DAYS_PER_YEAR = 365;

    private const BINARY_MULTIPLIER = 1_024;

    private const ZERO = 0;
    private const ZERO_DECIMALS = 0;

    public const DEFAULT_TO_STRING_FORMAT = 'progress => ${Counter}/${TotalCount} (${PercentageCompleted}%)' .
        '; elapsed: ${ElapsedTime}' .
        '; ete: ${EstimatedTimeEnroute}' .
        '; eta: ${EstimatedTimeOfArrival}';

    /**
     * @var int<0, max> $counter
     */
    private int $counter = 0;
    /**
     * @var array<
     *  string,
     *  array{
     *      'callback': callable(Progress $progress): void,
     *      'options': array{
     *          'update-interval-ms-threshold'?: int
     *      },
     *      'internal-data': array{
     *          'last-time-event-fired'?: \DateTimeInterface
     *      }
     *  }
     * >
     */
    private array $events = [];
    private \DateTimeInterface $startTime;


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
    public function __construct(
        private ?int $totalCount = null,
        private ?string $locale = null,
        private ?ProgressUnit $unit = null
    ) {
        $this->startTime = new \DateTimeImmutable(getenv('TEST_START_TIME', true) ?: 'now');
    }

    public function __get(string $name): mixed
    {
        $now = new \DateTimeImmutable(getenv('TEST_TIME_NOW', true) ?: 'now');
        return match ($name) {
            'Counter' => $this->counter,
            'ElapsedTime' => ($now)->diff($this->startTime),
            'PercentageCompleted' => $this->totalCount === null ? null : $this->counter / $this->totalCount * 100,
            'StartTime' => $this->startTime,
            'TotalCount' => $this->totalCount,
            default => null
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
            $this->ElapsedTime->i * self::SECONDS_PER_MINUTE +
            $this->ElapsedTime->h * self::SECONDS_PER_HOUR +
            $this->ElapsedTime->d * self::SECONDS_PER_DAY +
            $this->ElapsedTime->m * self::SECONDS_PER_MONTH +
            $this->ElapsedTime->y * self::SECONDS_PER_YEAR;

        $yearsRemaining = 0;
        $daysRemaining = 0;
        $hoursRemaining = 0;
        $minutesRemaining = 0;
        $secondsRemaining = ($totalElapsedSeconds / $this->counter * ($this->totalCount - $this->counter));
        if ($secondsRemaining >= self::SECONDS_PER_MINUTE) {
            $minutesRemaining = intval(floor($secondsRemaining / self::SECONDS_PER_MINUTE));
            $secondsRemaining = intval($secondsRemaining) % self::SECONDS_PER_MINUTE;
        }
        if ($minutesRemaining >= self::MINUTES_PER_HOUR) {
            $hoursRemaining = intval(floor($minutesRemaining / self::MINUTES_PER_HOUR));
            $minutesRemaining = intval($minutesRemaining) % self::MINUTES_PER_HOUR;
        }
        if ($hoursRemaining >= self::HOURS_PER_DAY) {
            $daysRemaining = intval(floor($hoursRemaining / self::HOURS_PER_DAY));
            $hoursRemaining = intval($hoursRemaining) % self::HOURS_PER_DAY;
        }
        if ($daysRemaining >= self::DAYS_PER_YEAR) {
            $yearsRemaining = intval(floor($daysRemaining / self::DAYS_PER_YEAR));
            $daysRemaining = intval($daysRemaining) % self::DAYS_PER_YEAR;
        }
        $secondsRemaining = intval($secondsRemaining);

        return new \DateInterval(
            "P{$yearsRemaining}Y{$daysRemaining}DT{$hoursRemaining}H{$minutesRemaining}M{$secondsRemaining}S"
        );
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
    public function calculateEstimatedTimeOfArrival(): ?\DateTimeInterface
    {
        $ete = $this->calculateEstimatedTimeEnroute();
        if (is_null($ete)) {
            return null;
        }

        $now = new \DateTimeImmutable(getenv('TEST_TIME_NOW', true) ?: 'now');
        return ($now)->add($ete);
    }
    
    private function formatValue(int $value, ?string $locale = null): string
    {
        $options = [];

        $unitExt = '';
        if (!is_null($this->unit) && $this->unit === ProgressUnit::Byte) {
            $byteUnits = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
            $index = 0;
            while ($value >= self::BINARY_MULTIPLIER && $index < count($byteUnits) - 1) {
                $value /= self::BINARY_MULTIPLIER;
                $index++;
            }
            $options['maximumFractionDigits'] = $index === 0 ? 0 : 2;
            $value = round($value, $options['maximumFractionDigits']);
            $unitExt = ' ' . $byteUnits[$index];
        }

        $valueString = number_format($value, self::ZERO_DECIMALS, '.', '');
        if (isset($options['maximumFractionDigits'])) {
            $valueString = sprintf('%.' . $options['maximumFractionDigits'] . 'f', $value);
        }
        if (!is_null($locale)) {
            $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            if (isset($options['maximumFractionDigits'])) {
                if ($options['maximumFractionDigits'] > 0) {
                    $numberFormatter->setAttribute(
                        \NumberFormatter::MIN_FRACTION_DIGITS,
                        $options['maximumFractionDigits']
                    );
                }
                $numberFormatter->setAttribute(
                    \NumberFormatter::MAX_FRACTION_DIGITS,
                    $options['maximumFractionDigits']
                );
            }
            $valueString = $numberFormatter->format($value);
        }

        return $valueString . $unitExt;
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

        $this->raiseEvent(ProgressEvent::Change);
    }

    /**
     * Register a callback for an event
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\{Progress, ProgressEvent};
     *
     * $progress = new Progress();
     * $progress->on(
     *      ProgressEvent::Change,
     *      function (Progress $progress) {
     *          print $progress->Counter; // 1
     *      },
     *      ['update-interval-ms-threshold' => 200]
     * );
     * $progress->incrementCounter();
     * ```
     *
     * @param callable(Progress $progress): void $callback
     * @param array{'update-interval-ms-threshold'?: int} $options
     */
    public function on(ProgressEvent $event, callable $callback, array $options = []): void
    {
        $this->events[$event->value] = [
            'callback' => $callback,
            'options' => $options,
            'internal-data' => []
        ];
    }

    /**
     * @param array<mixed> $args
     */
    private function raiseEvent(ProgressEvent $event, array $args = []): void
    {
        if (isset($this->events[$event->value])) {
            $evt = &$this->events[$event->value];
            $options = $evt['options'];
            $internalData = $evt['internal-data'];

            $now = new \DateTimeImmutable(getenv('TEST_TIME_NOW', true) ?: 'now');
            if (isset($options['update-interval-ms-threshold']) && isset($internalData['last-time-event-fired'])) {
                $lastTimeEventFired = $internalData['last-time-event-fired'];
                $interval = $now->diff($lastTimeEventFired);
                if ($interval !== false) {
                    $intervalMs = $interval->s * self::MILLISECONDS_PER_SECOND +
                        $interval->f * self::MILLISECONDS_PER_SECOND;
                    if ($intervalMs < $options['update-interval-ms-threshold']) {
                        return;
                    }
                }
            }

            $evt['internal-data']['last-time-event-fired'] = $now;

            $evt['callback']($this, ...$args);
        }
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
        if ($counter < self::ZERO) {
            throw new \InvalidArgumentException('Counter must be greater than or equal to ' . self::ZERO);
        }
        $this->counter = $counter;

        $this->raiseEvent(ProgressEvent::Change);
    }

    /**
     * Set the total count and trigger the change event if it is set
     *
     * ```php
     * <?php
     *
     * use Locr\Lib\Progress;
     *
     * $progress = new Progress();
     * $progress->setTotalCount(1_000);
     * print $progress->TotalCount; // 1000
     * ```
     */
    public function setTotalCount(int $totalCount): void
    {
        if ($totalCount < self::ZERO) {
            throw new \InvalidArgumentException('Total count must be greater than or equal to ' . self::ZERO);
        }
        $this->totalCount = $totalCount;

        $this->raiseEvent(ProgressEvent::Change);
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
            '${Counter}' => $this->formatValue($this->counter, $this->locale),
            '${ElapsedTime}' => $this->ElapsedTime->format('%H:%I:%S'),
            '${EstimatedTimeEnroute}' => $this->calculateEstimatedTimeEnroute()?->format('%H:%I:%S') ?? 'N/A',
            '${EstimatedTimeOfArrival}' => $this->calculateEstimatedTimeOfArrival()?->format('Y-m-d H:i:s') ?? 'N/A',
            '${PercentageCompleted}' => !is_null($this->PercentageCompleted) ?
                sprintf("%.2f", $this->PercentageCompleted) : 'N/A',
            '${TotalCount}' => !is_null($this->totalCount) ? $this->formatValue($this->totalCount, $this->locale) : '-',
        ];

        return str_replace(array_keys($replacements), $replacements, $format);
    }
}
