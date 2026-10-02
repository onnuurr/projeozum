<script setup>
import { ref, computed, watch, inject, nextTick, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzTabs, OzField, OzInput, OzTextarea, OzSwitch, OzButton, OzPill, OzAlert, OzSpinner, OzIcon,
} from '@/Components/Oz'

// Bayi E-postaları: bayilere giden e-postaların konu / giriş / alt paragraf metinleri, gruplar hâlinde —
//   Başvuru (bayi portalı başvurusunun adımları, bagisto::emails.dealer-application) ve İade (iade kararı,
//   fatura reddi, ödeme, bagisto::emails.return-status). Tasarım sabittir; kullanıcı yalnız yetkili olduğu
//   grupları görür (props.groups sunucuda süzülür). İletişim kartı tüm gruplarda ortaktır.
//   Metinlerde {{user.name}} gibi etiketler kullanılır; etiket çipi alana sürüklenip bırakılır (tarayıcı metni
//   bırakılan noktaya yazar, v-model input olayıyla güncellenir) ya da tıklanınca imlecin olduğu alana eklenir.
//   Sağdaki önizleme kaydedilmemiş taslağı örnek veriyle işler (POST .../preview);
//   "Test gönder" aynı taslağı oturumdaki kullanıcının adresine yollar.
//   Kaydet → PUT /dealer-mail-templates (tüm grupların taslakları + iletişim kartı birlikte).
defineOptions({ layout: AppLayout })

const props = defineProps({
	groups: { type: Array, default: () => [] }, // [{ key, label, stages: [...] }]
	initialGroup: { type: String, default: null },
	contact: { type: Object, default: () => ({}) },
	contactFallback: { type: Object, default: () => ({}) },
	sender: { type: Object, default: () => ({}) },
})

const BASE = '/dealer-mail-templates'
const FIELDS = ['subject', 'intro', 'body']

const showToast = inject('showToast', null)

const pick = (s) => ({ enabled: s.enabled, subject: s.subject, intro: s.intro, body: s.body ?? '' })
const initialTemplates = () => Object.fromEntries(props.groups.map((g) => [g.key, Object.fromEntries(g.stages.map((s) => [s.key, pick(s)]))]))
const initialContact = () => ({ ...props.contact })

const templates = ref(initialTemplates())
const contact = ref(initialContact())
const errors = ref({})

// ── Grup (Başvuru / İade) ve adım sekmesi ──────────────────────────────────
const group = ref(props.groups.some((g) => g.key === props.initialGroup) ? props.initialGroup : props.groups[0]?.key)
const activeGroup = computed(() => props.groups.find((g) => g.key === group.value) ?? null)
const stages = computed(() => activeGroup.value?.stages ?? [])
const groupDirty = (g) => g.stages.some((s) => stageDirty(s, g.key))
const groupTabs = computed(() => props.groups.map((g) => ({ key: g.key, label: `${g.label} e-postaları`, count: g.stages.length })))

const tab = ref(stages.value[0]?.key ?? 'contact')
const tabs = computed(() => [
	...stages.value.map((s) => ({ key: s.key, label: s.label })),
	{ key: 'contact', label: 'İletişim bilgileri', icon: 'phone' },
])
const stage = computed(() => stages.value.find((s) => s.key === tab.value) ?? null)
const current = computed(() => (stage.value ? templates.value[group.value][stage.value.key] : null))

// Grup değişince ilk adıma geç (İletişim sekmesindeyse orada kal); adres ?group= ile paylaşılabilir kalsın.
watch(group, (key) => {
	if (tab.value !== 'contact') tab.value = stages.value[0]?.key ?? 'contact'
	const url = new URL(window.location.href)
	url.searchParams.set('group', key)
	window.history.replaceState(window.history.state, '', url)
})

const stageDirty = (s, groupKey = group.value) => JSON.stringify(templates.value[groupKey][s.key]) !== JSON.stringify(pick(s))
const contactDirty = computed(() => Object.keys(props.contact).some((k) => (contact.value[k] ?? '') !== (props.contact[k] ?? '')))
const dirty = computed(() => contactDirty.value || props.groups.some(groupDirty))
const isDefault = computed(() => stage.value && FIELDS.every((f) => (current.value[f] ?? '') === (stage.value.defaults[f] ?? '')))

const fieldError = (field) => errors.value[`templates.${group.value}.${tab.value}.${field}`] || errors.value[`template.${field}`] || ''

const CONTACT_FIELDS = [
	{ key: 'support_email', label: 'Destek e-postası', placeholder: 'b2b@ozmkids.com' },
	{ key: 'advisor_name', label: 'Danışman adı', placeholder: 'Ad Soyad' },
	{ key: 'advisor_title', label: 'Danışman unvanı', placeholder: 'B2B Portföy Yöneticisi' },
	{ key: 'advisor_phone', label: 'Danışman telefonu', placeholder: '0532 123 45 67' },
	{ key: 'advisor_whatsapp', label: 'WhatsApp numarası', placeholder: '905321234567' },
]
const contactHint = (key) => (props.contactFallback[key] ? `Boş bırakılırsa: ${props.contactFallback[key]}` : 'Boş bırakılırsa gösterilmez')

// ── Etiket ekleme: son odaklanan alanın imlecine yazar ──────────────────────
const focused = ref(null) // { field, el }
function onFocusIn(field, e) {
	if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) focused.value = { field, el: e.target }
}
const token = (tag) => `{{${tag}}}`
async function insertTag(tag) {
	const text = token(tag)
	const target = focused.value ?? { field: 'intro', el: null }
	const value = current.value[target.field] ?? ''
	const el = target.el
	const start = el ? el.selectionStart ?? value.length : value.length
	const end = el ? el.selectionEnd ?? value.length : value.length
	current.value[target.field] = value.slice(0, start) + text + value.slice(end)
	await nextTick()
	if (el) {
		el.focus()
		el.setSelectionRange(start + text.length, start + text.length)
	}
}

const dragging = ref(false)
function onDragStart(e, tag) {
	e.dataTransfer.setData('text/plain', token(tag))
	e.dataTransfer.effectAllowed = 'copy'
	dragging.value = true
}

function resetStage() {
	if (!stage.value) return
	Object.assign(current.value, { ...stage.value.defaults })
}

// ── Önizleme (taslak, gecikmeli) ───────────────────────────────────────────
const preview = ref({ subject: '', html: '' })
const previewLoading = ref(false)
const previewError = ref('')
let timer = null
let seq = 0

async function loadPreview() {
	if (!stage.value) return
	const id = ++seq
	previewLoading.value = true
	try {
		const { data } = await axios.post(`${BASE}/preview`, { group: group.value, stage: stage.value.key, template: current.value })
		if (id !== seq) return
		preview.value = data
		previewError.value = ''
		clearDraftErrors()
	} catch (e) {
		if (id !== seq) return
		const errs = e.response?.data?.errors
		if (errs) {
			errors.value = { ...errors.value, ...Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]])) }
			previewError.value = 'Önizleme için alanlardaki hataları düzeltin.'
		} else {
			previewError.value = 'Önizleme yüklenemedi.'
		}
	} finally {
		if (id === seq) previewLoading.value = false
	}
}

function clearDraftErrors() {
	errors.value = Object.fromEntries(Object.entries(errors.value).filter(([k]) => !k.startsWith('template.') && !k.startsWith(`templates.${group.value}.${tab.value}.`)))
}

watch([group, tab], () => {
	focused.value = null
	if (stage.value) loadPreview()
}, { immediate: true })
watch(() => current.value && FIELDS.map((f) => current.value[f]).join('\u0000'), () => {
	clearTimeout(timer)
	timer = setTimeout(loadPreview, 600)
})
onBeforeUnmount(() => clearTimeout(timer))

// ── Test gönderimi ─────────────────────────────────────────────────────────
const testing = ref(false)
async function sendTest() {
	if (testing.value || !stage.value) return
	testing.value = true
	try {
		const { data } = await axios.post(`${BASE}/test`, { group: group.value, stage: stage.value.key, template: current.value })
		showToast?.({ type: 'success', title: 'Test e-postası gönderildi', message: data.message })
	} catch (e) {
		const errs = e.response?.data?.errors
		const message = errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Sunucu hatası.')
		showToast?.({ type: 'error', title: 'Gönderilemedi', message })
	} finally {
		testing.value = false
	}
}

// ── Kaydet ─────────────────────────────────────────────────────────────────
const saving = ref(false)
function save() {
	if (saving.value) return
	saving.value = true
	router.put(BASE, { templates: templates.value, contact: contact.value }, {
		preserveScroll: true,
		onSuccess: () => {
			templates.value = initialTemplates()
			contact.value = initialContact()
			errors.value = {}
			showToast?.({ type: 'success', title: 'Kaydedildi', message: 'Yeni e-postalar bu metinlerle gönderilecek.' })
		},
		onError: (errs) => {
			errors.value = errs
			const first = Object.keys(errs)[0] ?? ''
			const m = first.match(/^templates\.([^.]+)\.([^.]+)\./)
			if (m) {
				group.value = m[1]
				nextTick(() => { tab.value = m[2] })
			}
			else if (first.startsWith('contact.')) tab.value = 'contact'
			showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { saving.value = false },
	})
}

function reset() {
	templates.value = initialTemplates()
	contact.value = initialContact()
	errors.value = {}
}

// Başlıktaki geri butonu: grubun ait olduğu liste sayfası.
const BACK = {
	applications: { label: 'Başvurular', href: '/tenants/applications' },
	returns: { label: 'İadeler', href: '/order-returns' },
}
const back = computed(() => BACK[group.value] ?? null)

const senderLooksDefault = computed(() => !props.sender.name || props.sender.name === 'Laravel')
</script>

<template>
	<Head title="Bayi E-postaları" />

	<OzPageHeader title="Bayi E-postaları" subtitle="Bayilik başvurusu ve iade süreçlerinde bayiye giden e-postaların metinleri">
		<template v-if="back" #actions>
			<OzButton icon="chevron-left" @click="router.visit(back.href)">{{ back.label }}</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="senderLooksDefault" tone="warning" title="Gönderen adı ayarlanmamış" class="block">
		E-postalar “{{ sender.name || '—' }} &lt;{{ sender.address }}&gt;” adına gidiyor. .env içindeki MAIL_FROM_NAME değerini mağaza adıyla güncelleyin.
	</OzAlert>

	<div v-if="groups.length > 1" class="groups block">
		<OzTabs v-model="group" :tabs="groupTabs" variant="segment" aria-label="E-posta grubu" />
		<span v-if="groups.some((g) => g.key !== group && groupDirty(g))" class="groups-note">
			Diğer grupta kaydedilmemiş değişiklik var
		</span>
	</div>

	<OzTabs v-model="tab" :tabs="tabs" aria-label="E-posta adımları" class="block" />

	<!-- Adım şablonu -->
	<div v-if="stage" class="layout">
		<div class="main">
			<OzCard :title="stage.label" class="block">
				<template #actions>
					<div class="control">
						<OzPill v-if="stageDirty(stage)" tone="warning">Kaydedilmedi</OzPill>
						<span class="switch-label">{{ current.enabled ? 'Gönderiliyor' : 'Gönderilmiyor' }}</span>
						<OzSwitch v-model="current.enabled" :aria-label="`${stage.label} e-postası gönderilsin`" />
					</div>
				</template>
				<div class="pad">
					<p class="desc">{{ stage.description }}</p>

					<OzAlert v-if="!current.enabled" tone="info" class="block">
						<template v-if="group === 'returns'">Bu adımda bayiye e-posta gönderilmez (SMS bildirimi yine gider); iade süreci normal şekilde ilerler.</template>
						<template v-else>Bu adımda başvuru sahibine e-posta gönderilmez; başvuru süreci normal şekilde ilerler.</template>
					</OzAlert>

					<div class="tags">
						<div class="tags-head">Kullanılabilir etiketler <span>— alana sürükleyip bırakın ya da tıklayarak imlecin olduğu yere ekleyin</span></div>
						<div class="tag-list">
							<button
								v-for="t in stage.tags"
								:key="t.tag"
								type="button"
								class="tag"
								draggable="true"
								:title="`${t.description} — sürükleyip alana bırakın`"
								@dragstart="onDragStart($event, t.tag)"
								@dragend="dragging = false"
								@click="insertTag(t.tag)"
							>
								<OzIcon name="grid" :size="12" class="grip" />
								<code v-text="token(t.tag)" />
								<span>{{ t.description }}</span>
							</button>
						</div>
					</div>

					<div class="fields" :class="{ 'is-dragging': dragging }">
						<OzField label="Konu" :error="fieldError('subject')" required @focusin="onFocusIn('subject', $event)">
							<OzInput v-model="current.subject" :invalid="!!fieldError('subject')" maxlength="200" />
						</OzField>
						<OzField label="Giriş" hint="“Sayın …,” selamlamasının hemen altında gösterilir." :error="fieldError('intro')" required @focusin="onFocusIn('intro', $event)">
							<OzTextarea v-model="current.intro" :rows="4" :maxlength="2000" :invalid="!!fieldError('intro')" />
						</OzField>
						<OzField label="Paragraf" hint="Adıma özel bilgilerin (özet, belgeler, notlar) altında, butonun üstünde gösterilir. Boş bırakılabilir." :error="fieldError('body')" @focusin="onFocusIn('body', $event)">
							<OzTextarea v-model="current.body" :rows="4" :maxlength="3000" :invalid="!!fieldError('body')" />
						</OzField>
					</div>

					<div class="card-actions">
						<OzButton icon="undo" :disabled="isDefault" @click="resetStage">Varsayılan metne dön</OzButton>
						<OzButton icon="send" :loading="testing" @click="sendTest">Kendime test gönder</OzButton>
					</div>
				</div>
			</OzCard>
		</div>

		<aside class="side">
			<OzCard title="Önizleme">
				<template #actions>
					<OzSpinner v-if="previewLoading" :size="14" label="Önizleme yükleniyor" />
				</template>
				<div class="pad">
					<div class="subject">
						<span>Konu</span>
						<strong>{{ preview.subject || '—' }}</strong>
					</div>
					<p v-if="previewError" class="error">{{ previewError }}</p>
					<iframe class="frame" :srcdoc="preview.html" title="E-posta önizlemesi" sandbox="" />
					<p class="note">Örnek veriyle gösterilir; kaydedilmemiş değişiklikler de önizlemeye yansır.</p>
				</div>
			</OzCard>
		</aside>
	</div>

	<!-- İletişim bilgileri -->
	<div v-else class="layout">
		<div class="main">
			<OzCard title="Destek ve danışman kartı" class="block">
				<div class="pad">
					<p class="desc">Tüm bayi e-postalarının (başvuru ve iade) altındaki iletişim kartında ve alt bilgide gösterilir.</p>
					<div class="grid">
						<OzField v-for="f in CONTACT_FIELDS" :key="f.key" :label="f.label" :hint="contactHint(f.key)" :error="errors[`contact.${f.key}`]">
							<OzInput v-model="contact[f.key]" :placeholder="contactFallback[f.key] || f.placeholder" :invalid="!!errors[`contact.${f.key}`]" />
						</OzField>
					</div>
				</div>
			</OzCard>
		</div>
		<aside class="side">
			<OzCard title="Gönderen">
				<div class="pad">
					<dl class="facts">
						<dt>Ad</dt>
						<dd>{{ sender.name || '—' }}</dd>
						<dt>Adres</dt>
						<dd>{{ sender.address || '—' }}</dd>
					</dl>
					<p class="note">Gönderen bilgisi sunucunun e-posta ayarlarından (.env MAIL_FROM_*) gelir.</p>
				</div>
			</OzCard>
		</aside>
	</div>

	<Transition name="bar">
		<div v-if="dirty" class="savebar">
			<span>Kaydedilmemiş değişiklikler var</span>
			<div class="savebar-actions">
				<OzButton :disabled="saving" @click="reset">Vazgeç</OzButton>
				<OzButton variant="primary" icon="check" :loading="saving" @click="save">Kaydet</OzButton>
			</div>
		</div>
	</Transition>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.groups { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.groups-note { font-size: 12px; color: var(--oz-warning); }
/* OzCard gövdeye padding vermez; içerik başlığın 18px yatay boşluğuyla hizalanır. */
.pad { padding: 2px 18px 18px; }
.pad > .block:last-child { margin-bottom: 0; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 460px; gap: 14px; align-items: start; }
.side { position: sticky; top: 84px; }
.control { display: flex; align-items: center; gap: 10px; }
.switch-label { font-size: 12px; color: var(--oz-muted); }
.desc { margin: 0 0 14px; font-size: 13px; line-height: 1.55; color: var(--oz-muted); }
.fields { display: flex; flex-direction: column; gap: 14px; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.tags { margin-bottom: 16px; padding: 12px 14px; border: 1px dashed var(--oz-border); border-radius: 10px; background: var(--oz-surface-2); }
.tags-head { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); margin-bottom: 8px; }
.tags-head span { font-weight: 400; color: var(--oz-muted); }
.tag-list { display: flex; flex-wrap: wrap; gap: 8px; }
.tag {
	display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px;
	border: 1px solid var(--oz-border); border-radius: 8px; background: var(--oz-surface);
	font-size: 12px; color: var(--oz-muted); cursor: pointer;
}
.tag:hover { border-color: var(--oz-accent); color: var(--oz-ink-soft); }
.tag[draggable="true"] { cursor: grab; }
.tag:active { cursor: grabbing; }
.grip { color: var(--oz-muted); }
/* Sürüklerken bırakılabilecek alanlar vurgulanır. */
.fields.is-dragging :deep(input), .fields.is-dragging :deep(textarea) {
	outline: 2px dashed var(--oz-accent); outline-offset: 2px;
}
.tag code { font-size: 12px; color: var(--oz-accent); }
.card-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.subject { display: flex; flex-direction: column; gap: 2px; margin-bottom: 10px; font-size: 13px; }
.subject span { font-size: 11px; color: var(--oz-muted); text-transform: uppercase; letter-spacing: .06em; }
.subject strong { color: var(--oz-ink-soft); font-weight: 600; overflow-wrap: anywhere; }
.frame { display: block; width: 100%; height: 640px; border: 1px solid var(--oz-border); border-radius: 8px; background: #fff8f5; }
.error { margin: 0 0 10px; padding: 8px 10px; border-radius: 8px; background: var(--oz-danger-soft); color: var(--oz-danger); font-size: 12px; }
.note { margin: 10px 0 0; font-size: 12px; line-height: 1.55; color: var(--oz-muted); }
.facts { display: grid; grid-template-columns: 70px minmax(0, 1fr); gap: 8px 12px; margin: 0; font-size: 13px; }
.facts dt { color: var(--oz-muted); }
.facts dd { margin: 0; color: var(--oz-ink-soft); overflow-wrap: anywhere; }
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
@media (max-width: 1180px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } }
@media (max-width: 640px) { .grid { grid-template-columns: minmax(0, 1fr); } }
</style>
