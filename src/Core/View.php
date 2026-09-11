<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $path = dirname(__DIR__) . '/Views/' . $template . '.php';

        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/partials/header.php';
        require $path;
        require dirname(__DIR__) . '/Views/partials/footer.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
