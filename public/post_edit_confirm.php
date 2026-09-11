<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\PostController;

$controller = new PostController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->confirmEdit();
} else {
    $controller->showEditConfirm();
}
