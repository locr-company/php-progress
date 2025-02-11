![php](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)
[![codecov](https://codecov.io/gh/locr-company/php-progress/graph/badge.svg?token=wFyOUFzaJ1)](https://codecov.io/gh/locr-company/php-progress)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Flocr-company%2Fphp-progress%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/locr-company/php-progress/main)
[![github_workflow_status](https://img.shields.io/github/actions/workflow/status/locr-company/php-progress/php.yml)](https://github.com/locr-company/php-progress/actions/workflows/php.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=locr-company_php-progress&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=locr-company_php-progress)
[![github_tag](https://img.shields.io/github/v/tag/locr-company/php-progress)](https://github.com/locr-company/php-progress/tags)
[![packagist](https://img.shields.io/packagist/v/locr-company/progress)](https://packagist.org/packages/locr-company/progress)

# 1. Installation

```bash
composer require locr-company/progress
```

# 2. How to use

```php
<?php

use Locr\Lib\Progress;

$progress = new Progress(totalCount: 1_000);
$progress->incrementCounter();
print $progress->Counter; // 1
print $progress->PercentageCompleted; // 0.1
print $progress->toFormattedString(); // progress => 1/1000 (0.10%); elapsed: 00:00:01; ete: 00:16:39; eta: 2021-10-10 20:00:01
$progress->setCounter(1000);
print $progress->PercentageCompleted; // 100
```

# 3. Development

Clone the repository

```bash
git clone git@github.com:locr-company/php-progress.git
cd php-progress && composer install
```

# 4. Publish a new version

```bash
# update CHANGELOG.md file

git tag -a <major>.<minor>.<patch> -m 'version <major>.<minor>.<patch>'
git push
git push origin --tags
```
