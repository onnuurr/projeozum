<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzButton, OzEmptyState, OzIcon,
	OzDrawer, OzModal, OzField, OzTextarea, OzAlert,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// İş ortağı başvuruları (Oz kit). Satıra tıklayınca ayrıntı paneli açılır (mesaj, adres, belgeler, inceleme bilgisi).
//   Onayla / reddet → Inertia POST (TenantApplicationController); onaylı başvurudan iş ortağı /tenants?from_application=ID ile oluşturulur.
defineOptions({ layout: AppLayout })

const props = defineProps({
	applications: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const { can } = useCan()
const canApprove = computed(() => can('tenant-application.approve'))
const showToast = inject('showToast', null)

const STATUS = {
	pending: { tone: 'warning', label: 'Beklemede' },
	approved: { tone: 'success', label: 'Onaylandı' },
	rejected: { tone: 'danger', label: 'Reddedildi' },
}
const DOC_LABELS = {
	vergi_levhasi: 'Vergi levhası',
	ticaret_sicil_gazetesi: 'Ticaret sicil gazetesi',
	imza_sirkuleri: 'İmza sirküleri',
	diger: 'Diğer',
}
const docLabel = (t) => DOC_LABELS[t] ?? t
const docUrl = (a, d) => `/tenants/applications/${a.id}/documents/${d.id}/download`
const fmtDate = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—')

/* ── özet ── */
const countOf = (s) => props.applications.filter((a) => a.status === s).length
const pendingCount = computed(() => countOf('pending'))
const awaitingTenant = computed(() => props.applications.filter((a) => a.status === 'approved' && !a.convertedTenantId).length)
const stats = computed(() => [
	{ key: 'pending', label: 'Bekleyen', value: pendingCount.value, icon: 'clock', tone: pendingCount.value ? 'warning' : 'success' },
	{ key: 'awaiting', label: 'İş ortağı bekleyen onaylı', value: awaitingTenant.value, icon: 'building', tone: awaitingTenant.value ? 'info' : 'success' },
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
</script>

<template>
	<Head title="İş Ortağı Başvuruları" />

	<OzPageHeader title="İş Ortağı Başvuruları" :subtitle="`${applications.length} başvuru · ${pendingCount} bekliyor`" />
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

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
					<a v-for="d in row.documents" :key="d.id" :href="docUrl(row, d)" target="_blank" rel="noopener" class="doc" :title="d.name">
						<OzIcon name="file" :size="12" />{{ docLabel(d.type) }}
					</a>
					<span v-if="!row.documents.length" class="dim">Belge yok</span>
				</div>
			</template>
			<template #cell-status="{ row }">
				<div class="stack">
					<OzPill :tone="STATUS[row.status]?.tone ?? 'neutral'">{{ STATUS[row.status]?.label ?? row.status }}</OzPill>
					<span v-if="row.status === 'approved' && row.convertedTenantId" class="dim small">İş ortağına dönüştürüldü</span>
				</div>
			</template>
			<template #cell-createdAt="{ value }"><span class="date">{{ fmtDate(value) }}</span></template>
			<template #cell-actions="{ row }">
				<div v-if="canApprove" class="acts" @click.stop>
					<template v-if="row.status === 'pending'">
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
				<OzEmptyState v-else icon="file" title="Henüz başvuru yok" description="Başvurular /tenant-basvuru formundan gelir." compact />
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
			<OzAlert v-else-if="detail.status === 'approved'" :tone="detail.convertedTenantId ? 'success' : 'info'" :title="detail.convertedTenantId ? 'İş ortağına dönüştürüldü' : 'Onaylandı'">
				{{ detail.convertedTenantId ? 'Bu başvurudan iş ortağı kaydı oluşturuldu.' : 'Başvuru onaylı; iş ortağı kaydı henüz oluşturulmadı.' }}
			</OzAlert>

			<dl class="facts">
				<dt>Durum</dt><dd><OzPill :tone="STATUS[detail.status]?.tone ?? 'neutral'">{{ STATUS[detail.status]?.label ?? detail.status }}</OzPill></dd>
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

			<div v-if="detail.message" class="section">Mesaj</div>
			<p v-if="detail.message" class="message">{{ detail.message }}</p>

			<div class="section">Belgeler</div>
			<p v-if="!canApprove" class="dim">Belgeleri görmek için başvuru onay yetkisi gerekir.</p>
			<div v-else-if="detail.documents.length" class="doc-list">
				<a v-for="d in detail.documents" :key="d.id" :href="docUrl(detail, d)" target="_blank" rel="noopener" class="doc-row">
					<OzIcon name="file" :size="16" />
					<span class="doc-text"><b>{{ docLabel(d.type) }}</b><span>{{ d.name }}</span></span>
					<OzIcon name="download" :size="14" />
				</a>
			</div>
			<p v-else class="dim">Belge yüklenmemiş.</p>
		</div>

		<template v-if="detail && canApprove" #footer>
			<template v-if="detail.status === 'pending'">
				<OzButton variant="danger" icon="x" @click="openReject(detail)">Reddet</OzButton>
				<OzButton variant="primary" icon="check" :loading="approving === detail.id" @click="approve(detail)">Onayla</OzButton>
			</template>
			<OzButton v-else-if="detail.status === 'approved' && !detail.convertedTenantId" variant="primary" icon="building" @click="createTenant(detail)">İş ortağı oluştur</OzButton>
			<OzButton v-else @click="detailOpen = false">Kapat</OzButton>
		</template>
	</OzDrawer>

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
</template>

<style scoped>
.block { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
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
.doc { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 500; color: var(--oz-accent); text-decoration: none; white-space: nowrap; }
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

@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .facts { grid-template-columns: minmax(0, 1fr); gap: 2px; } .facts dd { margin-bottom: 8px; } }
</style>
