<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\ContactController;

$controller = new ContactController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->submit();
} else {
    $controller->showForm();
}
