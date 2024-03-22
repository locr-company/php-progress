# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- This CHANGELOG file.
- localization and formatting byte units.
- method: setTotalCount(int $totalCount): void

### Changed

- constructor signature: __construct(private ?int $totalCount = null, private ?string $locale = null, private ?ProgressUnit $unit = null)
- method signature (added $options): on(ProgressEvent $event, callable $callback, array $options = []): void

## [1.0.0] - 2024-03-14

### Added

- constructor: __construct(private ?int $totalCount = null).
- method: calculateEstimatedTimeEnroute(): ?\DateInterval
- method: calculateEstimatedTimeOfArrival(): ?\DateTimeImmutable
- method: incrementCounter(): void
- method: on(ProgressEvent $event, callable $callback): void
- method: setCounter(int $counter): void
- method: toFormattedString(string $format = self::DEFAULT_TO_STRING_FORMAT): string
- property: int $Counter
- property: \DateInterval $ElapsedTime
- property: float $PercentageCompleted
- property: \DateTimeImmutable $StartTime
- property: ?int $TotalCount

[unreleased]: https://github.com/locr-company/php-progress/compare/1.0.0...HEAD
[1.0.0]: https://github.com/locr-company/php-progress/releases/tag/1.0.0