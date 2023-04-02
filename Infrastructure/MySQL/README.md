# MySQL

公式: https://www.mysql.com/

## スロークエリ

スロークエリ周りの調査メモ。

[参考](https://dev.mysql.com/doc/refman/8.0/en/slow-query-log.html)

### スロークエリを出力するには

スロークエリを出力すると、IOが発生するため負荷が掛かる。
よって、デフォルトでは出力されないので、slow_query_log変数を有効にする。
更に、出力先はslow_query_log_file変数で制御できる。

そして、スロークエリとして出力する閾値は、long_query_time変数から変更できる。

```
[mysqld]
# デフォルトは0
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
# デフォルトは10秒
# 0を指定することですべてのクエリを出力できる
long_query_time = 0
```

[slow_query_log](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_slow_query_log)
[slow_query_log_file](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_slow_query_log_file)
[long_query_time](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_long_query_time)

### スロークエリの見方は

まずはサンプルを見てみる。

```bash
/usr/sbin/mysqld, Version: 8.0.32-0ubuntu0.22.04.2 ((Ubuntu)). started with:
Tcp port: 3306  Unix socket: /var/run/mysqld/mysqld.sock
Time                 Id Command    Argument
# Time: 2023-04-02T07:06:35.796665Z
# User@Host: isuconp[isuconp] @ localhost []  Id:     8
# Query_time: 0.065923  Lock_time: 0.000008 Rows_sent: 10148  Rows_examined: 20296
use isuconp;
SET timestamp=1680419195;
SELECT `id`, `user_id`, `body`, `mime`, `created_at` FROM `posts` ORDER BY `created_at` DESC;
```

ここで重要なのは、行・時間要素である。各要素の概要を挙げる。

* Query_time: クエリの実行秒数
* Lock_time: LOCKを得た秒数
* Rows_sent: クライアントへ送信した結果行数
* Rows_examined: MySQLが内部で走査した行数

Query_timeが長ければクエリの実行に時間が掛かっており、それだけレスポンスが返るのが遅くなる。
Rows_examinedが多い場合は、それだけたくさんの行が走査されており、MySQLに負荷が掛かっている。

このように各要素の値から、クエリの性能を評価していく。

### スロークエリを集計して確認するには

mysqldumpslowコマンドにより集計。

[参考](https://dev.mysql.com/doc/refman/8.0/en/mysqldumpslow.html)

### mysqldumpslowコマンドの使い方は

基本的な書式は以下の通り。

```bash
mysqldumpslow [options] [log_file ...]
```

オプションは頻繁に使うものは無さそう。集計したいログファイルのパスを指定すればよいか。

### mysqldumpslowコマンドの結果の見方は

まずはサンプルから概要を見てみる。

```bash
$ mysqldumpslow /var/log/mysql/mysql-slow.log

Reading mysql slow query log from /var/log/mysql/mysql-slow.log
Count: 10  Time=0.01s (0s)  Lock=0.00s (0s)  Rows=10148.0 (101480), isuconp[isuconp]@localhost
  SELECT `id`, `user_id`, `body`, `mime`, `created_at` FROM `posts` ORDER BY `created_at` DESC

Count: 200  Time=0.00s (0s)  Lock=0.00s (0s)  Rows=1.0 (200), isuconp[isuconp]@localhost
  SELECT COUNT(*) AS `count` FROM `comments` WHERE `post_id` = 'S'

Count: 200  Time=0.00s (0s)  Lock=0.00s (0s)  Rows=0.0 (0), isuconp[isuconp]@localhost
  SELECT * FROM `comments` WHERE `post_id` = 'S' ORDER BY `created_at` DESC LIMIT N

Count: 200  Time=0.00s (0s)  Lock=0.00s (0s)  Rows=1.0 (200), isuconp[isuconp]@localhost
  SELECT * FROM `users` WHERE `id` = 'S'
```

これは、実行時間が長い順にクエリを出力している。
括弧の中は各種値 * Count値の積を表す。
表示項目について、Countがクエリの実行回数を表している以外は、スロークエリログと同じ。
