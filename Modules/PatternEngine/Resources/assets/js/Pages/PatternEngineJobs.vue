<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import DataTable from '@/Components/DataTable.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'
import FormCheckbox from '@/Components/Form/FormCheckbox.vue'
import { useCan } from '@/composables/useCan'
import { Download, ScanLine, TriangleAlert } from 'lucide-vue-next'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	jobs: { type: Array, default: () => [] },
	engineOnline: { type: Boolean, default: false },
	profiles: { type: Array, default: () => [] },
})

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
})))
const sizeOptions = computed(() => availableSizes.value.map((s) => ({ value: s, label: s })))
const fitOptions = [
	{ value: 'slim', label: 'Slim' },
	{ value: 'regular', label: 'Regular' },
	{ value: 'loose', label: 'Loose' },
]

// Profil değişince o profilin bedenlerine düş; artık uygun olmayan gradasyon
// seçimlerini temizle.
watch(() => form.profile, (name) => {
	const profile = props.profiles.find((p) => p.name === name)
	if (!profile) return
	form.garment = profile.garment
	if (!profile.sizes.includes(form.size)) form.size = profile.sizes[0] ?? ''
	form.grade_sizes = form.grade_sizes.filter((s) => profile.sizes.includes(s))
}, { immediate: true })

// Taban beden değişince, artık taban olan bedeni "ek beden" listesinden düşür.
// AI güven rozetleri eski bedene göre hesaplanmıştı — beden değişince artık
// yanıltıcı olur, temizlenir (alanlardaki değerler kalır, kullanıcı isterse düzeltir).
watch(() => form.size, (newSize) => {
	form.grade_sizes = form.grade_sizes.filter((s) => s !== newSize)
	digitizeConfidence.value = {}
})

const otherSizes = computed(() => availableSizes.value.filter((s) => s !== form.size))

function submit() {
	form.post(route('patternengine.generate'), {
		preserveScroll: true,
	})
}

// Technical flat'ten öneri (§4.1, Sprint 5) — AI omurga değil sağlayıcıdır (§P2):
// bu asla otomatik üretmez, yalnızca aşağıdaki alanları önceden doldurur.
const digitizeFile = ref(null)
const digitizing = ref(false)
const digitizeError = ref('')
const digitizeWarnings = ref([])
const digitizeConfidence = ref({})

function onDigitizeFileChange(event) {
	digitizeFile.value = event.target.files[0] ?? null
	digitizeError.value = ''
	// Yeni dosya henüz analiz edilmedi — eski öneriye ait güven rozetleri yanıltıcı olur.
	digitizeConfidence.value = {}
	digitizeWarnings.value = []
}

async function requestDigitizeSuggestion() {
	if (!digitizeFile.value) return

	digitizing.value = true
	digitizeError.value = ''
	digitizeWarnings.value = []

	const payload = new FormData()
	payload.append('image', digitizeFile.value)
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

function confidenceBadge(field) {
	const value = digitizeConfidence.value[field]
	if (value == null) return null
	return { color: value >= 0.6 ? 'success' : 'warning', label: `AI önerisi (güven ${value.toFixed(1)})` }
}

// Formdaki her alanın kalıp üretimindeki karşılığını anlatan rehber (§ aşağıdaki
// "Alan Rehberi" kartı) — teknik terimler (ease, gradasyon vb.) modelist olmayan
// kullanıcılar için formun altında sabit metin olarak açıklanır.
const FIELD_GUIDE = [
	{ term: 'Profil', desc: 'Kullanılacak beden ölçü tablosu (ör. ts_default = tişört varsayılan ölçüleri). "(yer tutucu)" etiketli profiller istatistiksel tahmindir, modelist onayı almamıştır.' },
	{ term: 'Beden', desc: 'Kalıbın üretileceği taban beden (S/M/L gibi) — seçilen profilin ölçü tablosundan gelir.' },
	{ term: 'Ek Bedenler (gradasyon)', desc: 'Taban bedenle birlikte aynı anda üretilecek diğer bedenler. Sabit bir büyütme katsayısıyla ölçeklenmez — her beden kendi ölçü satırından bağımsız çözülüp tek DXF içinde ayrı ayrı üretilir.' },
	{ term: 'Uyum (fit)', desc: 'Kalıbın vücuda oturma payı (ease). Slim = dar, Regular = standart, Loose = bol — göğüs/bel genişliğine eklenen payı belirler.' },
	{ term: 'Boyun Derinliği (mm)', desc: 'Yaka açıklığının ön ortadan ne kadar aşağı ineceği. Boş bırakılırsa seçilen beden/profil için tanımlı formülden hesaplanır.' },
	{ term: 'Beden Boyu (mm)', desc: 'Gövdenin omuz dikişinden etek ucuna toplam uzunluğu.' },
	{ term: 'Kol Uzunluğu (mm)', desc: 'Omuz dikişinden kol ucuna uzunluk (kısa/uzun kol modeline göre değişir).' },
	{ term: 'Etek Genişlemesi (mm)', desc: 'Etek ucunun göğüs/bel genişliğine göre ne kadar açılacağı — 0 düz kesim demektir, pozitif değer etekte evaze/genişleme verir.' },
	{ term: 'Teknik Çizimden Öneri Al (AI)', desc: 'Bir teknik çizim (flat) görseli yükleyip yukarıdaki ölçü alanları için otomatik öneri almayı sağlar. AI kalıp üretmez, yalnızca formu önceden doldurur — yeşil rozet güven ≥ 0.6, turuncu rozet daha düşük güveni gösterir; üretmeden önce değerler gözden geçirilmelidir.' },
]

const STATUS_LABELS = { queued: 'Sırada', processing: 'İşleniyor', done: 'Hazır', failed: 'Başarısız' }
const STATUS_COLORS = { queued: 'neutral', processing: 'warning', done: 'success', failed: 'danger' }

function statusLabel(s) { return STATUS_LABELS[s] || s }
function statusColor(s) { return STATUS_COLORS[s] ?? 'neutral' }

function validationSummary(job) {
	const report = job.validation_report
	if (!report) return null

	if (report.findings_by_size) {
		const sizes = Object.keys(report.all_passed_by_size || {})
		const passed = sizes.filter((s) => report.all_passed_by_size[s]).length
		return { graded: true, total: sizes.length, passed, allPassed: passed === sizes.length && sizes.length > 0 }
	}

	if (Array.isArray(report.findings)) {
		const failed = report.findings.filter((f) => !f.passed).length
		return { graded: false, total: report.findings.length, failed, allPassed: report.all_passed }
	}

	return null
}

const columns = [
	{ key: 'id', label: '#', align: 'left' },
	{ key: 'garment', label: 'Giysi' },
	{ key: 'size', label: 'Beden', sortable: false },
	{ key: 'status', label: 'Durum' },
	{ key: 'validation', label: 'Doğrulama', sortable: false },
	{ key: 'created_at', label: 'Tarih' },
]

const hasPending = computed(() => props.jobs.some((j) => j.status === 'queued' || j.status === 'processing'))

function refresh() {
	router.reload({ only: ['jobs', 'engineOnline'] })
}

let timer = null
onMounted(() => {
	timer = setInterval(() => { if (hasPending.value) refresh() }, 5000)
})
onUnmounted(() => {
	if (timer) clearInterval(timer)
})
</script>

<template>
	<Head title="Kalıp Motoru" />

	<Breadcrumb
		:items="[
			{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
			{ label: 'Kalıp Motoru' },
		]"
	/>

	<PageHeader
		title="Kalıp Motoru"
		subtitle="Seçilen profilin ölçü tablosundan, beden/tasarım parametresiyle doğrulanmış DXF kalıp üret."
	>
		<template #actions>
			<Link v-if="can('patternengine.profile.manage')" :href="route('patternengine.profiles.index')" class="btn btn-ghost btn-sm">Profiller</Link>
			<StatusIndicator :status="engineOnline ? 'online' : 'offline'" :label="engineOnline ? 'Servis Ayakta' : 'Servis Kapalı (mock)'" />
		</template>
	</PageHeader>

	<div v-if="currentProfile && !currentProfile.verifiedByModelist" class="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning/10 px-3.5 py-2.5 mb-5 text-xs text-ink">
		<TriangleAlert :size="16" class="text-warning shrink-0 mt-0.5" />
		<p>
			<strong>Ölçüler geçici, modelist onayı yok</strong> — <code>{{ currentProfile.name }}</code> profili
			istatistiksel yer tutucudur. Modelist onayı gelmeden bu profille üretim vaadi verilmez.
			<Link v-if="can('patternengine.profile.manage')" :href="route('patternengine.profiles.index')" class="underline">Profilleri yönet</Link>
		</p>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
		<Card title="Yeni Kalıp Üret" class="lg:col-span-1">
			<form class="flex flex-col gap-3" @submit.prevent="submit">
				<FormField label="Profil" :error="form.errors.profile">
					<CustomSelect v-model="form.profile" :show-label="false" :options="profileOptions" />
				</FormField>

				<FormField label="Beden" :error="form.errors.size">
					<CustomSelect v-model="form.size" :show-label="false" :options="sizeOptions" />
				</FormField>

				<FormField label="Ek Bedenler (gradasyon, opsiyonel)" :error="form.errors['grade_sizes'] || form.errors['grade_sizes.0']">
					<div class="flex flex-wrap gap-3 pt-1">
						<FormCheckbox v-for="s in otherSizes" :key="s" v-model="form.grade_sizes" :value="s" :label="s" />
					</div>
					<p class="text-2xs text-muted mt-1">
						Seçilirse taban bedenle birlikte, her biri kendi ölçülerinden bağımsız çözülüp
						tek DXF'te (her beden kendi satırında) üretilir — sabit katsayı ile büyütülmez (§4.5).
					</p>
				</FormField>

				<div class="border-t border-line pt-3">
					<p class="text-xs font-semibold text-ink mb-2 flex items-center gap-1.5">
						<ScanLine :size="14" /> Teknik Çizimden Öneri Al (AI)
					</p>
					<input type="file" accept="image/png,image/jpeg" class="form-input" @change="onDigitizeFileChange" />
					<Button
						type="button" variant="ghost" size="sm" class="mt-2"
						:loading="digitizing" :disabled="!digitizeFile || !form.size"
						@click="requestDigitizeSuggestion"
					>
						Görselden Öneri Al
					</Button>
					<p v-if="digitizeError" class="text-xs text-danger mt-1">{{ digitizeError }}</p>
					<p v-for="(w, i) in digitizeWarnings" :key="i" class="text-2xs text-warning mt-1">{{ w }}</p>
					<p class="text-2xs text-muted mt-1">
						AI omurga değil sağlayıcıdır (§P2) — öneri aşağıdaki alanları önceden doldurur,
						üretmeden önce gözden geçirip düzeltebilirsiniz.
					</p>
				</div>

				<FormField label="Uyum (fit)" :error="form.errors['design_params.fit']">
					<CustomSelect v-model="form.design_params.fit" :show-label="false" :options="fitOptions" />
				</FormField>

				<FormField label="Boyun Derinliği (mm, opsiyonel)" for-id="neck_depth_front" :error="form.errors['design_params.neck_depth_front']">
					<FormInput id="neck_depth_front" v-model="form.design_params.neck_depth_front" type="number" step="0.1" min="0" variant="default" placeholder="Formülden türet" />
					<Badge v-if="confidenceBadge('neck_depth_front')" v-bind="confidenceBadge('neck_depth_front')" variant="tonal" class="mt-1" />
				</FormField>

				<FormField label="Beden Boyu (mm, opsiyonel)" for-id="body_length" :error="form.errors['design_params.body_length']">
					<FormInput id="body_length" v-model="form.design_params.body_length" type="number" step="0.1" min="0" variant="default" placeholder="Formülden türet" />
					<Badge v-if="confidenceBadge('body_length')" v-bind="confidenceBadge('body_length')" variant="tonal" class="mt-1" />
				</FormField>

				<FormField label="Kol Uzunluğu (mm, opsiyonel)" for-id="sleeve_length" :error="form.errors['design_params.sleeve_length']">
					<FormInput id="sleeve_length" v-model="form.design_params.sleeve_length" type="number" step="0.1" min="0" variant="default" placeholder="Formülden türet" />
				</FormField>

				<FormField label="Etek Genişlemesi (mm)" for-id="hem_flare" :error="form.errors['design_params.hem_flare']">
					<FormInput id="hem_flare" v-model="form.design_params.hem_flare" type="number" step="0.1" variant="default" placeholder="0" />
					<Badge v-if="confidenceBadge('hem_flare')" v-bind="confidenceBadge('hem_flare')" variant="tonal" class="mt-1" />
				</FormField>

				<Button type="submit" variant="primary" :loading="form.processing" class="mt-1">
					Üret
				</Button>
			</form>
		</Card>

		<Card title="Son İşler" class="lg:col-span-2">
			<DataTable
				:columns="columns"
				:data="jobs"
				:paginated="false"
				empty-icon="folder"
				empty-title="Henüz kalıp üretimi yok"
				empty-hint="Soldaki formdan bir tişört/beden seçip üretimi başlatın."
			>
				<template #size="{ row }">
					<span class="text-ink">{{ row.size }}</span>
					<span v-if="row.graded_sizes && row.graded_sizes.length" class="text-2xs text-muted">
						+ {{ row.graded_sizes.join(', ') }}
					</span>
				</template>

				<template #status="{ row }">
					<Badge :color="statusColor(row.status)" :label="statusLabel(row.status)" variant="tonal" />
				</template>

				<template #validation="{ row }">
					<span v-if="row.status === 'failed'" class="text-xs text-danger">{{ row.error }}</span>
					<template v-else-if="validationSummary(row)">
						<Badge
							:color="validationSummary(row).allPassed ? 'success' : 'danger'"
							:label="validationSummary(row).graded
								? `${validationSummary(row).passed}/${validationSummary(row).total} beden geçti`
								: (validationSummary(row).allPassed
									? `${validationSummary(row).total}/${validationSummary(row).total} geçti`
									: `${validationSummary(row).failed} kural başarısız`)"
							variant="tonal"
						/>
					</template>
					<span v-else class="text-xs text-muted">—</span>
				</template>

				<template #created_at="{ value }">
					<span class="text-xs text-muted">{{ new Date(value).toLocaleString('tr-TR') }}</span>
				</template>

				<template #actions="{ row }">
					<Link
						v-if="row.status === 'done'"
						:href="route('patternengine.jobs.download', row.id)"
						class="btn btn-ghost btn-sm"
					>
						<Download :size="14" />
					</Link>
				</template>
			</DataTable>
		</Card>
	</div>

	<Card title="Alan Rehberi" class="mt-4">
		<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 text-xs">
			<div v-for="item in FIELD_GUIDE" :key="item.term">
				<dt class="font-semibold text-ink">{{ item.term }}</dt>
				<dd class="text-muted mt-0.5">{{ item.desc }}</dd>
			</div>
		</dl>
	</Card>
</template>
