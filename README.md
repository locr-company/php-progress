![php](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)

# 1. How to use

```php
<?php

use Locr\Lib\Progress;

$progress = new Progress(totalCount: 1000);
$progress->incrementCounter();
print $progress->Counter; // 1
print $progress->PercentageCompleted; // 0.1
$progress->setCounter(1000);
print $progress->PercentageCompleted; // 100
```

# 2. Development

Clone the repository

```bash
git clone git@github.com:locr-company/php-progress.git
cd php-progress/.git/hooks && ln -s ../../git-hooks/* . && cd ../..
composer install
```
