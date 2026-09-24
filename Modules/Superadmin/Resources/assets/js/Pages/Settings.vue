<script setup>
import { ref, computed, watch, onBeforeUnmount, inject } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { useEventListener } from '@vueuse/core'
import { motion, AnimatePresence } from 'motion-v'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzIcon, OzInput, OzButton, useOzConfirm } from '@/Components/Oz'
import { useCan } from '@/composables/useCan'
import SettingsGeneral from './SettingsGeneral.vue'
import SettingsSecurity from './SettingsSecurity.vue'
import SettingsRoles from './SettingsRoles.vue'
import SettingsMail from './SettingsMail.vue'
import SettingsNotifications from './SettingsNotifications.vue'
import SettingsBilling from './SettingsBilling.vue'
import SettingsStorage from './SettingsStorage.vue'
import SettingsApi from './SettingsApi.vue'
import SettingsPerformance from './SettingsPerformance.vue'
import SettingsBarcode from './SettingsBarcode.vue'
import SettingsSystem from './SettingsSystem.vue'

// Süper admin · sistem ayarları (Oz kit). Sunucu sözleşmesi değişmedi:
//   props.settings.{general…barcode} → tek useForm; Kaydet yalnızca değişen grupları POST /superadmin/settings'e yollar.
//   Hassas alanlar '••••••••' maskesiyle gelir; alana odaklanınca maske temizlenir, boş bırakılırsa sunucu mevcut değeri korur.
//   Roller & izinler kendi uç noktalarına anında yazar (Kaydet'ten bağımsız); sistem bilgisi yalnızca o bölüm açıkken yoklanır.
defineOptions({ layout: AppLayout })

const props = defineProps({
	settings: { type: Object, required: true },
	options: { type: Object, required: true },
})

const { can } = useCan()
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const sections = [
	{ key: 'general', label: 'Genel', icon: 'settings', desc: 'Sistem adı, dil, zaman dilimi, bakım modu', keywords: 'url logo tarih para kayıt' },
	{ key: 'security', label: 'Güvenlik', icon: 'shield', desc: 'Parola politikası, 2FA, oturum, log şifresi', keywords: 'sifre ip kilit' },
	{ key: 'roles', label: 'Roller & izinler', icon: 'users', desc: 'Roller, izin matrisi ve izinler', keywords: 'rol yetki permission' },
	{ key: 'mail', label: 'E-posta', icon: 'mail', desc: 'SMTP ve gönderici bilgileri', keywords: 'smtp mail' },
	{ key: 'notifications', label: 'Bildirimler', icon: 'bell', desc: 'E-posta olayları, Slack, push', keywords: 'slack push' },
	{ key: 'billing', label: 'Ödeme & faturalama', icon: 'credit-card', desc: 'iyzico, Stripe, KDV, fatura', keywords: 'iyzico stripe kdv fatura' },
	{ key: 'storage', label: 'Depolama & yedek', icon: 'database', desc: 'Dosya depolama, CDN, yedekleme', keywords: 's3 cdn backup yedek' },
	{ key: 'api', label: 'API & geliştirici', icon: 'code', desc: 'Rate limit, webhook, CORS, Cloudflare', keywords: 'webhook cors cloudflare swagger' },
	{ key: 'performance', label: 'Performans', icon: 'rocket', desc: 'Cache, kuyruk, log, önbellek temizleme', keywords: 'cache queue redis log' },
	{ key: 'barcode', label: 'Barkod (GS1)', icon: 'barcode', desc: 'EAN-13 önek ve seri aralığı', keywords: 'ean gs1' },
	{ key: 'system', label: 'Sistem bilgisi', icon: 'info', desc: 'Sürümler, canlı metrikler, servisler', keywords: 'cpu ram disk reverb' },
]

/* ── etkin bölüm: adres satırında ?section= (Inertia ziyareti yapmadan) ── */
const initial = new URLSearchParams(window.location.search).get('section')
const active = ref(sections.some((s) => s.key === initial) ? initial : 'general')
watch(active, (key) => {
	const url = new URL(window.location.href)
	url.searchParams.set('section', key)
	window.history.replaceState(window.history.state, '', url)
})
const activeSection = computed(() => sections.find((s) => s.key === active.value))

const query = ref('')
const fold = (s) => s.toLocaleLowerCase('tr')
const visibleSections = computed(() => {
	const q = fold(query.value.trim())
	return q ? sections.filter((s) => fold(`${s.label} ${s.desc} ${s.keywords}`).includes(q)) : sections
})

/* ── form ── */
const FORM_GROUPS = ['general', 'security', 'mail', 'notifications', 'billing', 'storage', 'api', 'performance', 'barcode']
const form = useForm({
	...Object.fromEntries(FORM_GROUPS.map((g) => [g, { ...props.settings[g] }])),
	security: { ...props.settings.security, logAccessPassword: '' },
})

// Karşılaştırmada tür farkı sayılmaz: .env'den gelen "587" ile formdaki 587 aynı değerdir
const same = (a, b) => (a ?? '') === (b ?? '') || (typeof a !== 'boolean' && typeof b !== 'boolean' && String(a ?? '') === String(b ?? ''))
const dirtyKeys = computed(() => FORM_GROUPS.filter((g) => {
	const current = form[g]
	const original = props.settings[g] ?? {}
	if (g === 'security' && current.logAccessPassword) return true
	return Object.keys(original).some((f) => !same(current[f], original[f]))
}))
const dirty = computed(() => dirtyKeys.value.length > 0)
const labelOf = (key) => sections.find((s) => s.key === key)?.label ?? key

const MASK = '••••••••'
function clearMask(group, key) {
	if (form[group]?.[key] === MASK) form[group][key] = ''
}

// Sunucu hataları "grup.alan" anahtarlı gelir → ilgili bölüme geç
const errors = computed(() => form.errors)
function save() {
	const groups = dirtyKeys.value
	if (!groups.length) return
	form
		.transform((data) => Object.fromEntries(groups.filter((g) => g in data).map((g) => [g, data[g]])))
		.post('/superadmin/settings', {
			preserveScroll: true,
			preserveState: true,
			onSuccess: (page) => {
				// Kaydedilen grupları sunucunun döndürdüğü güncel değerlerle eşitle: hassas alanlar yeniden
				// maskeli gelir, e-posta .env'den okunur. Aksi halde kayıttan sonra da "değişiklik var" görünür.
				const fresh = page.props.settings ?? props.settings
				for (const g of groups) {
					if (fresh[g]) form[g] = { ...fresh[g], ...(g === 'security' ? { logAccessPassword: '' } : {}) }
				}
				form.defaults()
				showToast?.({ type: 'success', title: 'Ayarlar kaydedildi', message: groups.map(labelOf).join(', ') })
			},
			onError: (errs) => {
				const firstKey = Object.keys(errs)[0] ?? ''
				const group = firstKey.split('.')[0]
				if (sections.some((s) => s.key === group)) active.value = group
				showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Lütfen alanları kontrol edin.' })
			},
		})
}
function discard() {
	form.reset()
	form.clearErrors()
}

/* ── yedekleme / önbellek ── */
const backupBusy = ref(false)
function runBackup() {
	if (backupBusy.value) return
	backupBusy.value = true
	router.post('/superadmin/backups/run', {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'info', title: 'Yedekleme kuyruğa alındı', message: 'Kuyruk işçisi çalıştığında başlar; ilerlemeyi Yedeklemeler sayfasından izleyebilirsiniz.' }),
		onError: () => showToast?.({ type: 'error', title: 'Yedekleme tetiklenemedi', message: 'Bir hata oluştu, tekrar deneyin.' }),
		onFinish: () => { backupBusy.value = false },
	})
}
const cacheBusy = ref(false)
function cacheAction(type) {
	if (cacheBusy.value) return
	cacheBusy.value = type
	router.post('/superadmin/settings/cache/purge', { type }, {
		preserveScroll: true,
		preserveState: true,
		onFinish: () => { cacheBusy.value = false },
	})
}

/* ── sistem bilgisi: yalnızca bölüm açıkken 5 sn'de bir ── */
const liveSystem = ref({ ...props.settings.system })
const systemFetchError = ref(false)
let systemTimer = null
async function fetchSystemInfo() {
	try {
		const { data } = await window.axios.get('/superadmin/system-info')
		if (data?.system) {
			liveSystem.value = data.system
			systemFetchError.value = false
		}
	} catch (e) {
		systemFetchError.value = true
	}
}
function stopPolling() {
	if (systemTimer) clearInterval(systemTimer)
	systemTimer = null
}
watch(active, (s) => {
	if (s === 'system' && !systemTimer) {
		fetchSystemInfo()
		systemTimer = setInterval(fetchSystemInfo, 5000)
	} else if (s !== 'system') stopPolling()
}, { immediate: true })
onBeforeUnmount(stopPolling)

/* ── bölüm bileşenlerine verilecek prop'lar ── */
const sectionProps = computed(() => {
	const base = { errors: errors.value }
	switch (active.value) {
		case 'general': return { ...base, form: form.general, options: props.options }
		case 'security': return { ...base, form: form.security, logAccessPasswordSet: !!props.settings.security?.logAccessPasswordSet }
		case 'roles': return { roles: props.settings.roles?.list ?? [], modules: props.settings.roles?.modules ?? [], canManage: can('rbac.manage') }
		case 'mail': return { ...base, form: form.mail, options: props.options }
		case 'notifications': return { ...base, form: form.notifications }
		case 'billing': return { ...base, form: form.billing, options: props.options }
		case 'storage': return { ...base, form: form.storage, options: props.options, backupBusy: backupBusy.value, canManageBackups: can('backups.manage') }
		case 'api': return { ...base, form: form.api }
		case 'performance': return { ...base, form: form.performance, options: props.options, cacheBusy: cacheBusy.value }
		case 'barcode': return { ...base, form: form.barcode }
		case 'system': return { system: liveSystem.value, fetchError: systemFetchError.value }
		default: return base
	}
})
const components = {
	general: SettingsGeneral, security: SettingsSecurity, roles: SettingsRoles, mail: SettingsMail,
	notifications: SettingsNotifications, billing: SettingsBilling, storage: SettingsStorage, api: SettingsApi,
	performance: SettingsPerformance, barcode: SettingsBarcode, system: SettingsSystem,
}

/* ── kaydetmeden çıkma uyarısı (Inertia GET gezinmeleri + sekme kapatma) ── */
let leaveConfirmed = false
const offBefore = router.on('before', (event) => {
	const visit = event.detail.visit
	if (!dirty.value || leaveConfirmed || visit.method !== 'get' || visit.url.pathname === window.location.pathname) return
	confirm.warning('Kaydedilmemiş değişiklikler var', `${dirtyKeys.value.map(labelOf).join(', ')} bölümlerindeki değişiklikler kaybolacak.`, {
		confirmText: 'Kaydetmeden çık',
		cancelText: 'Sayfada kal',
	}).then((ok) => {
		if (!ok) return
		leaveConfirmed = true
		router.visit(visit.url.href, { method: visit.method, preserveScroll: visit.preserveScroll, preserveState: visit.preserveState })
	})
	return false
})
onBeforeUnmount(offBefore)
useEventListener(window, 'beforeunload', (e) => { if (dirty.value) e.preventDefault() })
</script>

<template>
	<Head title="Sistem Ayarları · Süper Admin" />

	<OzPageHeader title="Sistem Ayarları" subtitle="Tüm platformu etkileyen genel ayarlar · değişiklikler bütün iş ortaklarına yansır" />
	<OzModuleTabs />

	<div class="settings">
		<aside class="side">
			<OzInput v-model="query" icon="search" type="search" placeholder="Ayar ara…" aria-label="Ayar ara" class="side-search" />
			<nav class="nav" aria-label="Ayar bölümleri">
				<button
					v-for="s in visibleSections"
					:key="s.key"
					type="button"
					class="nav-item"
					:class="{ active: s.key === active }"
					:aria-current="s.key === active ? 'page' : undefined"
					@click="active = s.key"
				>
					<OzIcon :name="s.icon" :size="15" />
					<span class="nav-label">{{ s.label }}</span>
					<span v-if="dirtyKeys.includes(s.key)" class="nav-dirty" title="Kaydedilmemiş değişiklik" />
				</button>
				<p v-if="!visibleSections.length" class="nav-empty">“{{ query }}” ile eşleşen ayar yok</p>
			</nav>
		</aside>

		<OzCard class="panel">
			<header class="panel-head">
				<span class="panel-ico"><OzIcon :name="activeSection.icon" :size="17" /></span>
				<div>
					<h2>{{ activeSection.label }}</h2>
					<p>{{ activeSection.desc }}</p>
				</div>
			</header>
			<AnimatePresence mode="wait" :initial="false">
				<motion.div
					:key="active"
					class="panel-body"
					:initial="{ opacity: 0, y: 6 }"
					:animate="{ opacity: 1, y: 0 }"
					:exit="{ opacity: 0, y: -4 }"
					:transition="{ duration: 0.16 }"
				>
					<component
						:is="components[active]"
						v-bind="sectionProps"
						@focus-sensitive="(key) => clearMask(active, key)"
						@run-backup="runBackup"
						@view-history="router.visit('/superadmin/backups')"
						@cache-action="cacheAction"
					/>
				</motion.div>
			</AnimatePresence>
		</OzCard>
	</div>

	<AnimatePresence>
		<motion.div
			v-if="dirty"
			class="savebar"
			role="region"
			aria-label="Kaydedilmemiş değişiklikler"
			:initial="{ y: 24, opacity: 0 }"
			:animate="{ y: 0, opacity: 1 }"
			:exit="{ y: 24, opacity: 0, transition: { duration: 0.18 } }"
			:transition="{ type: 'spring', stiffness: 400, damping: 32 }"
		>
			<span class="savebar-dot" />
			<span class="savebar-text">
				<b>Kaydedilmemiş değişiklikler</b>
				<span>{{ dirtyKeys.map(labelOf).join(', ') }}</span>
			</span>
			<OzButton icon="undo" :disabled="form.processing" @click="discard">Geri al</OzButton>
			<OzButton variant="primary" icon="check" :loading="form.processing" @click="save">Kaydet</OzButton>
		</motion.div>
	</AnimatePresence>
</template>

<style scoped>
.settings { display: grid; grid-template-columns: 236px minmax(0, 1fr); gap: 14px; align-items: start; padding-bottom: 90px; }

.side { position: sticky; top: 0; display: flex; flex-direction: column; gap: 10px; }
.nav {
	display: flex; flex-direction: column; gap: 2px; padding: 6px;
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius); box-shadow: var(--oz-shadow-sm);
}
.nav-item {
	display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; border-radius: 9px;
	border: none; background: none; cursor: pointer; text-align: left;
	font: inherit; font-size: 12.5px; font-weight: 600; color: var(--oz-muted);
	transition: background .12s, color .12s;
}
.nav-item:hover { background: var(--oz-surface-2); color: var(--oz-ink); }
.nav-item.active { background: var(--oz-accent-soft); color: var(--oz-accent); }
.nav-item:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.nav-label { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-dirty { width: 7px; height: 7px; border-radius: 50%; background: var(--oz-warning); flex-shrink: 0; box-shadow: 0 0 0 3px rgba(var(--oz-warning-rgb), .18); }
.nav-empty { margin: 0; padding: 10px; font-size: 12px; color: var(--oz-muted); }

.panel-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px 14px; border-bottom: 1px solid var(--oz-border-soft); }
.panel-ico { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-accent-soft); color: var(--oz-accent); }
.panel-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); }
.panel-head p { margin: 2px 0 0; font-size: 12px; color: var(--oz-muted); }
.panel-body { padding: 20px; }

/* bölüm bileşenlerinin ortak düzen sınıfları (saas ayarlar sayfasıyla aynı) */
.panel-body :deep(.stack) { display: flex; flex-direction: column; gap: 22px; }
.panel-body :deep(.block) { display: flex; flex-direction: column; gap: 12px; }
.panel-body :deep(.block h4) { margin: 0; font-size: 12px; font-weight: 700; color: var(--oz-ink-soft); text-transform: uppercase; letter-spacing: .04em; }
.panel-body :deep(.form-grid) { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.panel-body :deep(.form-grid.three) { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.panel-body :deep(.form-grid.four) { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.panel-body :deep(.span-2) { grid-column: span 2; }
.panel-body :deep(.span-all) { grid-column: 1 / -1; }
.panel-body :deep(.rows) { display: flex; flex-direction: column; gap: 10px; }
.panel-body :deep(.check-grid) { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.panel-body :deep(.lead) { margin: 0; font-size: 12.5px; color: var(--oz-muted); line-height: 1.5; }
.panel-body :deep(.mono input), .panel-body :deep(.mono textarea) { font-family: ui-monospace, monospace; }

.savebar {
	position: sticky; bottom: 0; z-index: 30;
	display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
	margin-top: -76px; padding: 12px 14px 12px 16px;
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius);
	box-shadow: 0 -4px 24px -6px rgba(15, 23, 42, .18), var(--oz-shadow-sm);
}
.savebar-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--oz-warning); flex-shrink: 0; box-shadow: 0 0 0 4px rgba(var(--oz-warning-rgb), .18); }
.savebar-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.savebar-text b { font-size: 13px; color: var(--oz-ink-soft); }
.savebar-text span { font-size: 11.5px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 1180px) { .panel-body :deep(.form-grid.four) { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 900px) {
	.settings { grid-template-columns: minmax(0, 1fr); }
	.side { position: static; }
	.side-search { display: none; }
	.nav { flex-direction: row; overflow-x: auto; scrollbar-width: none; padding: 5px; }
	.nav::-webkit-scrollbar { display: none; }
	.nav-item { width: auto; flex-shrink: 0; padding: 8px 12px; }
	.nav-label { overflow: visible; }
}
@media (max-width: 640px) {
	.panel-head { padding: 14px 16px 12px; }
	.panel-body { padding: 16px; }
	.panel-body :deep(.form-grid), .panel-body :deep(.form-grid.three), .panel-body :deep(.form-grid.four), .panel-body :deep(.check-grid) { grid-template-columns: minmax(0, 1fr); }
	.panel-body :deep(.span-2) { grid-column: auto; }
	.savebar-text span { display: none; }
}
</style>
