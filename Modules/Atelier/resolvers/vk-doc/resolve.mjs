// vk.com/vk.ru belge görüntüleyicisi (Mail.ru Cloud tabanlı) JS ile "Dosyayı indir"
// düğmesine tıklanınca imzalı, düz HTTP ile indirilebilir bir CDN linki üretir —
// sayfanın ilk HTML'inde bu link yok, yalnızca tıklama sonrası oluşuyor. Bu betik
// gerçek bir headless tarayıcıyla o tıklamayı yapıp indirilen dosyayı kaydeder.
//
// Kullanım: node resolve.mjs <vkDocUrl> <outputFilePath>
// stdout: tek satır JSON — { ok: true, path, filename, bytes } | { ok: false, error }
//
// Çağıran: Modules/Atelier/Services/PatternUrlImportService.php (bkz. resolveViaHeadlessBrowser)

import { chromium } from 'playwright';

const [, , vkDocUrl, outputPath] = process.argv;

// Her koşulda tek satır JSON basıp exit code 0 ile çıkar — başarı/başarısızlık
// PHP tarafında yalnızca "ok" alanına bakılarak ayırt edilir (exit code'a güvenilmez,
// çünkü finally'de browser.close() bekleniyor).
function emit(result) {
	process.stdout.write(JSON.stringify(result) + '\n');
}

if (!vkDocUrl || !outputPath) {
	emit({ ok: false, error: 'usage: node resolve.mjs <vkDocUrl> <outputFilePath>' });
	process.exit(0);
}
if (!/^https:\/\/(vk\.com|vk\.ru)\//i.test(vkDocUrl)) {
	emit({ ok: false, error: 'yalnızca vk.com/vk.ru belge linkleri desteklenir' });
	process.exit(0);
}

let browser;
let result;
try {
	browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
	const page = await browser.newPage();

	await page.goto(vkDocUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });

	const clickTargets = ['text=Dosyayı indir', 'text=Скачать', 'text=Download'];
	let download = null;

	for (const selector of clickTargets) {
		try {
			const [d] = await Promise.all([
				page.waitForEvent('download', { timeout: 6000 }),
				page.click(selector, { timeout: 4000 }),
			]);
			download = d;
			break;
		} catch {
			// bu selector bulunamadı/tıklanamadı — sıradakini dene
		}
	}

	if (!download) {
		result = { ok: false, error: 'indirme düğmesi bulunamadı/tıklanamadı (sayfa yapısı değişmiş olabilir)' };
	} else {
		await download.saveAs(outputPath);
		const { statSync } = await import('node:fs');
		result = {
			ok: true,
			path: outputPath,
			filename: download.suggestedFilename(),
			bytes: statSync(outputPath).size,
		};
	}
} catch (e) {
	result = { ok: false, error: e?.message || String(e) };
} finally {
	if (browser) await browser.close();
}

emit(result);
