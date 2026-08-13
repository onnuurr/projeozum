<template>
	<Head :title="`Tenant Ayarları · ${product.name}`" />
	<div class="page-product-tenants">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: product.name },
				{ label: 'Tenant Ayarları' },
			]"
		/>

		<PageHeader :title="`${product.name} · Tenant Ayarları`">
			<template #subtitle>
				<span class="mono">{{ product.sku }}</span>
				<span v-if="product.brand_name"> · {{ product.brand_name }}</span>
				<span v-if="product.category_name"> · {{ product.category_name }}</span>
				<span class="dim"> · Baz Fiyat: <strong>₺{{ formatPrice(product.default_price) }}</strong></span>
			</template>
		</PageHeader>

		<Alert variant="info" class="page-alert">
			<strong>Sadece sistem yöneticisi tarafından düzenlenebilir.</strong>
			Tüm aktif tenantlar listelenir. Her satırda durum kaynağı belirtilir:
			<span class="src-tag default">varsayılan</span>
			<span class="src-tag rule">marka/kategori kuralı</span>
			<span class="src-tag override">ürün override</span>
		</Alert>

		<Card :title="`Tenant Listesi · ${rows.length} aktif tenant`" body-class="p-0">
			<template #actions>
				<div class="card-search">
					<Search :size="13" />
					<input v-model="searchQuery" type="text" placeholder="Tenant ara..." />
				</div>
			</template>

			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 22%">Tenant</th>
						<th style="width: 12%">Tip</th>
						<th style="width: 13%">Durum</th>
						<th style="width: 12%">Tip Fiyatı</th>
						<th style="width: 16%">Özel Fiyat (₺)</th>
						<th style="width: 13%">Görünürlük</th>
						<th style="width: 12%">İşlem</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="7" class="empty-row">Aktif tenant bulunamadı</td>
					</tr>
					<tr v-for="r in filtered" :key="r.tenant_id" :class="{ dirty: isDirty(r.tenant_id) }">
						<td>
							<div class="tenant-cell">
								<Avatar :initials="r.tenant_name.charAt(0).toUpperCase()" size="sm" />
								<div>
									<div class="tenant-name">{{ r.tenant_name }}</div>
									<div class="tenant-code mono">{{ r.tenant_code }}</div>
								</div>
							</div>
						</td>
						<td>
							<Badge v-if="r.tenant_type" color="info" variant="tonal" :label="r.tenant_type" />
							<span v-else class="dim">—</span>
						</td>
						<td>
							<Badge
								:color="r.effective === 'allowed' ? 'success' : 'danger'"
								variant="tonal"
								:icon="r.effective === 'allowed' ? Check : Ban"
								:label="r.effective === 'allowed' ? 'Açık' : 'Gizli'"
								class="mr-1.5"
							/>
							<span :class="['src-tag', sourceTagClass(r.source)]">{{ sourceLabel(r.source) }}</span>
						</td>
						<td>
							<span v-if="r.type_price !== null" class="mono">₺{{ formatPrice(r.type_price) }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td>
							<input
								type="number"
								min="0"
								step="0.01"
								class="form-input compact"
								:placeholder="formatPrice(r.type_price ?? r.default_price)"
								:value="getDraft(r.tenant_id).custom_price"
								@input="setDraft(r.tenant_id, 'custom_price', $event.target.value === '' ? null : Number($event.target.value))"
								:disabled="getDraft(r.tenant_id).is_blocked"
							/>
						</td>
						<td>
							<label class="form-check">
								<input
									type="checkbox"
									:checked="getDraft(r.tenant_id).is_blocked"
									@change="setDraft(r.tenant_id, 'is_blocked', $event.target.checked)"
								/>
								<span>Gizle</span>
							</label>
						</td>
						<td>
							<div class="table-actions">
								<Button variant="primary" size="sm" :disabled="!isDirty(r.tenant_id)" :loading="!!busyMap[r.tenant_id]" @click="save(r)">
									Kaydet
								</Button>
								<button v-if="r.override_id" class="table-action-btn delete" @click="confirmReset(r)" title="Override'ı sil (varsayılana dön)"><RotateCcw :size="14" /></button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Search, Check, Ban, RotateCcw } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import Avatar from '@/Components/Avatar.vue'
import Alert from '@/Components/Alert.vue'
import Button from '@/Components/Button.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, required: true },
	rows: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.rows
	return props.rows.filter(r =>
		r.tenant_name.toLowerCase().includes(q) ||
		r.tenant_code.toLowerCase().includes(q) ||
		(r.tenant_type ?? '').toLowerCase().includes(q),
	)
})

function formatPrice(v) {
	if (v === null || v === undefined) return '0,00'
	return Number(v).toFixed(2).replace('.', ',')
}

const SOURCE_LABELS = {
	default: 'varsayılan',
	rule_block: 'marka/kategori kuralı',
	override_block: 'ürün override',
	override_allow: 'ürün override',
}
function sourceLabel(s) { return SOURCE_LABELS[s] ?? s }
function sourceTagClass(s) {
	if (s === 'default') return 'default'
	if (s === 'rule_block') return 'rule'
	return 'override'
}

/* ── Draft state per row ── */
const drafts = reactive({})
const busyMap = reactive({})

function getDraft(tenantId) {
	if (!drafts[tenantId]) {
		const r = props.rows.find(x => x.tenant_id === tenantId)
		drafts[tenantId] = {
			is_blocked: r?.override_id ? !!r.override_is_blocked : false,
			custom_price: r?.custom_price ?? null,
			notes: r?.notes ?? '',
		}
	}
	return drafts[tenantId]
}

function setDraft(tenantId, key, value) {
	getDraft(tenantId)[key] = value
}

function isDirty(tenantId) {
	const r = props.rows.find(x => x.tenant_id === tenantId)
	if (!r) return false
	const d = drafts[tenantId]
	if (!d) return false
	const origBlocked = r.override_id ? !!r.override_is_blocked : false
	const origPrice = r.custom_price
	const origNotes = r.notes ?? ''
	return d.is_blocked !== origBlocked
		|| (d.custom_price ?? null) !== (origPrice ?? null)
		|| (d.notes ?? '') !== origNotes
}

function save(r) {
	if (busyMap[r.tenant_id]) return
	busyMap[r.tenant_id] = true
	const d = drafts[r.tenant_id]
	router.post(`/products/${props.product.id}/tenants/${r.tenant_id}`, {
		is_blocked: d.is_blocked,
		custom_price: d.is_blocked ? null : d.custom_price,
		notes: d.notes,
	}, {
		preserveScroll: true,
		preserveState: false,  // güncel resolve sonucunu görmek için yeniden yükle
		onSuccess: () => {
			showToast?.({
				type: 'success',
				title: 'Kaydedildi',
				message: `${r.tenant_name} için ayarlar uygulandı.`,
			})
			delete drafts[r.tenant_id]
		},
		onError: (errs) => {
			showToast?.({
				type: 'error',
				title: 'Kayıt başarısız',
				message: Object.values(errs)[0] || 'Sunucu hatası.',
			})
		},
		onFinish: () => { busyMap[r.tenant_id] = false },
	})
}

async function confirmReset(r) {
	const ok = await $swal.dangerConfirm({
		title: 'Override\'ı Sil',
		html: `<strong>${r.tenant_name}</strong> için override silinecek. Tenant marka/kategori kuralına ve varsayılan fiyatlandırmaya dönecek.`,
		confirmText: 'Sıfırla',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/products/${props.product.id}/tenants/${r.tenant_id}`, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			delete drafts[r.tenant_id]
			showToast?.({ type: 'warning', title: 'Override silindi', message: r.tenant_name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-alert { margin-bottom: 16px; }

.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg)); border: 1px solid rgb(var(--color-border)); border-radius: 8px; padding: 5px 10px; min-width: 220px; }
.card-search svg { color: rgb(var(--color-muted)); flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); width: 100%; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: rgb(var(--color-bg)); }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: rgb(var(--color-muted)); border-bottom: 1px solid rgb(var(--color-border)); text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 10px 16px; font-size: 13px; color: rgb(var(--color-ink)); border-bottom: 1px solid rgb(var(--color-border)); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgb(var(--color-bg) / .5); }
.data-table tr.dirty td { background: rgb(var(--color-warning) / .08); }
.empty-row { text-align: center !important; color: rgb(var(--color-muted)); padding: 32px 0 !important; font-style: italic; }
.dim { color: rgb(var(--color-muted)); font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.tenant-cell { display: flex; align-items: center; gap: 10px; }
.tenant-name { font-weight: 600; color: rgb(var(--color-ink)); font-size: 13px; }
.tenant-code { color: rgb(var(--color-muted)); }

.src-tag { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.src-tag.default { background: rgb(var(--color-bg)); color: rgb(var(--color-muted)); }
.src-tag.rule { background: rgb(var(--color-warning) / .12); color: rgb(var(--color-warning)); }
.src-tag.override { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

.form-input { padding: 7px 10px; border: 1px solid rgb(var(--color-border)); border-radius: 6px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input.compact { width: 100%; max-width: 140px; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-input:disabled { background: rgb(var(--color-bg)); color: rgb(var(--color-muted)); cursor: not-allowed; }
.form-check { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px; color: rgb(var(--color-ink)); }

.table-actions { display: flex; gap: 4px; align-items: center; }
.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn.delete:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }
</style>
