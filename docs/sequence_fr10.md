# シーケンス図（FR-10）

実装コード（`public/*.php` → `src/Controllers/*.php` → `src/Core/*.php` / `src/Models/*.php`）に基づく処理フローを示す。FR-01・FR-02の資料（`docs/sequence_fr01_fr02.md`）と同様に、「業務フロー版（利用者・関係者向け）」「実装コード対応版」の2段構成とする。

## FR-10：管理者権限の委譲（業務フロー版・利用者/関係者向け）

CSRF検証やDBクエリ等の実装詳細を省き、「誰が」「何を」「どういう順番で」行うかという業務レベルの粒度で示す。実装レベルの詳細は次節（実装コード対応版）を参照。

```mermaid
sequenceDiagram
    actor 現管理者
    participant Web as 本サイト
    actor 新管理者 as 新管理者（委譲先の会員）

    現管理者->>Web: 「アカウント管理」画面でログインIDを検索
    Web-->>現管理者: 該当会員の情報（表示名・現在のロール）を表示

    現管理者->>Web: 「管理者権限を委譲する」を選択
    Web-->>現管理者: 委譲確認画面を表示（「実行すると自分は会員になる」旨の警告付き）

    現管理者->>Web: 内容を確認し、「委譲を実行する」を送信

    alt 委譲先が既に管理者、または不正な対象を指定した場合
        Web-->>現管理者: アカウント管理画面へ差し戻す（委譲を行わない）
    else 委譲先が管理者以外の正しい対象
        Web->>Web: 委譲先を管理者に、自分自身を会員に、同時に切り替える
        Web-->>現管理者: 「管理者権限を委譲しました。あなたのロールは会員になりました」を表示

        note over 現管理者,新管理者: この経路（口頭等）はシステムの範囲外
        現管理者->>新管理者: 管理者になったことを伝達

        新管理者->>Web: 次回ログイン以降、管理者として各種操作が可能になる
    end
```

補足：
- 委譲は**新管理者への昇格と現管理者の降格が不可分の1操作**として実行される（片方だけが成功する状態を作らない）。
- 委譲操作を実行した本人はその場でロールが「会員」に変わるため、以後は元の管理者専用画面（アカウント発行・管理等）にアクセスできなくなる。
- 委譲は取り消し操作を持たない（元に戻すには、新管理者が改めて委譲を実行する必要がある）。

## FR-10：管理者権限の委譲（実装コード対応版）

対応コード：`public/account_manage.php` → `AccountController::showManage()`、`public/account_transfer.php` → `AccountController::showTransferConfirm()` / `transferAdmin()` → `User::updateRole()`

```mermaid
sequenceDiagram
    actor Admin as 現管理者（ブラウザ）
    participant ManageEntry as account_manage.php
    participant TransferEntry as account_transfer.php
    participant Ctrl as AccountController
    participant Auth as Auth
    participant Csrf as Csrf
    participant UserModel as User（モデル）
    participant DB as MySQL

    Admin->>ManageEntry: GET /account_manage.php?login_id=xxx
    ManageEntry->>Ctrl: showManage()
    Ctrl->>Auth: requireRole('admin')
    Auth-->>Ctrl: 現管理者User
    Ctrl->>UserModel: findByLoginId(login_id)
    UserModel->>DB: SELECT * FROM users WHERE login_id = :login_id
    DB-->>UserModel: users行 or 該当なし
    UserModel-->>Ctrl: 対象User or null
    Ctrl-->>Admin: 検索結果（対象の表示名・現在のロール）を表示

    Admin->>TransferEntry: GET /account_transfer.php?login_id=xxx
    TransferEntry->>Ctrl: showTransferConfirm()
    Ctrl->>Auth: requireRole('admin')
    Auth-->>Ctrl: 現管理者User
    Ctrl->>Ctrl: findTransferTargetOrRedirect(admin, login_id)
    Ctrl->>UserModel: findByLoginId(login_id)
    UserModel->>DB: SELECT * FROM users WHERE login_id = :login_id
    DB-->>UserModel: users行
    UserModel-->>Ctrl: 対象User

    alt 対象が存在しない／自分自身／既に管理者
        Ctrl-->>Admin: 302 Redirect /account_manage.php（確認画面を出さず終了）
    else 対象が管理者以外の実在ユーザー
        Ctrl->>Csrf: token()（ビュー内で発行）
        Ctrl-->>Admin: 委譲確認画面を表示

        Admin->>TransferEntry: POST /account_transfer.php (login_id, csrf_token)
        TransferEntry->>Ctrl: transferAdmin()
        Ctrl->>Auth: requireRole('admin')
        Auth-->>Ctrl: 現管理者User
        Ctrl->>Ctrl: findTransferTargetOrRedirect(admin, login_id)（再検証）
        Ctrl->>UserModel: findByLoginId(login_id)
        UserModel->>DB: SELECT * FROM users WHERE login_id = :login_id
        DB-->>UserModel: users行
        UserModel-->>Ctrl: 対象User

        Ctrl->>Csrf: verify(csrf_token)
        Csrf-->>Ctrl: true / false

        alt CSRFトークン不正
            Ctrl-->>Admin: エラー「不正なリクエストです」を表示（確認画面再表示）
        else CSRFトークン正当
            Ctrl->>DB: beginTransaction()
            Ctrl->>UserModel: target.updateRole('admin')
            UserModel->>DB: UPDATE users SET role='admin' WHERE id=:targetId
            Ctrl->>UserModel: admin.updateRole('member')
            UserModel->>DB: UPDATE users SET role='member' WHERE id=:adminId

            alt 更新中に例外発生
                Ctrl->>DB: rollBack()
                Ctrl-->>Admin: エラー「権限の委譲に失敗しました」を表示（確認画面再表示）
            else 両方の更新に成功
                Ctrl->>DB: commit()
                Ctrl-->>Admin: 「管理者権限を「{target.loginId}」に委譲しました。あなたのロールは「会員」になりました」を表示
            end
        end
    end
```

補足：
- `showTransferConfirm()`と`transferAdmin()`の両方で`findTransferTargetOrRedirect()`による対象検証を独立して行っている（確認画面表示時と実行時で対象の状態が変わっている可能性への対策）。
- 新管理者への昇格と現管理者の降格は`Database::connection()->beginTransaction()`〜`commit()`で1つのトランザクションにまとめられており、片方のみが反映される状態（例：新管理者は昇格したが旧管理者は降格していない）を防いでいる。
- `Auth::user()`はDBを毎回再取得する実装のため、`transferAdmin()`実行中に別タブ等で旧管理者のセッションを操作しても、次回アクセス時には即座に新しいロール（会員）が反映される。
