<script setup>
import { computed, inject, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzCard, OzSettingRow, OzSwitch, OzInput, OzSelect, OzButton, OzStatStrip } from '@/Components/Oz'

// Mokap · Genel: otomatik tetik, günlük limit (maliyet sınırı), cinsiyete göre varsayılan manken.
const props = defineProps({
	general: { type: Object, required: true },
	mannequins: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const busy = ref(false)
const errors = ref({})
const form = reactive({
	auto_trigger: props.general.auto_trigger,
	daily_limit: props.general.daily_limit,
	mannequins: { girl: props.general.mannequins.girl ?? '', boy: props.general.mannequins.boy ?? '' },
})

const mannequinOptions = computed(() => [{ value: '', label: 'Seçilmedi' }, ...props.mannequins.map((m) => ({ value: m.id, label: m.name }))])

const stats = computed(() => [
	{ key: 'today', icon: 'zap', label: 'Bugün başlatılan', value: props.general.started_today, context: `/ ${props.general.daily_limit}` },
	{ key: 'auto', icon: 'sparkles', tone: props.general.auto_trigger ? 'success' : 'neutral', label: 'Otomatik başlatma', value: props.general.auto_trigger ? 'Açık' : 'Kapalı' },
])

function save() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	router.put('/creative/settings/mockup/general', {
		auto_trigger: form.auto_trigger,
		daily_limit: form.daily_limit === '' ? 0 : Number(form.daily_limit),
		mannequins: { girl: form.mannequins.girl || null, boy: form.mannequins.boy || null },
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Mokap ayarları kaydedildi' }),
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	})
}
</script>

<template>
	<OzStatStrip :items="stats" label="Mokap özeti" class="strip" />

	<OzCard title="Mokap üretimi" subtitle="Ücretli AI çağrıları · sonuçlar her zaman onay listesine düşer, onaysız hiçbir görsel yayınlanmaz">
		<template #actions>
			<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="save">Kaydet</OzButton>
		</template>
		<OzSettingRow icon="sparkles" title="Fotoğraf eklenince otomatik başlat"
			description="Mokap kategorisindeki, yalın fotoğrafı olan ve henüz mokapı olmayan bir ürüne fotoğraf eklendiğinde 2 dakika sonra mokap başlar.">
			<OzSwitch v-model="form.auto_trigger" aria-label="Otomatik başlatma" />
		</OzSettingRow>
		<OzSettingRow icon="shield" title="Günlük limit"
			:description="`Bir günde başlatılabilecek en fazla ürün (otomatik + elle + toplu). Ürün başı tahmini ~0,22 $ (Batch API). 0 = kapalı.${errors.daily_limit ? ' — ' + errors.daily_limit : ''}`">
			<OzInput v-model.number="form.daily_limit" type="number" min="0" max="500" class="num" :invalid="!!errors.daily_limit" aria-label="Günlük limit" />
		</OzSettingRow>
		<OzSettingRow icon="user" title="Kız ürünleri için manken" description="Cinsiyeti kız (ya da belirtilmemiş) ürünlerde kullanılır. Yalnız hazır ve onaylı mankenler listelenir.">
			<OzSelect v-model="form.mannequins.girl" :options="mannequinOptions" class="sel" :invalid="!!errors['mannequins.girl']" aria-label="Kız mankeni" />
		</OzSettingRow>
		<OzSettingRow icon="user" title="Erkek ürünleri için manken" description="Cinsiyeti erkek olan ürünlerde kullanılır.">
			<OzSelect v-model="form.mannequins.boy" :options="mannequinOptions" class="sel" :invalid="!!errors['mannequins.boy']" aria-label="Erkek mankeni" />
		</OzSettingRow>
	</OzCard>
</template>

<style scoped>
.strip { margin-bottom: 14px; }
.num { width: 96px; }
.sel { min-width: 200px; }
</style>
