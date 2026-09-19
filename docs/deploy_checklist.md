# 本番公開までの手順（デプロイチェックリスト）

要件定義書・PROTOTYPE.md・これまでの疑似本番環境での検証結果を踏まえた、実際の公開（本番サーバーへの反映）までの作業手順です。上から順に実施してください。チェック欄は作業時に埋めてください。

## 0. 前提：現時点でわかっていること

- [x] ホスティングサーバー払い出し：承認済み（2026-08-17）
- [x] DNS・ファイアウォール・SSL証明書の申請：反映済み（2026-09-03時点、インフラとしては利用可能な状態）
- [x] 本番サーバーへのPHP 8.3系導入（`dnf install php php-cli php-gd php-mysqlnd php-mbstring php-xml php-json php-opcache`）：実施済み、`gd_info()`でJPEG/WebP対応まで確認済み
- [x] PHPアップロード上限の設定ファイル（`deploy/php.d/99-ksmc-uploads.ini`）：リポジトリに追加済み。本番サーバーへの実際の配置はこの章の作業として実施する
- [ ] **本番サーバーにこのリポジトリのコードはまだ配置されていない**（2026-09-19時点で確認済み。PHP導入のみ完了しており、アプリ自体のデプロイ作業はこれから）

### ⚠️ 重要な運用方針：顧問の最終確認までは外部公開しない

DNS・ファイアウォール・SSL証明書の申請はインフラとして反映済みだが、**顧問による最終確認が完了するまでは、外部から到達可能な状態には一切移行しない**方針とする。これは要件定義書 第2.4節の「顧問の確認はデプロイ前の一度きりの確認」という位置づけに沿った運用判断であり、DNS/FW/SSLが技術的に「使える状態」であることと「実際に公開してよいか」は別の判断であることに注意する。

このため、下記の手順のうち**5章（Apacheの公開設定）と6章（実ドメインでの動作確認）は顧問確認が完了するまで保留**し、それまでの動作確認は以下のいずれかの方法でサーバー内部からのみ行う。

- サーバー上で直接 `curl http://127.0.0.1/...` 等により確認する
- SSHポートフォワーディング（`ssh -L 8080:127.0.0.1:80 user@server`）でローカルPCのブラウザから確認する
- Apache仮想ホストを一旦`127.0.0.1`または内部ネットワークのみにバインドし、外部インターフェースでは待ち受けない
- 追加の保険として、OSのファイアウォール（`firewalld`）で80/443番ポートへのアクセス元を開発者・顧問など特定IPのみに一時的に制限する

以下、1〜4章（コード配置・DB初期化・PHP設定）は顧問確認を待たずに進めてよい。5〜6章（公開設定・実ドメイン確認）は保留し、顧問確認完了後に着手する。

## 1. コードの配置

- [ ] 本番サーバー上でリポジトリを取得する
  - まだcloneしていない場合：`git clone https://github.com/24RS108-create/ksmc-website.git`
  - 既にcloneしている場合：`git pull origin main`
- [ ] `erdiagram.md`はリポジトリに含めない方針のため、本番にも配置不要

## 2. 環境変数（`.env`）の設定

`.env`は`.gitignore`対象のため、サーバー上で手動作成する。

```bash
cat > /path/to/ksmc_web/.env << 'EOF'
DB_HOST=localhost
DB_NAME=ksmc_web
DB_USER=ksmc_app
DB_PASS=（強固なパスワードを設定）
DB_CHARSET=utf8mb4
EOF
```

- [ ] `DB_USER`は`root`ではなく、後述のアプリ専用アカウントを使う
- [ ] ファイルの権限を絞る（`chmod 640 .env`等、Apache実行ユーザーのみ読み取り可能に）

## 3. データベースの初期化

- [ ] MySQLにDBとアプリ専用ユーザーを作成
  ```sql
  CREATE DATABASE ksmc_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'ksmc_app'@'localhost' IDENTIFIED BY '（.envと同じパスワード）';
  GRANT SELECT, INSERT, UPDATE, DELETE ON ksmc_web.* TO 'ksmc_app'@'localhost';
  FLUSH PRIVILEGES;
  ```
  （`root`をアプリの接続ユーザーに使わないことで、万一の脆弱性発覚時の被害範囲を限定する）
- [ ] スキーマを流し込む：`mysql -u ksmc_app -p ksmc_web < database/schema.sql`
- [ ] **最初の管理者アカウントを手動で1件INSERT**（招待制のため、最初の1人は手動作成が必須）
  ```bash
  php -r "echo password_hash('初期パスワード', PASSWORD_BCRYPT);"
  ```
  で得たハッシュ値を使い、
  ```sql
  INSERT INTO users (login_id, password_hash, must_change_password, display_name, role)
  VALUES ('admin', '（上記で生成したハッシュ）', 1, '管理者', 'admin');
  ```
  （`must_change_password=1`により初回ログイン時にパスワード変更が強制される）

## 4. PHP設定の反映

- [ ] `deploy/php.d/99-ksmc-uploads.ini`を配置
  ```bash
  sudo cp deploy/php.d/99-ksmc-uploads.ini /etc/php.d/99-ksmc-uploads.ini
  ```
- [ ] `sudo systemctl restart httpd`（php-fpm構成の場合は`php-fpm`も）
- [ ] 反映確認：`php -r "echo ini_get('upload_max_filesize'), ' / ', ini_get('post_max_size');"` → `20M / 24M`になっていること

## 4.5 顧問の最終確認（外部公開ゲート）

- [ ] 顧問による最終確認が完了した（完了日：　　　　　　）

**この項目が完了するまで、5章・6章には着手しない。**

## 5. Apacheの設定 🚧 顧問確認完了まで保留

**この章は顧問の最終確認が完了するまで着手しない。** 着手する場合も、外部インターフェースでは待ち受けず、サーバー内部（`127.0.0.1`）または限定IPからのみアクセスできる状態にとどめる。

- [ ] VirtualHostの`DocumentRoot`をリポジトリ直下ではなく**`public/`ディレクトリ**に向ける（アプリの全エントリーポイントは`public/*.php`のため）
- [ ] SSL証明書を実際にvhostへ設定する（申請の「反映」＝証明書の発行/承認であり、Apache側への設置はまだの可能性があるため要確認）
- [ ] `public/uploads/`ディレクトリをApache実行ユーザー（`apache`等）が書き込めるようにする
  ```bash
  sudo chown -R apache:apache public/uploads
  sudo chmod -R 775 public/uploads
  ```

## 6. 動作確認（実ドメイン経由） 🚧 顧問確認完了まで保留

**実際のドメインでの外部到達確認は顧問確認後に行う。** それまではサーバー内部からの確認（0章参照）にとどめる。

- [ ] 実際のドメイン（`https://ksmc.cs.kyusan-u.ac.jp`等）でトップページが表示される
- [ ] HTTPSでアクセスでき、証明書エラーが出ない
- [ ] 管理者アカウントでログインでき、初回パスワード変更が求められる
- [ ] 画像付き投稿（作成・確認画面・公開）が一通り動作する
- [ ] お問い合わせフォームの送信・管理画面での確認ができる

## 7. 公開前に判断すべき既知の課題

これまでのコードレビュー・疑似本番環境での検証で判明した未対応事項（詳細は会話履歴のレビューまとめを参照）。公開前に直すか、公開後の早期対応とするかを判断する。

- [ ] **（強く推奨）** ロゴ合成処理（`LogoCompositor`）の画像ピクセル寸法上限が無い問題：疑似本番環境でOOM Killを実際に再現済み。運用開始後に大きな写真がアップロードされると同様の事象が起きうる
- [ ] 広報担当権限剥奪後の投稿確定時チェック漏れ（`PostController.php:206`）
- [ ] 管理者権限委譲で休止会員を除外していない（`AccountController.php:277`）
- [ ] 投稿編集時の画像削除とDBロールバックの不整合（`PostController.php:474`）
- [ ] 会員ページが休止会員を除外していない（`GalleryController.php:130`）
- [ ] セッションCookieのhttponly/secure/samesite未設定（`bootstrap.php:38`）
- [ ] その他：N+1クエリ、create/edit確認フローの重複（優先度は下げてよい）

## 8. バックアップ体制の初期化（NFR-06）

- [ ] 初回のDB・アップロード画像のエクスポート手順を確立し、実施する
- [ ] `docs/admin_handover_procedure.md`のバックアップ引き継ぎ欄に、初回バックアップの取得日・保管場所を記録する

## 9. 引き継ぎドキュメントの最終確認

- [ ] `docs/admin_handover_procedure.md`の内容が実際のサーバー構成と一致しているか確認
- [ ] `.env`の内容・サーバーSSHログイン情報を、口頭等の安全な方法で管理者間で共有する
