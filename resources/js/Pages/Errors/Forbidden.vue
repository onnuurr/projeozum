<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { OzButton, OzIcon, useOzTheme } from '@/Components/Oz'
// Layout (OzShell) yok; Oz token'ları (.oz-root değişkenleri) OzShell'in global stilinde tanımlı
// olduğundan modülü yalnızca stili için yüklüyoruz (Auth/Login.vue ile aynı).
import '@/Components/Oz/OzShell.vue'

// 403 sayfası — bootstrap/app.php'deki exception respond() burayı render eder.
// Hata sayfası: AppLayout (header + sidebar menü) kullanılmaz, tam ekran tek başına çizilir.
// Birkaç saniye sonra kendiliğinden ana sayfaya (dashboard; oturum yoksa giriş) yönlendirir.
const REDIRECT_SECONDS = 5

const page = usePage()
const { theme } = useOzTheme()
const isAuthed = computed(() => !!page.props.auth?.user)
const homeUrl = computed(() => (isAuthed.value ? route('dashboard') : '/'))

const remaining = ref(REDIRECT_SECONDS)
let timer = null

const goHome = () => {
	clearInterval(timer)
	router.visit(homeUrl.value, { replace: true })
}

const goBack = () => {
	clearInterval(timer)
	window.history.length > 1 ? window.history.back() : goHome()
}

onMounted(() => {
	timer = setInterval(() => {
		remaining.value -= 1
		if (remaining.value <= 0) goHome()
	}, 1000)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
	<Head title="Yetkiniz yok" />

	<div class="oz-root forbidden-page" :data-theme="theme">
		<div class="forbidden">
			<div class="ico"><OzIcon name="lock" :size="26" /></div>
			<div class="code">403</div>
			<h1 class="title">Bu sayfaya erişim yetkiniz yok</h1>
			<p class="desc">
				Görüntülemek istediğiniz sayfa için gerekli izne sahip değilsiniz.
				Erişime ihtiyacınız varsa yöneticinizle iletişime geçin.
			</p>

			<p class="countdown">
				<strong>{{ remaining }}</strong> saniye içinde ana sayfaya yönlendiriliyorsunuz…
			</p>
			<div class="bar"><span :style="{ animationDuration: `${REDIRECT_SECONDS}s` }" /></div>

			<div class="actions">
				<OzButton icon="chevron-left" @click="goBack">Geri dön</OzButton>
				<OzButton variant="primary" icon="home" @click="goHome">Ana sayfaya git</OzButton>
			</div>
		</div>
	</div>
</template>

<style scoped>
.forbidden-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--oz-page-bg); }
.forbidden {
	display: flex; flex-direction: column; align-items: center; text-align: center;
	max-width: 440px; margin: 0 auto; padding: 72px 20px;
}
.ico {
	width: 60px; height: 60px; border-radius: 16px; margin-bottom: 16px;
	display: flex; align-items: center; justify-content: center;
	background: rgba(var(--oz-danger-rgb), .1); color: var(--oz-danger);
	box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 0 0 8px rgba(var(--oz-danger-rgb), .05);
}
.code { font-size: 44px; font-weight: 800; letter-spacing: -.02em; line-height: 1; color: var(--oz-ink); }
.title { margin: 10px 0 0; font-size: 16px; font-weight: 700; color: var(--oz-ink-soft); }
.desc { margin: 6px 0 0; font-size: 12.5px; line-height: 1.55; color: var(--oz-muted); }
.countdown { margin: 22px 0 8px; font-size: 12px; color: var(--oz-muted); }
.countdown strong { color: var(--oz-ink); }
.bar { width: 220px; height: 4px; border-radius: 999px; overflow: hidden; background: var(--oz-surface-2); }
.bar span { display: block; height: 100%; background: var(--oz-accent); transform-origin: left; animation: oz-forbidden-drain linear forwards; }
@keyframes oz-forbidden-drain { from { transform: scaleX(1); } to { transform: scaleX(0); } }
.actions { display: flex; gap: 8px; margin-top: 22px; flex-wrap: wrap; justify-content: center; }
</style>
