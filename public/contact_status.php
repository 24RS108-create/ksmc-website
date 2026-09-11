<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\ContactController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact_manage.php');
    exit;
}

(new ContactController())->updateStatus();
