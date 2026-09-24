<template>
	<Head title="İş Ortağı Kreatif Profilleri" />
	<div class="page-partner-brandkits">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'İş Ortağı Profilleri' },
			]"
		/>

		<PageHeader
			title="İş Ortağı Kreatif Profilleri"
			subtitle="'İş ortağı paylaşımı' kanalında BrandKit'e ek olarak Rules Engine'e beslenir — logo/iletişim/CTA burada tanımlanır, AI bunları hiç çizmez."
		>
			<template #actions>
				<Button variant="primary" with-icon @click="newKit">
					<template #leading><Plus :size="13" /></template>
					Yeni Profil
				</Button>
			</template>
		</PageHeader>

		<div class="pbk-layout">
			<aside class="pbk-list">
				<EmptyState v-if="kits.length === 0" :icon="Handshake" title="Henüz iş ortağı profili yok." />
				<button
					v-for="k in kits"
					:key="k.id"
					class="pbk-list-item"
					:class="{ active: form.id === k.id, inactive: !k.is_active }"
					@click="editKit(k)"
				>
					<div class="pbk-logo">
						<img v-if="k.logo_url" :src="k.logo_url" :alt="k.name" />
						<Handshake v-else :size="18" />
					</div>
					<div class="pbk-list-meta">
						<span class="pbk-list-name">{{ k.name }}</span>
						<span v-if="!k.is_active" class="pbk-inactive-chip">Pasif</span>
					</div>
				</button>
			</aside>

			<Card class="pbk-editor" :title="form.id ? 'Profili Düzenle' : 'Yeni Profil'">
				<template #actions>
					<button v-if="form.id && can('creative.partnerbrandkit.manage')" class="link-btn danger" @click="remove">Sil</button>
				</template>
				<div>
					<div class="field">
						<label>İş ortağı adı</label>
						<input v-model="form.name" type="text" placeholder="örn. Ana Bayi A.Ş." />
						<span v-if="errors.name" class="err">{{ errors.name }}</span>
					</div>

					<label class="default-toggle" :class="{ on: form.is_active }">
						<input v-model="form.is_active" type="checkbox" />
						<span class="dt-dot"></span>
						<span>Aktif (paylaşım oluştururken seçilebilir)</span>
					</label>

					<div class="section">
						<div class="section-head">
							<h4>Logo</h4>
							<label v-if="can('creative.partnerbrandkit.manage')" class="upload-btn" :class="{ busy: uploading }">
								{{ uploading ? '…' : 'Yükle' }}
								<input type="file" accept="image/*" @change="uploadLogo" hidden />
							</label>
						</div>
						<div v-if="form.logo_path" class="logo-preview">
							<img :src="logoPreviewUrl" alt="Logo" />
							<span class="logo-path">{{ form.logo_path }}</span>
							<button class="row-del" @click="form.logo_path = ''"><X :size="12" /></button>
						</div>
						<p v-else class="muted">Henüz logo yüklenmedi.</p>
					</div>

					<div class="section">
						<div class="section-head">
							<h4>İletişim</h4>
						</div>
						<div class="grid2">
							<div class="field">
								<label>Telefon</label>
								<input v-model="form.contact.phone" type="text" placeholder="0212 555 00 00" />
							</div>
							<div class="field">
								<label>E-posta</label>
								<input v-model="form.contact.email" type="email" placeholder="info@ornek.com" />
							</div>
						</div>
						<div class="field">
							<label>Web adresi</label>
							<input v-model="form.contact.website" type="text" placeholder="www.ornek.com" />
						</div>
					</div>

					<div class="section">
						<div class="section-head">
							<h4>CTA İfadeleri (kapalı liste)</h4>
							<button class="link-btn" @click="form.cta_phrases.push('')">+ CTA</button>
						</div>
						<p v-if="form.cta_phrases.length === 0" class="muted">Boşsa render katmanı marka kitinin genel CTA'larını kullanır.</p>
						<div v-for="(row, i) in form.cta_phrases" :key="'cta' + i" class="token-row">
							<input v-model="form.cta_phrases[i]" class="token-path" type="text" placeholder="Bizi Arayın" maxlength="191" />
							<button class="row-del" @click="form.cta_phrases.splice(i, 1)"><X :size="12" /></button>
						</div>
					</div>

					<div class="section">
						<div class="section-head">
							<h4>Fiyat Gösterimi</h4>
						</div>
						<label class="default-toggle" :class="{ on: form.price_display.enabled }">
							<input v-model="form.price_display.enabled" type="checkbox" />
							<span class="dt-dot"></span>
							<span>Bu iş ortağı paylaşımlarında fiyat gösterilsin</span>
						</label>
						<div class="field" style="margin-top:10px">
							<label>Fiyat notu (opsiyonel)</label>
							<input v-model="form.price_display.note" type="text" maxlength="500" placeholder="örn. KDV dahil, kampanya fiyatı" />
						</div>
					</div>

					<div class="field">
						<label>Notlar</label>
						<textarea v-model="form.notes" maxlength="2000" rows="3" placeholder="İç kullanım için serbest not"></textarea>
					</div>

					<div class="editor-actions">
						<Button variant="ghost" @click="newKit">Temizle</Button>
						<Button v-if="can('creative.partnerbrandkit.manage')" variant="primary" :loading="busy" @click="save">
							{{ form.id ? 'Güncelle' : 'Oluştur' }}
						</Button>
					</div>
				</div>
			</Card>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, X, Handshake } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	kits: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')
const busy = ref(false)
const uploading = ref(false)
const errors = reactive({})

function blankForm() {
	return {
		id: null,
		name: '',
		is_active: true,
		logo_path: '',
		logo_url: '',
		contact: { phone: '', email: '', website: '' },
		cta_phrases: [],
		price_display: { enabled: false, note: '' },
		notes: '',
	}
}

const form = reactive(blankForm())

const logoPreviewUrl = computed(() => form.logo_url || '')

function newKit() {
	Object.assign(form, blankForm())
	clearErrors()
}

function editKit(k) {
	clearErrors()
	Object.assign(form, {
		id: k.id,
		name: k.name,
		is_active: !!k.is_active,
		logo_path: k.logo_path || '',
		logo_url: k.logo_url || '',
		contact: { phone: k.contact?.phone || '', email: k.contact?.email || '', website: k.contact?.website || '' },
		cta_phrases: Array.isArray(k.cta_phrases) ? [...k.cta_phrases] : [],
		price_display: { enabled: !!k.price_display?.enabled, note: k.price_display?.note || '' },
		notes: k.notes || '',
	})
}

function clearErrors() { Object.keys(errors).forEach(k => delete errors[k]) }

function payload() {
	return {
		name: form.name,
		is_active: form.is_active,
		logo_path: form.logo_path || null,
		contact: {
			phone: form.contact.phone || null,
			email: form.contact.email || null,
			website: form.contact.website || null,
		},
		cta_phrases: form.cta_phrases.filter(v => (v || '').trim() !== ''),
		price_display: {
			enabled: form.price_display.enabled,
			note: form.price_display.note || null,
		},
		notes: form.notes || null,
	}
}

async function uploadLogo(e) {
	const file = e.target.files?.[0]
	if (!file) return
	uploading.value = true
	try {
		const fd = new FormData()
		fd.append('logo', file)
		const { data } = await window.axios.post('/creative/partner-brandkits/logo', fd)
		form.logo_path = data.path
		form.logo_url = data.url
		showToast?.({ type: 'success', title: 'Logo yüklendi', message: data.path })
	} catch (err) {
		showToast?.({ type: 'error', title: 'Logo yüklenemedi', message: err?.response?.data?.message || 'Hata' })
	} finally {
		uploading.value = false
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
		onFinish: () => { busy.value = false },
	}
	if (form.id) router.put(`/creative/partner-brandkits/${form.id}`, payload(), opts)
	else router.post('/creative/partner-brandkits', payload(), opts)
}

async function remove() {
	if (!form.id || busy.value) return
	const ok = await $swal.dangerConfirm({ title: 'İş ortağı profili silinsin mi?', html: `<b>${form.name}</b> kalıcı olarak silinecek.` })
	if (!ok) return
	busy.value = true
	router.delete(`/creative/partner-brandkits/${form.id}`, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => { newKit() },
		onFinish: () => { busy.value = false },
	})
}
</script>

<style scoped>
.pbk-layout { display: grid; grid-template-columns: 260px 1fr; gap: 18px; align-items: start; }

.pbk-list { display: flex; flex-direction: column; gap: 8px; }
.pbk-list-item { display: flex; align-items: center; gap: 10px; text-align: left; background: var(--color-surface); border: 2px solid var(--color-outline-variant); border-radius: 12px; padding: 9px 13px; cursor: pointer; font-family: inherit; transition: border-color .15s; }
.pbk-list-item:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.pbk-list-item.active { border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 10%, transparent); }
.pbk-list-item.inactive { opacity: .6; }
.pbk-logo { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--color-muted); }
.pbk-logo img { width: 100%; height: 100%; object-fit: contain; }
.pbk-list-meta { display: flex; align-items: center; gap: 8px; min-width: 0; }
.pbk-list-name { font-size: 13px; font-weight: 600; color: var(--color-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-inactive-chip { font-size: 10px; font-weight: 700; background: var(--color-surface-container-low); color: var(--color-muted); padding: 2px 7px; border-radius: 5px; flex-shrink: 0; }

.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--color-on-surface-variant); margin-bottom: 5px; }
.field input, .field select, .field textarea { width: 100%; border: 1px solid var(--color-outline-variant); border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit; color: var(--color-ink); background: var(--color-surface); }
.field textarea { resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.err { color: var(--color-danger); font-size: 11.5px; margin-top: 4px; display: block; }

.default-toggle { display: flex; align-items: center; gap: 9px; cursor: pointer; user-select: none; padding: 9px 12px; border: 1px solid var(--color-outline-variant); border-radius: 10px; font-size: 12.5px; color: var(--color-on-surface-variant); margin-bottom: 18px; }
.default-toggle.on { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary-hover); }
.default-toggle input { display: none; }
.dt-dot { width: 32px; height: 18px; border-radius: 10px; background: color-mix(in srgb, var(--color-primary) 35%, transparent); position: relative; flex-shrink: 0; transition: background .15s; }
.dt-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--color-surface); transition: transform .15s; }
.default-toggle.on .dt-dot { background: var(--color-primary); }
.default-toggle.on .dt-dot::after { transform: translateX(14px); }

.section { border-top: 1px solid var(--color-outline-variant); padding-top: 16px; margin-top: 16px; }
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-head h4 { font-size: 13px; font-weight: 700; color: var(--color-ink); }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
.link-btn.danger { color: var(--color-danger); }
.muted { font-size: 12px; color: var(--color-muted); font-style: italic; }

.token-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.token-path { flex: 1; border: 1px solid var(--color-outline-variant); border-radius: 7px; padding: 7px 9px; font-size: 12px; font-family: inherit; }
.token-path:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.row-del { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; background: var(--color-surface-container-low); color: var(--color-muted); border-radius: 7px; cursor: pointer; flex-shrink: 0; }
.row-del:hover { background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); }
.upload-btn { font-size: 11.5px; font-weight: 600; color: var(--color-primary-hover); background: var(--color-primary-soft); padding: 7px 11px; border-radius: 7px; cursor: pointer; flex-shrink: 0; }
.upload-btn.busy { opacity: .6; }

.logo-preview { display: flex; align-items: center; gap: 10px; }
.logo-preview img { width: 44px; height: 44px; object-fit: contain; border-radius: 8px; background: var(--color-surface-container-low); border: 1px solid var(--color-outline-variant); }
.logo-path { flex: 1; font-size: 12px; color: var(--color-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.editor-actions { display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--color-outline-variant); padding-top: 16px; margin-top: 18px; }

@media (max-width: 900px) {
	.pbk-layout { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
	.grid2 { grid-template-columns: 1fr; }
	.editor-actions { flex-wrap: wrap; }
	.editor-actions .btn { flex: 1 1 auto; }
}
</style>
