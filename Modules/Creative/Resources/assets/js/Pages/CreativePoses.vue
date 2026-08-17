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

		<PageHeader title="Poz Kütüphanesi" subtitle="Mankenden bağımsız pozlar; her poz nötr bir figürle önizlenir, giydirmede seçilir">
			<template #actions>
				<Button variant="ghost" @click="refresh">Yenile</Button>
				<Button v-if="can('creative.asset.manage')" variant="primary" with-icon :loading="busy" @click="generateAll">
					<template #leading><Sparkles :size="14" /></template>
					Katalog pozlarını üret ({{ catalogCount }})
				</Button>
			</template>
		</PageHeader>

		<!-- Özel poz ekle -->
		<Card v-if="can('creative.asset.manage')" title="Özel Poz Ekle">
			<div class="add-row">
				<label class="field">
					<span class="field-label">Etiket <em>*</em></span>
					<input v-model="form.label" type="text" placeholder="örn. Elini cebine koymuş" />
				</label>
				<label class="field field-grow">
					<span class="field-label">Duruş yönergesi (İngilizce önerilir) <em>*</em></span>
					<input v-model="form.prompt" type="text" placeholder="e.g. standing with one hand in pocket, looking aside" />
				</label>
				<Button variant="primary" :disabled="!canAdd" :loading="adding" @click="addPose">Ekle</Button>
			</div>
		</Card>

		<!-- Poz grid -->
		<Card title="Pozlar">
			<template #actions>
				<span class="hint">{{ readyCount }}/{{ poses.length }} hazır</span>
			</template>

			<EmptyState
				v-if="poses.length === 0"
				:icon="Footprints"
				title="Henüz poz yok"
				:hint="`«Katalog pozlarını üret» ile ${catalogCount} hazır pozu kuyruğa alın.`"
			/>
			<div v-else class="pose-grid">
				<div v-for="p in poses" :key="p.id" class="pose-card">
					<div class="pose-thumb">
						<img v-if="p.preview_url" :src="p.preview_url" :alt="p.label" />
						<span v-else class="no-preview">{{ statusLabel(p.status) }}</span>
						<Badge class="status-badge" :color="statusColor(p.status)" :label="statusLabel(p.status)" variant="filled" />
					</div>
					<div class="pose-meta">
						<span class="pose-label">{{ p.label }}</span>
						<span v-if="can('creative.asset.manage')" class="pose-actions">
							<button type="button" class="link-btn" @click="regenerate(p)">Yeniden</button>
							<button type="button" class="link-btn danger" @click="destroy(p)">Sil</button>
						</span>
					</div>
					<p v-if="p.error" class="pose-error" :title="p.error"><AlertTriangle :size="10" /> {{ p.error }}</p>
				</div>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Sparkles, Footprints, AlertTriangle } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

const STATUS_COLORS = { draft: 'neutral', generating: 'warning', ready: 'success', failed: 'danger' }
function statusColor(s) { return STATUS_COLORS[s] ?? 'neutral' }

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
.hint { font-size: 12px; color: var(--color-muted); white-space: nowrap; }

.add-row { display: flex; gap: 14px; align-items: flex-end; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-grow { flex: 1; }
.field-label { font-size: 12px; font-weight: 600; color: var(--color-on-surface-variant); }
.field-label em { color: var(--color-primary); font-style: normal; }
.field input { border: 1px solid var(--color-outline-variant); border-radius: 8px; padding: 8px 10px; font-family: inherit; font-size: 13px; color: var(--color-ink); outline: none; background: var(--color-surface); }
.field input:focus { border-color: var(--color-primary); }

.pose-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
.pose-card { border: 1px solid var(--color-outline-variant); border-radius: 12px; overflow: hidden; background: var(--color-surface); display: flex; flex-direction: column; }
.pose-thumb { position: relative; aspect-ratio: 3 / 4; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; }
.pose-thumb img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { font-size: 11px; color: var(--color-muted); font-weight: 700; }
.status-badge { position: absolute; top: 6px; left: 6px; }
.pose-meta { padding: 8px 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pose-label { font-size: 12px; font-weight: 600; color: var(--color-ink); }
.pose-actions { display: flex; gap: 8px; }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; white-space: nowrap; }
.link-btn.danger { color: var(--color-danger); }
.pose-error { display: flex; align-items: center; gap: 3px; font-size: 10px; color: var(--color-danger); padding: 0 10px 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.add-row { flex-wrap: wrap; }
	.field, .field-grow { width: 100%; }
	.add-row .btn { width: 100%; }

	.pose-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
}
</style>
