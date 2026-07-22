<template>
	<Head title="Poz Kütüphanesi" />
	<div class="page-poses">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Pozlar' },
			]"
		/>

		<CreativeNav current="poses" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Poz Kütüphanesi</h1>
				<p class="page-subtitle">Mankenden bağımsız pozlar; her poz nötr bir figürle önizlenir, giydirmede seçilir</p>
			</div>
			<div class="header-actions">
				<button type="button" class="btn btn-ghost" @click="refresh">Yenile</button>
				<button v-if="can('creative.asset.manage')" type="button" class="btn btn-primary btn-with-icon" :disabled="busy" @click="generateAll">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z" /></svg>
					{{ busy ? 'Kuyruğa alınıyor…' : `Katalog pozlarını üret (${catalogCount})` }}
				</button>
			</div>
		</div>

		<!-- Özel poz ekle -->
		<div v-if="can('creative.asset.manage')" class="card">
			<div class="card-header"><h3>Özel Poz Ekle</h3></div>
			<div class="card-body">
				<div class="add-row">
					<label class="field">
						<span class="field-label">Etiket <em>*</em></span>
						<input v-model="form.label" type="text" placeholder="örn. Elini cebine koymuş" />
					</label>
					<label class="field field-grow">
						<span class="field-label">Duruş yönergesi (İngilizce önerilir) <em>*</em></span>
						<input v-model="form.prompt" type="text" placeholder="e.g. standing with one hand in pocket, looking aside" />
					</label>
					<button class="btn btn-primary" :disabled="!canAdd || adding" @click="addPose">Ekle</button>
				</div>
			</div>
		</div>

		<!-- Poz grid -->
		<div class="card">
			<div class="card-header">
				<h3>Pozlar</h3>
				<span class="hint">{{ readyCount }}/{{ poses.length }} hazır</span>
			</div>
			<div class="card-body">
				<div v-if="poses.length === 0" class="empty-block">
					Henüz poz yok. "Katalog pozlarını üret" ile {{ catalogCount }} hazır pozu kuyruğa alın.
				</div>
				<div v-else class="pose-grid">
					<div v-for="p in poses" :key="p.id" class="pose-card">
						<div class="pose-thumb">
							<img v-if="p.preview_url" :src="p.preview_url" :alt="p.label" />
							<span v-else class="no-preview">{{ statusLabel(p.status) }}</span>
							<span class="status-badge" :class="p.status">{{ statusLabel(p.status) }}</span>
						</div>
						<div class="pose-meta">
							<span class="pose-label">{{ p.label }}</span>
							<span v-if="can('creative.asset.manage')" class="pose-actions">
								<button type="button" class="link-btn" @click="regenerate(p)">Yeniden</button>
								<button type="button" class="link-btn danger" @click="destroy(p)">Sil</button>
							</span>
						</div>
						<p v-if="p.error" class="pose-error" :title="p.error">⚠ {{ p.error }}</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	poses: { type: Array, default: () => [] },
	catalog_count: { type: Number, default: 0 },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')

const busy = ref(false)
const adding = ref(false)
const form = reactive({ label: '', prompt: '' })

const catalogCount = computed(() => props.catalog_count)
const readyCount = computed(() => props.poses.filter(p => p.status === 'ready').length)
const canAdd = computed(() => form.label.trim() && form.prompt.trim())
const hasPending = computed(() => props.poses.some(p => p.status === 'draft' || p.status === 'generating'))

const STATUS_LABELS = { draft: 'Taslak', generating: 'Üretiliyor', ready: 'Hazır', failed: 'Başarısız' }
function statusLabel(s) { return STATUS_LABELS[s] || s }

function generateAll() {
	if (busy.value) return
	busy.value = true
	router.post('/creative/poses/generate', {}, {
		preserveScroll: true,
		onFinish: () => { busy.value = false },
	})
}

function addPose() {
	if (!canAdd.value || adding.value) return
	adding.value = true
	router.post('/creative/poses', { ...form }, {
		preserveScroll: true,
		onSuccess: () => { form.label = ''; form.prompt = '' },
		onError: (errs) => showToast?.({ type: 'error', title: 'Eklenemedi', message: Object.values(errs)[0] || 'Hata.' }),
		onFinish: () => { adding.value = false },
	})
}

function regenerate(p) {
	router.post(`/creative/poses/${p.id}/regenerate`, {}, {
		preserveScroll: true,
	})
}

async function destroy(p) {
	const ok = await $swal.dangerConfirm({
		title: 'Pozu Sil',
		html: `<strong>${p.label}</strong> pozu ve önizlemesi silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/creative/poses/${p.id}`, {
		preserveScroll: true,
	})
}

function refresh() {
	router.reload({ only: ['poses'] })
}

let timer = null
onMounted(() => { timer = setInterval(() => { if (hasPending.value) refresh() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.header-actions { display: flex; gap: 10px; align-items: center; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

.add-row { display: flex; gap: 14px; align-items: flex-end; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-grow { flex: 1; }
.field-label { font-size: 12px; font-weight: 600; color: #555; }
.field-label em { color: rgb(var(--color-primary)); font-style: normal; }
.field input { border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 10px; font-family: inherit; font-size: 13px; color: #1a1a2e; outline: none; }
.field input:focus { border-color: rgb(var(--color-primary)); }

.pose-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
.pose-card { border: 1px solid #ebebf0; border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.pose-thumb { position: relative; aspect-ratio: 3 / 4; background: #f5f5f8; display: flex; align-items: center; justify-content: center; }
.pose-thumb img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { font-size: 11px; color: #bbb; font-weight: 700; }
.status-badge { position: absolute; top: 6px; left: 6px; font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 20px; color: #fff; text-transform: uppercase; letter-spacing: .03em; }
.status-badge.draft { background: #9ca3af; }
.status-badge.generating { background: #f59e0b; }
.status-badge.ready { background: #16a34a; }
.status-badge.failed { background: #dc2626; }
.pose-meta { padding: 8px 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pose-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.pose-actions { display: flex; gap: 8px; }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; white-space: nowrap; }
.link-btn.danger { color: #dc2626; }
.pose-error { font-size: 10px; color: #dc2626; padding: 0 10px 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.header-actions { width: 100%; flex-wrap: wrap; }
	.header-actions .btn { flex: 1 1 auto; justify-content: center; }

	.add-row { flex-wrap: wrap; }
	.field, .field-grow { width: 100%; }
	.add-row .btn { width: 100%; }

	.pose-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
}
</style>
