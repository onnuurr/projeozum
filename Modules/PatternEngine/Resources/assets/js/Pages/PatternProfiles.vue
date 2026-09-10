<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import DataTable from '@/Components/DataTable.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'
import FormTextarea from '@/Components/Form/FormTextarea.vue'
import FormCheckbox from '@/Components/Form/FormCheckbox.vue'
import { useCan } from '@/composables/useCan'
import { Plus, Trash2 } from 'lucide-vue-next'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	profiles: { type: Array, default: () => [] },
})

// tshirt bloklarının (blocks/tshirt.py) resolve() için okuduğu ölçü alanları —
// bkz. sizes.yaml şeması, mimari doküman §5.3.
const MEASUREMENT_FIELDS = [
	{ key: 'height', label: 'Boy' },
	{ key: 'chest_circ', label: 'Göğüs Çevresi' },
	{ key: 'waist_circ', label: 'Bel Çevresi' },
	{ key: 'shoulder_width', label: 'Omuz Genişliği' },
	{ key: 'neck_circ', label: 'Boyun Çevresi' },
	{ key: 'back_width', label: 'Sırt Genişliği' },
	{ key: 'arm_length', label: 'Kol Uzunluğu' },
	{ key: 'armhole_depth', label: 'Kol Evi Derinliği' },
	{ key: 'upper_arm_circ', label: 'Pazı Çevresi' },
	{ key: 'body_length', label: 'Beden Boyu' },
]

function emptyMeasurements() {
	const obj = {}
	for (const f of MEASUREMENT_FIELDS) obj[f.key] = ''
	return obj
}

const sizeRows = ref([{ sizeKey: '', ...emptyMeasurements() }])
const duplicateSizeError = ref('')

function addSizeRow() {
	sizeRows.value.push({ sizeKey: '', ...emptyMeasurements() })
}
function removeSizeRow(index) {
	if (sizeRows.value.length > 1) sizeRows.value.splice(index, 1)
}

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

// Yazılan ad var olan bir profille eşleşiyorsa mevcut bedenlerini hatırlat —
// gönderim MERGE olduğu için (bkz. create_or_update_profile) veri kaybı yok,
// ama kullanıcı hangi bedenlerin zaten tanımlı olduğunu görmeli.
const existingSizesForName = computed(() => {
	const match = props.profiles.find((p) => p.name === form.name)
	return match ? match.sizes : null
})

const baseProfileOptions = computed(() => props.profiles.map((p) => ({ value: p.name, label: p.name })))

function resetForm() {
	sizeRows.value = [{ sizeKey: '', ...emptyMeasurements() }]
	form.reset()
}

function submit() {
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
		onSuccess: resetForm,
	})
}

const columns = [
	{ key: 'name', label: 'Profil' },
	{ key: 'garment', label: 'Giysi' },
	{ key: 'sizes', label: 'Bedenler', sortable: false },
	{ key: 'source', label: 'Kaynak' },
	{ key: 'verifiedByModelist', label: 'Onay' },
]

// Formdaki her alanın ne anlama geldiğini anlatan rehber (bkz. Kalıp Motoru
// sayfasındaki aynı desen) — ölçü terimleri modelist olmayan kullanıcılar için
// formun altında sabit metin olarak açıklanır.
const FIELD_GUIDE = [
	{ term: 'Profil Adı (slug)', desc: 'Bu ölçü tablosunu tanımlayan benzersiz kısa ad (ör. müşteri/firma kodu). Kalıp Motoru formundaki "Profil" seçiminde bu ad görünür.' },
	{ term: 'Taban Profil', desc: 'Çizim formüllerinin (ease, oranlar) kopyalanacağı kaynak profil. Ölçü tablosundan bağımsızdır — yalnızca formüller taban profilden alınır, bedenler bu formdan girilir.' },
	{ term: 'Kaynak', desc: 'Ölçülerin nereden geldiğine dair not (ör. "Müşteri X modelist tablosu", "istatistiksel tahmin").' },
	{ term: 'Modelist onaylı', desc: 'İşaretlenmezse profil "yer tutucu" sayılır — Kalıp Motoru sayfasında uyarı gösterilir ve bu profille üretim vaadi verilmez.' },
	{ term: 'Not (opsiyonel)', desc: 'Ölçü tablosuna dair serbest metin açıklama.' },
	{ term: 'Beden (satır anahtarı)', desc: 'Bu ölçü setinin karşılık geldiği beden kodu (ör. 6Y, S, M). Kalıp Motoru\'ndaki Beden seçiminde bu değer listelenir.' },
	{ term: 'Boy (mm)', desc: 'Tepeden tabana toplam vücut boyu.' },
	{ term: 'Göğüs Çevresi (mm)', desc: 'Göğüs hizasından ölçülen tam çevre.' },
	{ term: 'Bel Çevresi (mm)', desc: 'Bel hizasından ölçülen tam çevre.' },
	{ term: 'Omuz Genişliği (mm)', desc: 'Bir omuz ucundan diğerine yatay mesafe.' },
	{ term: 'Boyun Çevresi (mm)', desc: 'Boyun kökünden ölçülen tam çevre.' },
	{ term: 'Sırt Genişliği (mm)', desc: 'Kürek kemikleri hizasında ölçülen sırt genişliği.' },
	{ term: 'Kol Uzunluğu (mm)', desc: 'Omuzdan bilek ucuna kol uzunluğu.' },
	{ term: 'Kol Evi Derinliği (mm)', desc: 'Omuzdan koltuk altına dikey derinlik.' },
	{ term: 'Pazı Çevresi (mm)', desc: 'Üst kol (pazı) çevresi.' },
	{ term: 'Beden Boyu (mm)', desc: 'Omuz dikişinden etek ucuna gövde uzunluğu.' },
]

// Kalıp Motoru sayfası ayrı bir yetkiyle (patternengine.manage) korunuyor —
// bu yetkisi olmayan bir profil-editörüne kırık/403 link göstermeyelim.
const breadcrumbItems = computed(() => [
	{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
	can('patternengine.manage')
		? { label: 'Kalıp Motoru', to: route('patternengine.index') }
		: { label: 'Kalıp Motoru' },
	{ label: 'Profiller' },
])
</script>

<template>
	<Head title="Kalıp Profilleri" />

	<Breadcrumb :items="breadcrumbItems" />

	<PageHeader
		title="Kalıp Profilleri"
		subtitle="Her firma/modelist kendi ölçü tablosunu tanımlar (§5.2) — çizim formülleri taban profilden kopyalanır, ölçü tablosu bağımsızdır."
	>
		<template #actions>
			<Link v-if="can('patternengine.manage')" :href="route('patternengine.index')" class="btn btn-ghost btn-sm">Kalıp Motoruna Dön</Link>
		</template>
	</PageHeader>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
		<Card title="Yeni Profil" class="lg:col-span-1">
			<form class="flex flex-col gap-3" @submit.prevent="submit">
				<FormField label="Profil Adı (slug)" for-id="profile_name" :error="form.errors.name">
					<FormInput id="profile_name" v-model="form.name" variant="default" placeholder="musteri_x" />
					<p v-if="existingSizesForName" class="text-2xs text-muted mt-1">
						Bu profil zaten var, mevcut bedenleri: {{ existingSizesForName.join(', ') }}.
						Aşağıda yalnızca eklediğiniz/değiştirdiğiniz bedenler güncellenir, diğerleri korunur.
					</p>
				</FormField>

				<FormField label="Taban Profil (formüller buradan kopyalanır)" :error="form.errors.base_profile">
					<CustomSelect v-model="form.base_profile" :show-label="false" :options="baseProfileOptions" />
				</FormField>

				<FormField label="Kaynak" for-id="provenance_source" :error="form.errors['provenance.source']">
					<FormInput id="provenance_source" v-model="form.provenance.source" variant="default" placeholder="ör. Müşteri X modelist tablosu" />
				</FormField>

				<FormCheckbox v-model="form.provenance.verified_by_modelist" label="Modelist onaylı" />

				<FormField label="Not (opsiyonel)" for-id="provenance_note" :error="form.errors['provenance.note']">
					<FormTextarea id="provenance_note" v-model="form.provenance.note" :rows="2" />
				</FormField>

				<div class="border-t border-line pt-3 mt-1">
					<p class="text-xs font-semibold text-ink mb-2">Bedenler</p>

					<div v-for="(row, index) in sizeRows" :key="index" class="rounded-lg border border-line p-2.5 mb-2">
						<div class="flex items-center gap-2 mb-2">
							<FormInput v-model="row.sizeKey" variant="default" placeholder="ör. 6Y" />
							<Button v-if="sizeRows.length > 1" type="button" variant="ghost" size="sm" icon-only @click="removeSizeRow(index)">
								<Trash2 :size="14" />
							</Button>
						</div>
						<div class="grid grid-cols-2 gap-2">
							<label v-for="f in MEASUREMENT_FIELDS" :key="f.key" class="text-2xs text-muted">
								{{ f.label }} (mm)
								<FormInput v-model="row[f.key]" type="number" step="0.1" min="0" variant="default" />
							</label>
						</div>
					</div>

					<Button type="button" variant="ghost" size="sm" @click="addSizeRow">
						<template #leading><Plus :size="14" /></template>
						Beden Ekle
					</Button>

					<p v-if="duplicateSizeError" class="text-xs text-danger mt-1">{{ duplicateSizeError }}</p>
					<p v-if="form.errors.sizes" class="text-xs text-danger mt-1">{{ form.errors.sizes }}</p>
				</div>

				<Button type="submit" variant="primary" :loading="form.processing" class="mt-1">
					Profili Kaydet
				</Button>
			</form>
		</Card>

		<Card title="Var Olan Profiller" class="lg:col-span-2">
			<DataTable
				:columns="columns"
				:data="profiles"
				:paginated="false"
				empty-icon="folder"
				empty-title="Henüz profil yok"
			>
				<template #sizes="{ value }">
					<span class="text-xs text-muted">{{ value.join(', ') }}</span>
				</template>

				<template #verifiedByModelist="{ value }">
					<Badge :color="value ? 'success' : 'warning'" :label="value ? 'Onaylı' : 'Yer tutucu'" variant="tonal" />
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
