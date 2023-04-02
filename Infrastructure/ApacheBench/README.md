# ApacheBench

公式: https://httpd.apache.org/docs/2.4/programs/ab.html

## 概要

### Apache Benchとは何か

HTTPサーバのベンチマークを測るためのツール。主にスループットを軸に計測。

### Apache Benchのインストール手順(Ubuntu)

以下コマンドによりインストールできる。

```bash
apt update
apt install apache2-utils
```

### 基本的な使い方は

abコマンドはどのホストのどのパスへ、どれだけリクエストを送るか指定するのが基本。

```bash
# 指定回数実行
ab -c 1 -n 10 http://localhost/
# 指定時間実行
ab -c 1 -t 30 http://localhost/request
```

### 実行結果の見方は

実行結果から性能を測るにはどうすればよいか見てみる。

```bash
$ ab -c 1 -n 10 http://localhost/

# 出力
Benchmarking localhost (be patient).....done

# どこへリクエストを送ったか
Server Software:        nginx/1.18.0
Server Hostname:        localhost
Server Port:            80

Document Path:          /
Document Length:        22161 bytes

# どれだけリクエストを送ったか
Concurrency Level:      1
Time taken for tests:   0.332 seconds
Complete requests:      10
Failed requests:        0
Total transferred:      224750 bytes
HTML transferred:       221610 bytes
# 1秒辺りに処理できるリクエスト数-スループット
Requests per second:    30.08 [#/sec] (mean)
Time per request:       33.243 [ms] (mean)
Time per request:       33.243 [ms] (mean, across all concurrent requests)
Transfer rate:          660.24 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.1      0       0
Processing:    13   33  54.3     16     188
Waiting:       13   33  54.2     15     187
Total:         13   33  54.4     16     188

Percentage of the requests served within a certain time (ms)
  50%     16
  66%     20
  75%     20
  80%     20
  90%    188
  95%    188
  98%    188
  99%    188
 100%    188 (longest request)
```

最も重要なのは、Requests per secondの項。
1秒に処理できるリクエストから、Webサーバの大まかなスピードをみることができる。

そのほかの項目は、概ね以下のように分類できる。

* どこへリクエストを送ったか
* どれだけの数リクエストを送ったか
* どんな速さでリクエストを処理できたか
* 各リクエストの内訳(どれだけ待ち状態があったか・平均どれだけの時間が掛かったかなど)
