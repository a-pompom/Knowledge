FROM mysql:8.0
ENV TZ=Asia/Tokyo

# 設定ファイル
COPY "./my.cnf" "/etc/mysql/conf.d/custom_my.cnf"
# 初期化スクリプト
COPY "./init.sh" "/docker-entrypoint-initdb.d/init.sh"

# ユーザ設定
USER mysql

