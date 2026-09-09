<template>
	<Head title="Tenant Başvuruları" />
	<div class="page-tenant-applications">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İlişkiler' },
				{ label: 'Başvurular' },
			]"
		/>

		<PageHeader title="Tenant Başvuruları">
			<template #subtitle><strong>{{ pendingCount }}</strong> bekleyen başvuru</template>
		</PageHeader>

		<Card title="Başvuru Listesi" body-class="p-0">
			<template #actions>
				<select v-model="filterStatus" class="filter-select">
					<option value="">Tüm durumlar</option>
					<option value="pending">Beklemede</option>
					<option value="approved">Onaylandı</option>
					<option value="rejected">Reddedildi</option>
				</select>
			</template>

			<DataTable :columns="columns" :data="filtered" row-key-field="id" empty-title="Başvuru bulunamadı">
				<template #applicant="{ row }">
					<div class="applicant-cell">
						<span class="applicant-name">{{ row.name }}</span>
						<span v-if="row.legal_name" class="applicant-legal">{{ row.legal_name }}</span>
					</div>
				</template>
				<template #tax_number="{ value }"><span class="mono">{{ value }}</span></template>
				<template #contact="{ row }">
					<div class="contact-cell">
						<span class="contact-line">{{ row.contact_person }}</span>
						<span class="contact-line dim">{{ row.contact_email }}</span>
					</div>
				</template>
				<template #documents="{ row }">
					<div class="doc-list">
						<a
							v-for="d in row.documents"
							:key="d.id"
							:href="`/tenants/applications/${row.id}/documents/${d.id}/download`"
							class="doc-link"
							target="_blank"
						>
							<FileText :size="12" /> {{ docLabel(d.type) }}
						</a>
						<span v-if="!row.documents.length" class="dim">Belge yok</span>
					</div>
				</template>
				<template #status="{ row }">
					<Badge :color="statusColor(row.status)" variant="tonal" :label="statusLabel(row.status)" />
					<span v-if="row.status === 'approved' && row.convertedTenantId" class="dim converted-hint">Tenant'a dönüştürüldü</span>
				</template>
				<template #actions="{ row }">
					<template v-if="row.status === 'pending' && canManage">
						<button class="table-action-btn approve" title="Onayla" @click="approve(row)"><Check :size="14" /></button>
						<button class="table-action-btn reject" title="Reddet" @click="openReject(row)"><X :size="14" /></button>
					</template>
					<Button
						v-else-if="row.status === 'approved' && !row.convertedTenantId && canManage"
						variant="primary"
						size="sm"
						@click="createTenant(row)"
					>
						Tenant Oluştur
					</Button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="rejectOpen" title="Başvuruyu Reddet" variant="danger">
			<div class="form-row">
				<label class="form-label">Red gerekçesi <span class="req">*</span></label>
				<textarea v-model="rejectReason" class="form-input" rows="3" placeholder="Örn. belgeler unvan ile uyuşmuyor..." />
				<span v-if="rejectError" class="form-error">{{ rejectError }}</span>
			</div>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="busy" @click="close">Vazgeç</Button>
				<Button variant="danger" :loading="busy" @click="submitReject">Reddet</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Check, X, FileText } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	applications: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const columns = [
	{ key: 'applicant', label: 'Firma', sortable: false },
	{ key: 'tax_number', label: 'Vergi No' },
	{ key: 'contact', label: 'İletişim', sortable: false },
	{ key: 'documents', label: 'Belgeler', sortable: false },
	{ key: 'status', label: 'Durum' },
	{ key: 'createdAt', label: 'Tarih' },
]

const showToast = inject('showToast')
const page = usePage()
const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('tenant.manage'))

const filterStatus = ref(props.filters.status ?? '')
const filtered = computed(() => {
	if (!filterStatus.value) return props.applications
	return props.applications.filter(a => a.status === filterStatus.value)
})
const pendingCount = computed(() => props.applications.filter(a => a.status === 'pending').length)

const docLabels = {
	vergi_levhasi: 'Vergi Levhası',
	ticaret_sicil_gazetesi: 'Ticaret Sicil Gazetesi',
	imza_sirkuleri: 'İmza Sirküleri',
	diger: 'Diğer',
}
function docLabel(type) { return docLabels[type] ?? type }

const statusMeta = {
	pending: { color: 'warning', label: 'Beklemede' },
	approved: { color: 'success', label: 'Onaylandı' },
	rejected: { color: 'danger', label: 'Reddedildi' },
}
function statusColor(s) { return statusMeta[s]?.color ?? 'info' }
function statusLabel(s) { return statusMeta[s]?.label ?? s }

function approve(row) {
	router.post(`/tenants/applications/${row.id}/approve`, {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Başvuru onaylandı', message: row.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

function createTenant(row) {
	router.visit(`/tenants?from_application=${row.id}`)
}

const rejectOpen = ref(false)
const rejectReason = ref('')
const rejectError = ref('')
const busy = ref(false)
const rejectTarget = ref(null)

function openReject(row) {
	rejectTarget.value = row
	rejectReason.value = ''
	rejectError.value = ''
	rejectOpen.value = true
}

function submitReject() {
	if (!rejectReason.value.trim()) {
		rejectError.value = 'Red gerekçesi zorunlu.'
		return
	}
	busy.value = true
	router.post(`/tenants/applications/${rejectTarget.value.id}/reject`, {
		rejection_reason: rejectReason.value,
	}, {
		preserveScroll: true,
		onSuccess: () => {
			rejectOpen.value = false
			showToast?.({ type: 'warning', title: 'Başvuru reddedildi', message: rejectTarget.value.name })
		},
		onError: (errs) => { rejectError.value = Object.values(errs)[0] || 'Sunucu hatası.' },
		onFinish: () => { busy.value = false },
	})
}
</script>

<style scoped>
.filter-select { border: 1px solid rgb(var(--color-border-rgb)); background: rgb(var(--color-bg-rgb)); border-radius: 8px; padding: 6px 10px; font-size: 12.5px; color: rgb(var(--color-ink-rgb)); font-family: inherit; outline: none; }
.dim { color: rgb(var(--color-muted-rgb)); font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.applicant-cell { display: flex; flex-direction: column; gap: 2px; }
.applicant-name { font-weight: 600; color: rgb(var(--color-ink-rgb)); font-size: 13px; }
.applicant-legal { font-size: 11px; color: rgb(var(--color-muted-rgb)); }

.contact-cell { display: flex; flex-direction: column; gap: 2px; }
.contact-line { font-size: 12px; color: rgb(var(--color-ink-rgb)); }

.doc-list { display: flex; flex-direction: column; gap: 4px; }
.doc-link { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: rgb(var(--color-primary-rgb)); text-decoration: none; }
.doc-link:hover { text-decoration: underline; }

.converted-hint { display: block; margin-top: 2px; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg-rgb)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted-rgb)); transition: all .15s; }
.table-action-btn.approve:hover { background: rgb(var(--color-success-rgb) / .15); color: rgb(var(--color-success-rgb)); }
.table-action-btn.reject:hover { background: rgb(var(--color-danger-rgb) / .12); color: rgb(var(--color-danger-rgb)); }

.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }
.form-label .req { color: rgb(var(--color-danger-rgb)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink-rgb)); background: rgb(var(--color-surface-rgb)); outline: none; }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger-rgb)); }
</style>
