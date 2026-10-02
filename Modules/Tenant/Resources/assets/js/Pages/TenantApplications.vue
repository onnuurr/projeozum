<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzButton, OzEmptyState, OzIcon,
	OzDrawer, OzModal, OzField, OzTextarea, OzAlert, OzCheckbox, OzInput, OzSelect,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// İş ortağı başvuruları (Oz kit). Satıra tıklayınca ayrıntı paneli açılır (mesaj, adres, belgeler, inceleme bilgisi).
//   Web (/tenant-basvuru): Onayla → /tenants?from_application=ID ile iş ortağı oluşturulur.
//   Bayi portalı (source=bagisto), iki aşama: ön başvuru → "Belge aşamasını aç" (görüşme sonrası) → bayi resmi
//   belgeleri yükler → evrak incelemesi (⇄ eksik evrak) → "Onayla ve hesabı aç" (iş ortağı pasif açılır, bayiye
//   üyelik tamamlama daveti gider) → bayi parolasını/eksik bilgilerini girince iş ortağı aktifleşir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	applications: { type: Array, default: () => [] },
	tenantTypes: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	// Bayi Portalı › "Cari hesap ve bakiyeden ödeme" kapalıyken kredi limiti sorulmaz.
	cariEnabled: { type: Boolean, default: false },
	defaultDiscountRate: { type: Number, default: 0 },
})

const { can } = useCan()
const canApprove = computed(() => can('tenant-application.approve'))
const canManageTenants = computed(() => can('tenant.manage'))
const canManageMails = computed(() => can('tenant-application-mail.manage'))
const showToast = inject('showToast', null)

const STATUS = {
	pending: { tone: 'warning', label: 'Beklemede' },
	documents_requested: { tone: 'info', label: 'Resmi belge bekleniyor' },
	documents_review: { tone: 'warning', label: 'Evrak incelemesi' },
	missing_documents: { tone: 'info', label: 'Eksik evrak bekleniyor' },
	approved: { tone: 'success', label: 'Onaylandı' },
	rejected: { tone: 'danger', label: 'Reddedildi' },
}
// Bayi portalı başvurularında aynı durumun anlamı farklı: pending = ön başvuru, approved = hesap açıldı.
function statusOf(a) {
	if (a.source !== 'bagisto') return STATUS[a.status] ?? { tone: 'neutral', label: a.status }
	if (a.status === 'pending') return { tone: 'warning', label: 'Ön başvuru' }
	if (a.status === 'approved') {
		if (a.completedAt) return { tone: 'success', label: 'Üyelik tamamlandı' }
		return a.invitationOpen ? { tone: 'info', label: 'Hesap açıldı · davet bekliyor' } : { tone: 'warning', label: 'Davetin süresi doldu' }
	}
	return STATUS[a.status] ?? { tone: 'neutral', label: a.status }
}
const DOC_LABELS = {
	vergi_levhasi: 'Vergi levhası',
	ticaret_sicil_gazetesi: 'Ticaret sicil gazetesi',
	imza_sirkuleri: 'İmza sirküleri',
	diger: 'Diğer / mağaza görseli',
}
// Bayi portalı (Bagisto ozmbayi) başvurularının ek adımları → details
const PORTAL_LABELS = {
	company_type: { sahis: 'Şahıs şirketi', limited: 'Limited şirketi', anonim: 'Anonim şirketi' },
	dealer_model: { dropshipping: 'Dropshipping', toptan: 'Toptan serili alım', hibrit: 'Hibrit' },
	channel: { fiziki: 'Fiziki butik', instagram: 'Instagram / sosyal medya', web: 'Kendi e-ticaret sitesi', pazaryeri: 'Pazaryeri' },
	payment_preference: { kart: 'Kredi kartı / 3D Secure', havale: 'Havale / EFT cari bakiye' },
}
const portal = (group, key) => PORTAL_LABELS[group]?.[key] ?? key ?? '—'
const docLabel = (t) => DOC_LABELS[t] ?? t
const docUrl = (a, d) => `/tenants/applications/${a.id}/documents/${d.id}/download`
const previewUrl = (a, d) => `/tenants/applications/${a.id}/documents/${d.id}/preview`
const fmtSize = (bytes) => (bytes ? `${(bytes / 1048576).toLocaleString('tr-TR', { maximumFractionDigits: 1 })} MB` : '')
const fmtDate = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—')

/* ── özet ── */
const countOf = (s) => props.applications.filter((a) => a.status === s).length
// Bizden aksiyon bekleyenler: ön başvuru/web başvurusu, evrak incelemesi, iş ortağı açılmamış web onayı.
const ourTurn = computed(() => props.applications.filter((a) =>
	['pending', 'documents_review'].includes(a.status) || (a.status === 'approved' && a.source !== 'bagisto' && !a.convertedTenantId),
).length)
// Başvuru sahibinden beklenenler: resmi belge, eksik evrak, üyelik tamamlama.
const theirTurn = computed(() => props.applications.filter((a) =>
	['documents_requested', 'missing_documents'].includes(a.status) || (a.status === 'approved' && a.source === 'bagisto' && !a.completedAt),
).length)
const pendingCount = computed(() => ourTurn.value)
const stats = computed(() => [
	{ key: 'ours', label: 'İncelemenizi bekleyen', value: ourTurn.value, icon: 'clock', tone: ourTurn.value ? 'warning' : 'success' },
	{ key: 'theirs', label: 'Başvuru sahibinde', value: theirTurn.value, icon: 'building', tone: theirTurn.value ? 'info' : 'success' },
	{ key: 'approved', label: 'Onaylanan', value: countOf('approved'), icon: 'success', tone: 'success' },
	{ key: 'rejected', label: 'Reddedilen', value: countOf('rejected'), icon: 'x', tone: 'danger' },
])

/* ── tablo ── */
const columns = [
	{ key: 'name', label: 'Firma', sortable: true, searchValue: (r) => `${r.name} ${r.legal_name ?? ''} ${r.tax_number ?? ''}` },
	{ key: 'tax_number', label: 'Vergi no', sortable: true },
	{ key: 'contact', label: 'İletişim', searchValue: (r) => `${r.contact_person ?? ''} ${r.contact_email ?? ''} ${r.contact_phone ?? ''}` },
	{ key: 'documents', label: 'Belgeler', searchable: false },
	{
		key: 'status', label: 'Durum', sortable: true, searchable: false,
		filter: { type: 'select', options: Object.entries(STATUS).map(([value, s]) => ({ value, label: s.label })) },
	},
	{ key: 'createdAt', label: 'Tarih', sortable: true, searchable: false, filter: { type: 'date' } },
	{ key: 'actions', label: '', align: 'right', searchable: false },
]
const tableFilters = ref({ status: props.filters.status ?? '' })

/* ── ayrıntı ── */
const detailOpen = ref(false)
// id üzerinden: onay/red sonrası gelen güncel satırı gösterir
const detailId = ref(null)
const detail = computed(() => props.applications.find((a) => a.id === detailId.value) ?? null)
function openDetail(a) {
	detailId.value = a.id
	detailOpen.value = true
}

// Onaylanan bayi sözleşmeleri: bayinin onay anındaki doldurulmuş kopyaları (Bayi Sözleşmeleri arşivi,
// Bagisto modülü — bu modül ona PHP'den bağımlı olamadığı için ayrıntı açılınca uç noktadan okunur).
const acceptances = ref([])
const acceptancesLoading = ref(false)
watch([detailId, detailOpen], async ([id, open]) => {
	acceptances.value = []
	if (!open || !id || !canApprove.value || !detail.value?.details) return
	acceptancesLoading.value = true
	try {
		const { data } = await axios.get('/dealer-agreements/acceptances', { params: { application: id } })
		if (detailId.value === id) acceptances.value = data.data
	} catch {
		acceptances.value = []
	} finally {
		acceptancesLoading.value = false
	}
})

/* ── işlemler ── */
const approving = ref(null)
function approve(a) {
	if (approving.value) return
	approving.value = a.id
	router.post(`/tenants/applications/${a.id}/approve`, {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Başvuru onaylandı', message: a.name })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { approving.value = null },
	})
}

function createTenant(a) {
	router.visit(`/tenants?from_application=${a.id}`)
}

/* ── bayi portalı: aşama geçişleri ── */
function post(a, action, data, success) {
	if (approving.value) return
	approving.value = a.id
	router.post(`/tenants/applications/${a.id}/${action}`, data, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: success, message: a.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { approving.value = null },
	})
}
const openDocuments = (a) => post(a, 'open-documents', {}, 'Resmi belge aşaması açıldı')
const resendInvitation = (a) => post(a, 'resend-invitation', {}, 'Davet yeniden gönderildi')
const remindDocuments = (a) => post(a, 'remind-documents', {}, 'Hatırlatma e-postası gönderildi')

/* ── belge önizleme: PDF iframe'de, görsel img olarak; buradan yazdırılır / indirilir ── */
const preview = ref(null) // { app, doc }
const previewOpen = ref(false)
const previewFrame = ref(null)
function openPreview(a, d) {
	if (!d.kind) {
		download(docUrl(a, d))
		return
	}
	preview.value = { app: a, doc: d }
	previewOpen.value = true
}
// Görseli kendi başına basmak için gizli bir iframe'e yerleştirip yazdırır (sayfanın geri kalanı basılmaz).
function printImage(url) {
	const frame = document.createElement('iframe')
	frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'
	frame.srcdoc = `<style>@page{margin:10mm}body{margin:0}img{display:block;max-width:100%;max-height:100vh;margin:auto}</style><img src="${url}">`
	frame.onload = () => {
		const img = frame.contentDocument.querySelector('img')
		const go = () => { frame.contentWindow.print(); setTimeout(() => frame.remove(), 1000) }
		img.complete ? go() : (img.onload = go)
	}
	document.body.appendChild(frame)
}
function printPreview() {
	const { app, doc } = preview.value
	const url = previewUrl(app, doc)
	if (doc.kind === 'image') return printImage(url)
	try {
		previewFrame.value.contentWindow.focus()
		previewFrame.value.contentWindow.print()
	} catch {
		window.open(url, '_blank', 'noopener')
	}
}
const openTab = (url) => window.open(url, '_blank', 'noopener')
const download = (url) => { window.location.href = url }
const printApplication = (a) => openTab(`/tenants/applications/${a.id}/print`)

async function copyLink(url) {
	try {
		await navigator.clipboard.writeText(url)
		showToast?.({ type: 'success', title: 'Bağlantı kopyalandı' })
	} catch {
		showToast?.({ type: 'error', title: 'Kopyalanamadı', message: url })
	}
}

// "Onayla ve hesabı aç": iş ortağı kodu + ticari koşullar; kalan bilgiler başvurudan gelir.
const accountOpen = ref(false)
const accountTarget = ref(null)
const accountForm = ref({})
const accountErrors = ref({})
const typeOptions = computed(() => [{ value: '', label: 'Seçilmedi' }, ...props.tenantTypes.map((t) => ({ value: t.id, label: t.name }))])

function openAccountModal(a) {
	accountTarget.value = a
	accountForm.value = { code: a.suggestedCode ?? '', tenant_type_id: '', credit_limit: '', discount_rate: props.defaultDiscountRate }
	accountErrors.value = {}
	accountOpen.value = true
}

function submitAccount() {
	if (busy.value) return
	busy.value = true
	const a = accountTarget.value
	router.post(`/tenants/applications/${a.id}/open-account`, accountForm.value, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			accountOpen.value = false
			showToast?.({ type: 'success', title: 'Hesap açıldı, davet gönderildi', message: a.name })
		},
		onError: (errs) => { accountErrors.value = errs },
		onFinish: () => { busy.value = false },
	})
}

const rejectOpen = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')
const rejectError = ref('')
const busy = ref(false)

function openReject(a) {
	rejectTarget.value = a
	rejectReason.value = ''
	rejectError.value = ''
	rejectOpen.value = true
}

function submitReject() {
	if (busy.value) return
	if (!rejectReason.value.trim()) {
		rejectError.value = 'Red gerekçesi zorunlu.'
		return
	}
	busy.value = true
	const a = rejectTarget.value
	router.post(`/tenants/applications/${a.id}/reject`, { rejection_reason: rejectReason.value }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			rejectOpen.value = false
			showToast?.({ type: 'warning', title: 'Başvuru reddedildi', message: a.name })
		},
		onError: (errs) => { rejectError.value = errs.rejection_reason || Object.values(errs)[0] || 'Sunucu hatası.' },
		onFinish: () => { busy.value = false },
	})
}

/* ── eksik evrak iste (başvuru sahibi bayi portalındaki durum sayfasından yükler) ── */
const missingOpen = ref(false)
const missingTarget = ref(null)
const missingTypes = ref({})
const missingNote = ref('')
const missingError = ref('')

function openMissing(a) {
	missingTarget.value = a
	missingTypes.value = Object.fromEntries(Object.keys(DOC_LABELS).map((t) => [t, (a.missing_documents ?? []).includes(t)]))
	missingNote.value = a.missing_note ?? ''
	missingError.value = ''
	missingOpen.value = true
}

function submitMissing() {
	if (busy.value) return
	const types = Object.entries(missingTypes.value).filter(([, on]) => on).map(([t]) => t)
	if (!types.length) { missingError.value = 'En az bir belge seçin.'; return }
	if (!missingNote.value.trim()) { missingError.value = 'Başvuru sahibine gösterilecek açıklama zorunlu.'; return }
	busy.value = true
	const a = missingTarget.value
	router.post(`/tenants/applications/${a.id}/request-documents`, { types, note: missingNote.value }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			missingOpen.value = false
			showToast?.({ type: 'info', title: 'Eksik evrak istendi', message: a.name })
		},
		onError: (errs) => { missingError.value = Object.values(errs)[0] || 'Sunucu hatası.' },
		onFinish: () => { busy.value = false },
	})
}
</script>

<template>
	<Head title="İş Ortağı Başvuruları" />

	<OzPageHeader title="İş Ortağı Başvuruları" :subtitle="`${applications.length} başvuru · ${pendingCount} bekliyor`">
		<template v-if="canManageMails" #actions>
			<OzButton icon="mail" @click="router.visit('/dealer-mail-templates?group=applications')">E-posta şablonları</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="Başvuru listesi" :meta="`${applications.length} kayıt`" class="block">
		<OzTable
			v-model:filters="tableFilters"
			:columns="columns"
			:rows="applications"
			:page-size="20"
			:default-sort="{ key: 'createdAt', dir: 'desc' }"
			searchable
			search-placeholder="Firma, vergi no, yetkili ara…"
			min-width="960px"
			class="apps"
			@row-click="openDetail"
		>
			<template #cell-name="{ row }">
				<div class="who-text">
					<b>{{ row.name }}</b>
					<span v-if="row.legal_name">{{ row.legal_name }}</span>
					<span v-if="row.source === 'bagisto'" class="src">Bayi portalı · {{ row.reference }}</span>
				</div>
			</template>
			<template #cell-tax_number="{ value }"><span class="mono">{{ value || '—' }}</span></template>
			<template #cell-contact="{ row }">
				<div class="stack">
					<span>{{ row.contact_person }}</span>
					<span class="dim">{{ row.contact_email }}</span>
				</div>
			</template>
			<template #cell-documents="{ row }">
				<div class="docs" @click.stop>
					<button v-for="d in row.documents" :key="d.id" type="button" class="doc" :title="`${d.name} · ${d.kind ? 'önizle' : 'indir'}`" @click="openPreview(row, d)">
						<OzIcon :name="d.kind ? 'eye' : 'download'" :size="12" />{{ docLabel(d.type) }}
					</button>
					<span v-if="!row.documents.length" class="dim">Belge yok</span>
				</div>
			</template>
			<template #cell-status="{ row }">
				<div class="stack">
					<OzPill :tone="statusOf(row).tone">{{ statusOf(row).label }}</OzPill>
					<span v-if="row.source !== 'bagisto' && row.status === 'approved' && row.convertedTenantId" class="dim small">İş ortağına dönüştürüldü</span>
				</div>
			</template>
			<template #cell-createdAt="{ value }"><span class="date">{{ fmtDate(value) }}</span></template>
			<template #cell-actions="{ row }">
				<div v-if="canApprove" class="acts" @click.stop>
					<template v-if="row.source === 'bagisto'">
						<OzButton v-if="row.status === 'pending'" size="sm" variant="primary" icon="file" :loading="approving === row.id" @click="openDocuments(row)">Belge aşamasını aç</OzButton>
						<template v-else-if="row.status === 'documents_review'">
							<OzButton v-if="canManageTenants" size="sm" variant="primary" icon="check" @click="openAccountModal(row)">Onayla ve hesabı aç</OzButton>
							<OzButton size="sm" icon="file" @click="openMissing(row)">Eksik evrak</OzButton>
						</template>
						<OzButton v-else-if="row.status === 'missing_documents'" size="sm" icon="file" @click="openMissing(row)">Talebi düzenle</OzButton>
						<OzButton v-else-if="row.status === 'approved' && !row.completedAt" size="sm" icon="send" :loading="approving === row.id" @click="resendInvitation(row)">Daveti yeniden gönder</OzButton>
						<OzButton v-if="['pending', 'documents_requested', 'documents_review', 'missing_documents'].includes(row.status)" size="sm" variant="danger" icon="x" @click="openReject(row)">Reddet</OzButton>
					</template>
					<template v-else-if="row.status === 'pending'">
						<OzButton size="sm" icon="check" :loading="approving === row.id" @click="approve(row)">Onayla</OzButton>
						<OzButton size="sm" variant="danger" icon="x" @click="openReject(row)">Reddet</OzButton>
					</template>
					<OzButton v-else-if="row.status === 'approved' && !row.convertedTenantId" size="sm" variant="primary" icon="building" @click="createTenant(row)">İş ortağı oluştur</OzButton>
				</div>
			</template>
			<template #empty="{ filtered, clear }">
				<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen başvuru yok" compact>
					<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
				</OzEmptyState>
				<OzEmptyState v-else icon="file" title="Henüz başvuru yok" description="Başvurular /tenant-basvuru formundan ve bayi portalından gelir." compact />
			</template>
		</OzTable>
	</OzCard>

	<!-- Ayrıntı -->
	<OzDrawer
		v-model:open="detailOpen"
		:title="detail?.name ?? ''"
		:subtitle="detail?.legal_name || 'Başvuru ayrıntısı'"
		icon="file"
		size="md"
	>
		<div v-if="detail" class="detail">
			<OzAlert v-if="detail.status === 'rejected'" tone="danger" title="Reddedildi">
				{{ detail.rejection_reason || 'Gerekçe belirtilmemiş.' }}
			</OzAlert>
			<OzAlert v-else-if="detail.status === 'missing_documents'" tone="info" title="Başvuru sahibinden belge bekleniyor">
				{{ (detail.missing_documents ?? []).map(docLabel).join(', ') }} — {{ detail.missing_note }}
			</OzAlert>
			<template v-else-if="detail.source === 'bagisto'">
				<OzAlert v-if="detail.status === 'pending'" tone="warning" title="Ön başvuru">
					Firma ve satış kanalı bilgileri alındı. Ön değerlendirme ve görüşme sonrası “Belge aşamasını aç” ile bayinin durum sayfasında resmi belge adımı açılır ve kendisine e-posta gider.
				</OzAlert>
				<OzAlert v-else-if="detail.status === 'documents_requested'" tone="info" title="Resmi belgeler bekleniyor">
					Belge aşaması {{ fmtDate(detail.documentsRequestedAt) }} tarihinde açıldı; bayinin belgeleri, banka bilgisini ve sözleşme onayını göndermesi bekleniyor.
					<template v-if="detail.documentsRemindedAt"> Son hatırlatma {{ fmtDate(detail.documentsRemindedAt) }} tarihinde gönderildi.</template>
				</OzAlert>
				<OzAlert v-else-if="detail.status === 'documents_review'" tone="warning" title="Resmi evrak incelemesi">
					Belgeler {{ fmtDate(detail.documentsSubmittedAt) }} tarihinde yüklendi. Uygunsa “Onayla ve hesabı aç”: iş ortağı pasif açılır ve bayiye üyelik tamamlama bağlantısı gider.
				</OzAlert>
				<OzAlert v-else-if="detail.status === 'approved' && detail.completedAt" tone="success" title="Üyelik tamamlandı">
					Bayi {{ fmtDate(detail.completedAt) }} tarihinde parolasını oluşturdu; iş ortağı aktif.
				</OzAlert>
				<OzAlert v-else-if="detail.status === 'approved'" :tone="detail.invitationOpen ? 'info' : 'warning'" :title="detail.invitationOpen ? 'Üyelik daveti gönderildi' : 'Davetin süresi doldu'">
					<template v-if="detail.invitationOpen">
						Davet {{ fmtDate(detail.invitationSentAt) }} tarihinde gönderildi, {{ fmtDate(detail.invitationExpiresAt) }} tarihine kadar geçerli. E-posta ulaşmadıysa bağlantıyı bayiye kendiniz iletebilirsiniz.
					</template>
					<template v-else>Bayi süresi içinde üyeliğini tamamlamadı; “Daveti yeniden gönder” ile yeni bağlantı oluşturun.</template>
				</OzAlert>
			</template>
			<OzAlert v-else-if="detail.status === 'approved'" :tone="detail.convertedTenantId ? 'success' : 'info'" :title="detail.convertedTenantId ? 'İş ortağına dönüştürüldü' : 'Onaylandı'">
				{{ detail.convertedTenantId ? 'Bu başvurudan iş ortağı kaydı oluşturuldu.' : 'Başvuru onaylı; iş ortağı kaydı henüz oluşturulmadı.' }}
			</OzAlert>

			<div v-if="detail.invitationUrl || detail.statusUrl" class="links">
				<div v-if="detail.invitationUrl" class="link-row">
					<span>Üyelik tamamlama bağlantısı</span>
					<OzButton size="sm" icon="copy" @click="copyLink(detail.invitationUrl)">Kopyala</OzButton>
				</div>
				<div v-if="detail.statusUrl" class="link-row">
					<span>Başvuru durum sayfası</span>
					<OzButton size="sm" icon="copy" @click="copyLink(detail.statusUrl)">Kopyala</OzButton>
				</div>
			</div>

			<dl class="facts">
				<dt>Durum</dt><dd><OzPill :tone="statusOf(detail).tone">{{ statusOf(detail).label }}</OzPill></dd>
				<dt>Vergi no</dt><dd class="mono">{{ detail.tax_number || '—' }}</dd>
				<dt>Vergi dairesi</dt><dd>{{ detail.tax_office || '—' }}</dd>
				<dt>Yetkili</dt><dd>{{ detail.contact_person || '—' }}</dd>
				<dt>E-posta</dt><dd><a v-if="detail.contact_email" :href="`mailto:${detail.contact_email}`">{{ detail.contact_email }}</a><template v-else>—</template></dd>
				<dt>Telefon</dt><dd>{{ detail.contact_phone || '—' }}</dd>
				<dt>Adres</dt><dd>{{ [detail.address, detail.district, detail.city].filter(Boolean).join(', ') || '—' }}</dd>
				<dt>Başvuru</dt><dd>{{ fmtDate(detail.createdAt) }}</dd>
				<template v-if="detail.reviewedAt">
					<dt>İnceleyen</dt><dd>{{ detail.reviewer || '—' }} · {{ fmtDate(detail.reviewedAt) }}</dd>
				</template>
			</dl>

			<template v-if="detail.details">
				<div class="section">Bayi portalı başvurusu · {{ detail.reference }}</div>
				<dl class="facts">
					<dt>Şirket türü</dt><dd>{{ portal('company_type', detail.details.company_type) }}</dd>
					<dt>Bayilik modeli</dt><dd>{{ portal('dealer_model', detail.details.dealer_model) }}</dd>
					<dt>Satış kanalları</dt>
					<dd>
						<span v-for="(c, i) in detail.details.channels ?? []" :key="i" class="chan">
							{{ portal('channel', c.type) }}<template v-if="c.value">: {{ c.value }}</template>
						</span>
					</dd>
					<dt>Aylık hacim</dt><dd>{{ detail.details.monthly_volume }} adet</dd>
					<!-- Ciro hedefi / ödeme / banka / sözleşme resmi belge adımında gelir -->
					<template v-if="detail.details.revenue_target">
						<dt>Ciro hedefi</dt><dd>{{ detail.details.revenue_target }} bin TL / ay</dd>
						<dt>Ödeme tercihi</dt><dd>{{ portal('payment_preference', detail.details.payment_preference) }}</dd>
					</template>
					<template v-if="canApprove && detail.details.bank">
						<dt>Banka</dt><dd>{{ detail.details.bank.bank_name }} · {{ detail.details.bank.account_holder }}</dd>
						<dt>IBAN</dt><dd class="mono">{{ detail.details.bank.iban }}</dd>
					</template>
					<dt>Onaylar</dt>
					<dd>
						Taahhütname ✓
						<template v-if="detail.details.consents?.contract"> · Sözleşme {{ detail.details.consents.contract_version ?? '' }} ✓</template>
						<template v-if="detail.details.consents?.membership_accepted_at"> · Üyelikte son onay ✓</template>
						<template v-if="detail.details.consents?.marketing"> · Ticari ileti ✓</template>
					</dd>
				</dl>
			</template>

			<div v-if="detail.message" class="section">Mesaj</div>
			<p v-if="detail.message" class="message">{{ detail.message }}</p>

			<div class="section">Belgeler</div>
			<p v-if="!canApprove" class="dim">Belgeleri görmek için başvuru onay yetkisi gerekir.</p>
			<div v-else-if="detail.documents.length" class="doc-list">
				<div v-for="d in detail.documents" :key="d.id" class="doc-row" role="button" tabindex="0" :title="d.kind ? 'Önizle' : 'İndir'" @click="openPreview(detail, d)" @keydown.enter="openPreview(detail, d)">
					<OzIcon name="file" :size="16" />
					<span class="doc-text"><b>{{ docLabel(d.type) }}</b><span>{{ d.name }}<template v-if="d.size"> · {{ fmtSize(d.size) }}</template></span></span>
					<OzIcon v-if="d.kind" name="eye" :size="15" />
					<a :href="docUrl(detail, d)" class="doc-dl" title="İndir" aria-label="İndir" @click.stop><OzIcon name="download" :size="14" /></a>
				</div>
			</div>
			<p v-else class="dim">Belge yüklenmemiş.</p>

			<template v-if="canApprove && detail.details">
				<div class="section">Onaylanan sözleşmeler</div>
				<p v-if="acceptancesLoading" class="dim">Yükleniyor…</p>
				<div v-else-if="acceptances.length" class="doc-list">
					<a v-for="c in acceptances" :key="c.id" :href="c.printUrl" target="_blank" rel="noopener" class="doc-row" title="Bayi bilgileriyle doldurulmuş onay kopyasını yazdır">
						<OzIcon name="file" :size="16" />
						<span class="doc-text"><b>{{ c.title }}</b><span>{{ c.version }} · {{ c.context }} · {{ fmtDate(c.acceptedAt) }}</span></span>
						<OzIcon name="print" :size="15" />
					</a>
				</div>
				<p v-else class="dim">Arşivlenmiş sözleşme onayı yok.</p>
			</template>
		</div>

		<template v-if="detail && canApprove" #footer>
			<OzButton v-if="detail.status === 'approved'" icon="print" @click="printApplication(detail)">Başvuru dosyasını yazdır</OzButton>
			<template v-if="detail.source === 'bagisto'">
				<OzButton v-if="['pending', 'documents_requested', 'documents_review', 'missing_documents'].includes(detail.status)" variant="danger" icon="x" @click="openReject(detail)">Reddet</OzButton>
				<OzButton v-if="detail.status === 'pending'" variant="primary" icon="file" :loading="approving === detail.id" @click="openDocuments(detail)">Belge aşamasını aç</OzButton>
				<OzButton v-else-if="detail.status === 'documents_requested'" icon="send" :loading="approving === detail.id" @click="remindDocuments(detail)">Hatırlatma gönder</OzButton>
				<template v-else-if="detail.status === 'documents_review'">
					<OzButton icon="file" @click="openMissing(detail)">Eksik evrak iste</OzButton>
					<OzButton v-if="canManageTenants" variant="primary" icon="check" @click="openAccountModal(detail)">Onayla ve hesabı aç</OzButton>
				</template>
				<OzButton v-else-if="detail.status === 'missing_documents'" icon="file" @click="openMissing(detail)">Talebi düzenle</OzButton>
				<OzButton v-else-if="detail.status === 'approved' && !detail.completedAt" icon="send" :loading="approving === detail.id" @click="resendInvitation(detail)">Daveti yeniden gönder</OzButton>
				<OzButton v-else @click="detailOpen = false">Kapat</OzButton>
			</template>
			<template v-else-if="detail.status === 'pending'">
				<OzButton variant="danger" icon="x" @click="openReject(detail)">Reddet</OzButton>
				<OzButton variant="primary" icon="check" :loading="approving === detail.id" @click="approve(detail)">Onayla</OzButton>
			</template>
			<OzButton v-else-if="detail.status === 'approved' && !detail.convertedTenantId" variant="primary" icon="building" @click="createTenant(detail)">İş ortağı oluştur</OzButton>
			<OzButton v-else @click="detailOpen = false">Kapat</OzButton>
		</template>
	</OzDrawer>

	<!-- Belge önizleme -->
	<OzModal v-model:open="previewOpen" :title="preview ? docLabel(preview.doc.type) : ''" :subtitle="preview?.doc.name ?? ''" icon="file" size="lg">
		<div v-if="preview" class="preview">
			<iframe v-if="preview.doc.kind === 'pdf'" ref="previewFrame" :src="previewUrl(preview.app, preview.doc)" :title="preview.doc.name" />
			<img v-else :src="previewUrl(preview.app, preview.doc)" :alt="preview.doc.name">
		</div>
		<template v-if="preview" #footer>
			<OzButton icon="external-link" @click="openTab(previewUrl(preview.app, preview.doc))">Yeni sekmede aç</OzButton>
			<OzButton icon="print" @click="printPreview">Yazdır</OzButton>
			<OzButton variant="primary" icon="download" @click="download(docUrl(preview.app, preview.doc))">İndir</OzButton>
		</template>
	</OzModal>

	<!-- Red -->
	<OzModal v-model:open="rejectOpen" title="Başvuruyu reddet" :subtitle="rejectTarget?.name ?? ''" icon="x" tone="danger" size="sm" :persistent="busy">
		<OzField label="Red gerekçesi" required :error="rejectError" hint="Başvuru sahibine iletilebilecek kısa bir açıklama">
			<OzTextarea v-model="rejectReason" :rows="3" placeholder="Örn. belgeler unvan ile uyuşmuyor…" @update:model-value="rejectError = ''" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="rejectOpen = false">Vazgeç</OzButton>
			<OzButton variant="danger" icon="x" :loading="busy" @click="submitReject">Reddet</OzButton>
		</template>
	</OzModal>

	<!-- Onayla ve hesabı aç -->
	<OzModal v-model:open="accountOpen" title="Onayla ve hesabı aç" :subtitle="accountTarget?.name ?? ''" icon="building" size="md" :persistent="busy">
		<div class="account-form">
			<OzAlert v-if="accountErrors.application" tone="danger" title="Hesap açılamadı">{{ accountErrors.application }}</OzAlert>
			<p class="modal-note">
				Başvurudaki firma, vergi ve iletişim bilgileriyle iş ortağı <b>pasif</b> açılır. {{ accountTarget?.contact_email }} adresine
				üyelik tamamlama bağlantısı gider; bayi parolasını ve kargo anlaşmasını girince hesap aktifleşir.
			</p>
			<div class="form-row">
				<OzField label="İş ortağı kodu" required :error="accountErrors.code" hint="Başvuru numarasından otomatik; benzersizdir">
					<OzInput v-model="accountForm.code" class="mono" maxlength="32" autocomplete="off" />
				</OzField>
				<OzField label="İş ortağı tipi" :error="accountErrors.tenant_type_id">
					<OzSelect v-model="accountForm.tenant_type_id" :options="typeOptions" />
				</OzField>
			</div>
			<div class="form-row">
				<OzField v-if="cariEnabled" label="Kredi limiti" :error="accountErrors.credit_limit">
					<OzInput v-model="accountForm.credit_limit" type="number" min="0" placeholder="0" suffix="₺" />
				</OzField>
				<OzField label="İskonto" :error="accountErrors.discount_rate" hint="Standart bayi iskontosu">
					<OzInput v-model="accountForm.discount_rate" type="number" min="0" max="100" step="0.01" suffix="%" />
				</OzField>
			</div>
		</div>
		<template #footer>
			<OzButton :disabled="busy" @click="accountOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="busy" @click="submitAccount">Hesabı aç ve davet gönder</OzButton>
		</template>
	</OzModal>

	<!-- Eksik evrak -->
	<OzModal v-model:open="missingOpen" title="Eksik evrak iste" :subtitle="missingTarget?.name ?? ''" icon="file" size="sm" :persistent="busy">
		<OzField label="Güncellenmesi / eklenmesi gereken belgeler" required>
			<div class="missing-types">
				<OzCheckbox v-for="(label, type) in DOC_LABELS" :key="type" v-model="missingTypes[type]" :label="label" />
			</div>
		</OzField>
		<OzField label="Başvuru sahibine açıklama" required :error="missingError" hint="Bayi portalındaki durum sayfasında aynen gösterilir">
			<OzTextarea v-model="missingNote" :rows="3" placeholder="Örn. 2024/2025 tasdikli barkodlu vergi levhasını yükleyin…" @update:model-value="missingError = ''" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="missingOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="file" :loading="busy" @click="submitMissing">Talebi gönder</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.src { font-size: 10.5px; font-weight: 600; color: var(--oz-accent); }
.chan { display: block; }
.missing-types { display: grid; gap: 8px; }
.modal-note { margin: 0; font-size: 13px; line-height: 1.5; color: var(--oz-muted); }
.account-form { display: grid; gap: 16px; }
.form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.form-row.is-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
@media (max-width: 560px) { .form-row, .form-row.is-3 { grid-template-columns: minmax(0, 1fr); } }
.links { display: flex; flex-direction: column; gap: 6px; }
.link-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 12px; border: 1px dashed var(--oz-border); border-radius: 10px; font-size: 13px; color: var(--oz-ink-soft); }
.block { margin-bottom: 14px; }
.apps :deep(tbody tr) { cursor: pointer; }
.who-text { display: flex; flex-direction: column; gap: 1px; }
.who-text b { font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; }
.who-text span { font-size: 11.5px; color: var(--oz-muted); }
.stack { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; font-size: 12.5px; white-space: nowrap; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim { color: var(--oz-muted-2); }
.small { font-size: 11px; }
.date { white-space: nowrap; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.docs { display: flex; flex-direction: column; gap: 3px; }
.doc { display: inline-flex; align-items: center; gap: 4px; padding: 0; border: 0; background: none; cursor: pointer; font: inherit; font-size: 12px; font-weight: 500; color: var(--oz-accent); text-decoration: none; white-space: nowrap; }
.doc:hover { text-decoration: underline; }
.doc:hover { text-decoration: underline; }
.acts { display: flex; justify-content: flex-end; gap: 6px; white-space: nowrap; }

.detail { display: flex; flex-direction: column; gap: 14px; }
.facts { display: grid; grid-template-columns: 120px minmax(0, 1fr); gap: 8px 12px; margin: 0; font-size: 13px; }
.facts dt { color: var(--oz-muted); }
.facts dd { margin: 0; color: var(--oz-ink-soft); overflow-wrap: anywhere; }
.facts a { color: var(--oz-accent); }
.section { padding-top: 12px; border-top: 1px solid var(--oz-border-soft); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.message { margin: -4px 0 0; font-size: 13px; line-height: 1.55; color: var(--oz-ink-soft); white-space: pre-line; }
.doc-list { display: flex; flex-direction: column; gap: 6px; margin-top: -4px; }
.doc-row { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: 1px solid var(--oz-border); border-radius: 10px; color: var(--oz-muted); text-decoration: none; transition: border-color .15s, background .15s; }
.doc-row:hover { border-color: var(--oz-accent); background: var(--oz-accent-soft); color: var(--oz-accent); }
.doc-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.doc-text b { font-size: 13px; color: var(--oz-ink-soft); }
.doc-text span { font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-row { cursor: pointer; }
.doc-dl { display: inline-flex; padding: 4px; margin: -4px -4px -4px 0; border-radius: 6px; color: var(--oz-muted); }
.doc-dl:hover { background: var(--oz-surface); color: var(--oz-accent); }
.preview { margin: -4px 0; border: 1px solid var(--oz-border); border-radius: 10px; overflow: hidden; background: var(--oz-surface-2); }
.preview iframe { display: block; width: 100%; height: 70vh; border: 0; }
.preview img { display: block; max-width: 100%; max-height: 70vh; margin: 0 auto; object-fit: contain; }

@media (max-width: 560px) { .facts { grid-template-columns: minmax(0, 1fr); gap: 2px; } .facts dd { margin-bottom: 8px; } }
</style>
