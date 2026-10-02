<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzButton, OzPill, OzAvatar, OzDrawer, OzModal,
	OzField, OzInput, OzSelect, OzCombobox, OzTextarea, OzFileDrop,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	assignments: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	counts: { type: Object, default: () => ({}) },
	canManage: { type: Boolean, default: false },
	assignees: { type: Array, default: () => [] },
	patterns: { type: Array, default: () => [] },
	designCards: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })

const KIND = { pattern_maker: 'Kalıpçı', designer: 'Tasarımcı' }
const kindOptions = Object.entries(KIND).map(([value, label]) => ({ value, label }))

/* ── filtre: bana atananlar ── */
const mine = ref(!!props.filters.mine)
function toggleMine() {
	mine.value = !mine.value
	router.get('/atelier/assignments', mine.value ? { mine: 1 } : {}, { preserveState: true, preserveScroll: true, replace: true })
}

/* ── şeritler ── */
const byStatus = (...s) => props.assignments.filter((a) => s.includes(a.status))
const lanes = computed(() => [
	{ key: 'pending', label: 'Atandı', icon: 'clock', items: byStatus('pending') },
	{ key: 'in_progress', label: 'Çalışılıyor', icon: 'edit', items: byStatus('in_progress') },
	{ key: 'delivered', label: 'Teslim Edildi', icon: 'upload', items: byStatus('delivered') },
	{ key: 'done', label: 'Sonuçlandı', icon: 'success', items: byStatus('accepted', 'rejected') },
])
const lateCount = computed(() => props.assignments.filter((a) => a.isLate).length)
const stats = computed(() => [
	{ key: 'pending', label: 'Atandı', value: byStatus('pending').length, icon: 'clock', tone: 'info' },
	{ key: 'progress', label: 'Çalışılıyor', value: byStatus('in_progress').length, icon: 'edit', tone: 'accent' },
	{ key: 'review', label: 'Onay Bekleyen', value: byStatus('delivered').length, icon: 'upload', tone: 'warning' },
	{ key: 'late', label: 'Termini Geçen', value: lateCount.value, icon: 'alert', tone: lateCount.value ? 'danger' : 'success' },
])

const busyId = ref(null)
function act(a, verb) {
	busyId.value = `${a.id}-${verb}`
	router.post(`/atelier/assignments/${a.id}/${verb}`, {}, {
		preserveScroll: true,
		onError: errorToast('İşlem yapılamadı'),
		onFinish: () => { busyId.value = null },
	})
}

/* ── yeni atama ── */
const assigneeOptions = computed(() => props.assignees.map((u) => ({ value: u.id, label: u.name })))
const patternOptions = computed(() => props.patterns.map((p) => ({ value: p.id, label: p.name, description: p.product_type })))
const cardOptions = computed(() => props.designCards.map((c) => ({ value: c.id, label: c.label })))

const emptyAssign = () => ({ title: '', kind: 'pattern_maker', assigned_to: '', pattern_id: '', design_card_id: '', instructions: '', due_date: '' })
const assignOpen = ref(false)
const assignForm = useForm(emptyAssign())
function openAssign() {
	assignForm.defaults(emptyAssign())
	assignForm.reset()
	assignForm.clearErrors()
	assignOpen.value = true
}
function submitAssign() {
	if (assignForm.processing) return
	// boş seçimler sunucuya null gider (nullable exists kuralları)
	assignForm.transform((d) => ({
		...d,
		assigned_to: d.assigned_to || null,
		pattern_id: d.pattern_id || null,
		design_card_id: d.design_card_id || null,
	})).post('/atelier/assignments', {
		preserveScroll: true,
		onSuccess: () => { assignOpen.value = false },
		onError: errorToast('Atama yapılamadı'),
	})
}

/* ── teslim ── */
const deliver = reactive({ open: false, item: null })
const deliverForm = useForm({ file: null, note: '' })
const deliverFiles = computed({
	get: () => (deliverForm.file ? [deliverForm.file] : []),
	set: (list) => { deliverForm.file = list[0] ?? null },
})
function openDeliver(a) {
	deliver.item = a
	deliverForm.reset()
	deliverForm.clearErrors()
	deliver.open = true
}
function submitDeliver() {
	if (deliverForm.processing) return
	deliverForm.post(`/atelier/assignments/${deliver.item.id}/deliver`, {
		forceFormData: true,
		preserveScroll: true,
		onSuccess: () => { deliver.open = false },
		onError: errorToast('Teslim edilemedi'),
	})
}

/* ── ret ── */
const reject = reactive({ open: false, item: null })
const rejectForm = useForm({ note: '' })
function openReject(a) {
	reject.item = a
	rejectForm.reset()
	rejectForm.clearErrors()
	reject.open = true
}
function submitReject() {
	if (rejectForm.processing) return
	rejectForm.post(`/atelier/assignments/${reject.item.id}/reject`, {
		preserveScroll: true,
		onSuccess: () => { reject.open = false },
		onError: errorToast('Reddedilemedi'),
	})
}
</script>

<template>
	<Head title="Atölye İş Akışı" />

	<OzPageHeader title="Atölye İş Akışı" subtitle="Tasarım kartı / kalıp → kalıpçıya ata, teslimi takip et.">
		<template #actions>
			<OzButton :variant="mine ? 'primary' : 'ghost'" icon="user" :aria-pressed="mine" @click="toggleMine">Bana Atananlar</OzButton>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openAssign">Yeni Atama</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<div class="board">
		<section v-for="lane in lanes" :key="lane.key" class="lane" :aria-label="lane.label">
			<header class="lane-head">
				<span class="lane-title">{{ lane.label }}</span>
				<span class="lane-count">{{ lane.items.length }}</span>
			</header>
			<div class="lane-body">
				<p v-if="!lane.items.length" class="lane-empty">Kayıt yok</p>
				<article v-for="a in lane.items" :key="a.id" class="card" :class="{ late: a.isLate }">
					<div class="card-top">
						<OzPill :tone="a.kind === 'designer' ? 'accent' : 'info'">{{ KIND[a.kind] ?? a.kind }}</OzPill>
						<span v-if="a.dueDate" class="due" :class="{ late: a.isLate }">{{ a.dueDate }}</span>
					</div>
					<h4 class="card-title">{{ a.title }}</h4>
					<div v-if="a.pattern || a.designCardId" class="refs">
						<span v-if="a.pattern" class="ref">Kalıp · {{ a.pattern.name }}</span>
						<span v-else class="ref">Konsept #{{ a.designCardId }}</span>
					</div>
					<p v-if="a.instructions" class="note">{{ a.instructions }}</p>
					<div class="people">
						<OzAvatar :name="a.assignee?.name || '?'" :size="22" tone="auto" />
						<span class="who">{{ a.assignee?.name || '—' }}</span>
						<OzPill v-if="a.isMine" tone="accent">sen</OzPill>
					</div>
					<p v-if="a.reviewNote" class="review">“{{ a.reviewNote }}”</p>
					<a v-if="a.deliveredUrl" :href="a.deliveredUrl" class="file" download>Teslim dosyasını indir</a>

					<div class="card-actions">
						<template v-if="a.status === 'pending'">
							<OzButton v-if="a.isMine || canManage" size="sm" variant="primary" :loading="busyId === `${a.id}-start`" @click="act(a, 'start')">Başla</OzButton>
						</template>
						<template v-else-if="a.status === 'in_progress'">
							<OzButton v-if="a.isMine || canManage" size="sm" variant="primary" icon="upload" @click="openDeliver(a)">Teslim Et</OzButton>
						</template>
						<template v-else-if="a.status === 'delivered'">
							<OzButton v-if="canManage" size="sm" variant="primary" icon="check" :loading="busyId === `${a.id}-accept`" @click="act(a, 'accept')">Kabul</OzButton>
							<OzButton v-if="canManage" size="sm" variant="danger" @click="openReject(a)">Reddet</OzButton>
						</template>
						<template v-else-if="a.status === 'rejected'">
							<OzPill tone="danger">reddedildi</OzPill>
							<OzButton v-if="a.isMine || canManage" size="sm" icon="refresh" :loading="busyId === `${a.id}-start`" @click="act(a, 'start')">Yeniden Başla</OzButton>
						</template>
						<OzPill v-else tone="success">kabul edildi</OzPill>
					</div>
				</article>
			</div>
		</section>
	</div>

	<!-- Yeni atama -->
	<OzDrawer v-model:open="assignOpen" title="Yeni Atama" subtitle="Kalıp ya da konsept kartını bir kişiye ata" icon="user-plus" size="md" :persistent="assignForm.processing">
		<form class="form" @submit.prevent="submitAssign">
			<OzField label="İş başlığı" required :error="assignForm.errors.title">
				<OzInput v-model="assignForm.title" placeholder="örn. Kalıbı 116-134 bedene çıkar" />
			</OzField>
			<div class="row-2">
				<OzField label="Tür" :error="assignForm.errors.kind">
					<OzSelect v-model="assignForm.kind" :options="kindOptions" />
				</OzField>
				<OzField label="Atanan kişi" required :error="assignForm.errors.assigned_to">
					<OzCombobox v-model="assignForm.assigned_to" :options="assigneeOptions" placeholder="Kişi seçin" search-placeholder="İsim ara…" />
				</OzField>
			</div>
			<div class="row-2">
				<OzField label="Kalıp" :error="assignForm.errors.pattern_id" hint="Yalnızca onaylı kalıplar">
					<OzCombobox v-model="assignForm.pattern_id" :options="patternOptions" placeholder="Yok" clearable />
				</OzField>
				<OzField label="Konsept kartı" :error="assignForm.errors.design_card_id">
					<OzCombobox v-model="assignForm.design_card_id" :options="cardOptions" placeholder="Yok" clearable />
				</OzField>
			</div>
			<OzField label="Talimat" :error="assignForm.errors.instructions">
				<OzTextarea v-model="assignForm.instructions" :rows="3" placeholder="Detaylar (opsiyonel)" />
			</OzField>
			<OzField label="Termin" :error="assignForm.errors.due_date">
				<OzInput v-model="assignForm.due_date" type="date" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="assignForm.processing" @click="assignOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="assignForm.processing" @click="submitAssign">Ata</OzButton>
		</template>
	</OzDrawer>

	<!-- Teslim -->
	<OzDrawer v-model:open="deliver.open" title="İşi Teslim Et" :subtitle="deliver.item?.title ?? ''" icon="upload" size="sm" :persistent="deliverForm.processing">
		<form class="form" @submit.prevent="submitDeliver">
			<OzField label="Teslim dosyası (opsiyonel)" :error="deliverForm.errors.file">
				<OzFileDrop v-model="deliverFiles" :max-size="50 * 1024 * 1024" title="Dosyayı buraya bırakın" hint="Revize DXF / PDF · en fazla 50 MB" />
			</OzField>
			<OzField label="Not" :error="deliverForm.errors.note">
				<OzTextarea v-model="deliverForm.note" :rows="3" placeholder="Teslim notu (opsiyonel)" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="deliverForm.processing" @click="deliver.open = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="deliverForm.processing" @click="submitDeliver">Teslim Et</OzButton>
		</template>
	</OzDrawer>

	<!-- Ret -->
	<OzModal v-model:open="reject.open" title="İşi Reddet" :subtitle="reject.item?.title ?? ''" icon="x" tone="danger" size="sm" :persistent="rejectForm.processing">
		<OzField label="Ret gerekçesi" :error="rejectForm.errors.note" hint="Kişiye kartta gösterilir">
			<OzTextarea v-model="rejectForm.note" :rows="3" placeholder="Neden geri gönderiliyor?" />
		</OzField>
		<template #footer>
			<OzButton :disabled="rejectForm.processing" @click="reject.open = false">Vazgeç</OzButton>
			<OzButton variant="danger" :loading="rejectForm.processing" @click="submitReject">Reddet</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>

.board { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: start; }
.lane { background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: var(--oz-radius); padding: 10px; min-height: 140px; }
.lane-head { display: flex; align-items: center; justify-content: space-between; padding: 2px 4px 10px; }
.lane-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.lane-count { min-width: 22px; text-align: center; font-size: 11px; font-weight: 700; color: var(--oz-muted); background: var(--oz-surface); border: 1px solid var(--oz-border); padding: 0 7px; border-radius: 999px; }
.lane-body { display: flex; flex-direction: column; gap: 10px; }
.lane-empty { margin: 0; padding: 14px 0; text-align: center; font-size: 12px; color: var(--oz-muted-2, var(--oz-muted)); }

.card { display: flex; flex-direction: column; gap: 8px; padding: 12px; background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); box-shadow: var(--oz-shadow-sm); }
.card.late { border-color: rgba(var(--oz-danger-rgb), .45); }
.card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.due { font-family: ui-monospace, monospace; font-size: 11px; color: var(--oz-muted); }
.due.late { color: var(--oz-danger); font-weight: 700; }
.card-title { margin: 0; font-size: 13.5px; font-weight: 700; line-height: 1.3; color: var(--oz-ink-soft); }
.refs { display: flex; flex-wrap: wrap; gap: 5px; }
.ref { font-size: 11px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 1px 7px; border-radius: 5px; }
.note { margin: 0; font-size: 12px; line-height: 1.45; color: var(--oz-muted); }
.people { display: flex; align-items: center; gap: 7px; }
.who { font-size: 12px; font-weight: 600; color: var(--oz-ink); }
.review { margin: 0; font-size: 11.5px; font-style: italic; color: var(--oz-warning); background: var(--oz-warning-soft); padding: 5px 8px; border-radius: 6px; }
.file { font-size: 12px; font-weight: 700; color: var(--oz-accent); text-decoration: none; }
.file:hover { text-decoration: underline; }
.card-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding-top: 8px; border-top: 1px solid var(--oz-border-soft); }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

@media (max-width: 1100px) { .board { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) {
	.board { grid-template-columns: 1fr; }
	.row-2 { grid-template-columns: 1fr; }
}
</style>
