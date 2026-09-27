/**
 * スマホ幅（@media (max-width: 700px)、style.css参照）でのサイドバーの開閉。
 * 通常幅ではハンバーガーボタン自体が非表示のため、このスクリプトは何もしない。
 *
 * ログイン時はサイドバー内に「アカウントメニュー」の折りたたみ（details要素）が
 * 入れ子になっているが、スマホ幅でハンバーガーメニューを開いた際は二度タップさせず
 * 済むよう、常に展開済みの状態で表示する。
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('.mobile-nav-toggle');
        var sidebar = document.querySelector('.sidebar');
        if (!toggle || !sidebar) {
            return;
        }

        var accountMenu = sidebar.querySelector('.sidebar-account-menu');

        toggle.addEventListener('click', function () {
            var isOpen = sidebar.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (isOpen && accountMenu) {
                accountMenu.open = true;
            }
        });
    });
})();
