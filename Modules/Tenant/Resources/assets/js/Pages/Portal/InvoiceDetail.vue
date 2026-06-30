<template>
	<Head :title="`Fatura #${invoice.id}`" />
	<div class="invoice-detail">
		<Link href="/invoices" class="back-link">← Faturalar</Link>
		<div class="header">
			<div>
				<h1 class="page-title">Fatura #{{ invoice.id }}</h1>
				<p class="page-subtitle">Tarih: {{ invoice.created_at?.slice(0, 10) }}</p>
			</div>
			<button class="btn-primary" :disabled="downloading" @click="download">
				{{ downloading ? 'Hazırlanıyor...' : 'PDF İndir' }}
			</button>
		</div>

		<div class="card">
			<div class="row"><span>Durum:</span><span class="status-pill" :class="invoice.status">{{ invoice.status }}</span></div>
			<div class="row"><span>Vade:</span><span>{{ invoice.due_date ?? '—' }}</span></div>
			<div class="row" v-if="invoice.paid_at"><span>Ödendi:</span><span>{{ invoice.paid_at?.slice(0, 10) }}</span></div>
			<div class="row" v-if="invoice.order_id"><span>Sipariş:</span><Link :href="`/orders/${invoice.order_id}`">#{{ invoice.order_id }}</Link></div>
			<div class="row" v-if="invoice.note"><span>Not:</span><span>{{ invoice.note }}</span></div>
			<div class="row grand"><span>Tutar:</span><span class="mono">{{ formatMoney(invoice.amount) }} {{ invoice.currency }}</span></div>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'
import { downloadInvoicePdf } from '@/lib/pdf'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	invoice: { type: Object, required: true },
})

const downloading = ref(false)

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

async function download() {
	downloading.value = true
	try {
		const res = await fetch(`/invoices/${props.invoice.id}/payload`, { headers: { Accept: 'application/json' } })
		if (!res.ok) throw new Error('Payload alınamadı')
		const json = await res.json()
		downloadInvoicePdf({ tenant: json.data.tenant, invoice: json.data.invoice })
	} catch (e) {
		alert('PDF hazırlanamadı: ' + e.message)
	} finally {
		downloading.value = false
	}
}
</script>

<style scoped>
.back-link { font-size: 12px; color: #4338ca; text-decoration: none; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 8px; margin-bottom: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.btn-primary { padding: 10px 18px; border-radius: 8px; background: #4338ca; color: #fff; border: none; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary:hover:not(:disabled) { background: #3730a3; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.card { background: #fff; border-radius: 16px; padding: 18px 20px; border: 1px solid #ebebf0; display: flex; flex-direction: column; gap: 8px; }
.row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #f5f5f8; }
.row:last-child { border-bottom: none; }
.row.grand { font-weight: 700; font-size: 16px; padding-top: 12px; border-top: 1px solid #ebebf0; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.status-pill { display: inline-block; padding: 2px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.paid { background: #dcfce7; color: #15803d; }
.status-pill.pending { background: #fef3c7; color: #b45309; }
.status-pill.cancelled { background: #fee2e2; color: #b91c1c; }
</style>
