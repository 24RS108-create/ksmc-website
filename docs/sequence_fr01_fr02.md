# シーケンス図（FR-01・FR-02）

実装コード（`public/*.php` → `src/Controllers/*.php` → `src/Core/*.php` / `src/Models/*.php`）に基づく処理フローを示す。

## FR-01：会員登録（業務フロー版・利用者/関係者向け）

CSRF検証やDBクエリ等の実装詳細を省き、「誰が」「何を」「どういう順番で」行うかという業務レベルの粒度で示す。実装レベルの詳細は次節（実装コード対応版）を参照。

```mermaid
sequenceDiagram
    actor 管理者
    participant Web as 本サイト
    actor 新規会員 as 新規会員（招待された本人）

    管理者->>Web: 「アカウント発行」画面を開く
    Web-->>管理者: 入力フォームを表示（ログインID／表示名／初期パスワード）

    管理者->>Web: ログインID・表示名・初期パスワードを入力して送信

    Web->>Web: 入力内容を確認（ログインIDの重複、文字数、パスワードの長さ等）

    alt 入力に不備がある（ID重複・必須未入力等）
        Web-->>管理者: エラー内容を表示し、再入力を求める
    else 入力に問題なし
        Web->>Web: アカウントを作成し、「初回ログイン時にパスワード変更が必要」な状態で登録
        Web-->>管理者: 発行完了を表示

        note over 管理者,新規会員: この経路（口頭・メール等）はシステムの範囲外
        管理者->>新規会員: ログインIDと初期パスワードを直接伝達

        新規会員->>Web: 伝達された初期パスワードで初回ログイン（FR-02へ続く）
    end
```

補足：
- 会員登録は招待コードの自動発行・自動送信ではなく、**管理者が発行から本人への伝達まで一貫して行う**運用（招待制、要件定義書FR-01確定事項）。
- 初期パスワードの伝達手段（口頭・メール等）はシステムの機能としては実装されておらず、管理者の運用に委ねられる。
- 発行されたアカウントは自動的に「会員」ロールとなり、権限の昇格（広報担当・管理者）は別の操作（FR-10・FR-14）で行う。

## FR-01：会員登録（実装コード対応版）

対応コード：`public/account_create.php` → `AccountController::showCreate()` / `create()` → `User::createInvited()`

```mermaid
sequenceDiagram
    actor Admin as 管理者（ブラウザ）
    participant Entry as account_create.php
    participant Ctrl as AccountController
    participant Auth as Auth
    participant Csrf as Csrf
    participant UserModel as User（モデル）
    participant DB as MySQL

    Admin->>Entry: GET /account_create.php
    Entry->>Ctrl: showCreate()
    Ctrl->>Auth: requireRole('admin')
    Auth->>UserModel: findById(session user_id)
    UserModel->>DB: SELECT * FROM users WHERE id = :id
    DB-->>UserModel: users行
    UserModel-->>Auth: User（role確認用）
    Auth-->>Ctrl: 管理者User（role !== 'admin'ならこの時点で / へリダイレクトし終了）
    Ctrl->>Csrf: token()（ビュー内で発行）
    Ctrl-->>Admin: 発行フォーム（account_create.php ビュー）を表示

    Admin->>Entry: POST /account_create.php<br/>(login_id, display_name, initial_password, initial_password_confirm, csrf_token)
    Entry->>Ctrl: create()
    Ctrl->>Auth: requireRole('admin')
    Auth-->>Ctrl: 管理者User

    Ctrl->>Csrf: verify(csrf_token)
    Csrf-->>Ctrl: true / false

    alt CSRFトークン不正
        Ctrl-->>Admin: エラー「不正なリクエストです」を表示（フォーム再表示）
    else CSRFトークン正当
        Ctrl->>Ctrl: 入力値検証（ログインID必須、表示名必須／100文字以内、初期パスワード8文字以上・確認一致）
        Ctrl->>UserModel: loginIdExists(login_id)
        UserModel->>DB: SELECT 1 FROM users WHERE login_id = :login_id
        DB-->>UserModel: 存在有無
        UserModel-->>Ctrl: bool

        alt 入力エラーあり（重複ログインIDを含む）
            Ctrl-->>Admin: エラー一覧を表示（フォーム再表示、入力値は保持）
        else 入力エラーなし
            Ctrl->>UserModel: createInvited(login_id, initial_password, display_name, invitedBy=管理者ID)
            UserModel->>UserModel: password_hash(initial_password, BCRYPT)
            UserModel->>DB: INSERT INTO users (..., must_change_password=1, role='member', invited_by=:invitedBy)
            DB-->>UserModel: lastInsertId
            UserModel->>DB: SELECT * FROM users WHERE id = :id
            DB-->>UserModel: 作成済み users行
            UserModel-->>Ctrl: 新規User
            Ctrl-->>Admin: 「アカウント「{login_id}」を発行しました」を表示
        end
    end
```

補足：
- 招待コード方式ではなく、管理者がログインIDと初期パスワードを直接指定して発行する方式（要件定義書 FR-01 確定事項）。
- 発行直後は `must_change_password = 1` が設定され、本人の初回ログイン時にパスワード変更が強制される（FR-02 と接続）。
- ロールは常に `member` 固定で発行される（広報担当・管理者への昇格は別途 FR-10／FR-14 で行う）。

## FR-02：ログイン／ログアウト／パスワード再設定（業務フロー版・利用者/関係者向け）

CSRF検証やDBクエリ等の実装詳細を省き、「誰が」「何を」「どういう順番で」行うかという業務レベルの粒度で示す。実装レベルの詳細は次節（実装コード対応版）を参照。

```mermaid
sequenceDiagram
    actor 利用者
    participant Web as 本サイト

    利用者->>Web: ログイン画面を開き、ログインID・パスワードを入力して送信
    Web->>Web: 入力されたログインID・パスワードを照合

    alt ログインID・パスワードが一致しない
        Web-->>利用者: 「ログインIDまたはパスワードが正しくありません」を表示
    else 一致した（ログイン成功）
        alt 初回ログイン、または管理者から再設定を指示されている場合
            Web-->>利用者: パスワード再設定画面へ案内（変更するまで他の操作はできない）
            利用者->>Web: 現在のパスワード・新しいパスワード（確認含む）を入力して送信
            Web->>Web: 現在のパスワードを確認し、新しいパスワードの条件（8文字以上・確認一致）を確認

            alt 入力に誤りがある
                Web-->>利用者: エラー内容を表示し、再入力を求める
            else 問題なし
                Web->>Web: パスワードを更新し、「変更必須」の状態を解除
                Web-->>利用者: 「パスワードを変更しました」を表示。以後は通常どおり利用可能
            end
        else 通常ログイン（変更不要）
            Web-->>利用者: トップページへ案内。以後は会員として通常どおり利用可能
        end
    end

    note over 利用者,Web: 別の機会に
    利用者->>Web: 「ログアウト」を選択
    Web-->>利用者: ログイン状態を終了し、ログイン画面へ戻す
```

補足：
- 「初回ログイン」以外にも、後述のFR-11（休止会員化）等の運用でパスワード変更が必要な状態になり得るため、分岐は「初回ログインに限らない」形で示している。
- パスワード再設定はログイン中の利用者であれば任意のタイミングでも行える（強制されていない場合も同じ画面・同じ手順を使う）。
- 休止会員（退会・卒業済み）もログイン・パスワード再設定は可能（要件定義書のロール定義に基づく。新規投稿・プロフィール編集のみ不可）。

## FR-02：ログイン／ログアウト／パスワード再設定（実装コード対応版）

対応コード：`public/login.php` → `AuthController::login()`、`public/logout.php` → `AuthController::logout()`、`public/password_edit.php` → `AuthController::showPasswordEdit()` / `updatePassword()`

### 2-1. ログイン（初回強制パスワード変更への分岐を含む）

```mermaid
sequenceDiagram
    actor User as 利用者（ブラウザ）
    participant Entry as login.php
    participant Ctrl as AuthController
    participant Csrf as Csrf
    participant UserModel as User（モデル）
    participant DB as MySQL
    participant Auth as Auth（セッション）

    User->>Entry: GET /login.php
    Entry->>Ctrl: showLogin()
    Ctrl->>Auth: check()
    Auth-->>Ctrl: 未ログイン
    Ctrl-->>User: ログインフォームを表示

    User->>Entry: POST /login.php (login_id, password, csrf_token)
    Entry->>Ctrl: login()
    Ctrl->>Csrf: verify(csrf_token)
    Csrf-->>Ctrl: true / false

    alt CSRFトークン不正、またはログインID／パスワード未入力
        Ctrl-->>User: エラーメッセージを表示（フォーム再表示）
    else 入力あり・CSRF正当
        Ctrl->>UserModel: findByLoginId(login_id)
        UserModel->>DB: SELECT * FROM users WHERE login_id = :login_id
        DB-->>UserModel: users行 or 該当なし
        UserModel-->>Ctrl: User or null

        alt ユーザーが存在しない、またはパスワード不一致
            Ctrl->>UserModel: verifyPassword(password)
            UserModel-->>Ctrl: false
            Ctrl-->>User: 「ログインIDまたはパスワードが正しくありません」を表示
        else 認証成功
            Ctrl->>UserModel: verifyPassword(password)
            UserModel-->>Ctrl: true
            Ctrl->>Auth: login(user)
            Auth->>Auth: session_regenerate_id(true)
            Auth->>Auth: $_SESSION['user_id'] = user.id

            alt must_change_password = true（初回ログイン等）
                Ctrl-->>User: 302 Redirect /password_edit.php
            else must_change_password = false
                Ctrl-->>User: 302 Redirect /（トップページ）
            end
        end
    end
```

### 2-2. ログアウト

```mermaid
sequenceDiagram
    actor User as 利用者（ブラウザ）
    participant Entry as logout.php
    participant Ctrl as AuthController
    participant Auth as Auth（セッション）

    User->>Entry: GET /logout.php
    Entry->>Ctrl: logout()
    Ctrl->>Auth: logout()
    Auth->>Auth: $_SESSION = []
    Auth->>Auth: session_regenerate_id(true)
    Ctrl-->>User: 302 Redirect /login.php
```

### 2-3. パスワード再設定（強制変更・任意変更共通）

```mermaid
sequenceDiagram
    actor User as 利用者（ブラウザ）
    participant Entry as password_edit.php
    participant Ctrl as AuthController
    participant Auth as Auth
    participant Csrf as Csrf
    participant UserModel as User（モデル）
    participant DB as MySQL

    User->>Entry: GET /password_edit.php
    Entry->>Ctrl: showPasswordEdit()
    Ctrl->>Auth: requireLogin()
    Auth-->>Ctrl: User（未ログインなら /login.php へリダイレクトし終了）
    Ctrl-->>User: フォーム表示（must_change_password=trueなら「変更が必須」の案内）

    User->>Entry: POST /password_edit.php<br/>(current_password, new_password, new_password_confirm, csrf_token)
    Entry->>Ctrl: updatePassword()
    Ctrl->>Auth: requireLogin()
    Auth-->>Ctrl: User

    Ctrl->>Csrf: verify(csrf_token)
    Csrf-->>Ctrl: true / false

    alt CSRFトークン不正
        Ctrl-->>User: エラー「不正なリクエストです」を表示
    else CSRFトークン正当
        Ctrl->>UserModel: verifyPassword(current_password)
        UserModel-->>Ctrl: true / false

        alt 現在のパスワード不一致、または新パスワードが8文字未満／確認不一致
            Ctrl-->>User: エラー一覧を表示（フォーム再表示）
        else 検証OK
            Ctrl->>UserModel: updatePassword(new_password, mustChangePassword=false)
            UserModel->>UserModel: password_hash(new_password, BCRYPT)
            UserModel->>DB: UPDATE users SET password_hash=:hash, must_change_password=0 WHERE id=:id
            DB-->>UserModel: 更新完了
            UserModel-->>Ctrl: 更新済みUser
            Ctrl-->>User: 「パスワードを変更しました」を表示（以後 must_change_password=false）
        end
    end
```
