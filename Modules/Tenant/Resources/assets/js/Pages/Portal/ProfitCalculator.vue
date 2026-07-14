<template>
	<Head title="Kâr Hesabı" />
	<div class="profit-page">
		<h1 class="page-title">Kâr Hesabı</h1>
		<p class="page-subtitle">Bir ürünü seçtiğin pazaryerinde satarsan ne kazanırsın?</p>

		<div class="grid">
			<form class="left card" @submit.prevent="compute">
				<h2 class="card-title">Girdiler</h2>

				<div class="row">
					<label>Ürün</label>
					<input v-model="search" type="text" placeholder="Ürün adı / SKU..." @input="onSearch" />
					<ul v-if="suggestions.length" class="suggest">
						<li v-for="s in suggestions" :key="s.id" @click="pickProduct(s)">
							<strong>{{ s.name }}</strong> <span class="mono dim">{{ s.sku }}</span>
						</li>
					</ul>
					<div v-if="form.product_id" class="picked mono">#{{ form.product_id }} seçildi</div>
				</div>

				<div class="row-2">
					<div class="row">
						<label>Pazaryeri</label>
						<select v-model="form.marketplace">
							<option v-for="m in marketplaces" :key="m.code" :value="m.code">{{ m.label }}</option>
						</select>
					</div>
					<div class="row">
						<label>Adet</label>
						<input v-model.number="form.qty" type="number" min="1" />
					</div>
				</div>

				<div class="row">
					<label>Satış Fiyatı (₺)</label>
					<input v-model.number="form.sell_price" type="number" min="0" step="0.01" />
				</div>

				<button class="btn" type="submit" :disabled="busy || !form.product_id">
					{{ busy ? 'Hesaplanıyor...' : 'Hesapla' }}
				</button>
			</form>

			<aside class="right">
				<section v-if="result" class="card" :class="{ profitable: result.net_profit > 0, loss: result.net_profit < 0 }">
					<h2 class="card-title">Sonuç</h2>
					<div class="big">{{ formatMoney(result.net_profit) }}</div>
					<div class="margin">Marj: <strong>%{{ result.margin_pct }}</strong></div>

					<div class="rows">
						<div><span>Bayilik maliyeti (toplam):</span><span class="mono">{{ formatMoney(result.tenant_cost) }}</span></div>
						<div><span>Komisyon:</span><span class="mono">{{ formatMoney(result.commission) }}</span></div>
						<div><span>Kargo:</span><span class="mono">{{ formatMoney(result.shipping) }}</span></div>
						<div><span>KDV:</span><span class="mono">{{ formatMoney(result.vat) }}</span></div>
						<div class="grand"><span>Brüt satış:</span><span class="mono">{{ formatMoney(result.sell_price) }}</span></div>
					</div>

					<canvas ref="chartCanvas" height="160" style="margin-top: 14px"></canvas>
				</section>
				<section v-else class="card placeholder">
					<p>Ürün seç, pazaryeri + satış fiyatı gir, "Hesapla"ya bas.</p>
				</section>
			</aside>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, nextTick, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Chart, registerables } from 'chart.js'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

Chart.register(...registerables)

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	marketplaces: { type: Array, default: () => [] },
})

const form = reactive({
	product_id: null,
	variant_id: null,
	marketplace: 'trendyol',
	sell_price: 100,
	qty: 1,
})

const search = ref('')
const suggestions = ref([])
const result = ref(null)
const busy = ref(false)
const chartCanvas = ref(null)
let chart = null

let searchTimer = null
function onSearch() {
	clearTimeout(searchTimer)
	searchTimer = setTimeout(async () => {
		if (search.value.length < 2) { suggestions.value = []; return }
		const res = await fetch(`/profit/search?q=${encodeURIComponent(search.value)}`, { headers: { Accept: 'application/json' } })
		const json = await res.json()
		suggestions.value = json.products
	}, 250)
}

function pickProduct(p) {
	form.product_id = p.id
	search.value = `${p.name} (${p.sku})`
	suggestions.value = []
}

async function compute() {
	if (!form.product_id) return
	busy.value = true
	try {
		const res = await fetch('/profit', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
				Accept: 'application/json',
			},
			body: JSON.stringify(form),
		})
		const json = await res.json()
		result.value = json.data
		await nextTick()
		renderChart()
	} finally {
		busy.value = false
	}
}

function renderChart() {
	if (!chartCanvas.value || !result.value) return
	if (chart) chart.destroy()
	const r = result.value
	chart = new Chart(chartCanvas.value, {
		type: 'doughnut',
		data: {
			labels: ['Bayilik', 'Komisyon', 'Kargo', 'KDV', 'Kâr'],
			datasets: [{
				data: [r.tenant_cost, r.commission, r.shipping, r.vat, Math.max(r.net_profit, 0)],
				backgroundColor: ['#a78bfa', '#fbbf24', '#60a5fa', '#f87171', '#34d399'],
			}],
		},
		options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
	})
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin: 4px 0 16px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; padding: 18px 20px; }
.card.profitable { border-color: #86efac; background: linear-gradient(135deg, #f0fdf4, #fff); }
.card.loss { border-color: #fca5a5; background: linear-gradient(135deg, #fef2f2, #fff); }
.card-title { font-size: 13px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 12px; }
.row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; position: relative; }
.row label { font-size: 11px; font-weight: 600; color: #555; }
.row input, .row select { padding: 8px 12px; border: 1px solid #ebebf0; border-radius: 8px; font-size: 13px; }
.row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 10px; }
.suggest { position: absolute; top: 56px; left: 0; right: 0; background: #fff; border: 1px solid #ebebf0; border-radius: 8px; list-style: none; padding: 4px; margin: 0; z-index: 10; max-height: 220px; overflow-y: auto; }
.suggest li { padding: 6px 10px; font-size: 12px; cursor: pointer; border-radius: 6px; }
.suggest li:hover { background: #f7f7fb; }
.picked { font-size: 11px; color: #4338ca; margin-top: 4px; }
.btn { padding: 12px 20px; background: #4338ca; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.big { font-size: 36px; font-weight: 800; color: #15803d; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.card.loss .big { color: #b91c1c; }
.margin { font-size: 13px; color: #555; margin-bottom: 14px; }
.rows div { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; border-bottom: 1px solid #f5f5f8; }
.rows .grand { font-weight: 700; padding-top: 8px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.dim { color: #888; font-size: 11px; }
.placeholder p { color: #888; font-size: 13px; text-align: center; padding: 32px; }

@media (max-width: 700px) {
	.grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
