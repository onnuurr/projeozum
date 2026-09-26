<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzSettingRow, OzSwitch, OzButton, OzPill, OzAlert,
} from '@/Components/Oz'

// İş Ortakları › Bayi Portalı: Bagisto mağazasındaki (bayi portalı) özellikleri açıp kapatır.
//   Kaydet → PUT /bagisto/settings: değerler SaaS'ta saklanır ve mağazaya imzalı webhook'la gider
//   (PushStorefrontFeaturesToBagisto). Senkron durumu sağ üstteki kartta; takılırsa "Şimdi gönder".
defineOptions({ layout: AppLayout })

const props = defineProps({
	features: { type: Array, default: () => [] },
	sync: { type: Object, default: () => ({}) },
	storeUrl: { type: String, default: '' },
	configured: { type: Boolean, default: false },
})

const showToast = inject('showToast', null)

const ICONS = { bulk_order_excel: 'file', cari_account: 'wallet', hide_prices_for_guests: 'eye-off' }

const initial = () => Object.fromEntries(props.features.map((f) => [f.key, f.enabled]))
const form = ref(initial())
const dirty = computed(() => props.features.some((f) => form.value[f.key] !== f.enabled))

const groups = computed(() => {
	const out = {}
	for (const f of props.features) (out[f.group] ??= []).push(f)
	return Object.entries(out)
})

const SYNC = {
	synced: { tone: 'success', label: 'Mağazayla eşit' },
	pending: { tone: 'warning', label: 'Gönderiliyor' },
	failed: { tone: 'danger', label: 'Gönderilemedi' },
}
const syncState = computed(() => SYNC[props.sync.state] ?? { tone: 'neutral', label: 'Henüz gönderilmedi' })
const fmtDate = (s) => (s ? new Date(s).toLocaleString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—')

const saving = ref(false)
function save() {
	if (saving.value) return
	saving.value = true
	router.put('/bagisto/settings', { features: form.value }, {
		preserveScroll: true,
		onSuccess: () => {
			form.value = initial()
			showToast?.({ type: 'success', title: 'Kaydedildi', message: 'Değişiklikler mağazaya gönderiliyor.' })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { saving.value = false },
	})
}

function reset() {
	form.value = initial()
}

const pushing = ref(false)
function pushNow() {
	if (pushing.value) return
	pushing.value = true
	router.post('/bagisto/settings/sync', {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'info', title: 'Mağazaya gönderiliyor' }),
		onFinish: () => { pushing.value = false },
	})
}

function refresh() {
	router.reload({ only: ['sync'] })
}
</script>

<template>
	<Head title="Bayi Portalı" />

	<OzPageHeader title="Bayi Portalı" subtitle="Bagisto mağazasındaki (bayi portalı) özellikleri buradan açıp kapatın" />
	<OzModuleTabs />

	<OzAlert v-if="!configured" tone="danger" title="Mağaza bağlantısı yapılandırılmamış" class="block">
		BAGISTO_SYNC_BASE_URL ve BAGISTO_SYNC_WEBHOOK_SECRET tanımlı değil; değişiklikler kaydedilir ama mağazaya gönderilemez.
	</OzAlert>

	<div class="layout">
		<div class="main">
			<OzCard v-for="[group, items] in groups" :key="group" :title="group" class="block">
				<div class="rows">
					<OzSettingRow
						v-for="f in items"
						:key="f.key"
						:icon="ICONS[f.key] ?? 'settings'"
						:title="f.label"
						:description="f.description"
					>
						<div class="control">
							<OzPill v-if="form[f.key] !== f.enabled" tone="warning">Kaydedilmedi</OzPill>
							<OzSwitch v-model="form[f.key]" :aria-label="f.label" />
						</div>
					</OzSettingRow>
				</div>
			</OzCard>
		</div>

		<aside class="side">
			<OzCard title="Mağaza senkronu">
				<dl class="facts">
					<dt>Durum</dt>
					<dd><OzPill :tone="syncState.tone">{{ syncState.label }}</OzPill></dd>
					<dt>Son gönderim</dt>
					<dd>{{ fmtDate(sync.synced_at) }}</dd>
					<template v-if="sync.state === 'pending'">
						<dt>İstek</dt>
						<dd>{{ fmtDate(sync.requested_at) }}</dd>
					</template>
					<dt>Mağaza</dt>
					<dd>
						<a v-if="storeUrl" :href="storeUrl" target="_blank" rel="noopener">{{ storeUrl.replace(/^https?:\/\//, '') }}</a>
						<template v-else>—</template>
					</dd>
				</dl>
				<p v-if="sync.state === 'failed' && sync.error" class="error">{{ sync.error }}</p>
				<div class="side-actions">
					<OzButton size="sm" icon="refresh" @click="refresh">Yenile</OzButton>
					<OzButton size="sm" icon="send" :loading="pushing" :disabled="!configured" @click="pushNow">Şimdi gönder</OzButton>
				</div>
			</OzCard>

			<p class="note">
				Değişiklikler kaydedildiğinde mağazaya otomatik gönderilir ve mağazanın sayfa önbelleği temizlenir.
				Mağaza bir özelliğin değerini henüz almadıysa kendi varsayılanını kullanır.
			</p>
		</aside>
	</div>

	<Transition name="bar">
		<div v-if="dirty" class="savebar">
			<span>Kaydedilmemiş değişiklikler var</span>
			<div class="savebar-actions">
				<OzButton :disabled="saving" @click="reset">Vazgeç</OzButton>
				<OzButton variant="primary" icon="check" :loading="saving" @click="save">Kaydet ve mağazaya gönder</OzButton>
			</div>
		</div>
	</Transition>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 14px; align-items: start; }
.rows { display: flex; flex-direction: column; gap: 10px; }
.control { display: flex; align-items: center; gap: 10px; }
.side { display: flex; flex-direction: column; gap: 12px; position: sticky; top: 84px; }
.facts { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 8px 12px; margin: 0; font-size: 13px; }
.facts dt { color: var(--oz-muted); }
.facts dd { margin: 0; color: var(--oz-ink-soft); overflow-wrap: anywhere; }
.facts a { color: var(--oz-accent); text-decoration: none; }
.error { margin: 12px 0 0; padding: 8px 10px; border-radius: 8px; background: var(--oz-danger-soft); color: var(--oz-danger); font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.side-actions { display: flex; gap: 8px; margin-top: 14px; }
.note { margin: 0; font-size: 12px; line-height: 1.55; color: var(--oz-muted); }
.savebar {
	position: sticky; bottom: 16px; z-index: 5; margin-top: 16px;
	display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
	padding: 10px 12px 10px 16px; border: 1px solid var(--oz-border); border-radius: 12px;
	background: var(--oz-surface); box-shadow: 0 8px 24px -8px rgba(0, 0, 0, .18);
	font-size: 13px; font-weight: 600; color: var(--oz-ink-soft);
}
.savebar-actions { display: flex; gap: 8px; }
.bar-enter-active, .bar-leave-active { transition: opacity .15s, transform .15s; }
.bar-enter-from, .bar-leave-to { opacity: 0; transform: translateY(8px); }
@media (max-width: 1040px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } }
</style>
