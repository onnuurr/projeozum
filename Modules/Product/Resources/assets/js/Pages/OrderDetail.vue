<template>
	<Head :title="`Sipariş ${order.order_no}`" />
	<div class="page-order-detail">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Siparişler', to: '/orders' },
				{ label: order.order_no },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">{{ order.order_no }}</h1>
				<p class="page-subtitle">
					{{ order.tenant?.name ?? '—' }} · {{ order.created_at?.slice(0, 10) }}
				</p>
			</div>
			<span class="status-pill" :class="`status-${order.status}`">{{ statuses[order.status] ?? order.status }}</span>
		</div>

		<!-- Aksiyon butonları: yalnız izin verilen geçişler -->
		<div v-if="canManage && allowedTransitions.length" class="actions-bar">
			<button
				v-for="to in allowedTransitions"
				:key="to"
				class="btn"
				:class="to === 'cancelled' ? 'btn-danger' : 'btn-primary'"
				:disabled="busy"
				@click="promptTransition(to)"
			>{{ statuses[to] ?? to }}</button>
		</div>

		<div class="grid">
			<div class="col-main">
				<div class="card">
					<div class="card-title">Kalemler</div>
					<div class="table-scroll">
					<table class="data-table">
						<thead>
							<tr>
								<th>Ürün</th>
								<th>Renk/Beden</th>
								<th>Adet</th>
								<th>Birim</th>
								<th>Toplam</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="i in order.items" :key="i.id">
								<td>
									<div class="item-cell">
										<img v-if="i.product_image_url" :src="i.product_image_url" class="item-thumb" alt="" />
										<div>
											<div class="item-name">{{ i.product_name }}</div>
											<div class="item-brand">{{ i.product_brand }}</div>
										</div>
									</div>
								</td>
								<td>{{ [i.color, i.size].filter(Boolean).join(' / ') || '—' }}</td>
								<td class="mono">{{ i.qty }}</td>
								<td class="mono">{{ formatMoney(i.unit_price) }}</td>
								<td class="mono">{{ formatMoney(i.total_price) }}</td>
							</tr>
						</tbody>
					</table>
					</div>
					<div class="totals">
						<div class="total-row"><span>Ara Toplam</span><span class="mono">{{ formatMoney(order.subtotal) }}</span></div>
						<div class="total-row"><span>Kargo</span><span class="mono">{{ formatMoney(order.shipping_fee) }}</span></div>
						<div class="total-row grand"><span>Genel Toplam</span><span class="mono">{{ formatMoney(order.total) }}</span></div>
					</div>
				</div>

				<div v-if="order.carrier || order.cargo_customer_code" class="card">
					<div class="card-title">Kargo</div>
					<div class="total-row"><span>Firma</span><span>{{ order.carrier ?? '—' }}</span></div>
					<div class="total-row"><span>Müşteri Kodu</span><span class="mono">{{ order.cargo_customer_code ?? '—' }}</span></div>
				</div>

				<div v-if="ledger.length" class="card">
					<div class="card-title">Kredi Hareketleri</div>
					<div class="table-scroll">
					<table class="data-table">
						<thead><tr><th>Tarih</th><th>Tür</th><th>Sebep</th><th>Tutar</th><th>Bakiye</th></tr></thead>
						<tbody>
							<tr v-for="l in ledger" :key="l.id">
								<td>{{ l.created_at?.slice(0, 10) }}</td>
								<td><span class="badge" :class="l.type">{{ l.type === 'debit' ? 'Borç' : 'Alacak' }}</span></td>
								<td>{{ l.reason }}</td>
								<td class="mono">{{ formatMoney(l.amount) }}</td>
								<td class="mono">{{ formatMoney(l.balance_after) }}</td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>
			</div>

			<div class="col-side">
				<div class="card">
					<div class="card-title">Durum Zaman Çizelgesi</div>
					<ul class="timeline">
						<li v-if="order.histories.length === 0" class="empty">Henüz geçiş yok.</li>
						<li v-for="h in order.histories" :key="h.id" class="timeline-item">
							<div class="timeline-dot" :class="`status-${h.to_status}`"></div>
							<div class="timeline-body">
								<div class="timeline-status">
									{{ statuses[h.from_status] ?? h.from_status ?? 'Başlangıç' }} → <strong>{{ statuses[h.to_status] ?? h.to_status }}</strong>
								</div>
								<div class="timeline-meta">{{ h.user ?? 'Sistem' }} · {{ h.created_at?.slice(0, 16).replace('T', ' ') }}</div>
								<div v-if="h.note" class="timeline-note">{{ h.note }}</div>
							</div>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<AppModal v-model="confirmOpen" :title="modalTitle" size="sm" :variant="pendingTo === 'cancelled' ? 'danger' : 'info'">
			<p class="modal-text">
				Sipariş durumunu <strong>{{ statuses[pendingTo] ?? pendingTo }}</strong> yapmak istediğinize emin misiniz?
			</p>
			<label class="modal-label">Not (opsiyonel)</label>
			<textarea v-model="note" class="modal-textarea" rows="3" placeholder="Açıklama…"></textarea>
			<template #footer>
				<button class="btn btn-ghost" @click="confirmOpen = false">Vazgeç</button>
				<button class="btn" :class="pendingTo === 'cancelled' ? 'btn-danger' : 'btn-primary'" :disabled="busy" @click="submitTransition">
					Onayla
				</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	order: { type: Object, required: true },
	statuses: { type: Object, default: () => ({}) },
	allowedTransitions: { type: Array, default: () => [] },
	ledger: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('order.manage'))

const confirmOpen = ref(false)
const pendingTo = ref(null)
const note = ref('')
const busy = ref(false)

const modalTitle = computed(() => pendingTo.value === 'cancelled' ? 'Siparişi İptal Et' : 'Durum Güncelle')

function promptTransition(to) {
	pendingTo.value = to
	note.value = ''
	confirmOpen.value = true
}

function submitTransition() {
	busy.value = true
	router.put(`/orders/${props.order.id}/status`, { status: pendingTo.value, note: note.value || null }, {
		preserveScroll: true,
		onSuccess: () => {
			confirmOpen.value = false
			showToast?.({ type: 'success', title: 'Durum güncellendi' })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Geçiş başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin: 4px 0; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.actions-bar { display: flex; gap: 8px; margin-bottom: 16px; }
.grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
.col-main { display: flex; flex-direction: column; gap: 16px; }
.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; padding: 16px; }
.card-title { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.data-table th { font-size: 11px; text-transform: uppercase; color: #888; }
.item-cell { display: flex; align-items: center; gap: 10px; }
.item-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f5f5f8; }
.item-name { font-weight: 600; color: #1a1a2e; }
.item-brand { font-size: 11px; color: #999; }
.totals { margin-top: 12px; border-top: 1px dashed #ebebf0; padding-top: 12px; }
.total-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; color: #555; }
.total-row.grand { font-weight: 700; color: #1a1a2e; font-size: 15px; margin-top: 6px; }
.timeline { list-style: none; margin: 0; padding: 0; }
.timeline-item { display: flex; gap: 10px; padding-bottom: 14px; position: relative; }
.timeline-dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 3px; flex-shrink: 0; background: #cbd5e1; }
.timeline-status { font-size: 13px; color: #333; }
.timeline-meta { font-size: 11px; color: #999; }
.timeline-note { font-size: 12px; color: #666; margin-top: 3px; font-style: italic; }
.status-pill { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #f0f0f5; color: #555; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #dbeafe; color: #1e40af; }
.status-preparing { background: #ede9fe; color: #5b21b6; }
.status-shipped { background: #cffafe; color: #155e75; }
.status-delivered { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.badge { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.badge.debit { background: #fee2e2; color: #991b1b; }
.badge.credit { background: #dcfce7; color: #166534; }
.btn { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid #ebebf0; background: #fff; }
.btn-primary { background: #4338ca; color: #fff; border-color: #4338ca; }
.btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
.btn-ghost { color: #555; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-text { font-size: 14px; color: #333; margin-bottom: 12px; }
.modal-label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px; }
.modal-textarea { width: 100%; border: 1px solid #ebebf0; border-radius: 8px; padding: 8px; font-size: 13px; resize: vertical; }
.empty { color: #aaa; font-style: italic; font-size: 13px; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
</style>
