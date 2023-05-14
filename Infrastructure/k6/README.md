# k6

公式: https://k6.io/docs/

## 概要

### k6とは

オープンソースの負荷試験ツール。
シナリオをJavaScriptで記述できるのが特徴。

[参考](https://k6.io/docs/)

### k6の環境構築をするには

[参考](https://k6.io/docs/get-started/installation/)

CPUの問題でインストールできないことがあるので、Dockerイメージで構築するのがシンプル。
`grafana/k6`イメージからコンテナを組み立てるのがよい。

また、k6イメージはENTRYPOINTにk6コマンドが設定されているので、繰り返し使いたい/docker composeで構築したい場合は、shなどで上書きする。

### k6でリクエストを送るには

公式にてHello World的な立ち位置で記述されているスクリプトを見てみる。

```JavaScript
import http from 'k6/http';
import { sleep } from 'k6';

export default function () {
  http.get('https://test.k6.io');
  sleep(1);
}
```

スクリプトをもとに負荷試験を実行。

```bash
$ k6 run script.js
```

[参考](https://k6.io/docs/get-started/running-k6/)

### 実行結果の見方は

上述の環境でコマンドを実行した結果を見てみる。

```
$ k6 run /home/app/index.js 

          /\      |‾‾| /‾‾/   /‾‾/   
     /\  /  \     |  |/  /   /  /    
    /  \/    \    |     (   /   ‾‾\  
   /          \   |  |\  \ |  (‾)  | 
  / __________ \  |__| \__\ \_____/ .io

  execution: local
     script: /home/app/index.js
     output: -

  scenarios: (100.00%) 1 scenario, 1 max VUs, 10m30s max duration (incl. graceful stop):
           * default: 1 iterations for each of 1 VUs (maxDuration: 10m0s, gracefulStop: 30s)


     data_received..................: 23 kB 15 kB/s
     data_sent......................: 78 B  53 B/s
     http_req_blocked...............: avg=16.63ms  min=16.63ms  med=16.63ms  max=16.63ms  p(90)=16.63ms  p(95)=16.63ms 
     http_req_connecting............: avg=13.88ms  min=13.88ms  med=13.88ms  max=13.88ms  p(90)=13.88ms  p(95)=13.88ms 
     http_req_duration..............: avg=428.48ms min=428.48ms med=428.48ms max=428.48ms p(90)=428.48ms p(95)=428.48ms
       { expected_response:true }...: avg=428.48ms min=428.48ms med=428.48ms max=428.48ms p(90)=428.48ms p(95)=428.48ms
     http_req_failed................: 0.00% ✓ 0        ✗ 1  
     http_req_receiving.............: avg=14.98ms  min=14.98ms  med=14.98ms  max=14.98ms  p(90)=14.98ms  p(95)=14.98ms 
     http_req_sending...............: avg=3.04ms   min=3.04ms   med=3.04ms   max=3.04ms   p(90)=3.04ms   p(95)=3.04ms  
     http_req_tls_handshaking.......: avg=0s       min=0s       med=0s       max=0s       p(90)=0s       p(95)=0s      
     http_req_waiting...............: avg=410.45ms min=410.45ms med=410.45ms max=410.45ms p(90)=410.45ms p(95)=410.45ms
     http_reqs......................: 1     0.677389/s
     iteration_duration.............: avg=1.46s    min=1.46s    med=1.46s    max=1.46s    p(90)=1.46s    p(95)=1.46s   
     iterations.....................: 1     0.677389/s
     vus............................: 1     min=1      max=1
     vus_max........................: 1     min=1      max=1


running (00m01.5s), 0/1 VUs, 1 complete and 0 interrupted iterations
default ✓ [======================================] 1 VUs  00m01.5s/10m0s  1/1 iters, 1 per VU
```

[参考](https://k6.io/docs/using-k6/metrics/)

各種属性の平均値・最小値・中央値・最大値・p(90)・p(95)を表示している。
※ パーセンタイル(p(n))はデータを100分割したときのn番目を指す。
パーセンタイルも計測しておくことで、極端な値に影響されず、5%, 10%程度のユーザが影響を受けたときに検知できるようになる。
より具体的には、10%以上のユーザのレスポンスが遅くなると、p(90)は極端に値が大きくなる。

重要な指標を抜き出しておく。

* http_reqs: k6が送ったリクエスト数
* http_req_failed: HTTPリクエストが失敗した数
* http_req_duration: リクエストを送ってレスポンスが返るまでの時間
* vus: 接続ユーザ数

また、送信・接続にかかった時間も表示することで、どこがボトルネックか見つけやすくなっている。

## シナリオ作成

### リクエストを送ってレスポンスを評価するまでの流れは

`http.get`または`http.post`でリクエストを送る。
getメソッドは上の例で見てきたので、ここではpostメソッドを見てみる。

[参考](https://k6.io/docs/javascript-api/k6-http/post/)

> 書式: `post( url, [body], [params] )`

bodyやparamsは辞書形式で指定。paramsには、パスやリクエストを識別するためのタグを指定するケースが多い。

```typescript
// サンプル
import http from 'k6/http';

import {url} from './config';

// ログイン
// ※ url関数はホスト名を付与してURLを組み立てる関数
const loginResponse = http.post(url('/login'), {
    account_name: 'terra',
    password: 'terraterra',
}, {
    tags: {
        name: 'login'
    }
});
```

#### check()

いわゆるassertionのような位置付け。レスポンスを評価するために呼び出す。

[参考](https://k6.io/docs/javascript-api/k6/check/)

> 書式: `check( val, sets, [tags] )`

引数setsはプロパティに検証名・値に検証処理を指定することで妥当性を判定する。

```typescript
// サンプル
import {check} from 'k6';

// レスポンスのステータスコードが200であるか
check(loginResponse, {
    "is status 200": (response) => response.status === 200,
});
```

### CSRFトークンなど、ページに存在するコンテンツを取得するには

POSTリクエストの中には、CSRFトークンが必要なもの・ユーザIDが必要なものなど、前のレスポンスを前提としているものがある。
これをk6にて実現するには、レスポンスを解析しなければならない。

そんなときのために、レスポンスのHTMLを解析し、必要な要素を取り出すためのAPIが用意されている。

```typescript
// import文は省略

// ユーザページへアクセス
const userPageResponse = <RefinedResponse<ResponseType>>http.get(url('/@terra'), {tags: {name: 'user'}});
const userPageResponseBody = parseHTML(<string>userPageResponse.body);
// コメントを投稿するために、対象の投稿・CSRFトークンをレスポンスから取得
const csrfToken = <string>userPageResponseBody.find('input[name="csrf_token"]').first().attr('value');
const postId = <string>userPageResponseBody.find('input[name="post_id"]').first().attr('value');
```

#### parseHTML

[参考](https://k6.io/docs/javascript-api/k6-html/parsehtml/)

> 書式: `parseHTML( src )`

parseHTMLメソッドは、Selectionと呼ばれるオブジェクトを返す。
これは、DOMのノードにアクセスするためのjQuery互換のAPIを提供している。

上の例では、`find('input[name="csrf_token"]').first().attr('value')`のような記述が該当する。

[参考](https://k6.io/docs/javascript-api/k6-html/selection/)