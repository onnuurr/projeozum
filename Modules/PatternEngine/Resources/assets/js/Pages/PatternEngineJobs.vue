<script setup>
import { computed, onMounted, onUnmounted, ref, watch, inject } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzPill, OzTable, OzAlert, OzAccordion,
	OzField, OzInput, OzSelect, OzCombobox, OzFileDrop,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const showToast = inject('showToast', null)

const props = defineProps({
	jobs: { type: Array, default: () => [] },
	engineOnline: { type: Boolean, default: false },
	profiles: { type: Array, default: () => [] },
})

/* ── özet (son 50 iş) ── */
const countBy = (...s) => props.jobs.filter((j) => s.includes(j.status)).length
const stats = computed(() => [
	{ key: 'done', label: 'Hazır Kalıp', value: countBy('done'), icon: 'success', tone: 'success' },
	{ key: 'pending', label: 'Sırada / İşleniyor', value: countBy('queued', 'processing'), icon: 'clock', tone: 'info' },
	{ key: 'failed', label: 'Başarısız', value: countBy('failed'), icon: 'alert', tone: countBy('failed') ? 'danger' : 'success' },
	{ key: 'profiles', label: 'Ölçü Profili', value: props.profiles.length, icon: 'layers', tone: 'accent' },
])

/* ── üretim formu ── */
const form = useForm({
	garment: 'tshirt',
	size: '',
	grade_sizes: [],
	profile: props.profiles[0]?.name ?? 'ts_default',
	design_params: {
		neck_depth_front: '',
		sleeve_length: '',
		body_length: '',
		hem_flare: '',
		fit: 'regular',
	},
})

// Beden/giysi seti profile göre değişir (§5.2, Sprint 4) — statik liste yok.
const currentProfile = computed(() => props.profiles.find((p) => p.name === form.profile) ?? null)
const availableSizes = computed(() => currentProfile.value?.sizes ?? [])
const profileOptions = computed(() => props.profiles.map((p) => ({
	value: p.name,
	label: p.name + (p.verifiedByModelist ? '' : ' (yer tutucu)'),
	description: p.garment,
})))
const sizeOptions = computed(() => availableSizes.value.map((s) => ({ value: s, label: s })))
const otherSizeOptions = computed(() => availableSizes.value.filter((s) => s !== form.size).map((s) => ({ value: s, label: s })))
const fitOptions = [
	{ value: 'slim', label: 'Slim — dar' },
	{ value: 'regular', label: 'Regular — standart' },
	{ value: 'loose', label: 'Loose — bol' },
]

// Profil değişince o profilin bedenlerine düş; artık uygun olmayan gradasyon seçimlerini temizle.
watch(() => form.profile, (name) => {
	const profile = props.profiles.find((p) => p.name === name)
	if (!profile) return
	form.garment = profile.garment
	if (!profile.sizes.includes(form.size)) form.size = profile.sizes[0] ?? ''
	form.grade_sizes = form.grade_sizes.filter((s) => profile.sizes.includes(s))
}, { immediate: true })

// Taban beden değişince taban bedeni "ek beden" listesinden düşür. AI güven rozetleri eski bedene göre
// hesaplanmıştı — beden değişince yanıltıcı olur, temizlenir (alan değerleri kalır).
watch(() => form.size, (newSize) => {
	form.grade_sizes = form.grade_sizes.filter((s) => s !== newSize)
	digitizeConfidence.value = {}
})

function submit() {
	if (form.processing) return
	form.post(route('patternengine.generate'), {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'Üretim başlatılamadı', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}

/* ── teknik çizimden öneri (§4.1, Sprint 5) — AI omurga değil sağlayıcıdır (§P2):
   asla otomatik üretmez, yalnızca aşağıdaki alanları önceden doldurur. ── */
const digitizeFiles = ref([])
const digitizing = ref(false)
const digitizeError = ref('')
const digitizeWarnings = ref([])
const digitizeConfidence = ref({})

// Yeni dosya henüz analiz edilmedi — eski öneriye ait rozet/uyarılar yanıltıcı olur.
watch(digitizeFiles, () => {
	digitizeError.value = ''
	digitizeConfidence.value = {}
	digitizeWarnings.value = []
})

async function requestDigitizeSuggestion() {
	const file = digitizeFiles.value[0]
	if (!file || digitizing.value) return
	digitizing.value = true
	digitizeError.value = ''
	digitizeWarnings.value = []

	const payload = new FormData()
	payload.append('image', file)
	payload.append('garment', form.garment)
	payload.append('size', form.size)
	payload.append('profile', form.profile)

	try {
		const { data } = await window.axios.post(route('patternengine.digitize'), payload)
		const dp = data.design_params ?? {}
		if (dp.neck_depth_front != null) form.design_params.neck_depth_front = dp.neck_depth_front
		if (dp.body_length != null) form.design_params.body_length = dp.body_length
		if (dp.hem_flare != null) form.design_params.hem_flare = dp.hem_flare
		digitizeConfidence.value = dp.confidence ?? {}
		digitizeWarnings.value = data.warnings ?? []
	} catch (e) {
		digitizeError.value = e.response?.data?.message || 'Görsel işlenemedi — teknik çizim net bir siluet olmalı.'
	} finally {
		digitizing.value = false
	}
}

function confidence(field) {
	const value = digitizeConfidence.value[field]
	if (value == null) return null
	return { tone: value >= 0.6 ? 'success' : 'warning', label: `AI önerisi · güven ${value.toFixed(1)}` }
}

/* ── son işler ── */
const STATUS = { queued: 'Sırada', processing: 'İşleniyor', done: 'Hazır', failed: 'Başarısız' }
const STATUS_TONE = { queued: 'neutral', processing: 'warning', done: 'success', failed: 'danger' }

function validationSummary(job) {
	const report = job.validation_report
	if (!report) return null
	if (report.findings_by_size) {
		const sizes = Object.keys(report.all_passed_by_size || {})
		const passed = sizes.filter((s) => report.all_passed_by_size[s]).length
		const allPassed = passed === sizes.length && sizes.length > 0
		return { allPassed, label: `${passed}/${sizes.length} beden geçti` }
	}
	if (Array.isArray(report.findings)) {
		const failed = report.findings.filter((f) => !f.passed).length
		const total = report.findings.length
		return { allPassed: report.all_passed, label: report.all_passed ? `${total}/${total} kural geçti` : `${failed} kural başarısız` }
	}
	return null
}

const columns = [
	{ key: 'id', label: '#', sortable: true, width: '64px' },
	{ key: 'garment', label: 'Giysi', sortable: true },
	{ key: 'profile', label: 'Profil', sortable: true },
	{ key: 'size', label: 'Beden', searchable: false },
	{ key: 'status', label: 'Durum', sortable: true, filter: { type: 'select', options: Object.entries(STATUS).map(([value, label]) => ({ value, label })) } },
	{ key: 'validation', label: 'Doğrulama', searchable: false },
	{ key: 'created_at', label: 'Tarih', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '64px', searchable: false },
]
const formatDate = (v) => (v ? new Date(v).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—')

const hasPending = computed(() => props.jobs.some((j) => j.status === 'queued' || j.status === 'processing'))
let timer = null
onMounted(() => {
	timer = setInterval(() => { if (hasPending.value) router.reload({ only: ['jobs', 'engineOnline'] }) }, 5000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })

/* ── alan rehberi ── */
const guideOpen = ref([])
const FIELD_GUIDE = [
	{ term: 'Profil', desc: 'Kullanılacak beden ölçü tablosu (ör. ts_default = tişört varsayılan ölçüleri). "(yer tutucu)" etiketli profiller istatistiksel tahmindir, modelist onayı almamıştır.' },
	{ term: 'Beden', desc: 'Kalıbın üretileceği taban beden (S/M/L gibi) — seçilen profilin ölçü tablosundan gelir.' },
	{ term: 'Ek Bedenler (gradasyon)', desc: 'Taban bedenle birlikte aynı anda üretilecek diğer bedenler. Sabit bir büyütme katsayısıyla ölçeklenmez — her beden kendi ölçü satırından bağımsız çözülüp tek DXF içinde ayrı ayrı üretilir.' },
	{ term: 'Uyum (fit)', desc: 'Kalıbın vücuda oturma payı (ease). Slim = dar, Regular = standart, Loose = bol — göğüs/bel genişliğine eklenen payı belirler.' },
	{ term: 'Boyun Derinliği (mm)', desc: 'Yaka açıklığının ön ortadan ne kadar aşağı ineceği. Boş bırakılırsa seçilen beden/profil için tanımlı formülden hesaplanır.' },
	{ term: 'Beden Boyu (mm)', desc: 'Gövdenin omuz dikişinden etek ucuna toplam uzunluğu.' },
	{ term: 'Kol Uzunluğu (mm)', desc: 'Omuz dikişinden kol ucuna uzunluk (kısa/uzun kol modeline göre değişir).' },
	{ term: 'Etek Genişlemesi (mm)', desc: 'Etek ucunun göğüs/bel genişliğine göre ne kadar açılacağı — 0 düz kesim demektir, pozitif değer etekte evaze/genişleme verir.' },
	{ term: 'Teknik Çizimden Öneri Al (AI)', desc: 'Bir teknik çizim (flat) görseli yükleyip ölçü alanları için otomatik öneri almayı sağlar. AI kalıp üretmez, yalnızca formu önceden doldurur — yeşil rozet güven ≥ 0.6, turuncu rozet daha düşük güveni gösterir; üretmeden önce değerler gözden geçirilmelidir.' },
]
</script>

<template>
	<Head title="Kalıp Motoru" />

	<OzPageHeader title="Kalıp Motoru" subtitle="Seçilen profilin ölçü tablosundan, beden/tasarım parametresiyle doğrulanmış DXF kalıp üret.">
		<template #actions>
			<OzPill :tone="engineOnline ? 'success' : 'warning'">{{ engineOnline ? 'Servis ayakta' : 'Servis kapalı (mock)' }}</OzPill>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzAlert v-if="currentProfile && !currentProfile.verifiedByModelist" tone="warning" title="Ölçüler geçici, modelist onayı yok" class="notice">
		<code>{{ currentProfile.name }}</code> profili istatistiksel yer tutucudur. Modelist onayı gelmeden bu profille üretim vaadi verilmez.
		<Link v-if="can('patternengine.profile.manage')" href="/pattern-engine/profiles" class="inline-link">Profilleri yönet</Link>
	</OzAlert>

	<div class="layout">
		<OzCard title="Yeni Kalıp Üret" class="form-card">
			<form class="form" @submit.prevent="submit">
				<OzField label="Profil" :error="form.errors.profile">
					<OzCombobox v-model="form.profile" :options="profileOptions" search-placeholder="Profil ara…" />
				</OzField>
				<div class="row-2">
					<OzField label="Beden" :error="form.errors.size">
						<OzSelect v-model="form.size" :options="sizeOptions" placeholder="Beden seçin" />
					</OzField>
					<OzField label="Uyum (fit)" :error="form.errors['design_params.fit']">
						<OzSelect v-model="form.design_params.fit" :options="fitOptions" />
					</OzField>
				</div>
				<OzField
					label="Ek bedenler (gradasyon)"
					:error="form.errors.grade_sizes || form.errors['grade_sizes.0']"
					hint="Her beden kendi ölçüsünden bağımsız çözülüp tek DXF'te üretilir — sabit katsayıyla büyütülmez (§4.5)."
				>
					<OzCombobox v-model="form.grade_sizes" :options="otherSizeOptions" multiple placeholder="Yok" :disabled="!otherSizeOptions.length" />
				</OzField>

				<section class="section">
					<span class="section-title">Teknik çizimden öneri (AI)</span>
					<OzFileDrop v-model="digitizeFiles" accept="image/png,image/jpeg" title="Teknik çizim (flat) bırakın" hint="PNG / JPG" />
					<OzButton size="sm" icon="sparkles" :loading="digitizing" :disabled="!digitizeFiles.length || !form.size" @click="requestDigitizeSuggestion">Görselden Öneri Al</OzButton>
					<p v-if="digitizeError" class="msg is-error">{{ digitizeError }}</p>
					<p v-for="(w, i) in digitizeWarnings" :key="i" class="msg is-warn">{{ w }}</p>
					<p class="msg">AI yalnızca aşağıdaki alanları önceden doldurur; üretmeden önce gözden geçirin (§P2).</p>
				</section>

				<section class="section">
					<span class="section-title">Tasarım parametreleri <span class="dim">(boş = formülden)</span></span>
					<div class="row-2">
						<OzField label="Boyun derinliği" :error="form.errors['design_params.neck_depth_front']">
							<OzInput v-model="form.design_params.neck_depth_front" type="number" step="0.1" min="0" suffix="mm" placeholder="Formül" />
							<OzPill v-if="confidence('neck_depth_front')" :tone="confidence('neck_depth_front').tone" class="ai">{{ confidence('neck_depth_front').label }}</OzPill>
						</OzField>
						<OzField label="Beden boyu" :error="form.errors['design_params.body_length']">
							<OzInput v-model="form.design_params.body_length" type="number" step="0.1" min="0" suffix="mm" placeholder="Formül" />
							<OzPill v-if="confidence('body_length')" :tone="confidence('body_length').tone" class="ai">{{ confidence('body_length').label }}</OzPill>
						</OzField>
						<OzField label="Kol uzunluğu" :error="form.errors['design_params.sleeve_length']">
							<OzInput v-model="form.design_params.sleeve_length" type="number" step="0.1" min="0" suffix="mm" placeholder="Formül" />
						</OzField>
						<OzField label="Etek genişlemesi" :error="form.errors['design_params.hem_flare']">
							<OzInput v-model="form.design_params.hem_flare" type="number" step="0.1" suffix="mm" placeholder="0" />
							<OzPill v-if="confidence('hem_flare')" :tone="confidence('hem_flare').tone" class="ai">{{ confidence('hem_flare').label }}</OzPill>
						</OzField>
					</div>
				</section>

				<OzButton type="submit" variant="primary" icon="rocket" class="submit" :loading="form.processing" :disabled="!form.size">Üret</OzButton>
			</form>
		</OzCard>

		<div class="col">
			<OzCard title="Son İşler" :meta="hasPending ? 'işleniyor · 5 sn\'de bir yenilenir' : `${jobs.length} iş`">
				<OzTable :columns="columns" :rows="jobs" row-key="id" :default-sort="{ key: 'id', dir: 'desc' }" empty-title="Henüz kalıp üretimi yok" empty-text="Soldaki formdan profil ve beden seçip üretimi başlatın." min-width="640px">
					<template #cell-id="{ value }"><span class="mono dim">#{{ value }}</span></template>
					<template #cell-profile="{ value }"><span class="mono">{{ value }}</span></template>
					<template #cell-size="{ row }">
						<b>{{ row.size }}</b>
						<span v-if="row.graded_sizes?.length" class="dim"> + {{ row.graded_sizes.join(', ') }}</span>
					</template>
					<template #cell-status="{ value }">
						<OzPill :tone="STATUS_TONE[value] ?? 'neutral'">{{ STATUS[value] ?? value }}</OzPill>
					</template>
					<template #cell-validation="{ row }">
						<span v-if="row.status === 'failed'" class="err" :title="row.error">{{ row.error }}</span>
						<OzPill v-else-if="validationSummary(row)" :tone="validationSummary(row).allPassed ? 'success' : 'danger'">{{ validationSummary(row).label }}</OzPill>
						<span v-else class="dim">—</span>
					</template>
					<template #cell-created_at="{ value }"><span class="dim">{{ formatDate(value) }}</span></template>
					<template #cell-actions="{ row }">
						<!-- Dosya indirme: Inertia Link değil düz bağlantı (XHR ile indirilemez) -->
						<a v-if="row.status === 'done'" :href="route('patternengine.jobs.download', row.id)" class="dl" :aria-label="`#${row.id} DXF indir`" download>DXF</a>
					</template>
				</OzTable>
			</OzCard>

			<OzCard title="Alan Rehberi" subtitle="formdaki terimlerin anlamı">
				<div class="guide">
					<OzAccordion v-model="guideOpen" :items="[{ key: 'guide', title: 'Terimleri göster', icon: 'help' }]" flush>
						<template #guide>
							<dl class="guide-list">
								<div v-for="item in FIELD_GUIDE" :key="item.term">
									<dt>{{ item.term }}</dt>
									<dd>{{ item.desc }}</dd>
								</div>
							</dl>
						</template>
					</OzAccordion>
				</div>
			</OzCard>
		</div>
	</div>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); font-weight: 400; }
.notice { margin-bottom: 14px; }
.inline-link { margin-left: 6px; color: var(--oz-accent); font-weight: 600; }

.layout { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 16px; align-items: start; }
.col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.form-card { position: sticky; top: 0; }
@media (max-width: 1100px) { .layout { grid-template-columns: 1fr; } .form-card { position: static; } }

.form { display: flex; flex-direction: column; gap: 14px; padding: 0 18px 18px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.section { display: flex; flex-direction: column; gap: 10px; padding-top: 14px; border-top: 1px solid var(--oz-border-soft); }
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.section-title .dim { text-transform: none; letter-spacing: 0; }
.msg { margin: 0; font-size: 11.5px; line-height: 1.45; color: var(--oz-muted); }
.msg.is-error { color: var(--oz-danger); }
.msg.is-warn { color: var(--oz-warning); }
.ai { margin-top: 6px; }
.submit { width: 100%; justify-content: center; }

.err { display: inline-block; max-width: 260px; font-size: 12px; color: var(--oz-danger); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
.dl { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-decoration: none; background: var(--oz-accent-soft); color: var(--oz-accent); }
.dl:hover { background: var(--oz-accent); color: #fff; }

.guide { padding: 0 18px 12px; }
.guide-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin: 0; padding: 4px 0 8px; }
.guide-list dt { font-size: 12px; font-weight: 700; color: var(--oz-ink-soft); }
.guide-list dd { margin: 2px 0 0; font-size: 12px; line-height: 1.5; color: var(--oz-muted); }
@media (max-width: 640px) { .guide-list, .row-2 { grid-template-columns: 1fr; } }
</style>
