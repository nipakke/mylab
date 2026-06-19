<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Shipped\Backend\ServerKernel;

$kernel = new ServerKernel;
$kernel->run();
