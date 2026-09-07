<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\TagController;

$controller = new TagController();
$controller->showManage();
