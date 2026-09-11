<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\ProfileController;

$controller = new ProfileController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->update();
} else {
    $controller->show();
}
