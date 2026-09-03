<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;

// トップページ（ホーム画面）本体は未実装（FR-01〜FR-03の対象外）。
// ログイン状態に応じて最小限の振り分けのみ行う。
if (Auth::check()) {
    header('Location: /profile_edit.php');
} else {
    header('Location: /login.php');
}
exit;
