<template>
	<Head title="Trendyol · Pano" />
	<div class="page">
		<Link href="/marketplace" class="back-link">← Pazaryerleri</Link>

		<header class="header">
			<div>
				<h1 class="page-title">Trendyol</h1>
				<p class="page-subtitle">{{ salesCount }} satış kaydı</p>
			</div>
			<div class="actions">
				<button class="btn" :disabled="busy" @click="pullOrders">{{ busy ? '...' : 'Siparişleri Çek' }}</button>
				<Link href="/marketplace/trendyol/listings" class="btn outline">Satışlar</Link>
			</div>
		</header>

		<section class="card">
			<h2 class="card-title">Son Senkronizasyonlar</h2>
			<table class="data-table" v-if="recentLogs.length">
				<thead>
					<tr><th>Tarih</th><th>İşlem</th><th>Durum</th><th>Adet</th><th>Hata</th></tr>
				</thead>
				<tbody>
					<tr v-for="l in recentLogs" :key="l.id">
						<td>{{ l.started_at?.slice(0, 19).replace('T', ' ') }}</td>
						<td>{{ l.operation }}</td>
						<td><span :class="['pill', l.status]">{{ l.status }}</span></td>
						<td class="mono">{{ l.items_processed }}</td>
						<td class="dim">{{ l.error_message || '—' }}</td>
					</tr>
				</tbody>
			</table>
			<p v-else class="empty">Henüz senkronizasyon kaydı yok.</p>
		</section>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	recentLogs: { type: Array, default: () => [] },
	salesCount: { type: Number, default: 0 },
})

const showToast = inject('showToast', null)
const busy = ref(false)

function pullOrders() {
	busy.value = true
	router.post('/marketplace/trendyol/pull-orders', {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Çekme kuyruğa alındı', message: 'Son 24 saat' }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Hata', message: Object.values(errs)[0] || '...' }),
		onFinish: () => { busy.value = false },
	})
}
</script>

<style scoped>
.back-link { font-size: 12px; color: #4338ca; text-decoration: none; }
.header { display: flex; justify-content: space-between; align-items: flex-end; margin: 8px 0 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.actions { display: flex; gap: 8px; }
.btn { padding: 8px 16px; border-radius: 8px; background: #4338ca; color: #fff; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; }
.btn.outline { background: transparent; color: #4338ca; border: 1px solid #4338ca; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; padding: 16px 18px; }
.card-title { font-size: 13px; font-weight: 700; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #555; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.data-table th { font-size: 11px; text-transform: uppercase; color: #888; }
.pill { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.pill.success { background: #dcfce7; color: #15803d; }
.pill.failed { background: #fee2e2; color: #b91c1c; }
.pill.running { background: #fef3c7; color: #b45309; }
.pill.queued { background: #e0e7ff; color: #4338ca; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.dim { color: #888; font-size: 11px; }
.empty { color: #aaa; font-style: italic; padding: 24px; text-align: center; }
</style>
