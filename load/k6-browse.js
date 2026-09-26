// Yük testi: giriş yapmış bir yöneticinin tipik gezintisi (panel → ürünler → ürün kartı → siparişler).
//
// Çalıştırma (mümkünse sunucu DIŞINDAN — aynı makinede k6 da CPU yer):
//   k6 run -e EMAIL=loadtest@ozum.test -e PASSWORD=... load/k6-browse.js
//
// Profiller (-e PROFILE=...):
//   smoke  → 2 kullanıcı, 1 dk   (script çalışıyor mu? önce bunu çalıştırın)
//   load   → 20 → 50 → 100 kullanıcı, ~8 dk (varsayılan)
//   stress → 100 → 200 kullanıcı, kırılma noktası arama
//
// Diğer: -e BASE=https://... (varsayılan ozumserver.com.tr), -e PRODUCT_ID=3

import http from 'k6/http'
import { check, group, sleep, fail } from 'k6'

const BASE = __ENV.BASE || 'https://ozumserver.com.tr'
const EMAIL = __ENV.EMAIL
const PASSWORD = __ENV.PASSWORD
const PRODUCT_ID = __ENV.PRODUCT_ID || '3'

const PROFILES = {
	smoke: [{ duration: '1m', target: 2 }],
	load: [
		{ duration: '1m', target: 20 },
		{ duration: '2m', target: 50 },
		{ duration: '3m', target: 100 },
		{ duration: '1m', target: 100 },
		{ duration: '1m', target: 0 },
	],
	stress: [
		{ duration: '2m', target: 100 },
		{ duration: '3m', target: 200 },
		{ duration: '1m', target: 0 },
	],
}

export const options = {
	stages: PROFILES[__ENV.PROFILE || 'load'],
	thresholds: {
		http_req_failed: ['rate<0.01'],        // hata oranı < %1
		http_req_duration: ['p(95)<1500'],     // isteklerin %95'i < 1,5 sn
		checks: ['rate>0.99'],
	},
}

if (!EMAIL || !PASSWORD) {
	fail('EMAIL ve PASSWORD verin: k6 run -e EMAIL=... -e PASSWORD=... load/k6-browse.js')
}

// Her sanal kullanıcı (VU) kendi çerez kavanozuyla bir kez giriş yapar; oturum sonraki
// iterasyonlarda korunur. Başarılı giriş LoginRequest'in hız sınırına takılmaz (yalnız hatalılar sayılır).
function login() {
	const page = http.get(`${BASE}/login`, { tags: { name: 'login-page' } })
	const token = page.html().find('meta[name="csrf-token"]').attr('content')
	if (!token) fail('csrf-token bulunamadı — /login sayfası beklenen HTML değil')

	const res = http.post(`${BASE}/login`, { _token: token, email: EMAIL, password: PASSWORD }, {
		tags: { name: 'login' },
		redirects: 0,
	})
	// Başarılı giriş 302 (veya Inertia::location ile 409) döner; 422/200 = yanlış bilgi / hız sınırı.
	const ok = check(res, { 'giriş başarılı': (r) => r.status === 302 || r.status === 409 })
	if (!ok) fail(`Giriş başarısız (HTTP ${res.status}) — e-posta/şifre veya kullanıcı aktifliğini kontrol edin`)
}

function visit(name, path) {
	const res = http.get(`${BASE}${path}`, { tags: { name }, redirects: 0 })
	check(res, {
		[`${name} 200`]: (r) => r.status === 200,
		// 302 → /login = oturum düştü (session sürücüsü yük altında kaybediyor olabilir)
		[`${name} oturum açık`]: (r) => !(r.status === 302 && String(r.headers.Location).includes('/login')),
	})
	sleep(Math.random() * 3 + 2) // gerçek kullanıcı gibi 2–5 sn düşünme süresi
}

export default function () {
	if (__ITER === 0) login()

	group('gezinti', () => {
		visit('dashboard', '/dashboard')
		visit('products', '/products')
		visit('product-card', `/products/${PRODUCT_ID}/card`)
		visit('orders', '/orders')
	})
}
