<template>
	<Head title="Ret Seçim Maddeleri" />
	<div class="page-rejection-reasons">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Ret Seçim Maddeleri' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Ret Seçim Maddeleri</h1>
				<p class="page-subtitle">
					Creative Galerisi, Manken ve Model Giydirme reddedilirken çıkan "düzeltilmesi gereken alan"
					seçenekleri. Bir madde "Tüm ekranlar" bağlamındaysa üçünde de görünür; belirli bir ekrana
					atarsanız yalnız orada çıkar. İşaretlenen maddeler AI sohbet asistanına otomatik aktarılır.
				</p>
			</div>
		</div>

		<!-- Yeni madde formu -->
		<div class="card">
			<div class="card-header">
				<h3>Yeni Madde Ekle</h3>
				<span class="hint">Örn. kategori "Düzeltilmesi gereken alan", etiket "Göz"</span>
			</div>
			<div class="card-body">
				<div class="form-grid">
					<label class="field">
						<span class="field-label">Kategori</span>
						<input
							v-model="form.category"
							type="text"
							list="category-list"
							placeholder="Düzeltilmesi gereken alan"
						/>
						<datalist id="category-list">
							<option v-for="c in categories" :key="c" :value="c" />
						</datalist>
					</label>
					<label class="field">
						<span class="field-label">Ekran</span>
						<select v-model="form.context">
							<option value="">Tüm ekranlar</option>
							<option v-for="(label, key) in contexts" :key="key" :value="key">{{ label }}</option>
						</select>
					</label>
					<label class="field">
						<span class="field-label">Etiket <em>*</em></span>
						<input v-model="form.label" type="text" placeholder="örn. Göz, Burun, Gülüş" @keyup.enter="create" />
					</label>
					<label class="field field-wide">
						<span class="field-label">AI ipucu (opsiyonel)</span>
						<input v-model="form.hint" type="text" placeholder="İngilizce kısa açıklama — örn. eyes shape or gaze" />
					</label>
					<label class="field field-narrow">
						<span class="field-label">Sıra</span>
						<input v-model.number="form.sort_order" type="number" min="0" placeholder="0" />
					</label>
				</div>
				<div class="form-actions">
					<button class="btn btn-primary btn-with-icon" :disabled="!canCreate || busy" @click="create">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M12 5v14M5 12h14" />
						</svg>
						{{ busy ? 'Ekleniyor…' : 'Madde Ekle' }}
					</button>
				</div>
			</div>
		</div>

		<!-- Mevcut maddeler -->
		<div class="card">
			<div class="card-header">
				<h3>Maddeler</h3>
				<div class="context-filter">
					<button
						v-for="tab in filterTabs"
						:key="tab.key"
						class="filter-tab"
						:class="{ active: contextFilter === tab.key }"
						@click="contextFilter = tab.key"
					>
						{{ tab.label }}
					</button>
				</div>
				<span class="hint">{{ filteredReasons.length }} madde</span>
			</div>
			<div class="card-body">
				<div v-if="filteredReasons.length === 0" class="empty-block">
					{{ reasons.length === 0 ? 'Henüz madde yok. Yukarıdan ekleyin.' : 'Bu ekranda madde yok.' }}
				</div>
				<div v-else class="groups">
					<div v-for="group in grouped" :key="group.category" class="group">
						<div class="group-title">{{ group.category }}</div>
						<div class="rows">
							<div v-for="item in group.items" :key="item.id" class="row" :class="{ inactive: !item.is_active }">
								<template v-if="editingId === item.id">
									<input v-model="edit.category" class="row-input" type="text" />
									<select v-model="edit.context" class="row-input narrow">
										<option value="">Tüm ekranlar</option>
										<option v-for="(label, key) in contexts" :key="key" :value="key">{{ label }}</option>
									</select>
									<input v-model="edit.label" class="row-input" type="text" />
									<input v-model="edit.hint" class="row-input flex" type="text" placeholder="AI ipucu" />
									<input v-model.number="edit.sort_order" class="row-input narrow" type="number" min="0" />
									<div class="row-actions">
										<button class="mini-btn save" :disabled="busy || !edit.label.trim()" @click="saveEdit(item)">Kaydet</button>
										<button class="mini-btn" @click="cancelEdit">Vazgeç</button>
									</div>
								</template>
								<template v-else>
									<span class="row-label">{{ item.label }}</span>
									<span class="context-badge" :class="`context-${item.context || 'all'}`">
										{{ item.context ? contexts[item.context] : 'Tüm ekranlar' }}
									</span>
									<span class="row-hint">{{ item.hint || '—' }}</span>
									<span class="row-order">#{{ item.sort_order }}</span>
									<label class="row-toggle" :title="item.is_active ? 'Aktif' : 'Pasif'">
										<input type="checkbox" :checked="item.is_active" @change="toggleActive(item)" />
										<span>{{ item.is_active ? 'Aktif' : 'Pasif' }}</span>
									</label>
									<div class="row-actions">
										<button class="mini-btn" @click="startEdit(item)">Düzenle</button>
										<button class="mini-btn danger" @click="destroy(item)">Kaldır</button>
									</div>
								</template>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	reasons: { type: Array, default: () => [] },
	contexts: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')

const busy = ref(false)
const editingId = ref(null)
const contextFilter = ref('')
const form = reactive({ category: '', context: '', label: '', hint: '', sort_order: 0 })
const edit = reactive({ category: '', context: '', label: '', hint: '', sort_order: 0 })

const canCreate = computed(() => form.label.trim().length > 0)

const categories = computed(() => [...new Set(props.reasons.map(r => r.category))])

const filterTabs = computed(() => [
	{ key: '', label: 'Tümü' },
	...Object.entries(props.contexts).map(([key, label]) => ({ key, label })),
])

const filteredReasons = computed(() => {
	if (!contextFilter.value) return props.reasons
	// Belirli bir ekran seçiliyse: o ekrana özel maddeler + "tüm ekranlar" maddeleri.
	return props.reasons.filter(r => !r.context || r.context === contextFilter.value)
})

const grouped = computed(() => {
	const map = new Map()
	for (const r of filteredReasons.value) {
		if (!map.has(r.category)) map.set(r.category, [])
		map.get(r.category).push(r)
	}
	return [...map.entries()].map(([category, items]) => ({ category, items }))
})

function onError(errs, title) {
	showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })
}

function create() {
	if (!canCreate.value || busy.value) return
	busy.value = true
	router.post('/creative/rejection-reasons', {
		category: form.category.trim() || 'Düzeltilmesi gereken alan',
		context: form.context || null,
		label: form.label.trim(),
		hint: form.hint.trim() || null,
		sort_order: form.sort_order || 0,
		is_active: true,
	}, {
		preserveScroll: true,
		onSuccess: () => { form.label = ''; form.hint = '' },
		onError: (errs) => onError(errs, 'Eklenemedi'),
		onFinish: () => { busy.value = false },
	})
}

function startEdit(item) {
	editingId.value = item.id
	edit.category = item.category
	edit.context = item.context || ''
	edit.label = item.label
	edit.hint = item.hint || ''
	edit.sort_order = item.sort_order
}

function cancelEdit() {
	editingId.value = null
}

function saveEdit(item) {
	if (busy.value || !edit.label.trim()) return
	busy.value = true
	router.put(`/creative/rejection-reasons/${item.id}`, {
		category: edit.category.trim() || 'Düzeltilmesi gereken alan',
		context: edit.context || null,
		label: edit.label.trim(),
		hint: edit.hint.trim() || null,
		sort_order: edit.sort_order || 0,
		is_active: item.is_active,
	}, {
		preserveScroll: true,
		onSuccess: () => { editingId.value = null },
		onError: (errs) => onError(errs, 'Güncellenemedi'),
		onFinish: () => { busy.value = false },
	})
}

function toggleActive(item) {
	router.put(`/creative/rejection-reasons/${item.id}`, {
		category: item.category,
		context: item.context || null,
		label: item.label,
		hint: item.hint || null,
		sort_order: item.sort_order,
		is_active: !item.is_active,
	}, {
		preserveScroll: true,
		onError: (errs) => onError(errs, 'Güncellenemedi'),
	})
}

async function destroy(item) {
	const ok = await $swal.dangerConfirm({
		title: 'Maddeyi Kaldır',
		html: `<strong>${item.label}</strong> ret seçeneklerinden kaldırılacak.`,
		confirmText: 'Kaldır',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/creative/rejection-reasons/${item.id}`, {
		preserveScroll: true,
		onError: (errs) => onError(errs, 'Kaldırılamadı'),
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

.context-filter { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-tab { border: 1px solid #e5e5ee; background: #fff; color: #666; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 999px; cursor: pointer; font-family: inherit; }
.filter-tab:hover { background: #f5f5f8; }
.filter-tab.active { background: rgb(var(--color-primary)); color: #fff; border-color: transparent; }

.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-wide { grid-column: 1 / -1; }
.field-narrow { max-width: 120px; }
.field-label { font-size: 12px; font-weight: 600; color: #555; }
.field-label em { color: rgb(var(--color-primary)); font-style: normal; }
.field input, .field select { border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 10px; font-family: inherit; font-size: 13px; color: #1a1a2e; outline: none; background: #fff; }
.field input:focus, .field select:focus { border-color: rgb(var(--color-primary)); }
.form-actions { margin-top: 16px; display: flex; justify-content: flex-end; }

.groups { display: flex; flex-direction: column; gap: 18px; }
.group-title { font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 8px; }
.rows { display: flex; flex-direction: column; gap: 6px; }
.row { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border: 1px solid #f0f0f5; border-radius: 10px; background: #fafafc; }
.row.inactive { opacity: .55; }
.row-label { font-size: 13px; font-weight: 600; color: #1a1a2e; min-width: 120px; }
.context-badge { font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #eef0ff; color: #4c4dc9; white-space: nowrap; }
.context-badge.context-all { background: #f0f0f5; color: #777; }
.context-badge.context-gallery { background: #eaf5ee; color: #2f8f52; }
.context-badge.context-mannequin { background: #fff2e0; color: #b9711a; }
.context-badge.context-tryon { background: #fdeaf3; color: #c23a86; }
.row-hint { font-size: 12px; color: #999; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-order { font-size: 11px; color: #bbb; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.row-toggle { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #888; cursor: pointer; }
.row-toggle input { cursor: pointer; }
.row-actions { display: flex; gap: 6px; }
.row-input { border: 1px solid #e8e8f0; border-radius: 7px; padding: 6px 9px; font-family: inherit; font-size: 12.5px; color: #1a1a2e; outline: none; }
.row-input.flex { flex: 1; }
.row-input.narrow { max-width: 120px; }
.row-input:focus { border-color: rgb(var(--color-primary)); }
.mini-btn { border: 1px solid #e5e5ee; background: #fff; color: #555; font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 7px; cursor: pointer; font-family: inherit; }
.mini-btn:hover:not(:disabled) { background: #f5f5f8; }
.mini-btn:disabled { opacity: .5; cursor: not-allowed; }
.mini-btn.save { background: rgb(var(--color-primary)); color: #fff; border-color: transparent; }
.mini-btn.danger { color: #dc2626; }
.mini-btn.danger:hover { background: #fef2f2; }

/* ── Dar ekran (telefon) ── */
@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.card-header { flex-wrap: wrap; row-gap: 6px; }
	.card-header .hint { margin-left: 0; }

	.row { flex-wrap: wrap; }
	.row-label { min-width: 0; width: 100%; }
	.row-hint { width: 100%; white-space: normal; }
	.row-order { order: 1; }
	.row-toggle { order: 2; }
	.row-actions { order: 3; width: 100%; justify-content: flex-end; }
	.row-input { flex: 1 1 100px; min-width: 0; }
	.row-input.flex { flex-basis: 100%; }
	.row-input.narrow { max-width: none; flex: 1 1 70px; }
}
</style>
