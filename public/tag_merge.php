<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\TagController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tag_manage.php');
    exit;
}

$controller = new TagController();
$controller->merge();
