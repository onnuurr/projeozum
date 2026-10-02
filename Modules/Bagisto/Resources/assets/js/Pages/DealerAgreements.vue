<script setup>
import { ref, computed, watch, nextTick, inject, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzTabs, OzField, OzInput, OzTextarea, OzButton, OzPill, OzAlert, OzSpinner,
	OzModal, OzEmptyState, OzCombobox, OzCheckbox, useOzConfirm,
} from '@/Components/Oz'
import AgreementEditor from '../Components/AgreementEditor.vue'

// Bayi Sözleşmeleri: bayi portalında (Bagisto) onaylatılan sözleşme ve onay metinleri.
//   Metin zengin metin editöründe (AgreementEditor, HTML — sunucuda süzülür) yazılır; eski Markdown sürümler
//   editöre HTML'e çevrilmiş gelir. Sağdaki önizleme sunucuda çizilir (POST /dealer-agreements/preview) ve
//   mağazaya giden HTML'in birebir aynısıdır. "Yayımla" yeni sürüm açar (POST .../{id}/publish);
//   yayımlanan sürüm değişmez — bayilerin onayladığı sürüm numarası başvuru kaydında saklanır.
//   Taslaklar sekmeler arasında korunur; sayfadan çıkarken kaydedilmemiş taslak varsa uyarılır.
//   Metinde {{company.legal_name}} gibi etiketler kullanılır (Bayi E-postaları'ndaki gibi): bayi ekranında ve onay
//   arşivinde bayinin bilgileriyle doldurulur; önizlemede örnek bayi verisi gösterilir. Etiket çipi tıklanınca
//   imlecin olduğu alana eklenir ya da alana sürüklenip bırakılır. Tanımsız etiket yayında reddedilir.
//   "Firma bilgileri ve etiketler" sekmesi: {{seller.*}} (firma bilgilerimiz) ve {{custom.*}} (özel etiketler) değerleri
//   (PUT /dealer-agreements/settings) — değer değişince tüm sözleşmelere yansır, onay arşivindeki kopyalar değişmez.
//   Dil (TR / EN): her dil ayrı taslak ve ayrı sürüm serisidir. liveLocales dışındaki diller (şimdilik İngilizce)
//   hazırlanır ve yayımlanabilir ama bayi portalında henüz gösterilmez (bekleme aşaması).
//   Taslak: "Taslak olarak kaydet" metni sunucuda saklar (PUT .../{id}/draft, sözleşme + dil başına tek taslak);
//   yayımlanmaz, bayi portalı yayımlanmış sürümü görmeye devam eder. Düzenleyici açılırken kayıtlı taslaktan başlar;
//   "Taslağı sil" (DELETE) yayımlanmış sürüme döner. Yayımlayınca o dilin taslağı sunucuda silinir.
//   Ekler (Ek-1, Ek-2 …): ana sözleşmenin alt belgeleri; "Ana metin | Ek-1 | …" seçicisiyle aynı düzenleyicide yazılır,
//   kendi sürüm/taslak/dil serileri vardır (ana metinden bağımsız yayımlanır). Ana metinde {{annex.N}} ile anılır.
//   Burada `agreement` = düzenlenen belge (ana metin ya da ek), `parentAgreement` = seçili sözleşme sekmesi.
defineOptions({ layout: AppLayout })

const props = defineProps({
	agreements: { type: Array, default: () => [] }, // [{ id, key, title, display, description, nextVersion, versions, drafts, annexes: [aynı biçim + annexNumber/annexLabel] }]
	tagGroups: { type: Array, default: () => [] }, // [{ label, tags: [{ tag, description }] }]
	seller: { type: Object, default: () => ({}) },
	sellerFields: { type: Object, default: () => ({}) }, // alan → açıklama
	customTags: { type: Array, default: () => [] }, // [{ key, description, value }]
	countryOptions: { type: Array, default: () => [] }, // [{ value: 'IR', label: 'İran', description: 'IR' }]
	blockedCountries: { type: Array, default: () => [] }, // ['IR', 'KP', …]
	blockedRegions: { type: Object, default: () => ({}) }, // { tr: 'Kırım, …', en: 'Crimea, …' }
	locales: { type: Object, default: () => ({ tr: 'Türkçe' }) }, // kod → ad
	liveLocales: { type: Array, default: () => ['tr'] },
})

const SETTINGS = '_settings'

const BASE = '/dealer-agreements'
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const LOCALE_CODES = Object.keys(props.locales)
const params = new URLSearchParams(window.location.search)
const locale = ref(LOCALE_CODES.includes(params.get('lang')) ? params.get('lang') : 'tr')
const isLive = (loc) => props.liveLocales.includes(loc)
const localeTabs = computed(() => LOCALE_CODES.map((code) => ({
	key: code,
	label: props.locales[code] + (isLive(code) ? '' : ' (bekleme)') + (agreement.value && isDirty(agreement.value, code) ? ' •' : ''),
})))

const versionsFor = (a, loc = locale.value) => a.versions.filter((v) => v.locale === loc)
const current = (a, loc = locale.value) => versionsFor(a, loc)[0] ?? null
// Yayımlanmış metin. Türkçe sözleşme adıyla başlar; başka dilde sürüm yoksa başlık boş başlar (çevirisi yazılır).
const publishedBase = (a, loc = locale.value) => ({
	title: current(a, loc)?.title ?? (loc === 'tr' ? a.title : ''),
	body: current(a, loc)?.body ?? '',
	change_note: '',
})
const savedDraft = (a, loc = locale.value) => a.drafts?.[loc] ?? null
// Düzenleyicinin başlangıcı: kayıtlı taslak varsa o, yoksa yayımlanmış metin.
const base = (a, loc = locale.value) => {
	const d = savedDraft(a, loc)
	return d ? { title: d.title, body: d.body, change_note: d.changeNote } : publishedBase(a, loc)
}
const same = (x, y) => x.title.trim() === y.title.trim() && x.body.trim() === y.body.trim()
const dkey = (a, loc = locale.value) => `${a.key}:${loc}`

// Ana sözleşmeler + ekleri (taslaklar ve "kaydedilmemiş değişiklik" denetimi hepsini kapsar).
const allDocs = computed(() => props.agreements.flatMap((a) => [a, ...(a.annexes ?? [])]))
const drafts = ref(Object.fromEntries(allDocs.value.flatMap((a) => LOCALE_CODES.map((loc) => [dkey(a, loc), base(a, loc)]))))
// Yeni açılan ekin taslak kaydı (mevcut taslaklara dokunulmaz).
watch(allDocs, (docs) => {
	for (const a of docs) for (const loc of LOCALE_CODES) if (!drafts.value[dkey(a, loc)]) drafts.value[dkey(a, loc)] = base(a, loc)
})
const errors = ref({})

const key = ref(params.get('agreement') ?? props.agreements[0]?.key)
const parentAgreement = computed(() => props.agreements.find((a) => a.key === key.value) ?? null)
const annexKey = ref(params.get('annex') ?? '')
// Düzenlenen belge: seçili ek ya da ana metin.
const agreement = computed(() => (annexKey.value
	? parentAgreement.value?.annexes?.find((x) => x.key === annexKey.value) ?? parentAgreement.value
	: parentAgreement.value) ?? null)
const isAnnex = computed(() => !!agreement.value?.annexNumber)
const docTitle = (a) => (a.annexLabel ? `${a.annexLabel} — ${current(a)?.title || a.title}` : a.title)
const draft = computed(() => (agreement.value ? drafts.value[dkey(agreement.value)] : null))
const nextVersion = computed(() => agreement.value?.nextVersion?.[locale.value] ?? '')

// Kaydedilmemiş değişiklik (kayıtlı taslağa ya da yayımlanmış metne göre).
const isDirty = (a, loc = null) => {
	if (loc === null) return LOCALE_CODES.some((code) => isDirty(a, code))
	const d = drafts.value[dkey(a, loc)]
	const b = base(a, loc)
	return !same(d, b) || (d.change_note ?? '').trim() !== (b.change_note ?? '').trim()
}
// Yayımlanacak bir fark var mı (düzenleyicideki metin yayımlanmış sürümden farklı)?
const differsFromPublished = (a, loc = locale.value) => !same(drafts.value[dkey(a, loc)], publishedBase(a, loc))
// ── Firma bilgilerimiz + özel etiketler ─────────────────────────────────────
const sellerBase = () => ({ ...props.seller })
const customBase = () => props.customTags.map((t) => ({ ...t }))
const blockedBase = () => [...props.blockedCountries]
const regionsBase = () => ({ tr: props.blockedRegions.tr ?? '', en: props.blockedRegions.en ?? '' })
const sellerForm = ref(sellerBase())
const customForm = ref(customBase())
// Gönderim yapılmayan ülkeler: sözleşmede {{shipping.blocked_countries}}, bayi portalında teslimat/sipariş engeli.
const blockedForm = ref(blockedBase())
const regionsForm = ref(regionsBase())
const settingsErrors = ref({})
const savingSettings = ref(false)
const settingsDirty = computed(() =>
	JSON.stringify(sellerForm.value) !== JSON.stringify(sellerBase())
	|| JSON.stringify(customForm.value) !== JSON.stringify(customBase())
	|| JSON.stringify([...blockedForm.value].sort()) !== JSON.stringify([...blockedBase()].sort())
	|| JSON.stringify(regionsForm.value) !== JSON.stringify(regionsBase()))
function resetSettings() {
	sellerForm.value = sellerBase()
	customForm.value = customBase()
	blockedForm.value = blockedBase()
	regionsForm.value = regionsBase()
	settingsErrors.value = {}
}

function addCustomTag() {
	customForm.value.push({ key: '', description: '', value: '' })
}
function removeCustomTag(i) {
	customForm.value.splice(i, 1)
}
// Anahtar: küçük harf, rakam, alt çizgi (Türkçe karakterler ASCII'ye, boşluk/tire alt çizgiye çevrilir).
const TR_ASCII = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'i̇': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u' }
const toKey = (text) => (text ?? '')
	.toLocaleLowerCase('tr-TR')
	.replace(/i̇|[çğıöşü]/g, (ch) => TR_ASCII[ch] ?? ch)
	.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
	.replace(/[\s-]+/g, '_')
	.replace(/[^a-z0-9_]/g, '')
	.replace(/_+/g, '_')
	.replace(/^_|_$/g, '')
	.slice(0, 40)
function normalizeKey(t) {
	t.key = toKey(t.key)
}
const isEmptyTag = (t) => !(t.key ?? '').trim() && !(t.description ?? '').trim() && !(t.value ?? '').trim()

async function saveSettings() {
	// Ülke listesi/bölgeler değiştiyse: ek sözleşme otomatik yayımlanır, mevcut bayilerden girişte onay istenir.
	const shippingChanged = JSON.stringify([...blockedForm.value].sort()) !== JSON.stringify([...blockedBase()].sort())
		|| JSON.stringify(regionsForm.value) !== JSON.stringify(regionsBase())
	if (shippingChanged) {
		const ok = await confirm({
			title: 'Gönderim listesi değişiyor',
			message: 'Kaydedince Gönderim Kısıtlaması Ek Sözleşmesi\'nin yeni sürümü yeni ülke listesiyle yayımlanır. Mevcut bayiler portala bir sonraki girişlerinde bu ek sözleşmeyi onaylamadan devam edemez. Sipariş engeli birkaç dakika içinde devreye girer.',
			confirmText: 'Kaydet ve yayımla',
			tone: 'warning',
		})
		if (!ok) return
	}
	// Boş satırlar gönderilmez; anahtarı boş bırakılan satırda anahtar açıklamadan üretilir.
	const tags = customForm.value
		.filter((t) => !isEmptyTag(t))
		.map((t) => ({ ...t, key: toKey(t.key) || toKey(t.description) }))
	savingSettings.value = true
	router.put(`${BASE}/settings`, { seller: sellerForm.value, custom_tags: tags, blocked_countries: blockedForm.value, blocked_regions: regionsForm.value }, {
		preserveScroll: true,
		onSuccess: () => resetSettings(),
		onError: (errs) => {
			settingsErrors.value = errs
			showToast?.({ type: 'error', title: 'Kaydedilemedi', message: 'Formdaki hataları düzeltip tekrar deneyin.' })
		},
		onFinish: () => { savingSettings.value = false },
	})
}
// Formun üstündeki özet: tüm hatalar (satır numarasıyla).
const settingsErrorList = computed(() => Object.entries(settingsErrors.value).map(([k, msg]) => {
	const row = k.match(/^custom_tags\.(\d+)\./)
	return row ? `Özel etiket ${Number(row[1]) + 1}: ${msg}` : msg
}))
const blockedCountriesError = computed(() => {
	const k = Object.keys(settingsErrors.value).find((x) => x === 'blocked_countries' || x.startsWith('blocked_countries.'))
	return k ? settingsErrors.value[k] : ''
})
const customError = (i, field) => settingsErrors.value[`custom_tags.${i}.${field}`] || ''

const anyDirty = computed(() => settingsDirty.value || allDocs.value.some(isDirty))
const parentDirty = (a) => isDirty(a) || (a.annexes ?? []).some((x) => isDirty(x))

// Ana metin / ek seçici
const annexTabs = computed(() => {
	const p = parentAgreement.value
	if (!p) return []
	return [
		{ key: '', label: 'Ana metin' + (isDirty(p) ? ' •' : '') },
		...(p.annexes ?? []).map((x) => ({ key: x.key, label: `${x.annexLabel}: ${current(x)?.title || x.title}` + (isDirty(x) ? ' •' : '') })),
	]
})
const canHaveAnnexes = computed(() => parentAgreement.value?.display === 'document')
watch(key, () => { annexKey.value = '' })

// Ek aç / kaldır
const annexOpen = ref(false)
const annexTitle = ref('')
const annexError = ref('')
const creatingAnnex = ref(false)
function openAnnexModal() {
	annexTitle.value = ''
	annexError.value = ''
	annexOpen.value = true
}
function createAnnex() {
	const p = parentAgreement.value
	if (!p) return
	creatingAnnex.value = true
	router.post(`${BASE}/${p.id}/annexes`, { title: annexTitle.value }, {
		preserveScroll: true,
		onSuccess: () => {
			annexOpen.value = false
			const fresh = props.agreements.find((x) => x.key === p.key)
			const created = [...(fresh?.annexes ?? [])].sort((a, b) => b.annexNumber - a.annexNumber)[0]
			if (created) annexKey.value = created.key
		},
		onError: (errs) => { annexError.value = errs.title ?? Object.values(errs)[0] ?? '' },
		onFinish: () => { creatingAnnex.value = false },
	})
}
async function retireAnnex() {
	const a = agreement.value
	if (!a?.annexNumber) return
	const ok = await confirm.danger(`${a.annexLabel} kaldırılsın mı?`, 'Ek bayi portalında artık gösterilmez. Daha önce onaylanmış kopyalar ve geçmiş sürümler korunur; numarası yeniden kullanılmaz.', { confirmText: 'Eki kaldır' })
	if (!ok) return
	router.delete(`${BASE}/${a.id}/annex`, {
		preserveScroll: true,
		onSuccess: () => { annexKey.value = '' },
	})
}

// Etiket çipleri: ana metinde ek atıfları da ({{annex.N}}).
const docTagGroups = computed(() => {
	const annexes = !isAnnex.value ? (parentAgreement.value?.annexes ?? []) : []
	if (!annexes.length) return props.tagGroups
	return [...props.tagGroups, {
		label: 'Ekler',
		tags: annexes.map((x) => ({ tag: `annex.${x.annexNumber}`, description: `${x.annexLabel}: ${current(x)?.title || x.title}` })),
	}]
})

const tabs = computed(() => [
	...props.agreements.map((a) => ({
		key: a.key,
		label: a.title + (parentDirty(a) ? ' •' : ''),
		icon: a.display === 'consent' ? 'check' : 'file',
	})),
	{ key: SETTINGS, label: 'Firma bilgileri ve etiketler' + (settingsDirty.value ? ' •' : ''), icon: 'settings' },
])

watch([key, locale, annexKey], ([k, loc, ax]) => {
	errors.value = {}
	const url = new URL(window.location.href)
	url.searchParams.set('agreement', k)
	url.searchParams.set('lang', loc)
	if (ax) url.searchParams.set('annex', ax)
	else url.searchParams.delete('annex')
	window.history.replaceState(window.history.state, '', url)
})

// ── Önizleme (taslak, gecikmeli) ───────────────────────────────────────────
const previewHtml = ref('')
const previewTitle = ref('')
const unknownTags = ref([])
const previewLoading = ref(false)
const previewError = ref('')
let timer = null
let seq = 0

async function loadPreview() {
	if (!agreement.value) return
	const id = ++seq
	previewLoading.value = true
	try {
		const { data } = await axios.post(`${BASE}/preview`, {
			title: draft.value.title,
			body: draft.value.body,
			display: agreement.value.display,
			agreement: agreement.value.id,
			version: nextVersion.value,
			locale: locale.value,
			format: 'html',
		})
		if (id !== seq) return
		previewHtml.value = data.html
		previewTitle.value = data.title
		unknownTags.value = data.unknown ?? []
		previewError.value = ''
	} catch {
		if (id === seq) previewError.value = 'Önizleme yüklenemedi.'
	} finally {
		if (id === seq) previewLoading.value = false
	}
}

watch(() => [key.value, annexKey.value, locale.value, draft.value?.body, draft.value?.title], () => {
	clearTimeout(timer)
	timer = setTimeout(loadPreview, 350)
}, { immediate: true })

// ── Etiket ekleme: son odaklanan alanın (başlık / metin) imlecine yazar ──────
const token = (tag) => `{{${tag}}}`
const focused = ref(null) // { field, el }
function onFocusIn(field, e) {
	if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) focused.value = { field, el: e.target }
}
const bodyEditor = ref(null)
async function insertTag(tag) {
	const text = token(tag)
	const target = focused.value ?? { field: 'body', el: null }
	// Metin alanı zengin editör: etiket imlecin olduğu yere editörün kendi komutuyla eklenir.
	if (target.field === 'body') {
		bodyEditor.value?.insertText(text)
		return
	}
	const value = draft.value[target.field] ?? ''
	const el = target.el
	const start = el ? el.selectionStart ?? value.length : value.length
	const end = el ? el.selectionEnd ?? value.length : value.length
	draft.value[target.field] = value.slice(0, start) + text + value.slice(end)
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

// ── Yayınla ────────────────────────────────────────────────────────────────
const publishing = ref(false)
// Mevcut bayilerden portala girişte yeniden onay iste (yalnız yayındaki dilde, uzun metin sözleşmelerde/eklerde).
const requireAcceptance = ref(false)
const canRequireAcceptance = computed(() => agreement.value?.display === 'document' && isLive(locale.value))
watch([key, annexKey, locale], () => { requireAcceptance.value = false })

async function publish() {
	const a = agreement.value
	if (!a) return
	const loc = locale.value
	const version = nextVersion.value
	const lang = props.locales[loc]
	const reaccept = canRequireAcceptance.value && requireAcceptance.value
	const ok = await confirm({
		title: `${lang} ${version} sürümü yayımlansın mı?`,
		message: isLive(loc)
			? `“${draft.value.title}” bayi portalında hemen güncellenir (en geç birkaç dakika içinde). Bundan sonra onaylayan bayiler ${version} sürümünü onaylamış sayılır; önceki sürümler geçmişte kalır ve değiştirilemez.`
				+ (reaccept ? ' Mevcut bayiler portala bir sonraki girişlerinde bu sürümü onaylamadan devam edemez.' : '')
			: `${lang} metin kaydedilir ve sürüm geçmişine eklenir; bekleme aşamasında olduğu için bayi portalında henüz gösterilmez. Yayımlanan sürüm değiştirilemez.`,
		confirmText: reaccept ? 'Yayımla ve onay iste' : 'Yayımla',
		tone: reaccept ? 'warning' : 'accent',
	})
	if (!ok) return

	publishing.value = true
	router.post(`${BASE}/${a.id}/publish`, { ...draft.value, locale: loc, requires_acceptance: reaccept }, {
		preserveScroll: true,
		onSuccess: () => {
			errors.value = {}
			const fresh = allDocs.value.find((x) => x.key === a.key)
			if (fresh) drafts.value[dkey(fresh, loc)] = base(fresh, loc)
		},
		onError: (errs) => { errors.value = errs },
		onFinish: () => { publishing.value = false },
	})
}

function resetDraft() {
	if (agreement.value) drafts.value[dkey(agreement.value)] = base(agreement.value)
	errors.value = {}
}

// ── Taslak (sunucuda) ──────────────────────────────────────────────────────
const savingDraft = ref(false)
function saveDraft() {
	const a = agreement.value
	if (!a) return
	const loc = locale.value
	savingDraft.value = true
	router.put(`${BASE}/${a.id}/draft`, { ...draft.value, locale: loc }, {
		preserveScroll: true,
		onSuccess: () => {
			errors.value = {}
			const fresh = allDocs.value.find((x) => x.key === a.key)
			if (fresh) drafts.value[dkey(fresh, loc)] = base(fresh, loc)
		},
		onError: (errs) => { errors.value = errs },
		onFinish: () => { savingDraft.value = false },
	})
}
async function discardDraft() {
	const a = agreement.value
	if (!a) return
	const loc = locale.value
	const ok = await confirm.danger('Taslak silinsin mi?', 'Kayıtlı taslak silinir ve düzenleyici yayımlanmış sürüme döner. Bu işlem geri alınamaz.', { confirmText: 'Taslağı sil' })
	if (!ok) return
	router.delete(`${BASE}/${a.id}/draft`, {
		data: { locale: loc },
		preserveScroll: true,
		onSuccess: () => {
			const fresh = allDocs.value.find((x) => x.key === a.key)
			if (fresh) drafts.value[dkey(fresh, loc)] = base(fresh, loc)
		},
	})
}

// ── Sürüm geçmişi ──────────────────────────────────────────────────────────
const viewing = ref(null)
const viewOpen = ref(false)
function viewVersion(v) {
	viewing.value = v
	viewOpen.value = true
}
function loadVersion(v) {
	drafts.value[dkey(agreement.value)] = { title: v.title, body: v.body, change_note: `${v.version} sürümüne geri dönüş` }
	viewOpen.value = false
}

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' }) : '—')

// ── Kaydedilmemiş taslak uyarısı ───────────────────────────────────────────
let leaving = false
const removeBefore = router.on('before', (event) => {
	if (leaving || !anyDirty.value || event.detail.visit.method !== 'get') return
	event.preventDefault()
	confirm.warning('Kaydedilmemiş değişiklikler var', 'Sayfadan çıkarsanız kaydetmediğiniz değişiklikler kaybolur. Saklamak için “Taslak olarak kaydet”i kullanın.', { confirmText: 'Çık', cancelText: 'Kal' })
		.then((ok) => {
			if (!ok) return
			leaving = true
			router.visit(event.detail.visit.url)
		})
})
const onBeforeUnload = (e) => {
	if (anyDirty.value) {
		e.preventDefault()
		e.returnValue = ''
	}
}
window.addEventListener('beforeunload', onBeforeUnload)
onBeforeUnmount(() => {
	removeBefore()
	window.removeEventListener('beforeunload', onBeforeUnload)
	clearTimeout(timer)
})
</script>

<template>
	<Head title="Bayi Sözleşmeleri" />

	<OzPageHeader title="Bayi Sözleşmeleri" subtitle="Bayi portalında onaylatılan sözleşme ve onay metinleri — her yayın yeni bir sürümdür" />
	<OzModuleTabs />

	<OzEmptyState v-if="!agreements.length" icon="file" title="Sözleşme kaydı yok" description="Sözleşme kayıtları migration ile açılır." />

	<template v-else>
		<OzTabs v-model="key" :tabs="tabs" aria-label="Sözleşmeler" class="block" />

		<div v-if="parentAgreement && canHaveAnnexes" class="annex-bar block">
			<OzTabs v-model="annexKey" :tabs="annexTabs" variant="segment" aria-label="Ana metin ve ekler" />
			<OzButton size="sm" icon="plus" @click="openAnnexModal">Ek ekle</OzButton>
		</div>

		<div v-if="agreement" class="lang-bar block">
			<OzTabs v-model="locale" :tabs="localeTabs" variant="segment" aria-label="Sözleşme dili" />
			<span v-if="!isLive(locale)" class="lang-note">Bekleme aşaması: hazırlanır ve arşivlenir, bayi portalında henüz gösterilmez.</span>
		</div>

		<div v-if="agreement" class="layout">
			<div class="main">
				<OzCard :title="docTitle(agreement)" class="block">
					<template #actions>
						<div class="control">
							<OzPill v-if="!isLive(locale)" tone="info">{{ locales[locale] }} · bekleme</OzPill>
							<OzPill v-if="isDirty(agreement, locale)" tone="warning">Kaydedilmedi</OzPill>
							<OzPill v-if="savedDraft(agreement)" tone="accent" :title="`${formatDate(savedDraft(agreement).updatedAt)} · ${savedDraft(agreement).editor || '—'}`">Taslak</OzPill>
							<OzPill v-if="current(agreement)" tone="success">Güncel: {{ current(agreement).version }}</OzPill>
							<OzPill v-else tone="neutral">Henüz yayımlanmadı</OzPill>
						</div>
					</template>
					<div class="pad">
						<p v-if="agreement.description" class="desc">{{ agreement.description }}</p>
						<p v-if="isAnnex" class="desc">
							{{ parentAgreement.title }} eki. Ana metinden bağımsız sürümlenir; bayi portalında ana metnin ardından gösterilir ve
							onay kopyasına bayinin gördüğü sürümüyle yazılır. Ana metinde <code v-pre>{{annex.</code>{{ agreement.annexNumber }}<code v-pre>}}</code> ile anılır.
						</p>

						<OzAlert v-if="savedDraft(agreement)" tone="accent" class="block">
							Kayıtlı taslak — {{ formatDate(savedDraft(agreement).updatedAt) }}, {{ savedDraft(agreement).editor || '—' }}.
							Yayımlanana kadar bayi portalında {{ current(agreement)?.version ? `${current(agreement).version} sürümü` : 'hiçbir metin' }} gösterilir.
						</OzAlert>

						<OzAlert v-if="!current(agreement) && isLive(locale)" tone="info" class="block">
							Bu metin henüz yayımlanmadı; bayi portalında başlığı bağlantısız gösterilir. Metni yazıp yayımlayınca bayiler okuyabilir.
						</OzAlert>
						<OzAlert v-if="agreement.display === 'consent'" tone="info" class="block">
							Onay kutusunun yanında tek paragraf olarak gösterilir; satır sonları ve başlıklar dikkate alınmaz.
						</OzAlert>

						<div class="tags">
							<div class="tags-head">
								Kullanılabilir etiketler <span>— tıklayınca imlecin olduğu yere eklenir ya da alana sürükleyin</span>
								<button type="button" class="link tags-edit" @click="key = SETTINGS">Firma bilgileri / özel etiketler</button>
							</div>
							<div v-for="g in docTagGroups" :key="g.label" class="tag-group">
							<div class="tag-group-label">{{ g.label }}</div>
							<div class="tag-list">
								<button
									v-for="t in g.tags"
									:key="t.tag"
									type="button"
									class="tag"
									draggable="true"
									:title="`${t.description} — sürükleyip alana bırakın`"
									@dragstart="onDragStart($event, t.tag)"
									@dragend="dragging = false"
									@click="insertTag(t.tag)"
								>
									<code v-text="token(t.tag)" />
									<span>{{ t.description }}</span>
								</button>
							</div>
							</div>
						</div>

						<OzAlert v-if="unknownTags.length" tone="danger" class="block">
							Tanımsız etiket: <code v-for="(t, i) in unknownTags" :key="t">{{ token(t) }}<template v-if="i < unknownTags.length - 1">, </template></code>. Yayımlamadan önce düzeltin.
						</OzAlert>

						<div class="fields" :class="{ 'is-dragging': dragging }">
							<OzField label="Başlık" required :error="errors.title" @focusin="onFocusIn('title', $event)">
								<OzInput v-model="draft.title" maxlength="255" :invalid="!!errors.title" />
							</OzField>
							<OzField label="Metin" required :error="errors.body" :hint="agreement.display === 'consent' ? 'Onay kutusunun yanında tek satır gösterilir; yalnızca kalın, italik, altı çizili ve bağlantı kullanılır.' : 'Araç çubuğuyla kalın, italik, altı çizili, başlık, liste, hizalama ve bağlantı ekleyebilirsiniz. Başka biçimler (renk, yazı tipi, görsel) kaydederken atılır.'">
								<AgreementEditor
									:key="`${agreement.key}:${locale}`"
									ref="bodyEditor"
									v-model="draft.body"
									:mode="agreement.display === 'consent' ? 'consent' : 'document'"
									:invalid="!!errors.body"
									@focus="focused = { field: 'body', el: null }"
								/>
							</OzField>
							<OzField label="Değişiklik notu" hint="Sürüm geçmişinde görünür; bayiye gösterilmez." :error="errors.change_note">
								<OzInput v-model="draft.change_note" maxlength="500" placeholder="Örn. Madde 4 güncellendi" />
							</OzField>
						</div>

						<div v-if="canRequireAcceptance" class="reaccept">
							<OzCheckbox v-model="requireAcceptance" label="Mevcut bayilerden yeniden onay iste" />
							<span>Bayiler portala bir sonraki girişte bu sürümü onaylamadan devam edemez (onay arşive yazılır).</span>
						</div>

						<div class="card-actions">
							<OzButton icon="undo" :disabled="!isDirty(agreement, locale)" @click="resetDraft">Değişiklikleri geri al</OzButton>
							<OzButton v-if="isAnnex" variant="danger" icon="archive" @click="retireAnnex">Eki kaldır</OzButton>
							<OzButton v-if="savedDraft(agreement)" variant="danger" icon="trash" @click="discardDraft">Taslağı sil</OzButton>
							<OzButton icon="save" :loading="savingDraft" :disabled="!isDirty(agreement, locale)" @click="saveDraft">Taslak olarak kaydet</OzButton>
							<OzButton variant="primary" icon="check" :loading="publishing" :disabled="!differsFromPublished(agreement) || !draft.title.trim() || !draft.body.trim() || unknownTags.length > 0" @click="publish">
								{{ locale.toUpperCase() }} {{ nextVersion }} olarak yayımla
							</OzButton>
						</div>
					</div>
				</OzCard>
			</div>

			<aside class="side">
				<OzCard title="Önizleme" class="block">
					<template #actions>
						<OzSpinner v-if="previewLoading" :size="14" label="Önizleme yükleniyor" />
					</template>
					<div class="pad">
						<p v-if="previewError" class="error">{{ previewError }}</p>
						<div class="doc">
							<div class="doc-title">{{ previewTitle || draft.title || '—' }}</div>
							<!-- Sunucu tarafında Markdown'dan üretilir; ham HTML atılmıştır. -->
							<div v-if="previewHtml" class="doc-body" :class="{ 'is-consent': agreement.display === 'consent' }" v-html="previewHtml" />
							<p v-else class="muted">Metin boş.</p>
						</div>
						<p class="note">Etiketler örnek bayi verisiyle (Lila Butik) doldurulur; bayi portalında ve onay kopyasında bayinin kendi bilgileri yazar. Kaydedilmemiş değişiklikler de önizlemeye yansır.</p>
					</div>
				</OzCard>

				<OzCard title="Sürüm geçmişi" :meta="`${versionsFor(agreement).length} sürüm · ${locales[locale]}`">
					<div class="pad">
						<p v-if="!versionsFor(agreement).length" class="muted">Bu dilde henüz sürüm yok.</p>
						<ol v-else class="versions">
							<li v-for="(v, i) in versionsFor(agreement)" :key="v.id" class="version">
								<div class="version-head">
									<strong class="mono">{{ v.version }}</strong>
									<OzPill v-if="i === 0" tone="success">Güncel</OzPill>
									<OzPill v-if="v.requiresAcceptance" tone="warning">Yeniden onay</OzPill>
									<button type="button" class="link" @click="viewVersion(v)">Görüntüle</button>
								</div>
								<div class="version-meta">{{ formatDate(v.publishedAt) }} · {{ v.publisher || 'Sistem' }}</div>
								<div v-if="v.changeNote" class="version-note">{{ v.changeNote }}</div>
							</li>
						</ol>
					</div>
				</OzCard>
			</aside>
		</div>

		<!-- Firma bilgilerimiz + özel etiketler -->
		<div v-else-if="key === SETTINGS" class="settings">
			<OzAlert v-if="settingsErrorList.length" tone="danger" title="Kaydedilemedi" class="block">
				<ul class="error-list"><li v-for="(m, i) in settingsErrorList" :key="i">{{ m }}</li></ul>
			</OzAlert>

			<OzCard title="Firma bilgilerimiz" subtitle="{{seller.*}} etiketleri" class="block">
				<div class="pad">
					<p class="desc">Sözleşmelerde satıcı taraf olarak geçen bilgilerimiz. Değiştirince tüm sözleşmelerde (bayi portalı dahil) yeni değer görünür; onay arşivindeki kopyalar onay anındaki bilgilerle kalır.</p>
					<div class="grid">
						<OzField v-for="(label, field) in sellerFields" :key="field" :label="label" :error="settingsErrors[`seller.${field}`]" :hint="`{{seller.${field}}}`">
							<OzTextarea v-if="field === 'address'" v-model="sellerForm[field]" :rows="2" />
							<OzInput v-else v-model="sellerForm[field]" />
						</OzField>
					</div>
				</div>
			</OzCard>

			<OzCard title="Gönderim yapılmayan ülkeler" subtitle="{{shipping.blocked_countries}}" class="block">
				<div class="pad">
					<p class="desc">
						Seçilen ülkelere bayi portalında teslimat adresi girilemez ve sipariş verilemez. Aynı liste sözleşmede
						<code v-pre>{{shipping.blocked_countries}}</code> etiketiyle, sözleşmenin dilinde ülke adları olarak yazılır (ör. Madde 19.1 "özellikle …").
						Yaptırım listeleri değiştikçe güncelleyin; hangi ülkelerin yazılacağına hukuk danışmanınız karar vermelidir.
					</p>
					<OzField label="Ülkeler" :error="blockedCountriesError">
						<OzCombobox v-model="blockedForm" :options="countryOptions" multiple :max-tags="8" placeholder="Ülke seçin" search-placeholder="Ülke ara…" />
					</OzField>
					<div class="grid regions">
						<OzField label="Ülke kodu olmayan bölgeler (Türkçe)" hint="Yalnız sözleşmede yazılır, sipariş engeline girmez. Ör. Kırım, Donetsk, Luhansk" :error="settingsErrors['blocked_regions.tr']">
							<OzInput v-model="regionsForm.tr" maxlength="1000" />
						</OzField>
						<OzField label="Bölgeler (İngilizce)" hint="İngilizce sözleşme için. Ör. Crimea, Donetsk, Luhansk" :error="settingsErrors['blocked_regions.en']">
							<OzInput v-model="regionsForm.en" maxlength="1000" />
						</OzField>
					</div>
				</div>
			</OzCard>

			<OzCard title="Özel etiketler" subtitle="{{custom.*}} etiketleri" class="block">
				<template #actions>
					<OzButton size="sm" icon="plus" @click="addCustomTag">Etiket ekle</OzButton>
				</template>
				<div class="pad">
					<p class="desc">Sözleşmelerde tekrar eden, zamanla değişebilen sabit değerler (iade süresi, asgari fiyat oranı, yetkili mahkeme…). Anahtar küçük harf, rakam ve alt çizgiden oluşur; metinde <code v-pre>{{custom.anahtar}}</code> olarak kullanılır.</p>
					<OzAlert v-if="settingsErrors.custom_tags" tone="danger" class="block">{{ settingsErrors.custom_tags }}</OzAlert>
					<p v-if="!customForm.length" class="muted">Henüz özel etiket yok.</p>
					<div v-for="(t, i) in customForm" :key="i" class="custom-row">
						<OzField label="Anahtar" :error="customError(i, 'key')" :hint="t.key ? `{{custom.${t.key}}}` : ''">
							<OzInput v-model="t.key" class="mono" maxlength="40" placeholder="iade_suresi" @blur="normalizeKey(t)" />
						</OzField>
						<OzField label="Açıklama" :error="customError(i, 'description')">
							<OzInput v-model="t.description" maxlength="120" placeholder="İade süresi" />
						</OzField>
						<OzField label="Değer" :error="customError(i, 'value')">
							<OzInput v-model="t.value" maxlength="1000" placeholder="14 gün" />
						</OzField>
						<OzButton class="custom-remove" icon="trash" aria-label="Etiketi sil" @click="removeCustomTag(i)" />
					</div>
				</div>
			</OzCard>

			<div class="card-actions">
				<OzButton icon="undo" :disabled="!settingsDirty" @click="resetSettings">Değişiklikleri geri al</OzButton>
				<OzButton variant="primary" icon="save" :loading="savingSettings" :disabled="!settingsDirty" @click="saveSettings">Kaydet</OzButton>
			</div>
		</div>
	</template>

	<OzModal v-model:open="annexOpen" title="Ek ekle" :subtitle="parentAgreement?.title ?? ''" icon="plus" size="sm" :persistent="creatingAnnex">
		<OzField label="Ek başlığı" required :error="annexError" hint="Örn. Asgari Satış Fiyatı Politikası. Numara (Ek-1, Ek-2 …) otomatik verilir.">
			<OzInput v-model="annexTitle" maxlength="255" @keydown.enter.prevent="createAnnex" />
		</OzField>
		<template #footer>
			<OzButton :disabled="creatingAnnex" @click="annexOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="plus" :loading="creatingAnnex" :disabled="!annexTitle.trim()" @click="createAnnex">Ek ekle</OzButton>
		</template>
	</OzModal>

	<OzModal v-model:open="viewOpen" :title="viewing ? `${viewing.title} · ${viewing.version}` : ''" :subtitle="viewing ? formatDate(viewing.publishedAt) : ''" icon="file" size="lg">
		<div v-if="viewing" class="doc">
			<div class="doc-body" :class="{ 'is-consent': agreement?.display === 'consent' }" v-html="viewing.html" />
		</div>
		<template #footer>
			<OzButton @click="viewOpen = false">Kapat</OzButton>
			<OzButton v-if="viewing && agreement && viewing.id !== current(agreement)?.id" icon="undo" @click="loadVersion(viewing)">Bu metni düzenleyiciye al</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.block { margin-bottom: 14px; }
/* OzCard gövdeye padding vermez; içerik başlığın 18px yatay boşluğuyla hizalanır. */
.pad { padding: 2px 18px 18px; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 460px; gap: 14px; align-items: start; }
.side { position: sticky; top: 84px; }
.control { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.desc { margin: 0 0 14px; font-size: 13px; line-height: 1.55; color: var(--oz-muted); }
.fields { display: flex; flex-direction: column; gap: 14px; }
.tags { margin-bottom: 16px; padding: 12px 14px; border: 1px dashed var(--oz-border); border-radius: 10px; background: var(--oz-surface-2); }
.tags-head { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); margin-bottom: 8px; }
.tags-head { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.tags-head span { font-weight: 400; color: var(--oz-muted); }
.tags-edit { margin-left: auto; font-weight: 500; }
.error-list { margin: 0; padding-left: 18px; }
.reaccept { display: flex; flex-direction: column; gap: 4px; margin-top: 16px; padding: 10px 12px; border: 1px dashed var(--oz-border); border-radius: 10px; background: var(--oz-surface-2); }
.reaccept span { font-size: 12px; color: var(--oz-muted); }
.regions { margin-top: 14px; }
.lang-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.annex-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.annex-bar :deep(.oz-tabs) { min-width: 0; max-width: 100%; overflow-x: auto; }
.lang-note { font-size: 12px; color: var(--oz-muted); }
.tag-group + .tag-group { margin-top: 10px; }
.tag-group-label { margin-bottom: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.settings { max-width: 1100px; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.custom-row { display: grid; grid-template-columns: 200px minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 10px; align-items: start; padding: 10px 0; border-top: 1px dashed var(--oz-border); }
.custom-row:first-of-type { border-top: none; }
.custom-remove { margin-top: 22px; }
@media (max-width: 760px) { .grid, .custom-row { grid-template-columns: minmax(0, 1fr); } .custom-remove { margin-top: 0; } }
.tag-list { display: flex; flex-wrap: wrap; gap: 8px; }
.tag {
	display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px;
	border: 1px solid var(--oz-border); border-radius: 8px; background: var(--oz-surface);
	font-size: 12px; color: var(--oz-muted); cursor: grab;
}
.tag:hover { border-color: var(--oz-accent); color: var(--oz-ink-soft); }
.tag:active { cursor: grabbing; }
.tag code { font-size: 12px; color: var(--oz-accent); }
/* Sürüklerken bırakılabilecek alanlar vurgulanır. */
.fields.is-dragging :deep(input), .fields.is-dragging :deep(textarea), .fields.is-dragging :deep(.agreement-prose) { outline: 2px dashed var(--oz-accent); outline-offset: 2px; }
.mono :deep(textarea) { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; line-height: 1.6; }
.card-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.error { margin: 0 0 10px; padding: 8px 10px; border-radius: 8px; background: var(--oz-danger-soft); color: var(--oz-danger); font-size: 12px; }
.note { margin: 10px 0 0; font-size: 12px; line-height: 1.55; color: var(--oz-muted); }
.muted { margin: 0; font-size: 13px; color: var(--oz-muted); }

.doc { max-height: 520px; overflow-y: auto; padding: 14px 16px; border: 1px solid var(--oz-border); border-radius: 10px; background: var(--oz-surface-2); }
.doc-title { margin-bottom: 8px; font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.doc-body { font-size: 13px; line-height: 1.65; color: var(--oz-ink-soft); overflow-wrap: anywhere; }
.doc-body :deep(p) { margin: 0 0 10px; }
.doc-body :deep(p:last-child) { margin-bottom: 0; }
.doc-body :deep(h1), .doc-body :deep(h2), .doc-body :deep(h3) { margin: 14px 0 6px; font-size: 14px; font-weight: 700; }
.doc-body :deep(ul), .doc-body :deep(ol) { margin: 0 0 10px; padding-left: 20px; }
.doc-body :deep(a) { color: var(--oz-accent); }

.versions { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.version { padding: 10px 0; border-bottom: 1px dashed var(--oz-border); }
.version:first-child { padding-top: 0; }
.version:last-child { border-bottom: none; padding-bottom: 0; }
.version-head { display: flex; align-items: center; gap: 8px; }
.version-head .link { margin-left: auto; }
.version-meta { margin-top: 2px; font-size: 12px; color: var(--oz-muted); }
.version-note { margin-top: 4px; font-size: 12.5px; color: var(--oz-ink-soft); }
.mono { font-family: ui-monospace, monospace; }
.link { padding: 0; border: none; background: none; color: var(--oz-accent); font: inherit; font-size: 12.5px; cursor: pointer; }
.link:hover { text-decoration: underline; }

@media (max-width: 1180px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } }
</style>
