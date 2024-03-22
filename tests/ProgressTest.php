<?php

declare(strict_types=1);

namespace UnitTests;

use Locr\Lib\Progress;
use Locr\Lib\ProgressEvent;
use Locr\Lib\ProgressUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Progress::class)]
final class ProgressTest extends TestCase
{
    private const TIME_PATTERN = '\d{2}:\d{2}:\d{2}';

    public function testNewInstance(): void
    {
        $testStartTime = new \DateTimeImmutable();
        $progress = new Progress();
        $this->assertInstanceOf(Progress::class, $progress);

        $this->assertLessThanOrEqual(1, $progress->ElapsedTime->s);
        $diffTime = $progress->StartTime->getTimestamp() - $testStartTime->getTimestamp();
        $this->assertLessThanOrEqual(1, $diffTime);

        $this->assertEquals(0, $progress->Counter);
        $this->assertNull($progress->TotalCount);
        $this->assertNull($progress->PercentageCompleted);
        $this->assertNull($progress->calculateEstimatedTimeOfArrival());
        $this->assertNull($progress->calculateEstimatedTimeEnroute());
    }

    public function testNewInstanceWithTotalCount(): void
    {
        $progress = new Progress(totalCount: 1_000);

        $this->assertEquals(0, $progress->Counter);
        $this->assertEquals(1_000, $progress->TotalCount);
        $this->assertEquals(0, $progress->PercentageCompleted);
    }

    public function testIncrementCounter(): void
    {
        $progress = new Progress(totalCount: 1_000);
        $this->assertEquals(0, $progress->Counter);

        $progress->incrementCounter();
        $this->assertEquals(1, $progress->Counter);
        $this->assertEquals(0.1, $progress->PercentageCompleted);
    }

    public function testSetCounter(): void
    {
        $progress = new Progress(totalCount: 1_000);
        $this->assertEquals(0, $progress->Counter);

        $progress->setCounter(500);
        $this->assertEquals(500, $progress->Counter);
        $this->assertEquals(50, $progress->PercentageCompleted);
    }

    public function testSetInvalidCounter(): void
    {
        $progress = new Progress();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Counter must be greater than or equal to 0');
        $progress->setCounter(-1);
    }

    public function testSetTotalCount(): void
    {
        $progress = new Progress();
        $this->assertNull($progress->TotalCount);

        $progress->setTotalCount(1_000);
        $this->assertEquals(1_000, $progress->TotalCount);
    }

    public function testSetInvalidTotalCount(): void
    {
        $progress = new Progress();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total count must be greater than or equal to 0');
        $progress->setTotalCount(-1);
    }

    public function testEstimatedTimeOfArrival(): void
    {
        $progress = new Progress(totalCount: 1_000);
        $progress->incrementCounter();
        sleep(1);

        $eta = $progress->calculateEstimatedTimeOfArrival();
        $this->assertInstanceOf(\DateTimeImmutable::class, $eta);
        $totalSeconds = $eta->getTimestamp() - $progress->StartTime->getTimestamp();
        $this->assertGreaterThanOrEqual(900, $totalSeconds);
        $this->assertLessThanOrEqual(1100, $totalSeconds);
    }

    public function testEstimatedTimeEnrouteIsGreaterThan0Seconds(): void
    {
        $progress = new Progress(totalCount: 1_000);
        $progress->setCounter(200);
        sleep(1);

        $ete = $progress->calculateEstimatedTimeEnroute();
        $this->assertInstanceOf(\DateInterval::class, $ete);
        $this->assertGreaterThanOrEqual(3, $ete->s);
        $this->assertLessThanOrEqual(5, $ete->s);
    }

    public function testEstimatedTimeEnrouteIsLessThan60Seconds(): void
    {
        $progress = new Progress(totalCount: 55);
        $progress->setCounter(1);
        sleep(1);

        $ete = $progress->calculateEstimatedTimeEnroute();
        $this->assertInstanceOf(\DateInterval::class, $ete);
        $this->assertEquals(0, $ete->y);
        $this->assertEquals(0, $ete->m);
        $this->assertEquals(0, $ete->d);
        $this->assertEquals(0, $ete->h);
        $this->assertEquals(0, $ete->i);
        $this->assertGreaterThanOrEqual(50, $ete->s);
        $this->assertLessThanOrEqual(60, $ete->s);
    }

    public function testEstimatedTimeEnrouteEquals1Minute(): void
    {
        $progress = new Progress(totalCount: 90);
        $progress->setCounter(1);
        sleep(1);

        $ete = $progress->calculateEstimatedTimeEnroute();
        $this->assertInstanceOf(\DateInterval::class, $ete);
        $this->assertEquals(0, $ete->y);
        $this->assertEquals(0, $ete->m);
        $this->assertEquals(0, $ete->d);
        $this->assertEquals(0, $ete->h);
        $this->assertEquals(1, $ete->i);
    }

    public function testEstimatedTimeEnrouteIsLessThan1Hour(): void
    {
        $progress = new Progress(totalCount: 3_500);
        $progress->setCounter(1);
        sleep(1);

        $ete = $progress->calculateEstimatedTimeEnroute();
        $this->assertInstanceOf(\DateInterval::class, $ete);
        $this->assertEquals(0, $ete->y);
        $this->assertEquals(0, $ete->m);
        $this->assertEquals(0, $ete->d);
        $this->assertEquals(0, $ete->h);
        $this->assertLessThanOrEqual(60, $ete->i);
        $this->assertGreaterThan(55, $ete->i);
    }

    public function testEstimatedTimeEnrouteEquals1Hour(): void
    {
        $progress = new Progress(totalCount: 3_700);
        $progress->setCounter(1);
        sleep(1);

        $ete = $progress->calculateEstimatedTimeEnroute();
        $this->assertInstanceOf(\DateInterval::class, $ete);
        $this->assertEquals(0, $ete->y);
        $this->assertEquals(0, $ete->m);
        $this->assertEquals(0, $ete->d);
        $this->assertEquals(1, $ete->h);
        $this->assertEquals(1, $ete->i);
    }

    public function testChangeEventForIncrementCounter(): void
    {
        $progress = new Progress();
        $progress->on(ProgressEvent::Change, function (Progress $progress) {
            $this->assertInstanceOf(Progress::class, $progress);
            $this->assertEquals(1, $progress->Counter);
        });

        $progress->incrementCounter();
    }

    public function testChangeEventForSetCounter(): void
    {
        $progress = new Progress();
        $progress->on(ProgressEvent::Change, function (Progress $progress) {
            $this->assertInstanceOf(Progress::class, $progress);
            $this->assertEquals(2, $progress->Counter);
        });

        $progress->setCounter(2);
    }

    public function testToFormattedStringWithNoTotalCount(): void
    {
        $progress = new Progress();
        $progress->incrementCounter();

        $expectedString = 'progress => 1/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndNoLocale(): void
    {
        $progress = new Progress();
        $progress->setCounter(1_000);

        $expectedString = 'progress => 1000/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndLocale(): void
    {
        $progress = new Progress(locale: 'de-DE');
        $progress->setCounter(1_000);

        $expectedString = 'progress => 1.000/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndNoLocaleAndByteUnit(): void
    {
        $progress = new Progress(unit: ProgressUnit::Byte);
        $progress->setCounter(1_000);

        $expectedString = 'progress => 1000 B/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndNoLocaleAndByteUnitGreaterThan1024(): void
    {
        $progress = new Progress(unit: ProgressUnit::Byte);
        $progress->setCounter(2_000);

        $expectedString = 'progress => 1.95 KiB/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndLocaleAndByteUnit(): void
    {
        $progress = new Progress(locale: 'de-DE', unit: ProgressUnit::Byte);
        $progress->setCounter(1_000);

        $expectedString = 'progress => 1.000 B/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithNoTotalCountAndLocaleAndByteUnitWithCounterGreaterThan1024(): void
    {
        $progress = new Progress(locale: 'de-DE', unit: ProgressUnit::Byte);
        $progress->setCounter(2_400);

        $expectedString = 'progress => 2,34 KiB/- (N/A%); elapsed: 00:00:00; ete: N/A; eta: N/A';
        $this->assertEquals($expectedString, $progress->toFormattedString());
    }

    public function testToFormattedStringWithTotalCountAndLocaleAndByteUnitWithTotalCountGreaterThan1024(): void
    {
        $progress = new Progress(totalCount: 2_000_000, locale: 'de-DE', unit: ProgressUnit::Byte);
        $progress->setCounter(2_400);

        $pattern = '/^';
        $pattern .= 'progress => \d+,\d+ ([KMGTPEZY]i)?B\/\d+,\d+ ([KMGTPEZY]i)?B \((\d{1,3}(\.\d+)?)%\)';
        $pattern .= '; elapsed: ' . self::TIME_PATTERN;
        $pattern .= '; ete: ' . self::TIME_PATTERN;
        $pattern .= '; eta: \d{4}-\d{2}-\d{2} ' . self::TIME_PATTERN;
        $pattern .= '$/';
        $matched = preg_match($pattern, $progress->toFormattedString());
        $this->assertEquals(1, $matched);
    }

    public function testToFormattedStringWithTotalCount(): void
    {
        $progress = new Progress(totalCount: 1_000);
        sleep(1);
        $progress->incrementCounter();

        $pattern = '/^';
        $pattern .= 'progress => 1\/1000 \((\d{1,3}(\.\d+)?)%\)';
        $pattern .= '; elapsed: ' . self::TIME_PATTERN;
        $pattern .= '; ete: ' . self::TIME_PATTERN;
        $pattern .= '; eta: \d{4}-\d{2}-\d{2} ' . self::TIME_PATTERN;
        $pattern .= '$/';
        $matched = preg_match($pattern, $progress->toFormattedString());
        $this->assertEquals(1, $matched);
    }

    public function testToFormattedStringWithTotalCountAndLocale(): void
    {
        $progress = new Progress(totalCount: 1_000, locale: 'de-DE');
        sleep(1);
        $progress->incrementCounter();

        $pattern = '/^';
        $pattern .= 'progress => 1\/1.000 \((\d{1,3}(\.\d+)?)%\)';
        $pattern .= '; elapsed: ' . self::TIME_PATTERN;
        $pattern .= '; ete: ' . self::TIME_PATTERN;
        $pattern .= '; eta: \d{4}-\d{2}-\d{2} ' . self::TIME_PATTERN;
        $pattern .= '$/';
        $matched = preg_match($pattern, $progress->toFormattedString());
        $this->assertEquals(1, $matched);
    }
}
