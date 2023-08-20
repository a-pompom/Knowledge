# MySQL

公式: https://www.mysql.com/

## 基本構文

### ログイン周り

### テーブル操作

#### テーブル構造を確認するには

`SHOW CREATE TABLE`文を実行。

[参考](https://dev.mysql.com/doc/refman/8.0/en/show-create-table.html)

書式は以下の通り。

> 書式: `SHOW CREATE TABLE tbl_name`

実行例を見てみる。

```
show create table comments;
+----------+----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
| Table    | Create Table                                                                                                                                                                                                                                                                                                                                             |
+----------+----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
| comments | CREATE TABLE `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id_ids` (`post_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103012 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci |
+----------+----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
1 row in set (0.00 sec)
```

#### 実行計画を確認するには

EXPLAINマンドを実行。
書式は以下の通り。

[参考](https://dev.mysql.com/doc/refman/8.0/en/explain.html)

> 書式: `{EXPLAIN | DESCRIBE | DESC} ANALYZE [FORMAT = TREE] select_statement`

#### 実行計画の見方は

まずはサンプルを確認しておく。

```
mysql> explain SELECT COUNT(*) AS count FROM `comments` WHERE `user_id` = '867';
+----+-------------+----------+------------+------+---------------+------+---------+------+-------+----------+-------------+
| id | select_type | table    | partitions | type | possible_keys | key  | key_len | ref  | rows  | filtered | Extra       |
+----+-------------+----------+------------+------+---------------+------+---------+------+-------+----------+-------------+
|  1 | SIMPLE      | comments | NULL       | ALL  | NULL          | NULL | NULL    | NULL | 99697 |    10.00 | Using where |
+----+-------------+----------+------------+------+---------------+------+---------+------+-------+----------+-------------+
1 row in set, 1 warning (0.00 sec)
```

各項目の意味は以下の通り。

* table: 対象テーブル名
* type: JOIN方法
* possible_keys: 候補となるインデックスやキー
* key: 実際に選ばれたキー
* rows: 走査される行数の見積もり

[参考](https://dev.mysql.com/doc/refman/8.0/en/explain-output.html)

まとめると、どういう戦略でテーブルから行を取得されるかが書かれている。

インデックスを追加するとどう変化するのかも見ておく。

```
explain SELECT COUNT(*) AS count FROM `comments` WHERE `user_id` = '867';
+----+-------------+----------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
| id | select_type | table    | partitions | type | possible_keys | key         | key_len | ref   | rows | filtered | Extra       |
+----+-------------+----------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
|  1 | SIMPLE      | comments | NULL       | ref  | idx_user_id   | idx_user_id | 4       | const |  105 |   100.00 | Using index |
+----+-------------+----------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
```

特筆すべきは、keyカラムにインデックスが指定されていること・rowカラムが大きく減っていることである。
インデックスにより、少ない行を走査するだけで対象のレコードを取得できたことが伺える。

#### インデックスを追加するには

`CREATE INDEX`文を実行。
書式は以下の通り。
[参考](https://dev.mysql.com/doc/refman/8.0/en/create-index.html)

> 書式: `CREATE [UNIQUE | FULLTEXT | SPATIAL] INDEX index_name [index_type] ON tbl_name (key_part,...)`

サンプルの実行例を示す。

```
CREATE INDEX idx_user_id ON comments(user_id);
```

`SHOW CREATE TABLE`文で見てみると、インデックスが有効となっていることが分かる。

```
# 一部抜粋
KEY `idx_user_id` (`user_id`)
```



## スロークエリ

スロークエリ周りの調査メモ。

[参考](https://dev.mysql.com/doc/refman/8.0/en/slow-query-log.html)

### MySQL標準

#### スロークエリを出力するには

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

#### スロークエリの見方は

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

### mysqldumpslowコマンド

スロークエリログを集計して確認したいときに有用

[参考](https://dev.mysql.com/doc/refman/8.0/en/mysqldumpslow.html)

#### mysqldumpslowコマンドの使い方は

基本的な書式は以下の通り。

```bash
mysqldumpslow [options] [log_file ...]
```

オプションは頻繁に使うものは無さそう。集計したいログファイルのパスを指定すればよいか。

#### mysqldumpslowコマンドの結果の見方は

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

### pt-query-digest

スロークエリログのほか、さざまなログを詳細に分析できるpt-query-digestというツールを使ってみる。

[参考](https://docs.percona.com/percona-toolkit/pt-query-digest.html)

#### pt-query-digestコマンドの使い方は

書式は以下の通り。

> 書式: `pt-query-digest [OPTIONS] [FILES] [DSN]`

現段階では、スロークエリログファイル名を指定するだけでよさそう。


#### pt-query-digestの結果の見方は

[参考](https://docs.percona.com/percona-toolkit/pt-query-digest.html#output)

以下の構成にて表示される。

* 統計: クエリの実行時間の総計, 最小, 最大, 平均など 全体像を掴むのに良さそう
* ランキング: 負荷の高いクエリをランキング形式で表示 どの程度時間が掛かったか・何回呼ばれたかなどを含む
* クエリの詳細: 負荷の高いクエリの詳細を表示

```bash
# 統計

# 71.4s user time, 160ms system time, 54.98M rss, 61.70M vsz
# Current date: Wed Apr 12 09:07:59 2023
# Hostname: private-app
# Files: ./mysql-slow.log.1
# Overall: 2.02M total, 32 unique, 1.78k QPS, 0.19x concurrency __________
# Time range: 2023-04-11T00:04:35 to 2023-04-11T00:23:30
# Attribute          total     min     max     avg     95%  stddev  median
# ============     ======= ======= ======= ======= ======= ======= =======
# Exec time           221s     1us   495ms   109us   152us     1ms    22us
# Lock time          868ms       0    13ms       0     1us    16us       0
# Rows sent          2.58M       0      23    1.34    2.90    2.42    0.99
# Rows examine     274.34M       0  97.98k  142.65   14.52   3.33k    0.99
# Query size       464.03M      27   1.14M  241.29   84.10  11.65k   65.89

# ランキング

# Profile
# Rank Query ID                     Response time Calls  R/Call V/M   Item
# ==== ============================ ============= ====== ====== ===== ====
#    1 0xCDEB1AFF2AE2BE51B2ED5CF... 52.7403 23.8%   2331 0.0226  0.00 SELECT comments
#    2 0x624863D30DAC59FA1684928... 33.6995 15.2% 566414 0.0001  0.00 SELECT comments
#    3 0x396201721CD58410E070DA9... 30.1465 13.6% 760115 0.0000  0.00 SELECT users
#    4 0x422390B42D4DD86C7539A5F... 26.7707 12.1% 580775 0.0000  0.00 SELECT comments
#    5 0x009A61E5EFBD5A5E4097914... 21.3464  9.6%    659 0.0324  0.03 INSERT posts
#    6 0x19759A5557089FD5B718D44... 16.6597  7.5%  14361 0.0012  0.01 SELECT posts
#    7 0xAA65B65D6FEC3514934B143... 11.0278  5.0%   2331 0.0047  0.00 SELECT posts
#    8 0xC9383ACA6FF14C29E819735...  7.2353  3.3%   2331 0.0031  0.00 SELECT posts
#    9 0x9F2038550F51B0A3AB05CA5...  4.5649  2.1%   1074 0.0043  0.02 INSERT comments
#   10 0x26489ECBE26887E480CA806...  4.1796  1.9%   1121 0.0037  0.02 INSERT users
#   11 0xF0B73AEF152FDF9E43E6940...  3.1511  1.4%  19122 0.0002  0.00 SELECT posts users
# MISC 0xMISC                        9.6863  4.4%  65940 0.0001   0.0 <21 ITEMS>

# 遅いクエリの詳細

# Query 1: 2.06 QPS, 0.05x concurrency, ID 0xCDEB1AFF2AE2BE51B2ED5CF03D4E749F at byte 256720718
# This item is included in the report because it matches --limit.
# Scores: V/M = 0.00
# Time range: 2023-04-11T00:04:36 to 2023-04-11T00:23:30
# Attribute    pct   total     min     max     avg     95%  stddev  median
# ============ === ======= ======= ======= ======= ======= ======= =======
# Count          0    2331
# Exec time     23     53s     8ms    93ms    23ms    42ms    10ms    20ms
# Lock time      0   963us       0   184us       0     1us     3us       0
# Rows sent      0   2.28k       1       1       1       1       0       1
# Rows examine  81 222.62M  97.66k  97.98k  97.79k  97.04k       0  97.04k
# Query size     0 145.50k      63      64   63.92   62.76       0   62.76
# String:
# Databases    isuconp
# Hosts        localhost
# Users        isuconp
# Query_time distribution
#   1us
#  10us
# 100us
#   1ms  ##
#  10ms  ################################################################
# 100ms
#    1s
#  10s+
# Tables
#    SHOW TABLE STATUS FROM `isuconp` LIKE 'comments'\G
#    SHOW CREATE TABLE `isuconp`.`comments`\G
# EXPLAIN /*!50100 PARTITIONS*/
SELECT COUNT(*) AS count FROM `comments` WHERE `user_id` = '867'\G

```