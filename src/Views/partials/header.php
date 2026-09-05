<?php

use App\Core\View;

/**
 * @var string|null $title
 * @var bool|null $wide
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($title ?? '九産模型愛好会') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<main class="page<?= !empty($wide) ? ' page-wide' : '' ?>">
<?php require __DIR__ . '/nav.php'; ?>
