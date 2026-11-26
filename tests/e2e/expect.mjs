// URL の応答が期待どおりかを調べて PASS / FAIL を出す.
//
// 使い方: node expect.mjs <ラベル> <host:port または port> "<パス>::<期待>" ...
//   期待の書き方:
//     404            ステータスだけ見る
//     302            ステータスだけ見る(転送先は問わない. ランダムなので)
//     301>/foo/      ステータスと, Location に /foo/ を含むこと
//     200#page-id-6  ステータスと, body の class に page-id-6 を含むこと
// 失敗が 1 つでもあれば終了コード 1.
//
// 127.0.0.1 に接続して Host ヘッダーでサイトを指定する. サブドメイン型のマルチサイト
// (site2.localhost:8904 など)でも, ホスト側で *.localhost の名前解決ができるかに頼らずに済む.
// fetch は Host ヘッダーを変えられないので node:http を使う. リダイレクトは追わない.
import http from 'node:http';

const [label, target, ...cases] = process.argv.slice(2);
const [hostName, port] = target.includes(':') ? target.split(':') : ['localhost', target];
const origin = `http://${hostName}:${port}`;

function get(path) {
	return new Promise((resolve, reject) => {
		const req = http.request({ host: '127.0.0.1', port, path, headers: { Host: `${hostName}:${port}` } }, (res) => {
			let body = '';
			res.setEncoding('utf8');
			res.on('data', (d) => (body += d));
			res.on('end', () => resolve({ status: res.statusCode, loc: res.headers.location || '', body }));
		});
		req.on('error', reject);
		req.end();
	});
}

let fail = 0;
for (const c of cases) {
	// パスと期待は :: で区切る. = だとクエリ文字列の = と区別できないため.
	const i = c.indexOf('::');
	const path = c.slice(0, i);
	const exp = c.slice(i + 2);
	const m = exp.match(/^(\d{3})(?:([>#])(.+))?$/);
	if (i < 0 || !m) {
		console.log(`FAIL ${label} 期待の書き方が不正: ${c}`);
		fail++;
		continue;
	}
	const r = await get(path);
	const cls = r.status === 200 ? (r.body.match(/<body[^>]*class="([^"]*)"/) || [, ''])[1] : '';
	let ok = String(r.status) === m[1];
	if (ok && m[2] === '>') ok = r.loc.includes(m[3]);
	if (ok && m[2] === '#') ok = cls.split(' ').includes(m[3]);
	if (!ok) fail++;
	const got = `${r.status}${r.loc ? ' ' + r.loc.replace(origin, '') : ''}${cls ? ' [' + cls.split(' ').slice(0, 4).join(' ') + ']' : ''}`;
	console.log(`${ok ? 'PASS' : 'FAIL'} ${label.padEnd(12)} ${(hostName === 'localhost' ? '' : hostName) + path}`.padEnd(60) + ` 期待 ${exp.padEnd(18)} 結果 ${got}`);
}
process.exit(fail ? 1 : 0);
