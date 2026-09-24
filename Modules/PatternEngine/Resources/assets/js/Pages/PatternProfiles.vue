<script setup>
import { computed, ref, watch, inject } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzPill, OzTable, OzDropdown, OzDrawer, OzAlert,
	OzAccordion, OzField, OzInput, OzSelect, OzTextarea, OzCheckbox, OzTooltip, OzIcon,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const showToast = inject('showToast', null)

const props = defineProps({
	profiles: { type: Array, default: () => [] },
})

// tshirt bloklarının (blocks/tshirt.py) resolve() için okuduğu ölçü alanları —
// bkz. sizes.yaml şeması, mimari doküman §5.3.
const MEASUREMENT_FIELDS = [
	{ key: 'height', label: 'Boy', hint: 'Tepeden tabana toplam vücut boyu.' },
	{ key: 'chest_circ', label: 'Göğüs Çevresi', hint: 'Göğüs hizasından ölçülen tam çevre.' },
	{ key: 'waist_circ', label: 'Bel Çevresi', hint: 'Bel hizasından ölçülen tam çevre.' },
	{ key: 'shoulder_width', label: 'Omuz Genişliği', hint: 'Bir omuz ucundan diğerine yatay mesafe.' },
	{ key: 'neck_circ', label: 'Boyun Çevresi', hint: 'Boyun kökünden ölçülen tam çevre.' },
	{ key: 'back_width', label: 'Sırt Genişliği', hint: 'Kürek kemikleri hizasında ölçülen sırt genişliği.' },
	{ key: 'arm_length', label: 'Kol Uzunluğu', hint: 'Omuzdan bilek ucuna kol uzunluğu.' },
	{ key: 'armhole_depth', label: 'Kol Evi Derinliği', hint: 'Omuzdan koltuk altına dikey derinlik.' },
	{ key: 'upper_arm_circ', label: 'Pazı Çevresi', hint: 'Üst kol (pazı) çevresi.' },
	{ key: 'body_length', label: 'Beden Boyu', hint: 'Omuz dikişinden etek ucuna gövde uzunluğu.' },
]

/* ── özet ── */
const verified = computed(() => props.profiles.filter((p) => p.verifiedByModelist).length)
const sizeCount = computed(() => props.profiles.reduce((s, p) => s + (p.sizes?.length ?? 0), 0))
const stats = computed(() => [
	{ key: 'total', label: 'Ölçü Profili', value: props.profiles.length, icon: 'layers', tone: 'accent' },
	{ key: 'verified', label: 'Modelist Onaylı', value: verified.value, icon: 'success', tone: 'success' },
	{ key: 'placeholder', label: 'Yer Tutucu', value: props.profiles.length - verified.value, icon: 'alert', tone: props.profiles.length - verified.value ? 'warning' : 'success' },
	{ key: 'sizes', label: 'Tanımlı Beden', value: sizeCount.value, icon: 'rows', tone: 'info' },
])

/* ── liste ── */
const columns = [
	{ key: 'name', label: 'Profil', sortable: true },
	{ key: 'garment', label: 'Giysi', sortable: true },
	{ key: 'sizes', label: 'Bedenler', searchable: false },
	{ key: 'source', label: 'Kaynak', sortable: true },
	{ key: 'verifiedByModelist', label: 'Onay', sortable: true, filter: { type: 'select', options: [{ value: true, label: 'Onaylı' }, { value: false, label: 'Yer tutucu' }] } },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]
const rowActions = [{ key: 'sizes', label: 'Beden Ekle / Güncelle', icon: 'plus' }]

/* ── form (oluştur ya da mevcut profile beden ekle; gönderim MERGE) ── */
function emptyMeasurements() {
	const obj = {}
	for (const f of MEASUREMENT_FIELDS) obj[f.key] = ''
	return obj
}
const sizeRows = ref([{ sizeKey: '', ...emptyMeasurements() }])
const duplicateSizeError = ref('')
function addSizeRow() { sizeRows.value.push({ sizeKey: '', ...emptyMeasurements() }) }
function removeSizeRow(index) { if (sizeRows.value.length > 1) sizeRows.value.splice(index, 1) }

const form = useForm({
	name: '',
	base_profile: props.profiles[0]?.name ?? 'ts_default',
	sizes: {},
	provenance: {
		source: '',
		verified_by_modelist: false,
		note: '',
	},
})
const formOpen = ref(false)

// Yazılan ad var olan bir profille eşleşiyorsa mevcut bedenlerini hatırlat —
// gönderim MERGE olduğu için (bkz. create_or_update_profile) veri kaybı yok,
// ama kullanıcı hangi bedenlerin zaten tanımlı olduğunu görmeli.
const existingSizesForName = computed(() => props.profiles.find((p) => p.name === form.name)?.sizes ?? null)
const baseProfileOptions = computed(() => props.profiles.map((p) => ({ value: p.name, label: p.name })))

function resetForm() {
	sizeRows.value = [{ sizeKey: '', ...emptyMeasurements() }]
	duplicateSizeError.value = ''
	form.reset()
	form.clearErrors()
}
watch(formOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

function openCreate() {
	resetForm()
	formOpen.value = true
}
function openForProfile(p) {
	resetForm()
	form.name = p.name
	form.provenance.source = p.source ?? ''
	form.provenance.verified_by_modelist = !!p.verifiedByModelist
	formOpen.value = true
}

function submit() {
	if (form.processing) return
	duplicateSizeError.value = ''

	const keys = sizeRows.value.map((row) => row.sizeKey).filter(Boolean)
	const duplicates = keys.filter((key, index) => keys.indexOf(key) !== index)
	if (duplicates.length > 0) {
		duplicateSizeError.value = `Aynı beden birden fazla satırda girilmiş: ${[...new Set(duplicates)].join(', ')} — biri diğerinin üzerine yazardı.`
		return
	}

	const sizes = {}
	for (const row of sizeRows.value) {
		if (!row.sizeKey) continue
		const measurements = {}
		for (const f of MEASUREMENT_FIELDS) measurements[f.key] = Number(row[f.key] || 0)
		sizes[row.sizeKey] = measurements
	}
	form.sizes = sizes

	form.post(route('patternengine.profiles.store'), {
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Profil kaydedilemedi', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}

/* ── alan rehberi ── */
const guideOpen = ref([])
const FIELD_GUIDE = [
	{ term: 'Profil Adı (slug)', desc: 'Bu ölçü tablosunu tanımlayan benzersiz kısa ad (ör. müşteri/firma kodu). Kalıp Motoru formundaki "Profil" seçiminde bu ad görünür.' },
	{ term: 'Taban Profil', desc: 'Çizim formüllerinin (ease, oranlar) kopyalanacağı kaynak profil. Ölçü tablosundan bağımsızdır — yalnızca formüller taban profilden alınır, bedenler bu formdan girilir.' },
	{ term: 'Kaynak', desc: 'Ölçülerin nereden geldiğine dair not (ör. "Müşteri X modelist tablosu", "istatistiksel tahmin").' },
	{ term: 'Modelist onaylı', desc: 'İşaretlenmezse profil "yer tutucu" sayılır — Kalıp Motoru sayfasında uyarı gösterilir ve bu profille üretim vaadi verilmez.' },
	{ term: 'Not (opsiyonel)', desc: 'Ölçü tablosuna dair serbest metin açıklama.' },
	{ term: 'Beden (satır anahtarı)', desc: 'Bu ölçü setinin karşılık geldiği beden kodu (ör. 6Y, S, M). Kalıp Motoru\'ndaki Beden seçiminde bu değer listelenir.' },
	...MEASUREMENT_FIELDS.map((f) => ({ term: `${f.label} (mm)`, desc: f.hint })),
]
</script>

<template>
	<Head title="Kalıp Profilleri" />

	<OzPageHeader title="Kalıp Profilleri" subtitle="Her firma/modelist kendi ölçü tablosunu tanımlar (§5.2) — çizim formülleri taban profilden kopyalanır, ölçü tablosu bağımsızdır.">
		<template #actions>
			<OzButton variant="primary" icon="plus" @click="openCreate">Yeni Profil</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Var Olan Profiller">
		<OzTable :columns="columns" :rows="profiles" row-key="name" searchable search-placeholder="Profil ya da kaynak ara…" empty-title="Henüz profil yok" empty-text="“Yeni Profil” ile ilk ölçü tablosunu ekleyin." min-width="620px">
			<template #cell-name="{ value }"><span class="mono strong">{{ value }}</span></template>
			<template #cell-sizes="{ value }">
				<div class="chips"><span v-for="s in value" :key="s" class="chip">{{ s }}</span></div>
			</template>
			<template #cell-source="{ value }"><span class="dim">{{ value || '—' }}</span></template>
			<template #cell-verifiedByModelist="{ value }">
				<OzPill :tone="value ? 'success' : 'warning'">{{ value ? 'Onaylı' : 'Yer tutucu' }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<OzDropdown :items="rowActions" :label="`${row.name} işlemleri`" @select="openForProfile(row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzCard title="Alan Rehberi" subtitle="formdaki terimlerin anlamı" class="guide-card">
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

	<OzDrawer v-model:open="formOpen" :title="existingSizesForName ? 'Profili Güncelle' : 'Yeni Profil'" subtitle="Ölçü tablosu · bedenler mevcut profile birleştirilir" icon="layers" size="lg" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<div class="row-2">
				<OzField
					label="Profil adı (slug)"
					required
					:error="form.errors.name"
					hint="Bu ölçü tablosunu tanımlayan benzersiz kısa ad (ör. müşteri/firma kodu). Kalıp Motoru formundaki &quot;Profil&quot; seçiminde bu ad görünür."
				>
					<OzInput v-model="form.name" class="mono" placeholder="musteri_x" />
				</OzField>
				<OzField
					label="Taban profil (formüller buradan kopyalanır)"
					:error="form.errors.base_profile"
					hint="Çizim formüllerinin (ease, oranlar) kopyalanacağı kaynak profil. Ölçü tablosundan bağımsızdır — yalnızca formüller taban profilden alınır, bedenler bu formdan girilir."
				>
					<OzSelect v-model="form.base_profile" :options="baseProfileOptions" />
				</OzField>
			</div>
			<OzAlert v-if="existingSizesForName" tone="info" title="Bu profil zaten var">
				Mevcut bedenler: <b>{{ existingSizesForName.join(', ') }}</b>. Yalnızca eklediğiniz/değiştirdiğiniz bedenler güncellenir, diğerleri korunur.
			</OzAlert>
			<OzField
				label="Kaynak"
				:error="form.errors['provenance.source']"
				hint="Ölçülerin nereden geldiğine dair not (ör. &quot;Müşteri X modelist tablosu&quot;, &quot;istatistiksel tahmin&quot;)."
			>
				<OzInput v-model="form.provenance.source" placeholder="ör. Müşteri X modelist tablosu" />
			</OzField>
			<OzCheckbox
				v-model="form.provenance.verified_by_modelist"
				label="Modelist onaylı"
				description="İşaretlenmezse profil &quot;yer tutucu&quot; sayılır — Kalıp Motoru sayfasında uyarı gösterilir ve bu profille üretim vaadi verilmez."
			/>
			<OzField label="Not (opsiyonel)" :error="form.errors['provenance.note']" hint="Ölçü tablosuna dair serbest metin açıklama.">
				<OzTextarea v-model="form.provenance.note" :rows="2" />
			</OzField>

			<section class="section">
				<div class="section-head">
					<span class="section-title">Bedenler</span>
					<OzButton size="sm" icon="plus" @click="addSizeRow">Beden Ekle</OzButton>
				</div>
				<div v-for="(row, index) in sizeRows" :key="index" class="size">
					<div class="size-head">
						<label class="m-field size-key">
							<span class="m-label">
								Beden
								<OzTooltip text="Bu ölçü setinin karşılık geldiği beden kodu (ör. 6Y, S, M). Kalıp Motoru'ndaki Beden seçiminde bu değer listelenir.">
									<span class="hint-ico" tabindex="0" aria-label="Beden açıklaması"><OzIcon name="help" :size="12" /></span>
								</OzTooltip>
							</span>
							<OzInput v-model="row.sizeKey" class="mono" placeholder="ör. 6Y" />
						</label>
						<OzButton v-if="sizeRows.length > 1" size="sm" icon="trash" :aria-label="`${row.sizeKey || index + 1}. bedeni kaldır`" @click="removeSizeRow(index)" />
					</div>
					<div class="measures">
						<label v-for="f in MEASUREMENT_FIELDS" :key="f.key" class="m-field">
							<span class="m-label">
								{{ f.label }}
								<OzTooltip :text="f.hint">
									<span class="hint-ico" tabindex="0" :aria-label="`${f.label} açıklaması`"><OzIcon name="help" :size="12" /></span>
								</OzTooltip>
							</span>
							<OzInput v-model="row[f.key]" type="number" step="0.1" min="0" suffix="mm" />
						</label>
					</div>
				</div>
				<p v-if="duplicateSizeError" class="field-error">{{ duplicateSizeError }}</p>
				<p v-if="form.errors.sizes" class="field-error">{{ form.errors.sizes }}</p>
			</section>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="form.processing" @click="submit">Profili Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.strong { font-weight: 700; color: var(--oz-ink-soft); }
.dim { color: var(--oz-muted); }
.chips { display: flex; flex-wrap: wrap; gap: 4px; }
.chip { font-family: ui-monospace, monospace; font-size: 11px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 0 6px; border-radius: 5px; }
.guide-card { margin-top: 16px; }
.guide { padding: 0 18px 12px; }
.guide-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin: 0; padding: 4px 0 8px; }
.guide-list dt { font-size: 12px; font-weight: 700; color: var(--oz-ink-soft); }
.guide-list dd { margin: 2px 0 0; font-size: 12px; line-height: 1.5; color: var(--oz-muted); }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.section { display: flex; flex-direction: column; gap: 10px; padding-top: 14px; border-top: 1px solid var(--oz-border-soft); }
.section-head { display: flex; align-items: center; justify-content: space-between; }
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.size { display: flex; flex-direction: column; gap: 10px; padding: 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2); }
.size-head { display: flex; align-items: flex-end; gap: 8px; }
.size-key { flex: 0 1 220px; }
.measures { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.field-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.m-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.m-label { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.hint-ico { display: inline-flex; color: var(--oz-muted); cursor: help; border-radius: 50%; }
.hint-ico:hover, .hint-ico:focus-visible { color: var(--oz-accent); outline: none; }
@media (max-width: 640px) {
	.row-2, .guide-list { grid-template-columns: 1fr; }
}
</style>
