<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { OzAlert, OzButton, OzCheckbox, OzField, OzIcon, OzInput, useOzTheme } from '@/Components/Oz'
// Giriş sayfası OzShell kullanmaz; Oz token'ları (.oz-root değişkenleri) OzShell'in global
// stilinde tanımlı olduğundan modülü yalnızca stili için yüklüyoruz.
import '@/Components/Oz/OzShell.vue'

const props = defineProps({
	canResetPassword: { type: Boolean, default: false },
	status: { type: String, default: '' },
})

const page = usePage()
const { theme } = useOzTheme()

const appName = computed(() => page.props.app?.name || 'OzumServer')
const year = new Date().getFullYear()

const features = [
	{ icon: 'factory', title: 'Atölye & üretim', text: 'İş emirleri, reçeteler ve fason takibi' },
	{ icon: 'package', title: 'Ürün, stok & sipariş', text: 'Tek ürün kartından tüm kanallara' },
	{ icon: 'store', title: 'Pazaryeri & bayi ağı', text: 'Senkron fiyat, stok ve iade akışı' },
	{ icon: 'sparkles', title: 'Creative stüdyo', text: 'Yapay zekâ ile ürün görselleri' },
]

const form = useForm({
	email: '',
	password: '',
	remember: false,
})

function submit() {
	form.post(route('login'), {
		onFinish: () => form.reset('password'),
	})
}
</script>

<template>
	<Head title="Giriş yap" />

	<div class="oz-root login" :data-theme="theme">
		<aside class="brand" aria-hidden="true">
			<div class="brand-logo">
				<span class="brand-mark"><OzIcon name="layers" :size="16" :stroke-width="2.5" /></span>
				<span class="brand-name">{{ appName }}<small>Üretim Platformu</small></span>
			</div>

			<div class="brand-hero">
				<h2>Üretimden satışa<br>tek panel.</h2>
				<p>Atölyeden pazaryerine kadar tüm iş akışınız aynı yerde, gerçek zamanlı.</p>

				<ul class="features">
					<li v-for="f in features" :key="f.title">
						<span class="feature-ico"><OzIcon :name="f.icon" :size="16" /></span>
						<span>
							<strong>{{ f.title }}</strong>
							<small>{{ f.text }}</small>
						</span>
					</li>
				</ul>
			</div>

			<div class="brand-foot">© {{ year }} {{ appName }}</div>
		</aside>

		<main class="panel">
			<div class="form-wrap">
				<div class="mobile-logo">
					<span class="brand-mark"><OzIcon name="layers" :size="16" :stroke-width="2.5" /></span>
					<span class="brand-name">{{ appName }}<small>Üretim Platformu</small></span>
				</div>

				<header class="heading">
					<h1>Tekrar hoş geldiniz</h1>
					<p>Hesabınıza giriş yaparak panelinize erişin.</p>
				</header>

				<OzAlert v-if="props.status" tone="success" class="status">{{ props.status }}</OzAlert>

				<form class="form" novalidate @submit.prevent="submit">
					<OzField label="E-posta adresi" :error="form.errors.email">
						<OzInput
							v-model="form.email"
							type="email"
							icon="mail"
							placeholder="ornek@firma.com"
							autocomplete="email"
							v-autofocus
							required
						/>
					</OzField>

					<OzField label="Parola" :error="form.errors.password">
						<OzInput
							v-model="form.password"
							type="password"
							icon="lock"
							placeholder="Parolanız"
							autocomplete="current-password"
							revealable
							required
						/>
					</OzField>

					<div class="row">
						<OzCheckbox v-model="form.remember" label="Beni hatırla" />
						<Link v-if="canResetPassword" :href="route('password.request')" class="link">
							Parolamı unuttum
						</Link>
					</div>

					<OzButton type="submit" variant="primary" class="submit" :loading="form.processing">
						{{ form.processing ? 'Giriş yapılıyor…' : 'Giriş yap' }}
					</OzButton>
				</form>

				<p class="secure">
					<OzIcon name="shield" :size="13" />
					Bağlantınız şifrelenmiştir.
				</p>
			</div>
		</main>
	</div>
</template>

<style scoped>
/* OzShell global stili html/body kaydırmasını kapatır; sayfa kendi içinde kayar. */
.login {
	position: fixed;
	inset: 0;
	overflow-y: auto;
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	gap: 12px;
	padding: 12px;
}

/* ── Marka paneli ── */
.brand {
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	gap: 32px;
	min-height: calc(100vh - 24px);
	padding: 36px 40px;
	border-radius: calc(var(--oz-radius) + 4px);
	background:
		radial-gradient(90% 70% at 100% 0%, rgba(255, 255, 255, .16) 0%, transparent 60%),
		radial-gradient(70% 60% at 0% 100%, rgba(0, 0, 0, .18) 0%, transparent 70%),
		linear-gradient(145deg, var(--oz-accent) 0%, var(--oz-accent-strong) 100%);
	color: #fff;
	box-shadow: var(--oz-shadow);
}
/* ince ızgara dokusu */
.brand::before {
	content: '';
	position: absolute;
	inset: 0;
	background-image:
		linear-gradient(rgba(255, 255, 255, .06) 1px, transparent 1px),
		linear-gradient(90deg, rgba(255, 255, 255, .06) 1px, transparent 1px);
	background-size: 32px 32px;
	mask-image: radial-gradient(80% 70% at 70% 30%, #000 0%, transparent 100%);
	pointer-events: none;
}
.brand > * { position: relative; }

.brand-logo,
.mobile-logo { display: flex; align-items: center; gap: 10px; }
.brand-mark {
	width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
	color: #fff;
}
.brand-name { font-size: 15px; font-weight: 800; line-height: 1.15; white-space: nowrap; }
.brand-name small {
	display: block; margin-top: 1px;
	font-size: 9.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
}
.brand .brand-mark { background: rgba(255, 255, 255, .18); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .25); }
.brand .brand-name small { color: rgba(255, 255, 255, .7); }

.brand-hero { max-width: 440px; }
.brand-hero h2 {
	margin: 0 0 12px;
	font-size: clamp(28px, 3vw, 38px);
	font-weight: 800;
	line-height: 1.12;
	letter-spacing: -.02em;
}
.brand-hero > p { margin: 0 0 28px; font-size: 14px; line-height: 1.6; color: rgba(255, 255, 255, .78); }

.features { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.features li {
	display: flex; align-items: center; gap: 12px;
	padding: 11px 14px;
	border-radius: var(--oz-radius-sm);
	background: rgba(255, 255, 255, .1);
	box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .14);
	backdrop-filter: blur(6px);
	animation: rise .5s cubic-bezier(.25, .7, .3, 1) both;
}
.features li:nth-child(2) { animation-delay: .06s; }
.features li:nth-child(3) { animation-delay: .12s; }
.features li:nth-child(4) { animation-delay: .18s; }
.feature-ico {
	width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
	background: rgba(255, 255, 255, .16);
}
.features strong { display: block; font-size: 13px; font-weight: 700; }
.features small { display: block; margin-top: 1px; font-size: 12px; color: rgba(255, 255, 255, .7); }

.brand-foot { font-size: 12px; color: rgba(255, 255, 255, .6); }

/* ── Form paneli ── */
.panel {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: calc(100vh - 24px);
	padding: 32px 24px;
}
.form-wrap {
	width: 100%;
	max-width: 380px;
	animation: rise .45s cubic-bezier(.25, .7, .3, 1) both;
}

.mobile-logo { display: none; margin-bottom: 28px; color: var(--oz-ink-soft); }
.mobile-logo .brand-mark {
	background: linear-gradient(135deg, var(--oz-accent), var(--oz-accent-hover));
	box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .35);
}
.mobile-logo .brand-name small { color: var(--oz-muted); }

.heading { margin-bottom: 24px; }
.heading h1 { margin: 0 0 6px; font-size: 24px; font-weight: 800; letter-spacing: -.02em; color: var(--oz-ink); }
.heading p { margin: 0; font-size: 13.5px; color: var(--oz-muted); }

.status { margin-bottom: 18px; }

.form { display: grid; gap: 16px; }
.form :deep(.oz-input) { height: 40px; }
.form :deep(.oz-input input) { font-size: 13.5px; }

.row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.link {
	font-size: 12.5px; font-weight: 600; color: var(--oz-accent); text-decoration: none;
	border-radius: 4px;
}
.link:hover { color: var(--oz-accent-hover); text-decoration: underline; }
.link:focus-visible { outline: 2px solid rgba(var(--oz-accent-rgb), .5); outline-offset: 2px; }

.submit { width: 100%; height: 40px; justify-content: center; font-size: 13.5px; margin-top: 4px; }

.secure {
	display: flex; align-items: center; justify-content: center; gap: 6px;
	margin: 24px 0 0;
	font-size: 12px; color: var(--oz-muted-2);
}

@keyframes rise {
	from { opacity: 0; transform: translateY(12px); }
	to { opacity: 1; transform: none; }
}

/* ── Duyarlı ── */
@media (max-width: 960px) {
	.login { grid-template-columns: 1fr; padding: 0; }
	.brand { display: none; }
	.panel { min-height: 100vh; min-height: 100dvh; align-items: flex-start; padding: 48px 20px 32px; }
	.mobile-logo { display: flex; }
}

@media (prefers-reduced-motion: reduce) {
	.features li, .form-wrap { animation: none; }
}
</style>
