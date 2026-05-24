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

		<div class="page-header">
			<div>
				<h1 class="page-title">{{ product.name }} · Tenant Ayarları</h1>
				<p class="page-subtitle">
					<span class="mono">{{ product.sku }}</span>
					<span v-if="product.brand_name"> · {{ product.brand_name }}</span>
					<span v-if="product.category_name"> · {{ product.category_name }}</span>
					<span class="dim"> · Baz Fiyat: <strong>₺{{ formatPrice(product.default_price) }}</strong></span>
				</p>
			</div>
		</div>

		<div class="info-box">
			<strong>Sadece sistem yöneticisi tarafından düzenlenebilir.</strong>
			Tüm aktif tenantlar listelenir. Her satırda durum kaynağı belirtilir:
			<span class="src-tag default">varsayılan</span>
			<span class="src-tag rule">marka/kategori kuralı</span>
			<span class="src-tag override">ürün override</span>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Tenant Listesi · {{ rows.length }} aktif tenant</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Tenant ara..." />
				</div>
			</div>

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
								<div class="tenant-logo">{{ r.tenant_name.charAt(0).toUpperCase() }}</div>
								<div>
									<div class="tenant-name">{{ r.tenant_name }}</div>
									<div class="tenant-code mono">{{ r.tenant_code }}</div>
								</div>
							</div>
						</td>
						<td>
							<span v-if="r.tenant_type" class="badge badge-type">{{ r.tenant_type }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td>
							<span :class="['status-pill', r.effective === 'allowed' ? 'allowed' : 'blocked']">
								{{ r.effective === 'allowed' ? '✓ Açık' : '🚫 Gizli' }}
							</span>
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
								<button class="btn btn-primary btn-sm" @click="save(r)" :disabled="!isDirty(r.tenant_id) || busyMap[r.tenant_id]">
									{{ busyMap[r.tenant_id] ? '...' : 'Kaydet' }}
								</button>
								<button v-if="r.override_id" class="table-action-btn delete" @click="confirmReset(r)" title="Override'ı sil (varsayılana dön)">↺</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

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
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }

.info-box { background: #f0f9ff; border: 1px solid #bae6fd; color: #0c4a6e; padding: 12px 16px; border-radius: 10px; font-size: 12.5px; line-height: 1.6; margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px; }
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 10px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.data-table tr.dirty td { background: #fffbeb; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.tenant-cell { display: flex; align-items: center; gap: 10px; }
.tenant-logo {
	width: 32px; height: 32px; border-radius: 8px;
	background: linear-gradient(135deg, #dbeafe, #bfdbfe);
	display: flex; align-items: center; justify-content: center;
	font-size: 14px; font-weight: 800; color: #2563eb; flex-shrink: 0;
}
.tenant-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.tenant-code { color: #888; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.badge-type { background: #e0e7ff; color: #4338ca; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; margin-right: 6px; }
.status-pill.allowed { background: #dcfce7; color: #15803d; }
.status-pill.blocked { background: #fee2e2; color: #b91c1c; }

.src-tag { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.src-tag.default { background: #f3f4f6; color: #6b7280; }
.src-tag.rule { background: #fef3c7; color: #92400e; }
.src-tag.override { background: #ede9fe; color: #7c3aed; }

.form-input { padding: 7px 10px; border: 1px solid #e8e8f0; border-radius: 6px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input.compact { width: 100%; max-width: 140px; }
.form-input:focus { border-color: #7c3aed; }
.form-input:disabled { background: #f5f5f8; color: #aaa; cursor: not-allowed; }
.form-check { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px; color: #444; }

.btn-sm { padding: 6px 14px; font-size: 12px; }

.table-actions { display: flex; gap: 4px; align-items: center; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 14px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; line-height: 1; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }
</style>
