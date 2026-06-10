<template>
	<Head title="Marka Kiti" />
	<div class="page-brandkits">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Marka Kiti' },
			]"
		/>

		<CreativeNav current="brandkits" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Marka Kiti</h1>
				<p class="page-subtitle">Render, AI sahne ve caption bu token'lardan beslenir. Varsayılan kit aktif olandır.</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="newKit">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
				Yeni Kit
			</button>
		</div>

		<div class="bk-layout">
			<!-- Kit listesi -->
			<aside class="bk-list">
				<div v-if="kits.length === 0" class="empty-block">Henüz kit yok.</div>
				<button
					v-for="k in kits"
					:key="k.id"
					class="bk-list-item"
					:class="{ active: form.id === k.id }"
					@click="editKit(k)"
				>
					<div class="bk-swatches">
						<span v-for="(c, i) in swatchList(k.palette)" :key="i" class="bk-swatch" :style="{ background: c }"></span>
					</div>
					<div class="bk-list-meta">
						<span class="bk-list-name">{{ k.name }}</span>
						<span v-if="k.is_default" class="bk-default-chip">Varsayılan</span>
					</div>
				</button>
			</aside>

			<!-- Editör -->
			<section class="card bk-editor">
				<div class="card-header">
					<h3>{{ form.id ? 'Kiti Düzenle' : 'Yeni Kit' }}</h3>
					<button v-if="form.id" class="link-btn danger" @click="remove">Sil</button>
				</div>
				<div class="card-body">
					<div class="field">
						<label>Kit adı</label>
						<input v-model="form.name" type="text" placeholder="örn. Ana Marka" />
						<span v-if="errors.name" class="err">{{ errors.name }}</span>
					</div>

					<label class="default-toggle" :class="{ on: form.is_default }">
						<input v-model="form.is_default" type="checkbox" />
						<span class="dt-dot"></span>
						<span>Varsayılan kit yap (render/AI/caption bunu kullanır)</span>
					</label>

					<!-- Palet -->
					<div class="section">
						<div class="section-head">
							<h4>Renk Paleti</h4>
							<button class="link-btn" @click="addRow('palette')">+ Renk</button>
						</div>
						<div v-for="(row, i) in form.palette" :key="'p' + i" class="token-row">
							<input v-model="row.key" class="token-key" type="text" :placeholder="paletteHint(i)" />
							<input v-model="row.value" class="token-color" type="color" />
							<input v-model="row.value" class="token-hex" type="text" placeholder="#000000" />
							<button class="row-del" @click="form.palette.splice(i, 1)">✕</button>
						</div>
						<p v-if="form.palette.length === 0" class="muted">Renk yok — varsayılanlar ({{ Object.keys(defaults.palette || {}).join(', ') }}) kullanılır.</p>
					</div>

					<!-- Tipografi -->
					<div class="section">
						<div class="section-head"><h4>Tipografi</h4></div>
						<div class="field">
							<label>Regular font yolu (.ttf)</label>
							<input v-model="form.typography.regular" type="text" placeholder="Modules/Creative/python/fonts/DejaVuSans.ttf" />
						</div>
						<div class="field">
							<label>Bold font yolu (.ttf)</label>
							<input v-model="form.typography.bold" type="text" placeholder="…DejaVuSans-Bold.ttf" />
						</div>
					</div>

					<!-- Boşluk -->
					<div class="section">
						<div class="section-head">
							<h4>Boşluk (Spacing)</h4>
							<button class="link-btn" @click="addRow('spacing')">+ Boşluk</button>
						</div>
						<div v-for="(row, i) in form.spacing" :key="'s' + i" class="token-row">
							<input v-model="row.key" class="token-key" type="text" placeholder="md" />
							<input v-model.number="row.value" class="token-num" type="number" min="0" placeholder="16" />
							<button class="row-del" @click="form.spacing.splice(i, 1)">✕</button>
						</div>
					</div>

					<!-- Logolar -->
					<div class="section">
						<div class="section-head">
							<h4>Logolar</h4>
							<button class="link-btn" @click="addRow('logos')">+ Logo</button>
						</div>
						<div v-for="(row, i) in form.logos" :key="'l' + i" class="token-row logo-row">
							<input v-model="row.key" class="token-key" type="text" placeholder="primary" />
							<input v-model="row.value" class="token-path" type="text" placeholder="brand_kits/logos/…" />
							<label class="upload-btn" :class="{ busy: uploading === i }">
								{{ uploading === i ? '…' : 'Yükle' }}
								<input type="file" accept="image/*" @change="uploadLogo($event, i)" hidden />
							</label>
							<button class="row-del" @click="form.logos.splice(i, 1)">✕</button>
						</div>
					</div>

					<div class="editor-actions">
						<button class="btn btn-ghost" @click="newKit">Temizle</button>
						<button class="btn btn-primary" :disabled="busy" @click="save">
							{{ busy ? 'Kaydediliyor…' : (form.id ? 'Güncelle' : 'Oluştur') }}
						</button>
					</div>
				</div>
			</section>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	kits: { type: Array, default: () => [] },
	defaults: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast', null)
const busy = ref(false)
const uploading = ref(null)
const errors = reactive({})

const PALETTE_KEYS = ['primary', 'secondary', 'accent', 'background', 'text']

function blankForm() {
	return {
		id: null,
		name: '',
		is_default: false,
		palette: PALETTE_KEYS.map(key => ({ key, value: props.defaults?.palette?.[key] || '#000000' })),
		typography: { regular: '', bold: '' },
		spacing: Object.entries(props.defaults?.spacing || { sm: 8, md: 16, lg: 32 }).map(([key, value]) => ({ key, value })),
		logos: [],
	}
}

const form = reactive(blankForm())

function objToRows(obj) {
	return Object.entries(obj || {}).map(([key, value]) => ({ key, value }))
}

function rowsToObj(rows) {
	const out = {}
	for (const r of rows) {
		const k = (r.key || '').trim()
		if (k && r.value !== '' && r.value !== null && r.value !== undefined) out[k] = r.value
	}
	return out
}

function swatchList(palette) {
	return Object.values(palette || {}).filter(v => typeof v === 'string').slice(0, 5)
}

function paletteHint(i) { return PALETTE_KEYS[i] || 'token' }

function newKit() {
	Object.assign(form, blankForm())
	clearErrors()
}

function editKit(k) {
	clearErrors()
	Object.assign(form, {
		id: k.id,
		name: k.name,
		is_default: !!k.is_default,
		palette: objToRows(k.palette),
		typography: { regular: k.typography?.regular || '', bold: k.typography?.bold || '' },
		spacing: objToRows(k.spacing),
		logos: objToRows(k.logos),
	})
}

function addRow(field) {
	form[field].push({ key: '', value: field === 'spacing' ? 0 : '' })
}

function clearErrors() { Object.keys(errors).forEach(k => delete errors[k]) }

function payload() {
	const typography = {}
	if (form.typography.regular?.trim()) typography.regular = form.typography.regular.trim()
	if (form.typography.bold?.trim()) typography.bold = form.typography.bold.trim()

	return {
		name: form.name,
		is_default: form.is_default,
		palette: rowsToObj(form.palette),
		typography,
		spacing: rowsToObj(form.spacing),
		logos: rowsToObj(form.logos),
	}
}

async function uploadLogo(e, i) {
	const file = e.target.files?.[0]
	if (!file) return
	uploading.value = i
	try {
		const fd = new FormData()
		fd.append('logo', file)
		const { data } = await window.axios.post('/creative/brandkits/logo', fd)
		form.logos[i].value = data.path
		showToast?.({ type: 'success', title: 'Logo yüklendi', message: data.path })
	} catch (err) {
		showToast?.({ type: 'error', title: 'Logo yüklenemedi', message: err?.response?.data?.message || 'Hata' })
	} finally {
		uploading.value = null
		e.target.value = ''
	}
}

function save() {
	if (busy.value) return
	busy.value = true
	clearErrors()
	const opts = {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => Object.assign(errors, errs),
		onSuccess: () => showToast?.({ type: 'success', title: form.id ? 'Kit güncellendi' : 'Kit oluşturuldu', message: form.name }),
		onFinish: () => { busy.value = false },
	}
	if (form.id) router.put(`/creative/brandkits/${form.id}`, payload(), opts)
	else router.post('/creative/brandkits', payload(), opts)
}

function remove() {
	if (!form.id || busy.value) return
	if (!confirm('Bu marka kitini silmek istediğinize emin misiniz?')) return
	busy.value = true
	router.delete(`/creative/brandkits/${form.id}`, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => { showToast?.({ type: 'warning', title: 'Kit silindi', message: form.name }); newKit() },
		onFinish: () => { busy.value = false },
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 560px; }

.bk-layout { display: grid; grid-template-columns: 260px 1fr; gap: 18px; align-items: start; }

.bk-list { display: flex; flex-direction: column; gap: 8px; }
.bk-list-item { text-align: left; background: #fff; border: 2px solid #ebebf0; border-radius: 12px; padding: 11px 13px; cursor: pointer; font-family: inherit; transition: border-color .15s; }
.bk-list-item:hover { border-color: #d8d4f0; }
.bk-list-item.active { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }
.bk-swatches { display: flex; gap: 3px; margin-bottom: 8px; }
.bk-swatch { width: 100%; height: 18px; border-radius: 4px; border: 1px solid rgba(0,0,0,.06); }
.bk-list-meta { display: flex; align-items: center; gap: 8px; }
.bk-list-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.bk-default-chip { font-size: 10px; font-weight: 700; background: #ede9fe; color: #6d28d9; padding: 2px 7px; border-radius: 5px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 5px; }
.field input { width: 100%; border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit; color: #1a1a2e; }
.field input:focus { outline: none; border-color: #d8d4f0; background: #faf8ff; }
.err { color: #dc2626; font-size: 11.5px; margin-top: 4px; display: block; }

.default-toggle { display: flex; align-items: center; gap: 9px; cursor: pointer; user-select: none; padding: 9px 12px; border: 1px solid #ebebf0; border-radius: 10px; font-size: 12.5px; color: #555; margin-bottom: 18px; }
.default-toggle.on { border-color: #7c3aed; background: #faf8ff; color: #6d28d9; }
.default-toggle input { display: none; }
.dt-dot { width: 32px; height: 18px; border-radius: 10px; background: #d8d4f0; position: relative; flex-shrink: 0; transition: background .15s; }
.dt-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: transform .15s; }
.default-toggle.on .dt-dot { background: #7c3aed; }
.default-toggle.on .dt-dot::after { transform: translateX(14px); }

.section { border-top: 1px solid #f0f0f5; padding-top: 16px; margin-top: 16px; }
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-head h4 { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.link-btn { background: none; border: none; color: #7c3aed; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
.link-btn.danger { color: #dc2626; }
.muted { font-size: 12px; color: #aaa; font-style: italic; }

.token-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.token-key { width: 110px; flex-shrink: 0; border: 1px solid #e8e8f0; border-radius: 7px; padding: 7px 9px; font-size: 12.5px; font-family: inherit; }
.token-color { width: 38px; height: 34px; padding: 2px; border: 1px solid #e8e8f0; border-radius: 7px; cursor: pointer; background: #fff; flex-shrink: 0; }
.token-hex { width: 100px; flex-shrink: 0; border: 1px solid #e8e8f0; border-radius: 7px; padding: 7px 9px; font-size: 12.5px; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.token-num { width: 90px; border: 1px solid #e8e8f0; border-radius: 7px; padding: 7px 9px; font-size: 12.5px; font-family: inherit; }
.token-path { flex: 1; border: 1px solid #e8e8f0; border-radius: 7px; padding: 7px 9px; font-size: 12px; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.token-key:focus, .token-hex:focus, .token-num:focus, .token-path:focus { outline: none; border-color: #d8d4f0; background: #faf8ff; }
.row-del { width: 28px; height: 28px; border: none; background: #f5f5f8; color: #999; border-radius: 7px; cursor: pointer; font-size: 12px; flex-shrink: 0; }
.row-del:hover { background: #fee2e2; color: #dc2626; }
.upload-btn { font-size: 11.5px; font-weight: 600; color: #6d28d9; background: #ede9fe; padding: 7px 11px; border-radius: 7px; cursor: pointer; flex-shrink: 0; }
.upload-btn.busy { opacity: .6; }

.editor-actions { display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f0f0f5; padding-top: 16px; margin-top: 18px; }
</style>
