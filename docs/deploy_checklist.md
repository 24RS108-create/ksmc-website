# 本番公開までの手順（デプロイチェックリスト）

要件定義書・PROTOTYPE.md・これまでの疑似本番環境での検証結果を踏まえた、実際の公開（本番サーバーへの反映）までの作業手順です。上から順に実施してください。チェック欄は作業時に埋めてください。

## 0. 前提：現時点でわかっていること

- [x] ホスティングサーバー払い出し：承認済み（2026-08-17）
- [x] DNS・ファイアウォール・SSL証明書の申請：反映済み（2026-09-03時点、インフラとしては利用可能な状態）
- [x] 本番サーバーへのPHP 8.3系導入（`dnf install php php-cli php-gd php-mysqlnd php-mbstring php-xml php-json php-opcache`）：実施済み、`gd_info()`でJPEG/WebP対応まで確認済み
- [x] PHPアップロード上限の設定ファイル（`deploy/php.d/99-ksmc-uploads.ini`）：リポジトリに追加済み。本番サーバーへの実際の配置はこの章の作業として実施する
- [x] 本番サーバーへのコード配置・DB初期化・確認環境の構築：2026-09-19に完了（詳細は1〜4.6章、教訓は10章）

### ⚠️ 重要な運用方針：顧問の最終確認までは外部公開しない

DNS・ファイアウォール・SSL証明書の申請はインフラとして反映済みだが、**顧問による最終確認が完了するまでは、外部から到達可能な状態には一切移行しない**方針とする。これは要件定義書 第2.4節の「顧問の確認はデプロイ前の一度きりの確認」という位置づけに沿った運用判断であり、DNS/FW/SSLが技術的に「使える状態」であることと「実際に公開してよいか」は別の判断であることに注意する。

このため、下記の手順のうち**5章（Apacheの公開設定）と6章（実ドメインでの動作確認）は顧問確認が完了するまで保留**し、それまでの動作確認は以下のいずれかの方法でサーバー内部からのみ行う。

- サーバー上で直接 `curl http://127.0.0.1/...` 等により確認する
- SSHポートフォワーディング（`ssh -L 8080:127.0.0.1:80 user@server`）でローカルPCのブラウザから確認する
- Apache仮想ホストを一旦`127.0.0.1`または内部ネットワークのみにバインドし、外部インターフェースでは待ち受けない
- 追加の保険として、OSのファイアウォール（`firewalld`）で80/443番ポートへのアクセス元を開発者・顧問など特定IPのみに一時的に制限する

以下、1〜4章（コード配置・DB初期化・PHP設定）は顧問確認を待たずに進めてよい。5〜6章（公開設定・実ドメイン確認）は保留し、顧問確認完了後に着手する。

## 1. コードの配置 ✅ 完了（2026-09-19）

- [x] 本番サーバー上でリポジトリを取得する（`git clone` → `/var/www/ksmc_web`）
- [x] `erdiagram.md`はリポジトリに含めない方針のため、本番にも配置不要

## 2. 環境変数（`.env`）の設定 ✅ 完了（2026-09-19）

`.env`は`.gitignore`対象のため、サーバー上で手動作成する。

```bash
cat > /var/www/ksmc_web/.env << 'EOF'
DB_HOST=localhost
DB_NAME=ksmc_web
DB_USER=ksmc_app
DB_PASS=（強固なパスワードを設定）
DB_CHARSET=utf8mb4
EOF
```

- [x] `DB_USER`は`root`ではなく、後述のアプリ専用アカウントを使う
- [x] ファイルの権限を絞る：`chmod 640 .env` に加えて、**`chgrp apache .env`が必須**（10章の教訓1参照。所有グループを`staff`のままにすると、php-fpm実行ユーザーが読めずアプリ全体がHTTP 500になる）

## 3. データベースの初期化 ✅ 完了（2026-09-19）

- [x] MySQLにDBとアプリ専用ユーザーを作成
  ```sql
  CREATE DATABASE ksmc_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'ksmc_app'@'localhost' IDENTIFIED BY '（.envと同じパスワード）';
  GRANT SELECT, INSERT, UPDATE, DELETE ON ksmc_web.* TO 'ksmc_app'@'localhost';
  FLUSH PRIVILEGES;
  ```
  （`root`をアプリの接続ユーザーに使わないことで、万一の脆弱性発覚時の被害範囲を限定する。rootのログインに詰まった場合は10章の教訓2を参照）
- [x] スキーマを**root権限で**流し込む（`ksmc_app`にはCREATE権限を与えていないため）：`mysql -u root -p ksmc_web < database/schema.sql`
- [x] **最初の管理者アカウントを手動で1件INSERT**（招待制のため、最初の1人は手動作成が必須）
  ```bash
  php -r "echo password_hash('初期パスワード', PASSWORD_BCRYPT);"
  ```
  で得たハッシュ値を使い、
  ```sql
  INSERT INTO users (login_id, password_hash, must_change_password, display_name, role)
  VALUES ('admin', '（上記で生成したハッシュ）', 1, '管理者', 'admin');
  ```
  （`must_change_password=1`により初回ログイン時にパスワード変更が強制される）

## 4. PHP設定の反映 ✅ 完了（2026-09-19）

- [x] `deploy/php.d/99-ksmc-uploads.ini`を配置
  ```bash
  sudo cp deploy/php.d/99-ksmc-uploads.ini /etc/php.d/99-ksmc-uploads.ini
  ```
- [x] `sudo systemctl restart httpd`
- [x] 反映確認：`php -r "echo ini_get('upload_max_filesize'), ' / ', ini_get('post_max_size');"` → `20M / 24M`

## 4.5 確認環境の構築（顧問確認用、外部非公開） ✅ 完了（2026-09-19）

顧問確認までは外部公開しない方針（下記）に沿い、サーバー内部からのみ動作確認できる状態を構築した。

- [x] `public/uploads/`の権限設定（SELinuxは`Disabled`のため、Unix権限のみで対応）
  ```bash
  sudo chown -R apache:apache /var/www/ksmc_web/public/uploads
  sudo chmod -R 775 /var/www/ksmc_web/public/uploads
  ```
- [x] VirtualHostを新規作成し、`DocumentRoot`を`public/`に設定（`/etc/httpd/conf.d/ksmc-internal.conf`）
- [x] **`Listen 80`を`Listen 127.0.0.1:80`に変更**（`/etc/httpd/conf/httpd.conf`）し、外部インターフェースでは一切待ち受けないようにした
- [x] **`Listen 443 https`も同様に`Listen 127.0.0.1:443 https`に変更**（`/etc/httpd/conf.d/ssl.conf`）。80番だけでなく443番も忘れずに制限すること（10章の教訓3参照）
- [x] サーバー内部（`curl http://127.0.0.1/`）・外部（自分のPCから`curl.exe`）の両方で疎通確認し、内部は200、外部は接続不可（`000`）であることを確認
- [x] 顧問への対面デモ用に、手元PCからのSSHポートフォワーディングで実際の動作を確認
  ```bash
  ssh -L 8899:127.0.0.1:80 <SSHユーザー名>@133.17.100.182
  ```
  （**必ず手元PC側の別ターミナルで実行する**こと。サーバーへの既存SSHセッションの中で実行しても意味が無い。ローカル側ポートは`8080`を避けること。10章の教訓4参照）

## 4.6 顧問の最終確認（外部公開ゲート）

- [ ] 顧問による最終確認が完了した（完了日：　　　　　　）

**この項目が完了するまで、5章・6章には着手しない。**

## 5. 本公開への切り替え 🚧 顧問確認完了まで保留

VirtualHost・DocumentRoot・アップロード権限は4.5章で構築済みのため、**本公開時に残る作業は「ループバック限定を解除するだけ」**になっている。

- [ ] `Listen`の制限を解除する（**80番・443番の両方**を忘れずに）
  ```bash
  sudo sed -i 's/^Listen 127.0.0.1:80$/Listen 80/' /etc/httpd/conf/httpd.conf
  sudo sed -i 's/^Listen 127.0.0.1:443 https$/Listen 443 https/' /etc/httpd/conf.d/ssl.conf
  sudo apachectl configtest
  sudo systemctl restart httpd
  ```
- [ ] SSL証明書が実際にvhostへ正しく設定され、ブラウザで証明書エラーが出ないか確認する（申請の「反映」＝証明書の発行/承認であり、Apache側への設置状況は別途要確認）

## 6. 動作確認（実ドメイン経由） 🚧 顧問確認完了まで保留

**実際のドメインでの外部到達確認は顧問確認後に行う。** それまではサーバー内部からの確認（0章参照）にとどめる。

- [ ] 実際のドメイン（`https://ksmc.cs.kyusan-u.ac.jp`等）でトップページが表示される
- [ ] HTTPSでアクセスでき、証明書エラーが出ない
- [ ] 管理者アカウントでログインでき、初回パスワード変更が求められる
- [ ] 画像付き投稿（作成・確認画面・公開）が一通り動作する
- [ ] お問い合わせフォームの送信・管理画面での確認ができる

## 7. 顧問確認前に対応する既知の課題 ✅ 全項目完了（2026-09-19）

これまでのコードレビュー・疑似本番環境での検証で判明した未対応事項。**方針（2026-09-19決定）通り、顧問確認（4.6章）前にすべて修正済み**（コミット`db0f0af`、使い捨てDocker環境で全項目検証済み）。

- [x] ロゴ合成処理（`LogoCompositor`）の画像ピクセル寸法上限が無い問題：`Uploads::MAX_IMAGE_DIMENSION_PX`（6000px）を追加し、`ImageUploader::validate()`でアップロード時点で拒否するよう修正
- [x] 広報担当権限剥奪後の投稿確定時チェック漏れ（`PostController::confirmCreate()`）：確定直前に再チェックを追加
- [x] 管理者権限委譲で休止会員を除外していない（`AccountController::findTransferTargetOrRedirect()`）：除外条件を追加
- [x] 投稿編集時の画像削除とDBロールバックの不整合（`PostController::confirmEdit()`）：実ファイル削除をコミット成功後に遅延
- [x] 会員ページが休止会員を除外していない（`GalleryController::showMember()`）：除外を追加
- [x] セッションCookieのhttponly/secure/samesite未設定（`bootstrap.php`）：`session_set_cookie_params()`で明示
- [x] 管理者PR付与操作のCSRF失敗時無言リダイレクト（`AccountController::updatePr()`）：エラー表示に変更
- [x] 一覧表示のN+1クエリ（`GalleryController::decorate()`）：`User::findByIds()` / `Image::findThumbnailsByPostIds()`で一括取得に変更
- [x] 投稿作成/編集フローの重複（`PostController`）：共通処理をprivateメソッドへ切り出し（post_type・既存画像削除等の異なる部分は維持）

**残作業**：この修正を本番サーバーの確認環境（4.5章）へ反映する（`git pull`のみで反映可能）。

## 8. バックアップ体制の初期化（NFR-06）

- [ ] 初回のDB・アップロード画像のエクスポート手順を確立し、実施する
- [ ] `docs/admin_handover_procedure.md`のバックアップ引き継ぎ欄に、初回バックアップの取得日・保管場所を記録する

## 9. 引き継ぎドキュメントの最終確認

- [ ] `docs/admin_handover_procedure.md`の内容が実際のサーバー構成と一致しているか確認
- [ ] `.env`の内容・サーバーSSHログイン情報を、口頭等の安全な方法で管理者間で共有する

## 10. トラブルシューティング・教訓（2026-09-19の確認環境構築時に判明）

今後同様の作業を行う際に同じ問題を繰り返さないための記録。

### 教訓1：`.env`は所有グループを`apache`にする

`chmod 640`だけでは、所有者・所有グループが`staff`のままだとphp-fpm実行ユーザー（`apache`）が読み込めない。`bootstrap.php`の`file()`呼び出しが`Permission denied`で失敗し、環境変数が一切読み込まれないままDB接続を試みて`PDOException`（ユーザー名・パスワードとも空文字列）→ 未捕捉例外 → **HTTP 500**という形で症状が現れる。エラーの表面（DB接続エラー）と根本原因（ファイル権限）が離れているため気づきにくい。

対処：
```bash
sudo chgrp apache /var/www/ksmc_web/.env
```

### 教訓2：MySQL rootのパスワードが不明な場合の復旧手順

このサーバーは大学側で事前構築されており、MySQL 8.4のrootパスワードが引き継がれていなかった。`sudo mysql`（unix_socket認証）も通らない構成だったため、以下の手順でリセットした。

```bash
sudo systemctl stop mysqld
echo "ALTER USER 'root'@'localhost' IDENTIFIED BY '（新しいパスワード）';" | sudo tee /tmp/mysql-init-file
sudo mysqld --init-file=/tmp/mysql-init-file --user=mysql > /tmp/mysqld-manual.log 2>&1 &
sleep 5
mysql -u root -p   # 新しいパスワードでログインできるか確認
sudo rm -f /tmp/mysql-init-file
sudo mysqladmin -u root -p shutdown
sudo systemctl start mysqld
```
また、MySQLのエラーログの実際の出力先は`/var/log/mysqld.log`ではなく**`/var/log/mysql/mysqld.log`**だった（`/etc/my.cnf`に`log-error`の明示指定は無かったため、パッケージの実際のデフォルトを`find /var/log -iname '*mysql*'`で特定する必要があった）。

### 教訓3：外部非公開にする際は80番だけでなく443番（`ssl.conf`）も忘れずに制限する

`httpd.conf`の`Listen 80`だけを`127.0.0.1`限定にし、`ssl.conf`の`Listen 443 https`を見落としたため、一時的にHTTPSだけ外部公開されたままの状態になっていた。非公開化・公開化のいずれの作業でも、**httpd.conf（80番）とssl.conf（443番）の両方を必ずセットで確認・変更する**こと。

### 教訓4：SSHポートフォワーディングのローカル側ポートは`8080`を避ける

開発機のDocker Desktop環境（`ksmc_web_preview`等の確認用LAMP環境）が`phpMyAdmin`コンテナを常時起動しており、ローカルの8080番ポートを既に使用していることがある。SSHポートフォワーディング（`ssh -L <ローカルポート>:127.0.0.1:80 ...`）でこのポートを指定すると、転送が意図通り機能せず、ローカルの別サービス（この場合phpMyAdminのログイン画面）に接続してしまい、あたかもサーバー側の設定ミスのように見える。**`8899`など、ローカルで未使用の空きポートを明示的に選ぶ**こと。また、このコマンドは**手元PC側の別ターミナルで実行する**必要があり、サーバーへの既存SSHセッションの中で実行しても意味が無い点にも注意。
