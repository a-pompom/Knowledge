# nginx

公式: https://www.nginx.com/

## 基本

### nginxのドキュメントの構成は

Introductionでは、nginxの代表的な機能を紹介。
How-Toは応用レベルのユースケースを紹介。

Modules referenceでは、ディレクティブ・変数や主要なモジュールをリファレンス形式で紹介。

[参考](http://nginx.org/en/docs/)

### nginxの特徴は何か

* リクエストを高速に処理できる
* リバースproxy機能

[参考](https://www.nginx.com/resources/glossary/nginx/)

### ディレクティブとは何か

nginxでは設定値をディレクティブと呼ぶ。
ディレクティブには、単に値を書くだけのシンプルディレクティブ・`{}`で複数のディレクティブを包含するブロックディレクティブがある。

ブロックディレクティブは、以下のように、特定のモジュールでのみ有効な設定を記述するときに定義。

```nginx
http {
    # directives...
}
```

[参考](http://nginx.org/en/docs/beginners_guide.html)

### コンテキストとは何か

ディレクティブについて、どのブロックディレクティブで指定できるか規定した範囲をコンテキストと呼ぶ。
例えば、`if_modified_since`ディレクティブはhttpモジュールのブロックディレクティブでのみ記述できる。
これを、httpコンテキストに属すると言う。

主なコンテキストには、http, server, location, ifディレクティブがある。
また、どのコンテキストにも属さないものは、mainコンテキストと呼ばれる。mainはディレクティブではないことに注意が必要。

### 変数とは何か

nginxが用意している、サーバやクライアントに関する情報をまとめたもの。
`$remote_addr`(クライアントのIPアドレス)などがある。

ログに特定の情報を出力したい場合などに有用。

[参考](http://nginx.org/en/docs/varindex.html)

## リバースproxyはなぜ生まれたのか

主にパフォーマンスを改善するのが目的。
リバースproxyがSSLやキャッシュ周りを担うことで、バックエンド側の負荷を下げ、パフォーマンスを上げることができる。

更にロードバランサの役割も担っており、サーバに問題が起きたときにトラフィックを制御することで、容易にシステムを稼働させ続けることができる。

[参考](https://www.nginx.com/resources/glossary/reverse-proxy-server/)

## ディレクティブ

### locationディレクティブの引数はどう記述するのか

> 記法: `location [ = | ~ | ~* | ^~ ] uri { ... }`

基本形は、`location / { ... }`のようにuriのみ指定する形式。
uriはプレフィックスと呼ばれ、修飾子(=や~)を指定しない場合は、前方一致かつ最長一致にて判定

修飾子は以下の条件と対応

* `=`: 完全一致
* `~`: 正規表現
* `~*`: 正規表現(case insensitive)
* `^~`: 前方一致(条件が複雑なのであまり使わない方がよい)

また、優先順位は以下の通り。

* 完全一致
* 前方一致(最も長くマッチしたパターンに修飾子`^~`が付与されていた場合のみ)
* 正規表現
* 前方一致で最も長くマッチしたもの

[公式参考](http://nginx.org/en/docs/http/ngx_http_core_module.html#location)

[参考1](https://muziyoshiz.hatenablog.com/entry/2019/06/30/203903)
[参考2](https://www.keycdn.com/support/nginx-location-directive)

### nginxはいかにして設定ファイルからリクエストを処理するのか

以下の設定ファイルを例に見てみる。

```
server {
    listen      80;
    server_name example.org www.example.org;
    root        /data/www;

    location / {
        index   index.html index.php;
    }

    location ~* \.(gif|jpg|png)$ {
        expires 30d;
    }

    location ~ \.php$ {
        fastcgi_pass  localhost:9000;
        fastcgi_param SCRIPT_FILENAME
                      $document_root$fastcgi_script_name;
        include       fastcgi_params;
    }
}
```

まずはserverディレクティブから合致するものを探索。
1つしかない場合は、唯一のものをデフォルトとして採用。

HOSTヘッダとlocationディレクティブのuriを比較し、該当するものを判定。
優先順位は前述の通り。
例えば、`/img.gif`は`~* \.(gif|jpg|png)$`と合致・`/test.php`は、`location ~ \.php$`と合致。

内側の階層にこれ以上設定値が無い場合は、各種設定を反映してレスポンスを組み立てる。
`/img.gif`を例に考える。
serverディレクティブ以下のrootディレクティブから、`/var/www/img.gif`ファイルをもとにレスポンスが組み立てられる。

まとめると、nginxは設定から該当するものを抽出し、得られた設定値に基づいてレスポンスを組み立てる。

## その他の設定

### コンテンツをキャッシュさせるには

[参考](http://nginx.org/en/docs/http/ngx_http_headers_module.html#expires)

> 記法: `expires [modified] time;`

Expires, Cache-Controlヘッダにてコンテンツをキャッシュさせるよう設定。

[Cache-Control参考](https://developer.mozilla.org/ja/docs/Web/HTTP/Headers/Cache-Control)
[Expires参考](https://developer.mozilla.org/ja/docs/Web/HTTP/Headers/Expires)