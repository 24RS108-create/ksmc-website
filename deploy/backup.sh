#!/bin/sh
# 手動バックアップ用スクリプト（NFR-06）。
#
# 実行方法（本番サーバー上、/var/www/ksmc_web で）：
#   sh deploy/backup.sh
#
# cronには登録しないこと。管理者が必要と判断した時に手動で実行する運用とする
# （NFR-06「当面は手動でのローカルバックアップ」の方針）。
#
# DBダンプ・アップロード画像のtarを $HOME に作成する。実行後、手元PCから
# scpでダウンロードし、サーバー上の一時ファイルは削除すること（手順は
# docs/admin_handover_procedure.md 第5章を参照）。

set -e

DATE=$(date +%Y%m%d)
DB_NAME=ksmc_web
DB_USER=ksmc_app
UPLOADS_DIR=/var/www/ksmc_web/public

BACKUP_SQL="$HOME/ksmc_backup_${DATE}.sql"
BACKUP_UPLOADS="$HOME/ksmc_uploads_${DATE}.tar.gz"

# --single-transactionによりInnoDBの整合性を保ちながらロックなしでダンプできる。
# ksmc_appユーザーはLOCK TABLES権限を持たないため、このオプションが必須。
# --no-tablespacesを付けないと、テーブルスペース情報の取得にPROCESS権限を要求され
# 警告が出る（このアプリでは明示的なテーブルスペースを使わないため実害はないが、
# バックアップ専用に権限を広げたくないため、オプション側で回避する）。
mysqldump -u "$DB_USER" -p --single-transaction --no-tablespaces "$DB_NAME" > "$BACKUP_SQL"

tar -czf "$BACKUP_UPLOADS" -C "$UPLOADS_DIR" uploads

echo "作成完了:"
echo "  $BACKUP_SQL"
echo "  $BACKUP_UPLOADS"
echo ""
echo "この後、手元PCから scp でダウンロードしてください。例："
echo "  scp <SSHユーザー名>@133.17.100.182:$BACKUP_SQL ."
echo "  scp <SSHユーザー名>@133.17.100.182:$BACKUP_UPLOADS ."
echo ""
echo "ダウンロード完了後、サーバー上の以下のファイルは削除してください："
echo "  rm $BACKUP_SQL $BACKUP_UPLOADS"
