# L2D disable random content redirect

WordPress 7.2 で追加された「ランダムな投稿へのリダイレクト」(`/random/` など)を無効化するプラグインです。

- バージョン: 0.1.0
- プラグインのスラッグ: `l2d-disable-random-content-redirect`
- 動作確認: WordPress 7.2-RC3、7.1.5(wp-env)

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

- 有効化したとき、リライトルールを作り直して `/random/` のルールを消します。
- 無効化したとき、リライトルールを作り直して `/random/` のルールを元に戻します。
- WordPress 7.2 未満では何もしません。7.2 に上げると、そのまま効きます。

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

**値を変えたら、管理画面の「設定 > パーマリンク」で「変更を保存」を押してください。** `/random/` のルールはデータベース(`rewrite_rules` オプション)に保存されているので、作り直すまで切り替わりません。WP-CLI なら `wp rewrite flush` でも作り直せます。

## 注意

- **`/random/` が 404 にならず、別のページへ 301 でリダイレクトされることがあります。** スラッグが `random` の投稿・ページ(子ページなど)があると、404 の URL から投稿を推測して転送するコアの機能(`redirect_guess_404_permalink()`)が働くためです。このプラグインの挙動ではなく、404 の URL 全般に対するコアの挙動です。止めたいときは `add_filter( 'do_redirect_guess_404_permalink', '__return_false' );` を使います(サイト全体の推測リダイレクトが止まります)。
- `random` は公開クエリ変数のまま残ります。このため `/category/news/?random` では、リダイレクトはされませんが、`is_random()` は `true` を返します。`is_random()` で表示を分けているテーマやプラグインがあれば、確認してください。
- ほかのプラグインが `random_rewrite_rules` フィルターで `/random/` のルールを足した場合、ルールには一致しますが、リダイレクトはされません。
- マルチサイトで「ネットワークで有効化」したときの動作は確認していません。リライトルールは、操作したサイトのものしか作り直されない見込みです。ほかのサイトでは、パーマリンク設定を保存して作り直してください。

## 参考

- [Your content is going to get more random](https://make.wordpress.org/core/2026/11/05/your-content-is-going-to-get-more-random/)(Make WordPress Core)

## ライセンス

GPL-2.0-or-later
