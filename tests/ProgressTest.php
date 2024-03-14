<?php

declare(strict_types=1);

namespace UnitTests;

use Locr\Lib\Progress;
use Locr\Lib\ProgressEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Progress::class)]
final class ProgressTest extends TestCase
{
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

    public function testChangeEvent(): void
    {
        $progress = new Progress();
        $progress->on(ProgressEvent::Change, function (Progress $progress) {
            $this->assertInstanceOf(Progress::class, $progress);
        });

        $progress->incrementCounter();
    }
}
