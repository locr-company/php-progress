<?php

declare(strict_types=1);

namespace UnitTests;

use Locr\Lib\Progress;
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
        $this->assertNull($progress->EstimatedTimeOfArrival);
        $this->assertNull($progress->EstimatedTimeEnroute);
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $progress->EstimatedTimeOfArrival);
        sleep(1);
        $totalSeconds = $progress->EstimatedTimeOfArrival->getTimestamp() - $progress->StartTime->getTimestamp();
        $this->assertGreaterThanOrEqual(900, $totalSeconds);
        $this->assertLessThanOrEqual(1100, $totalSeconds);
    }

    public function testEstimatedTimeEnroute(): void
    {
        $progress = new Progress(totalCount: 1_000);
        $progress->setCounter(200);
        $this->assertInstanceOf(\DateInterval::class, $progress->EstimatedTimeEnroute);
        sleep(1);
        $remainingSeconds = $progress->EstimatedTimeEnroute->s;
        $this->assertGreaterThanOrEqual(3, $remainingSeconds);
        $this->assertLessThanOrEqual(5, $remainingSeconds);
    }
}
