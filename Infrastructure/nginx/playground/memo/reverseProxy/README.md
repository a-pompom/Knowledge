# 概要

nginxのリバースProxy周りの設定を試してみる。

## ゴール

nginxをリバースProxyとして動作させ、別のプロセスへリクエストを中継させるための設定方法を理解することを目指す。

### PHP-FPMと通信したい

PHPアプリケーションへリクエストを委譲したい場合、PHP-FPMモジュールを介在させる。
これを実現するための設定方法を理解したい。

#### Fast CGI

PHP-FPMモジュールは、FastCGIの仕様に従ったリクエストを受け付ける。
ここではひとまず、CGIと読み替えてよい。CGIはWSGIなどのように生のHTTPリクエストをプログラムにて一定の形式で扱えるよう仕様を定めたものである。
より具体的には、SCRIPT_NAMEといったあらかじめ定められたパラメータをコマンドライン引数やクエリパラメータなどで受け取れるよう規定している。

[参考](https://www.tohoho-web.com/wwwcgi3.htm)

このとき、PHP-FPM自体は生のHTTPリクエストを受け取らず、パラメータを別で設定する処理が必要。
これをnginxが担っている。

以降では、nginxがいかにしてPHP-FPMへリクエストを渡すのか、ディレクティブを通じて流れを追っていく。

#### fastcgi_pass

[参考](http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_pass)

> 記法: `fastcgi_pass address;`

Fast CGIリクエストを送りたいアドレスを指定。アドレスにはURLやUNIXドメインソケットを記述できる。

#### fastcgi_param

[参考](http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param)

> 記法: `fastcgi_param parameter value [if_not_empty];`

Fast CGIへ渡すリクエストのパラメータを設定。

#### include fastcgi_params

これは、設定ファイル`/etc/nginx/fastcgi_params`ファイルを読み出す。
該当のファイルの中身は、以下のように書かれている。

```bash
$ cat /etc/nginx/fastcgi_params

# 出力
fastcgi_param  QUERY_STRING       $query_string;
fastcgi_param  REQUEST_METHOD     $request_method;
fastcgi_param  CONTENT_TYPE       $content_type;
fastcgi_param  CONTENT_LENGTH     $content_length;

fastcgi_param  SCRIPT_NAME        $fastcgi_script_name;
fastcgi_param  REQUEST_URI        $request_uri;
fastcgi_param  DOCUMENT_URI       $document_uri;
fastcgi_param  DOCUMENT_ROOT      $document_root;
fastcgi_param  SERVER_PROTOCOL    $server_protocol;
fastcgi_param  REQUEST_SCHEME     $scheme;
fastcgi_param  HTTPS              $https if_not_empty;

fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
fastcgi_param  SERVER_SOFTWARE    nginx/$nginx_version;

fastcgi_param  REMOTE_ADDR        $remote_addr;
fastcgi_param  REMOTE_PORT        $remote_port;
fastcgi_param  SERVER_ADDR        $server_addr;
fastcgi_param  SERVER_PORT        $server_port;
fastcgi_param  SERVER_NAME        $server_name;

# PHP only, required if PHP was built with --enable-force-cgi-redirect
fastcgi_param  REDIRECT_STATUS    200;
```

たくさんのfastcgi_paramディレクティブが書かれている。
これらはCGIにて規定されているパラメータのデフォルト値を表現している。

まとめると、nginxがHTTPリクエストをもとに組み立てた組み込みの変数($remote_addrなど)をもとにFast CGIへ渡すパラメータを組み立てている。
これをもとにFast CGIへ渡すリクエストがつくられる。

#### PHP-FPMがリクエストを受け取るまでの流れ

前提知識がそろったので、改めてnginxを入り口とし、PHP-FPMがリクエストを処理するまでの流れをたどる。
流れを理解するために、nginxがどのようにPHP-FPMモジュールへリクエストを渡すのか設定ファイルから見てみる。

```
server {
    listen 80;
    root /var/www/public;

    # CSSファイルはpublic/cssディレクトリをルートとする
    location ~ \.css$ {
        root /var/www/public/css;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        # /etc/nginx/fastcgi_paramsファイルに書かれたデフォルトの設定値を読み込み
        include fastcgi_params;
        # デフォルト値は$fastcgi_script_name
        fastcgi_param SCRIPT_FILENAME /var/www/html/$fastcgi_script_name;
    }
}
```

重要なのは、`location ~ \.php$`ディレクティブで書かれたブロックである。
nginxは、`http://localhost:8080/index.php` のように末尾が`.php`で終わるパスへのリクエストを受け取ると、上記ブロックの中身を読み出す。

最初に、`fastcgi_pass`ディレクティブから、どこへFastCGIリクエストを送るか判断する。
続いて、生のHTTPリクエストからFastCGIリクエストを組み立てるために、`fastcgi_params`ファイルからデフォルトの設定値を読み出す。
追加で設定したい項目があれば、`fastcgi_param`ディレクティブで設定。

ブロックを走査すると、どこに・どのようなFastCGIリクエストを送ればよいかが決まる。
今回はホスト名がphpのサーバの9000番ポートにてPHP-FPMモジュールがリクエストを受け付けているので、該当するアドレスへFastCGIリクエストを送信。
PHP-FPMモジュールはFastCGIリクエストからどのPHPファイルへリクエストを委譲するか決め、レスポンスを受け取る。
受け取ったFastCGIレスポンスをnginxへ返却し、nginxにて生のHTTPレスポンスをつくりだす。

出来上がったHTTPレスポンスをブラウザへ返すことで終了。

#### SCRIPT_NAME

よく設定されるSCRIPT_NAMEパラメータについて補足しておく。

`fastcgi_param SCRIPT_FILENAME /var/www/html/$fastcgi_script_name;`

これは、PHP-FPMモジュールが起動しているサーバにおいて、どのファイルを探索するかを規定している。
DockerなどでnginxとPHP-FPMモジュールを別々のサーバとして動かしていると、ディレクトリ構成が異なることがある。
よって、PHP-FPMモジュール側のディレクトリ構成を指定したい場合は、デフォルト値を上書きするために上記のように設定する。
