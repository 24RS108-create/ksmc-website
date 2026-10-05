# 本番公開までの手順（デプロイチェックリスト）

要件定義書・PROTOTYPE.md・これまでの疑似本番環境での検証結果を踏まえた、実際の公開（本番サーバーへの反映）までの作業手順です。上から順に実施してください。チェック欄は作業時に埋めてください。

## 0. 前提：現時点でわかっていること

- [x] ホスティングサーバー払い出し：承認済み（2026-08-17）
- [x] DNS・ファイアウォール・SSL証明書の申請：反映済み（2026-09-03時点、インフラとしては利用可能な状態）
- [x] 本番サーバーへのPHP 8.3系導入（`dnf install php php-cli php-gd php-mysqlnd php-mbstring php-xml php-json php-opcache`）：実施済み、`gd_info()`でJPEG/WebP対応まで確認済み
- [x] PHPアップロード上限の設定ファイル（`deploy/php.d/99-ksmc-uploads.ini`）：リポジトリに追加済み。本番サーバーへの実際の配置はこの章の作業として実施する
- [x] 本番サーバーへのコード配置・DB初期化・確認環境の構築：2026-09-19に完了（詳細は1〜4.6章、教訓は10章）

### ✅ 2026-10-05：顧問の最終確認完了・本公開済み

顧問による最終確認が完了し（4.6章）、5章・6章の作業も完了したため、**本サイトは実際のドメイン（`https://ksmc.cs.kyusan-u.ac.jp`）で外部に公開済み**である。以下は公開に至るまでの運用方針の記録として残す。

DNS・ファイアウォール・SSL証明書の申請はインフラとして反映済みだったが、**顧問による最終確認が完了するまでは、外部から到達可能な状態には一切移行しない**方針としていた。これは要件定義書 第2.4節の「顧問の確認はデプロイ前の一度きりの確認」という位置づけに沿った運用判断であり、DNS/FW/SSLが技術的に「使える状態」であることと「実際に公開してよいか」は別の判断であることによるものだった。

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

## 4. PHP設定の反映 ✅ 完了（2026-09-19、2026-10-05にphp-fpm再起動漏れを修正：教訓6参照）

PHPは`php-fpm`経由で動作しているため、`/etc/php.d/`配下のiniファイルを反映するには**`httpd`ではなく`php-fpm`の再起動が必要**。下記は当初の手順（`httpd`再起動のみ）を修正済みのもの。

- [x] `deploy/php.d/99-ksmc-uploads.ini`を配置
  ```bash
  sudo cp deploy/php.d/99-ksmc-uploads.ini /etc/php.d/99-ksmc-uploads.ini
  ```
- [x] `sudo systemctl restart php-fpm`（**`httpd`の再起動だけでは反映されない**。教訓6参照）
- [x] 反映確認：CLIでの`php -r`確認はphp-fpm側の反映を保証しないため使わない。以下でphp-fpm自体の設定を確認する
  ```bash
  sudo php-fpm -i | grep -iE "upload_max_filesize|post_max_size"
  ```
  → `20M` / `24M`相当になっていること（2026-10-05確認済み：`post_max_size => 24M => 24M` / `upload_max_filesize => 20M => 20M`）

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
  ssh -L 8899:127.0.0.1:80 staff@133.17.100.182
  ```
  （**必ず手元PC側の別ターミナルで実行する**こと。サーバーへの既存SSHセッションの中で実行しても意味が無い。ローカル側ポートは`8080`を避けること。10章の教訓4参照）

## 4.6 顧問の最終確認（外部公開ゲート） ✅ 完了（2026-10-05）

- [x] 顧問による最終確認が完了した（完了日：2026-10-05）

**この項目が完了したため、5章・6章に着手してよい。**

## 4.7 エラー表示・セキュリティヘッダー設定の反映（2026-10-02 デプロイ前最終確認で追加） ✅ 完了（2026-10-05にphp-fpm再起動漏れを修正：教訓6参照）

1〜4章までと同様、これはサーバー内部の設定変更であり外部への公開状態には影響しないため、**4.6章（顧問確認）を待たずに実施してよい**。

- [x] `deploy/php.d/99-ksmc-errors.ini`を配置（`display_errors=Off`等。未捕捉の例外やWarningがそのまま訪問者に表示される情報漏洩を防ぐ）
  ```bash
  sudo cp deploy/php.d/99-ksmc-errors.ini /etc/php.d/99-ksmc-errors.ini
  sudo systemctl restart httpd
  ```
- [x] **`sudo systemctl restart php-fpm`を追加で実行する**（教訓6参照。`httpd`再起動だけではphp-fpmワーカーに`display_errors=Off`が反映されない。2026-10-05時点でphp-fpmは2026-09-10起動のまま一度も再起動されておらず、このiniファイル自体がphp-fpmに未反映の状態だった）
- [x] 反映確認：CLIでの`ini_get()`確認ではphp-fpm側の実際の反映を保証しない（教訓6参照）。以下いずれかで確認する
  ```bash
  sudo php-fpm -i | grep -iE "display_errors|log_errors"
  ```
  → `display_errors`が`Off`、`log_errors`が`On`になっていること（2026-10-05確認済み：`display_errors => Off => Off` / `log_errors => On => On`）
- [x] `public/.htaccess`で追加したセキュリティヘッダー（`X-Frame-Options`等）が実際に付与されているか確認する（2026-10-05完了。`AllowOverride All`へ変更のうえ`X-Content-Type-Options`・`X-Frame-Options`とも付与を確認）
  ```bash
  curl -sI http://127.0.0.1/ | grep -i "x-frame-options\|x-content-type-options"
  ```

  **注意**：`public/.htaccess`にはヘッダー設定（`Header`、`AllowOverride FileInfo`で有効化）に加えて、ドットファイル拒否ルール（`<FilesMatch>` + `Require`、`AllowOverride AuthConfig`で有効化）も含まれる。両方を一度に有効化するには`AllowOverride FileInfo AuthConfig`、または（この用途専用のVirtualHostであることを踏まえ）単純に`AllowOverride All`とするのが確実
- [x] ドットファイル拒否ルールが実際に有効か確認する（2026-10-05完了。`403`を確認）
  ```bash
  echo test | sudo tee /var/www/ksmc_web/public/.deploycheck > /dev/null
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1/.deploycheck   # 403が期待値（200ならAllowOverride未反映）
  sudo rm /var/www/ksmc_web/public/.deploycheck
  ```

## 5. 本公開への切り替え ✅ 完了（2026-10-05）

VirtualHost・DocumentRoot・アップロード権限は4.5章で構築済みのため、本公開時に残る作業は「ループバック限定を解除するだけ」の想定だったが、**実際にはそれだけでは不十分だった**（詳細は10章・教訓5参照）。

- [x] `Listen`の制限を解除する（**80番・443番の両方**を忘れずに）
  ```bash
  sudo sed -i 's/^Listen 127.0.0.1:80$/Listen 80/' /etc/httpd/conf/httpd.conf
  sudo sed -i 's/^Listen 127.0.0.1:443 https$/Listen 443 https/' /etc/httpd/conf.d/ssl.conf
  sudo apachectl configtest
  sudo systemctl restart httpd
  ```
- [x] SSL証明書が実際にvhostへ正しく設定され、ブラウザで証明書エラーが出ないか確認する（2026-10-05確認。証明書エラーなし）

## 6. 動作確認（実ドメイン経由） ✅ 完了（2026-10-05）

- [x] 実際のドメイン（`https://ksmc.cs.kyusan-u.ac.jp`）でトップページが表示される
- [x] HTTPSでアクセスでき、証明書エラーが出ない
- [x] 管理者アカウントでログインできる
- [x] 投稿（作成・確認画面・公開）が一通り動作する
- [x] お問い合わせフォームの送信ができる

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

## 8. バックアップ体制の初期化（NFR-06） ✅ 完了（2026-09-25）

- [x] DB・アップロード画像のエクスポート手順を確立：`deploy/backup.sh`を新設し、`docs/admin_handover_procedure.md`第5章に手順を明文化（cronには登録せず、手動実行のみ）
- [x] 上記手順で初回バックアップを実際に取得する（2026-09-25、`ksmc_backup_20260925.sql` / `ksmc_uploads_20260925.tar.gz`）
- [x] `docs/admin_handover_procedure.md`第5.3節の記録表に、初回バックアップの取得日・保管場所を記入する

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

### 教訓5：ループバック限定の解除（5章）だけでは不十分だった。VirtualHostのIP/ポート指定とDocumentRootの未設定も確認すること

5章の想定では「`Listen`のループバック限定を解除するだけ」で本公開できるはずだったが、実際には`Listen`を解除しただけではApacheの初期ウェルカムページ（テストページ）が表示され、アプリにルーティングされなかった。原因は2点。

1. `ksmc-internal.conf`の`<VirtualHost 127.0.0.1:80>`が、4.5章で内部限定にした際のIPアドレス指定のまま残っていた。`Listen`を全インターフェースへ開放しても、VirtualHost自体が`127.0.0.1:80`専用のままだと、外部からのリクエスト（宛先IPが公開IPのもの）はこのVirtualHostにマッチせず、Apacheのデフォルト設定（グローバルな`DocumentRoot "/var/www/html"`）にフォールバックしてしまう。`<VirtualHost *:80>`に修正して解消。
2. `ssl.conf`の`<VirtualHost _default_:443>`は、SSL証明書（`SSLCertificateFile`/`SSLCertificateKeyFile`）こそ本番ドメインの証明書に差し替え済みだったが、`DocumentRoot "/var/www/html"`の行が**コメントアウトされたまま**だった。そのためHTTPS経由でも同様にグローバルなデフォルトDocumentRootにフォールバックしていた。コメントを外し`/var/www/ksmc_web/public`へ変更、あわせて`ServerName`も実ドメインに設定して解消（`<Directory /var/www/ksmc_web/public>`の権限設定はパスが共通のため`ksmc-internal.conf`側の定義がそのまま適用された）。

教訓：VirtualHostを`127.0.0.1`等の特定IPに限定してから後で解除する運用をとる場合、**`Listen`だけでなく`<VirtualHost>`宣言のIP/ポート指定自体も確認する**こと。また、SSL証明書をvhostに設定する際は、`SSLCertificateFile`等だけでなく**`DocumentRoot`・`ServerName`がコメントアウトされたままになっていないか**も必ず確認すること。

### 教訓6：`/etc/php.d/`のini変更は`httpd`再起動では反映されない。`php-fpm`自体の再起動が必要

会員が画像5枚程度（jpg）を添付して投稿しようとすると「不正なリクエストです」が表示される不具合から発覚。実際の原因は**アップロード上限（`post_max_size`）がデフォルト値`8M`のまま**で、5枚程度でも合計がそれを超え、PHPがリクエスト全体（CSRFトークンを含む`$_POST`）を空にしていたこと。

このサーバーのPHPは`mpm_event` + `proxy_fcgi_module`経由、つまり**php-fpm**で動作している。`/etc/php.d/`配下のiniファイルはphp-fpmのワーカープロセスが起動する際に読み込まれるため、**`sudo systemctl restart httpd`では一切反映されない**。4章・4.7章の手順はいずれも`httpd`の再起動のみを行っていたため、`99-ksmc-uploads.ini`（`post_max_size=24M`等）も`99-ksmc-errors.ini`（`display_errors=Off`等）も、**配置した時点では一度もphp-fpmに反映されていなかった**（調査時点でphp-fpmは2026-09-10起動のまま一度も再起動されていないことを`systemctl status php-fpm`で確認）。

さらに厄介なのは、**確認手順自体が誤った「反映済み」判定をしていた**点。`php -r "echo ini_get(...);"`はCLI版のPHPを都度新規プロセスで起動するため、その時点の`/etc/php.d/`の内容を正しく読み込み、見た目上は正しい値（`20M / 24M`、`display_errors`が`Off`等）を返す。しかしこれはCLI SAPIの話であり、**実際にWebリクエストを処理しているphp-fpmワーカーの設定を何も保証しない**。この食い違いに気づけたのは、ログの実測値（`exceeds the limit of 8388608 bytes` = デフォルトの8M）とCLIでの確認結果が矛盾していたことがきっかけだった。

ログの調査過程でも把握しておくべき点：
- php-fpmのエラーログは`httpd`のエラーログ（`/var/log/httpd/error_log`）ではなく、**`/etc/php-fpm.d/www.conf`の`php_admin_value[error_log]`で指定された場所**（このサーバーでは`/var/log/php-fpm/www-error.log`）に出力される
- ログの日時書式は`[05-Oct-2026 12:18:16 UTC]`のように`-`区切り＋タイムゾーン付き。ロケール依存で曖昧な`grep`パターンだと空振りしうるので、まずファイル全体を見るのが確実

対処・再発防止：
```bash
sudo systemctl restart php-fpm
sudo php-fpm -i | grep -iE "post_max_size|upload_max_filesize|display_errors|log_errors"
```
今後`/etc/php.d/`配下のiniファイルを追加・変更する際は、**`httpd`と`php-fpm`の両方を再起動**し、確認は**CLIではなく`php-fpm -i`、または実際のHTTPリクエスト経由**で行うこと。
