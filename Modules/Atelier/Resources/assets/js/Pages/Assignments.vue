<template>
	<Head title="Atölye İş Akışı" />
	<div class="page-assign">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Atölye İşleri' },
			]"
		/>

		<AtelierNav current="assignments" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Atölye İş Akışı</h1>
				<p class="page-subtitle">Tasarım kartı / kalıp → kalıpçıya ata, teslimi takip et.</p>
			</div>
			<div class="head-actions">
				<button class="toggle-mine" :class="{ active: filters.mine }" @click="toggleMine">Bana atananlar</button>
				<button v-if="canManage" class="btn btn-primary" @click="openAssign">+ Yeni Atama</button>
			</div>
		</div>

		<!-- İş akışı şeritleri -->
		<div class="lanes">
			<section v-for="lane in lanes" :key="lane.key" class="lane">
				<div class="lane-head">
					<span class="lane-title">{{ lane.label }}</span>
					<span class="lane-count">{{ lane.items.length }}</span>
				</div>
				<div class="lane-body">
					<p v-if="lane.items.length === 0" class="lane-empty">—</p>
					<article v-for="a in lane.items" :key="a.id" class="assign-card" :class="{ late: a.isLate }">
						<div class="ac-top">
							<span class="kind-pill" :class="a.kind">{{ a.kind === 'designer' ? 'Tasarımcı' : 'Kalıpçı' }}</span>
							<span v-if="a.dueDate" class="due" :class="{ late: a.isLate }">{{ a.dueDate }}</span>
						</div>
						<h4 class="ac-title">{{ a.title }}</h4>
						<div class="ac-refs">
							<span v-if="a.pattern" class="ref">◫ {{ a.pattern.name }}</span>
							<span v-else-if="a.designCardId" class="ref">✦ konsept #{{ a.designCardId }}</span>
						</div>
						<p v-if="a.instructions" class="ac-note">{{ a.instructions }}</p>
						<div class="ac-people">
							<span class="who">{{ a.assignee?.name || '—' }}</span>
							<span v-if="a.isMine" class="mine-tag">sen</span>
						</div>

						<div v-if="a.reviewNote" class="ac-review">“{{ a.reviewNote }}”</div>
						<a v-if="a.deliveredUrl" :href="a.deliveredUrl" class="ac-file" download>Teslim dosyası ↓</a>

						<div class="ac-actions">
							<template v-if="a.status === 'pending'">
								<button v-if="a.isMine || canManage" class="btn btn-ok btn-sm" @click="act(a, 'start')">Başla</button>
							</template>
							<template v-else-if="a.status === 'in_progress'">
								<button v-if="a.isMine || canManage" class="btn btn-ok btn-sm" @click="openDeliver(a)">Teslim et</button>
							</template>
							<template v-else-if="a.status === 'delivered'">
								<button v-if="canManage" class="btn btn-ok btn-sm" @click="act(a, 'accept')">Kabul</button>
								<button v-if="canManage" class="btn btn-rej btn-sm" @click="openReject(a)">Reddet</button>
							</template>
							<template v-else-if="a.status === 'rejected'">
								<button v-if="a.isMine || canManage" class="btn btn-ghost btn-sm" @click="act(a, 'start')">Yeniden başla</button>
							</template>
							<span v-else class="done-tag">✓ kabul edildi</span>
						</div>
					</article>
				</div>
			</section>
		</div>

		<!-- Atama modalı -->
		<div v-if="assign.open" class="modal-overlay" @click.self="assign.open = false">
			<div class="modal-box">
				<div class="modal-head"><span class="modal-title">Yeni Atama</span><button class="modal-close" @click="assign.open = false">✕</button></div>
				<form @submit.prevent="submitAssign" class="form-grid">
					<div class="form-row">
						<label class="form-label">İş başlığı <span class="req">*</span></label>
						<input v-model="assign.form.title" class="form-input" placeholder="örn. Kalıbı 116-134 bedene çıkar" />
						<span v-if="assign.form.errors.title" class="form-error">{{ assign.form.errors.title }}</span>
					</div>
					<div class="form-2">
						<div class="form-row">
							<label class="form-label">Tür</label>
							<select v-model="assign.form.kind" class="form-input">
								<option value="pattern_maker">Kalıpçı</option>
								<option value="designer">Tasarımcı</option>
							</select>
						</div>
						<div class="form-row">
							<label class="form-label">Atanan kişi <span class="req">*</span></label>
							<select v-model="assign.form.assigned_to" class="form-input">
								<option :value="null" disabled>Seç…</option>
								<option v-for="u in assignees" :key="u.id" :value="u.id">{{ u.name }}</option>
							</select>
							<span v-if="assign.form.errors.assigned_to" class="form-error">{{ assign.form.errors.assigned_to }}</span>
						</div>
					</div>
					<div class="form-2">
						<div class="form-row">
							<label class="form-label">Kalıp</label>
							<select v-model="assign.form.pattern_id" class="form-input">
								<option :value="null">— yok —</option>
								<option v-for="p in patterns" :key="p.id" :value="p.id">{{ p.name }} · {{ p.product_type }}</option>
							</select>
						</div>
						<div class="form-row">
							<label class="form-label">Konsept kartı</label>
							<select v-model="assign.form.design_card_id" class="form-input">
								<option :value="null">— yok —</option>
								<option v-for="c in designCards" :key="c.id" :value="c.id">{{ c.label }}</option>
							</select>
						</div>
					</div>
					<span v-if="assign.form.errors.pattern_id" class="form-error">{{ assign.form.errors.pattern_id }}</span>
					<div class="form-row">
						<label class="form-label">Talimat</label>
						<textarea v-model="assign.form.instructions" rows="2" class="form-input" placeholder="Detaylar (ops.)"></textarea>
					</div>
					<div class="form-row">
						<label class="form-label">Termin</label>
						<input v-model="assign.form.due_date" type="date" class="form-input" />
					</div>
					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="assign.open = false">Vazgeç</button>
						<button type="submit" class="btn btn-primary" :disabled="assign.form.processing">Ata</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Teslim modalı -->
		<div v-if="deliver.open" class="modal-overlay" @click.self="deliver.open = false">
			<div class="modal-box">
				<div class="modal-head"><span class="modal-title">İşi teslim et</span><button class="modal-close" @click="deliver.open = false">✕</button></div>
				<form @submit.prevent="submitDeliver" class="form-grid">
					<label class="file-drop">
						<span class="fd-label">Teslim dosyası (revize DXF/PDF — ops.)</span>
						<input type="file" @change="deliver.form.file = $event.target.files[0] || null" />
						<span class="fd-name">{{ deliver.form.file ? deliver.form.file.name : 'dosya seç' }}</span>
					</label>
					<div class="form-row">
						<label class="form-label">Not</label>
						<textarea v-model="deliver.form.note" rows="2" class="form-input" placeholder="Teslim notu (ops.)"></textarea>
					</div>
					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="deliver.open = false">Vazgeç</button>
						<button type="submit" class="btn btn-primary" :disabled="deliver.form.processing">Teslim et</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Ret modalı -->
		<div v-if="reject.open" class="modal-overlay" @click.self="reject.open = false">
			<div class="modal-box">
				<div class="modal-head"><span class="modal-title">İşi reddet</span><button class="modal-close" @click="reject.open = false">✕</button></div>
				<form @submit.prevent="submitReject" class="form-grid">
					<div class="form-row">
						<label class="form-label">Ret gerekçesi</label>
						<textarea v-model="reject.form.note" rows="3" class="form-input" placeholder="Neden geri gönderiliyor?"></textarea>
					</div>
					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="reject.open = false">Vazgeç</button>
						<button type="submit" class="btn btn-rej" :disabled="reject.form.processing">Reddet</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

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

const filters = reactive({ mine: !!props.filters.mine, status: props.filters.status || '' })
function toggleMine() {
	filters.mine = !filters.mine
	router.get('/atelier/assignments', { mine: filters.mine ? 1 : 0 }, { preserveState: true, preserveScroll: true, replace: true })
}

const lanes = computed(() => [
	{ key: 'pending', label: 'Atandı', items: byStatus('pending') },
	{ key: 'in_progress', label: 'Çalışılıyor', items: byStatus('in_progress') },
	{ key: 'delivered', label: 'Teslim', items: byStatus('delivered') },
	{ key: 'done', label: 'Sonuçlandı', items: props.assignments.filter((a) => ['accepted', 'rejected'].includes(a.status)) },
])
function byStatus(s) { return props.assignments.filter((a) => a.status === s) }

function act(a, verb) {
	router.post(`/atelier/assignments/${a.id}/${verb}`, {}, { preserveScroll: true })
}

/* Atama */
const assign = reactive({ open: false, form: useForm({ title: '', kind: 'pattern_maker', assigned_to: null, pattern_id: null, design_card_id: null, instructions: '', due_date: '' }) })
function openAssign() { assign.form.reset(); assign.form.clearErrors(); assign.open = true }
function submitAssign() { assign.form.post('/atelier/assignments', { preserveScroll: true, onSuccess: () => { assign.open = false } }) }

/* Teslim */
const deliver = reactive({ open: false, id: null, form: useForm({ file: null, note: '' }) })
function openDeliver(a) { deliver.id = a.id; deliver.form.reset(); deliver.open = true }
function submitDeliver() {
	deliver.form.post(`/atelier/assignments/${deliver.id}/deliver`, { forceFormData: true, preserveScroll: true, onSuccess: () => { deliver.open = false } })
}

/* Ret */
const reject = reactive({ open: false, id: null, form: useForm({ note: '' }) })
function openReject(a) { reject.id = a.id; reject.form.reset(); reject.open = true }
function submitReject() {
	reject.form.post(`/atelier/assignments/${reject.id}/reject`, { preserveScroll: true, onSuccess: () => { reject.open = false } })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.head-actions { display: flex; gap: 10px; align-items: center; }
.toggle-mine { padding: 8px 14px; border: 1px solid #ebebf0; background: #fff; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: #777; cursor: pointer; transition: all .15s; }
.toggle-mine.active { background: rgb(var(--color-primary)); color: #fff; border-color: rgb(var(--color-primary)); }

/* Şeritler */
.lanes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; align-items: start; }
.lane { background: #f7f7fb; border: 1px solid #ededf2; border-radius: 14px; padding: 10px; min-height: 120px; }
.lane-head { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 10px; }
.lane-title { font-size: 12.5px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .03em; }
.lane-count { font-size: 11px; font-weight: 600; color: #999; background: #fff; padding: 1px 8px; border-radius: 10px; }
.lane-body { display: flex; flex-direction: column; gap: 10px; }
.lane-empty { text-align: center; color: #ccc; font-size: 13px; padding: 10px 0; }

.assign-card { background: #fff; border: 1px solid #ebebf0; border-radius: 11px; padding: 12px; display: flex; flex-direction: column; gap: 7px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.assign-card.late { border-color: #fca5a5; }
.ac-top { display: flex; align-items: center; justify-content: space-between; }
.kind-pill { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px; }
.kind-pill.pattern_maker { background: #eff6ff; color: #2563eb; }
.kind-pill.designer { background: #f5f3ff; color: #7c3aed; }
.due { font-size: 11px; color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.due.late { color: #dc2626; font-weight: 700; }
.ac-title { font-size: 13.5px; font-weight: 700; color: #1a1a2e; line-height: 1.3; }
.ac-refs { display: flex; flex-wrap: wrap; gap: 5px; }
.ref { font-size: 11px; color: #666; background: #f4f4f8; padding: 2px 8px; border-radius: 5px; }
.ac-note { font-size: 12px; color: #888; line-height: 1.4; }
.ac-people { display: flex; align-items: center; gap: 7px; }
.who { font-size: 12px; font-weight: 600; color: #555; }
.mine-tag { font-size: 10px; font-weight: 700; background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); padding: 1px 7px; border-radius: 10px; }
.ac-review { font-size: 11.5px; color: #b45309; background: #fffbeb; padding: 5px 8px; border-radius: 6px; font-style: italic; }
.ac-file { font-size: 11.5px; font-weight: 700; color: #4f46e5; text-decoration: none; }
.ac-file:hover { text-decoration: underline; }
.ac-actions { display: flex; gap: 6px; margin-top: 2px; }
.done-tag { font-size: 11.5px; font-weight: 600; color: #059669; }

.btn { padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-primary { background: rgb(var(--color-primary)); color: #fff; }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; }
.btn-ghost { background: #f3f4f6; color: #555; }
.btn-ok { background: #059669; color: #fff; }
.btn-rej { background: #fef2f2; color: #dc2626; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: flex-start; justify-content: center; z-index: 9000; padding: 40px 16px; overflow-y: auto; }
.modal-box { background: #fff; border-radius: 16px; padding: 22px 24px; width: 460px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; gap: 14px; box-shadow: 0 8px 40px rgba(0,0,0,.15); }
.modal-head { display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: #888; padding: 2px 6px; border-radius: 6px; }
.modal-close:hover { background: #f0f0f5; }
.modal-foot { display: flex; gap: 8px; justify-content: flex-end; padding-top: 4px; }
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; width: 100%; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
textarea.form-input { resize: vertical; }
.form-error { font-size: 11.5px; color: #ef4444; }
.file-drop { display: flex; flex-direction: column; gap: 4px; border: 1px dashed #d8d8e2; border-radius: 9px; padding: 12px; cursor: pointer; }
.file-drop:hover { border-color: rgb(var(--color-primary)); }
.file-drop input[type=file] { display: none; }
.fd-label { font-size: 12px; font-weight: 600; color: #555; }
.fd-name { font-size: 11.5px; color: #999; }

@media (max-width: 900px) { .lanes { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .lanes { grid-template-columns: 1fr; } .form-2 { grid-template-columns: 1fr; } }

@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.head-actions { width: 100%; }
	.toggle-mine, .head-actions .btn { flex: 1; text-align: center; }
}
</style>
