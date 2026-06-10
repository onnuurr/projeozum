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
						<div class="section-head">
							<h4>Tipografi (Fontlar)</h4>
							<label class="upload-btn" :class="{ busy: fontUploading }">
								{{ fontUploading ? '…' : '+ Font / ZIP yükle' }}
								<input type="file" accept=".ttf,.otf,.woff,.woff2,.zip" multiple hidden @change="uploadFont" />
							</label>
						</div>
						<p class="muted">Tekli font (.ttf/.otf/.woff/.woff2) veya .zip yükleyin; ZIP içindeki fontlar otomatik çıkarılır. Render için en güvenlisi .ttf/.otf.</p>

						<div v-if="form.typography.fonts.length === 0" class="muted" style="margin-top:8px">Henüz font yüklenmedi.</div>

						<div v-for="g in fontGroups" :key="g.family" class="font-group">
							<!-- Aynı aileden birden çok stil → klasör (tıkla aç/kapat) -->
							<template v-if="g.items.length > 1">
								<button type="button" class="font-folder" @click="toggleFamily(g.family)">
									<svg class="folder-caret" :class="{ open: isFamilyOpen(g.family) }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 6l6 6-6 6" /></svg>
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" /></svg>
									<span class="ff-name">{{ g.family }}</span>
									<span class="ff-count">{{ g.items.length }} stil</span>
								</button>
								<div v-show="isFamilyOpen(g.family)" class="font-children">
									<div v-for="it in g.items" :key="it.index" class="token-row">
										<input v-model="it.font.name" class="token-key" style="width:150px" type="text" placeholder="stil adı" />
										<input :value="it.font.path" class="token-path" type="text" readonly title="Sistemdeki yol" />
										<button class="row-del" @click="removeFont(it.index)">✕</button>
									</div>
								</div>
							</template>
							<!-- Tek dosya → klasörsüz satır -->
							<div v-else class="token-row">
								<input v-model="g.items[0].font.name" class="token-key" style="width:150px" type="text" placeholder="font adı" />
								<input :value="g.items[0].font.path" class="token-path" type="text" readonly title="Sistemdeki yol" />
								<button class="row-del" @click="removeFont(g.items[0].index)">✕</button>
							</div>
						</div>

						<div class="grid2" style="margin-top:12px">
							<div class="field">
								<label>Gövde (regular) font</label>
								<select v-model="form.typography.regular">
									<option value="">— config varsayılanı —</option>
									<optgroup v-for="g in fontGroups" :key="g.family" :label="g.family">
										<option v-for="it in g.items" :key="it.font.path" :value="it.font.path">{{ it.font.name }}</option>
									</optgroup>
								</select>
							</div>
							<div class="field">
								<label>Kalın (bold) font</label>
								<select v-model="form.typography.bold">
									<option value="">— config varsayılanı —</option>
									<optgroup v-for="g in fontGroups" :key="g.family" :label="g.family">
										<option v-for="it in g.items" :key="it.font.path" :value="it.font.path">{{ it.font.name }}</option>
									</optgroup>
								</select>
							</div>
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
import { ref, reactive, computed, inject } from 'vue'
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
const fontUploading = ref(false)
const errors = reactive({})

const PALETTE_KEYS = ['primary', 'secondary', 'accent', 'background', 'text']

function blankForm() {
	return {
		id: null,
		name: '',
		is_default: false,
		palette: PALETTE_KEYS.map(key => ({ key, value: props.defaults?.palette?.[key] || '#000000' })),
		typography: { regular: '', bold: '', fonts: [] },
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

// Tipografiyi {regular,bold,fonts[]} biçimine getirir; regular/bold yolları
// kütüphanede yoksa onları da listeye ekler (eski/elle girilmiş yollar için).
function buildTypography(t) {
	const fonts = Array.isArray(t?.fonts)
		? t.fonts.filter(f => f && f.path).map(f => ({ name: f.name || basename(f.path), path: f.path }))
		: []
	const regular = t?.regular || ''
	const bold = t?.bold || ''
	for (const p of [regular, bold]) {
		if (p && !fonts.some(f => f.path === p)) fonts.push({ name: basename(p), path: p })
	}
	return { regular, bold, fonts }
}

function basename(p) { return String(p || '').split(/[\\/]/).pop() }

// ── Font ailesine göre gruplama (klasör görünümü) ────────────────────────
const STYLE_TOKENS = new Set([
	'thin', 'extralight', 'ultralight', 'light', 'regular', 'normal', 'book', 'text',
	'medium', 'semibold', 'demibold', 'demi', 'semi', 'bold', 'extrabold', 'ultrabold',
	'black', 'heavy', 'italic', 'oblique', 'condensed', 'expanded', 'extended',
	'narrow', 'wide', 'roman', 'variablefont', 'variable', 'font', 'vf',
	'wght', 'wdth', 'opsz', 'slnt', 'ital', // variable font eksenleri
])

// Dosya adından stil/ağırlık eklerini atıp aile adını çıkarır.
// CamelCase ("RobotoBoldItalic") ve ayraçlı ("Roboto-Bold_Italic") adları normalize eder.
function familyOf(name) {
	let base = String(name || '').replace(/\.(ttf|otf|woff2?|zip)$/i, '')
	base = base.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/[\s_\-.]+/g, ' ')
	const tokens = base.split(' ').filter(Boolean)
	const kept = tokens.filter(t => {
		const low = t.toLowerCase()
		if (STYLE_TOKENS.has(low)) return false
		if (/^\d{2,3}$/.test(low)) return false // 100–900 ağırlık değerleri
		return true
	})
	return kept.join(' ').trim() || base.trim()
}

// Yüklenen fontları aileye göre grupla (orijinal index'i koru → düzenle/sil).
const fontGroups = computed(() => {
	const map = new Map()
	form.typography.fonts.forEach((font, index) => {
		const fam = familyOf(font.name || basename(font.path)) || 'Diğer'
		if (!map.has(fam)) map.set(fam, [])
		map.get(fam).push({ font, index })
	})
	return Array.from(map, ([family, items]) => ({ family, items }))
})

const expandedFamilies = reactive(new Set())
function toggleFamily(f) { expandedFamilies.has(f) ? expandedFamilies.delete(f) : expandedFamilies.add(f) }
function isFamilyOpen(f) { return expandedFamilies.has(f) }

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
		typography: buildTypography(k.typography),
		spacing: objToRows(k.spacing),
		logos: objToRows(k.logos),
	})
}

function addRow(field) {
	form[field].push({ key: '', value: field === 'spacing' ? 0 : '' })
}

function clearErrors() { Object.keys(errors).forEach(k => delete errors[k]) }

function payload() {
	const typography = {
		fonts: form.typography.fonts.filter(f => f.path).map(f => ({ name: f.name || basename(f.path), path: f.path })),
	}
	if (form.typography.regular) typography.regular = form.typography.regular
	if (form.typography.bold) typography.bold = form.typography.bold

	return {
		name: form.name,
		is_default: form.is_default,
		palette: rowsToObj(form.palette),
		typography,
		spacing: rowsToObj(form.spacing),
		logos: rowsToObj(form.logos),
	}
}

async function uploadFont(e) {
	const files = Array.from(e.target.files || [])
	if (!files.length) return
	fontUploading.value = true
	try {
		let added = 0
		for (const file of files) {
			const fd = new FormData()
			fd.append('font', file)
			const { data } = await window.axios.post('/creative/brandkits/font', fd)
			for (const f of (data.fonts || [])) {
				if (!form.typography.fonts.some(x => x.path === f.path)) {
					form.typography.fonts.push({ name: f.name || basename(f.path), path: f.path })
					added++
				}
			}
		}
		showToast?.({ type: 'success', title: 'Font eklendi', message: added + ' font yüklendi' })
	} catch (err) {
		showToast?.({ type: 'error', title: 'Font yüklenemedi', message: err?.response?.data?.message || 'Hata' })
	} finally {
		fontUploading.value = false
		e.target.value = ''
	}
}

function removeFont(i) {
	const removed = form.typography.fonts.splice(i, 1)[0]
	if (removed && form.typography.regular === removed.path) form.typography.regular = ''
	if (removed && form.typography.bold === removed.path) form.typography.bold = ''
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
.field input, .field select { width: 100%; border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit; color: #1a1a2e; background: #fff; }
.field input:focus, .field select:focus { outline: none; border-color: #d8d4f0; background: #faf8ff; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
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

/* Font ailesi klasörleri */
.font-group { margin-bottom: 6px; }
.font-folder { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; background: #f7f6fb; border: 1px solid #ece9f6; border-radius: 8px; padding: 8px 11px; cursor: pointer; font-family: inherit; color: #4a4458; transition: background .15s; }
.font-folder:hover { background: #f1eefa; }
.folder-caret { color: #9b8ec7; transition: transform .15s; flex-shrink: 0; }
.folder-caret.open { transform: rotate(90deg); }
.ff-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.ff-count { margin-left: auto; font-size: 11px; font-weight: 600; color: #8a7fb0; background: #ede9fe; padding: 2px 8px; border-radius: 10px; }
.font-children { padding: 8px 0 4px 22px; border-left: 2px solid #ece9f6; margin: 4px 0 4px 16px; display: flex; flex-direction: column; gap: 6px; }

.editor-actions { display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f0f0f5; padding-top: 16px; margin-top: 18px; }
</style>
