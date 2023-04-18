# 概要

nginxの設定を色々試してみる。

## ゴール

キャッシュやリバースプロキシなど、やりたいことを実現する上でnginxの設定をどのように書けば良いのか理解することを目指す。


## Hello World

### index.htmlを表示させたい

```
server {
    listen 80;
    root /var/www/public;
}
```

以降にて各種ディレクティブの記法を読み解く。

#### listen

[参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#listen)

> 書式: 	listen address;

> context: server

どのサーバがリクエストを受け取るか規定するために設定。
addressにはIPアドレス・ホスト名・ポート番号・UNIXドメインソケットのパスを指定できる。

#### root

[参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#root)

> 書式: root path;

> context: http, server, location, if in location

リクエストにおけるルートディレクトリを設定。
パスをもとにレスポンスを生成するファイルを解決。

### locationディレクティブに入門したい

```
server {
    listen 80;
    root /var/www/public;

    # CSSファイルはpublic/cssディレクトリをルートとする
    location ~ \.css$ {
        root /var/www/public/css;
    }
}
```

#### location

[参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#location)

> 記法: 	location [ = | ~ | ~* | ^~ ] uri { ... }

パス部分をもとにリクエストを探索。
上の例では、正規表現修飾子により、拡張子がCSSのリクエストを対象としている。

このように、locationディレクティブはパス要素から柔軟にリクエストを制御するために記述する。

## 基本設定

### パフォーマンス関連

#### worker_processes

[参考](http://nginx.org/en/docs/ngx_core_module.html#worker_processes)

> 記法: `worker_processes number | auto;`

デフォルトは1。
ワーカーをいくつのプロセスで稼働させるか設定。
基本的にはCPUのコア数と同じにするのがよい。

#### worker_connections

[参考](http://nginx.org/en/docs/ngx_core_module.html#worker_connections)

> 記法: worker_connections number;

デフォルトは512。
クライアントが同時にアクセスできる最大数は、worker_process * worker_connectionsの値によって決まる。
メモリの状況などを見て値を増やすのがよい。

#### server_tokens

[参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#server_tokens)

> 記法: server_tokens on | off | build | string;

HTTPレスポンスのヘッダにnginxのバージョンを表示するか。
デフォルトは表示。
セキュリティを考えると、バージョン情報は、offに設定して非表示とするのが望ましい。

#### deny

[参考](http://nginx.org/en/docs/http/ngx_http_access_module.html#deny)

> 記法: deny address | CIDR | unix: | all;

#### allow

[参考](http://nginx.org/en/docs/http/ngx_http_access_module.html#allow)

> 記法: allow address | CIDR | unix: | all;

指定した条件のホストからのアクセスを許可。

deny, allowディレクティブを組み合わせ、特定のIPアドレスからのアクセスのみを許容する制御を実現できる。


### ログ関連

#### log_format

[参考](http://nginx.org/en/docs/http/ngx_http_log_module.html#log_format)

> 記法: `log_format name [escape=default|json|none] string ...;`

ログのフォーマットを規定。
nameにはフォーマット名を・stringには形式を指定する。

```
# サンプル
log_format combined '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent"';
```

ここで定義したフォーマットは、access_logディレクティブのformat属性に指定することで有効になる。

[参考](http://nginx.org/en/docs/http/ngx_http_log_module.html#access_log)

#### access_log

[参考](http://nginx.org/en/docs/http/ngx_http_log_module.html#access_log)

> 書式: `access_log path [format [buffer=size] [gzip[=level]] [flush=time] [if=condition]];`

デフォルトは、`access_log logs/access.log combined;`。combinedフォーマットにて、`logs/access.log`へ出力する。

### エラー処理関連

#### error_page

[参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#error_page)

> 記法: `error_page code ... [=[response]] uri;`

レスポンスコードごとに表示させるエラーページを設定。

```
# example
error_page 404             /404.html;
error_page 500 502 503 504 /50x.html;
```

#### error_log

[参考](http://nginx.org/en/docs/ngx_core_module.html#error_log)

> 記法: `error_log file [level];`

デフォルトは、`error_log logs/error.log error;`。
エラーログの出力先・出力レベルを規定。

