<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\AccountController;

$controller = new AccountController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->delete();
} else {
    $controller->showDeleteConfirm();
}
