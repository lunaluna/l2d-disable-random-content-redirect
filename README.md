# L2D disable random content redirect

WordPress 7.2 で追加された「ランダムな投稿へのリダイレクト」(`/random/` など)を無効化するプラグインです。

- バージョン: 0.1.0
- プラグインのスラッグ: `l2d-disable-random-content-redirect`
- 動作確認: WordPress 7.2-RC3(シングルサイト・サブディレクトリ型とサブドメイン型のマルチサイト)、7.1.5(シングルサイト)。いずれも wp-env

## 何をするか

WordPress 7.2 では、次のような URL にアクセスすると、ランダムに選ばれた投稿へ 302 でリダイレクトされます。この機能は既定で有効です。

- `/random/`
- `/random/category/{ターム}/`、`/random/author/{ユーザー}/` などのアーカイブ
- アーカイブの URL に `?random` を付けたもの(例: `/category/news/?random`)

このプラグインは、コアのフィルター `wp_enable_random_content_redirect` に `false` を返し、これらをまとめて無効化します。有効化すると次のようになります。

| URL | 有効化前 | 有効化後 |
| --- | --- | --- |
| `/random/` | 302(ランダムな投稿へ) | 404 |
| `/random/category/news/` | 302 | 404 |
| `/random/author/admin/` | 302 | 404 |
| `/category/news/?random` | 302 | 200(普通のカテゴリーアーカイブ) |
| `/?s=hello&random` | 301(`/?s=hello` へ) | 200(普通の検索結果) |

## 使い方

有効化するだけです。設定画面はありません。

- 有効化したとき、リライトルールを消します。次のアクセスで、`/random/` のルールが無い状態で作り直されます。
- 無効化したとき、リライトルールを消します。次のアクセスで、`/random/` のルールが元に戻ります。
- WordPress 7.2 未満では何もしません。7.2 に上げると、次のアクセスからそのまま効きます。

### マルチサイト

ネットワークで有効化・無効化したときは、ネットワーク内のすべてのサイトのリライトルールを消します。各サイトのルールは、そのサイトへの次のアクセスで作り直されます。サイト単位の有効化・無効化は、そのサイトだけが対象です。

| 操作 | 結果 |
| --- | --- |
| ネットワークで有効化 | すべてのサイトで無効化される |
| ネットワーク有効化中にサイトを追加 | 追加したサイトも無効化される |
| ネットワークで無効化 | すべてのサイトでコアの挙動に戻る |
| サイト単位で有効化・無効化 | そのサイトだけが切り替わる |

### ルールの自動の作り直し

どの状態でリライトルールを作ったかを、サイトごとにオプション `l2ddrcr_rules_state` に記録しています。記録するのは、このプラグインの設定ではなく、コアの `wp_is_random_content_redirect_enabled()` の最終結果(ほかのプラグインがコアのフィルター `wp_enable_random_content_redirect` で変えた値も含む)です。アクセスがあったとき(`wp_loaded`)に今の状態と食い違っていれば、ルールを作り直します。このため、次の場面でパーマリンク設定を保存する必要はありません。

- 独自フィルター `l2ddrcr_disable_random_redirect` の値を変えたとき
- ほかのプラグインが `wp_enable_random_content_redirect` の値を変えたとき(そのプラグインを入れた・外した・設定を変えた など)
- ネットワークで有効化したあとの各サイト
- WordPress を 7.2 に上げたとき

記録は、プラグインを無効化したときに消します。

## `random` スラッグの既存コンテンツとの関係

`random` スラッグのページなどがあっても、無効化します。無効化しても、それらは自分のリライトルールで表示されるので、表示できなくなることはありません(7.2-RC3 で確認)。

| 既存のコンテンツ | 有効化後の表示 |
| --- | --- |
| トップレベルの固定ページ `/random/` | 固定ページが表示される |
| 投稿 `random`(パーマリンクが `/%postname%/`) | 投稿が表示される |
| カスタム投稿タイプ(スラッグ `random`)のアーカイブ・個別投稿 | そのまま表示される |
| 子ページ(例: `/about/random/`) | 影響なし |

なお、コアは `random` スラッグのトップレベルの固定ページがあると `/random/` のルールだけを省きますが、`/random/category/…` などは有効なまま残します。このプラグインは、それらもまとめて無効化します。

## フィルター

### `l2ddrcr_disable_random_redirect`

無効化するかを返します。既定は `true`(無効化する)です。`false` を返すと、このプラグインは何もせず、コアの挙動に戻ります。

```php
// 例: このサイトでは /random/ を使う.
add_filter( 'l2ddrcr_disable_random_redirect', '__return_false' );
```

値を変えると、次のアクセスでリライトルールが自動で作り直され、切り替わります。パーマリンク設定を保存する必要はありません。マルチサイトでは、サイトごとに別の値を返せます。

**リクエストによって変わる値を返さないでください。** たとえば、ログインしているかどうかや、管理画面かどうかで値を変えると、値が変わるたびにルールを作り直すため、アクセスのたびに作り直しが起きてしまいます。ほかのプラグインが `wp_enable_random_content_redirect` に返す値も同じです。

### ほかのプラグインとの関係

このプラグインは、コアのフィルター `wp_enable_random_content_redirect` に優先度 10 で登録しています。

- ほかのプラグインが**後の優先度**(11 以上)で `true` を返すと、そちらが優先され、ランダムな投稿へのリダイレクトは有効になります。
- このプラグインを無効化しない設定(独自フィルターが `false`)のときは、受け取った値をそのまま返します。先にほかのプラグインが `false` にしていれば、無効のままです。

どちらの場合も、リライトルールは最終結果に合わせて自動で作り直されます。

## 注意

- **`/random/` が 404 にならず、別のページへ 301 でリダイレクトされることがあります。** スラッグが `random` で**始まる**公開済みの投稿・ページ(例: `random-notes`、子ページ `/about/random/`)があると、404 の URL から投稿を推測して転送するコアの機能(`redirect_guess_404_permalink()`)が働くためです。コアは `post_name LIKE 'random%'` で探すので、`my-random-post` のように途中に `random` を含むだけのスラッグは対象外です。候補が複数あるときにどれへ転送されるかは決まっていません。
  - これは 404 の URL 全般に対するコアの挙動で、WordPress 7.2 より前と同じです。このプラグインでは止めません。
  - 止めたいときは `add_filter( 'do_redirect_guess_404_permalink', '__return_false' );` を使います(サイト全体の推測リダイレクトが止まります)。
  - 確認したパーマリンク構造は `/%postname%/` だけです。
- `random` は公開クエリ変数のまま残ります。このため `/category/news/?random` では、リダイレクトはされませんが、`is_random()` は `true` を返します。`is_random()` で表示を分けているテーマやプラグインがあれば、確認してください。
- ほかのプラグインが `random_rewrite_rules` フィルターで `/random/` のルールを足した場合、ルールには一致しますが、リダイレクトはされません。

## 今後の課題

- **サイト数がとても多いネットワークでの、ネットワーク有効化・無効化の処理時間。** ネットワークで有効化・無効化すると、すべてのサイトを順に切り替えてリライトルールと記録を消します(`get_sites()` で全サイトの ID を一度に取得)。サイト数が多いと、PHP の最大実行時間を超えたり、途中で止まって一部のサイトだけ処理された状態になったりする可能性があります。現状は子サイトが 100 を超える使い方を想定していないため対応していません。必要になったら、想定する最大のサイト数で処理時間を測り、サイトを分けて取得・処理する方法などを検討します。

## テスト

wp-env(Docker)を使います。動作確認したのは wp-env 8.4.0 です。

### PHPUnit の統合テスト

`tests/phpunit/` にあります。wp-env の tests-cli コンテナに入っている PHPUnit・WordPress のテスト用ライブラリ・Yoast PHPUnit Polyfills を使うので、このリポジトリで `composer install` をする必要はありません。WordPress 7.2 以降の環境で実行してください(7.2 未満ではスキップされます)。

```sh
# このプラグインを wp-content/plugins/l2d-disable-random-content-redirect にマウントした wp-env の環境で
wp-env run tests-cli --env-cwd=wp-content/plugins/l2d-disable-random-content-redirect -- bash -c 'phpunit -c phpunit.xml.dist'
wp-env run tests-cli --env-cwd=wp-content/plugins/l2d-disable-random-content-redirect -- bash -c 'phpunit -c phpunit-multisite.xml.dist'
```

- `phpunit.xml.dist` はシングルサイト、`phpunit-multisite.xml.dist` はマルチサイト(サブディレクトリ型)です。コアのテスト用ライブラリはサブドメイン型のマルチサイトを作れないため、サブドメイン型は次の HTTP の通しテストで確かめます。
- `phpunit` は `bash -c` を通して実行します(直接 `wp-env run tests-cli phpunit` とすると、PATH に見つからないことがありました)。

### HTTP の通しテスト

`tests/e2e/` にあります。実際に URL にアクセスして、応答(ステータスコード・転送先・表示されたページ)と、保存されたリライトルールの状態を期待値と照らし合わせます。Node.js が必要です。

```sh
# 1. 環境を作る(<型> は single / subdir / subdomain)
tests/e2e/setup.sh <型> <WordPress 7.2 以降の zip> <作業ディレクトリ> <ポート>

# 2. テストを流す(何度流しても同じ結果になります)
tests/e2e/test-single.sh <作業ディレクトリ> <ポート>
tests/e2e/test-multisite.sh subdir <作業ディレクトリ> <ポート>
tests/e2e/test-multisite.sh subdomain <作業ディレクトリ> <ポート>
```

- 環境ごとに、ポートは `<ポート>` と `<ポート>+1`(wp-env のテスト用のサイト)を使います。
- テスト専用の MU プラグイン `tests/e2e/mu-plugins/l2ddrcr-e2e.php` を読み込みます。本番のサイトには入れないでください。
- 終わったら、作業ディレクトリの `env/` で `wp-env destroy` を実行し、作業ディレクトリを消してください。

## 参考

- [Your content is going to get more random](https://make.wordpress.org/core/2026/11/05/your-content-is-going-to-get-more-random/)(Make WordPress Core)

## ライセンス

GPL-2.0-or-later
