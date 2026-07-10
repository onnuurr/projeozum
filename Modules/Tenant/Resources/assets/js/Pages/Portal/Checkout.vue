<template>
	<Head title="Sepet · Ödeme" />
	<div class="checkout">
		<h1 class="page-title">Sepet · Dropship Sipariş</h1>

		<div class="grid">
			<form class="left" @submit.prevent="submit">
				<section class="card">
					<h2 class="card-title">Teslimat Adresi</h2>
					<div class="row-2">
						<div class="row">
							<label>Ad Soyad <span class="req">*</span></label>
							<input v-model="form.address.name" type="text" required />
						</div>
						<div class="row">
							<label>Telefon <span class="req">*</span></label>
							<input v-model="form.address.phone" type="text" required />
						</div>
					</div>
					<div class="row">
						<label>Adres <span class="req">*</span></label>
						<textarea v-model="form.address.street" rows="2" required />
					</div>
					<div class="row-3">
						<div class="row">
							<label>İl <span class="req">*</span></label>
							<input v-model="form.address.city" type="text" required />
						</div>
						<div class="row">
							<label>İlçe</label>
							<input v-model="form.address.district" type="text" />
						</div>
						<div class="row">
							<label>Posta Kodu</label>
							<input v-model="form.address.postal_code" type="text" />
						</div>
					</div>
				</section>

				<section class="card">
					<h2 class="card-title">Fatura Yönü</h2>
					<label class="radio">
						<input v-model="form.billing_to" type="radio" value="us" />
						<span>Bize kesilsin (bayi)</span>
					</label>
					<label class="radio">
						<input v-model="form.billing_to" type="radio" value="customer" />
						<span>Son müşteriye kesilsin</span>
					</label>
				</section>

				<section class="card">
					<h2 class="card-title">Kargo</h2>
					<label v-for="m in shippingMethods" :key="m.id" class="radio">
						<input v-model="form.shipping_method" type="radio" :value="m.id" />
						<span>{{ m.label }} <em class="muted">— {{ m.description }}</em></span>
					</label>
				</section>

				<section class="card">
					<h2 class="card-title">Not</h2>
					<textarea v-model="form.note" rows="2" placeholder="Sipariş notu..." />
				</section>

				<label class="terms">
					<input v-model="form.terms_accepted" type="checkbox" required />
					<span>Şartları kabul ediyorum.</span>
				</label>

				<button class="btn-primary" :disabled="submitting || belowMinOrder" type="submit">
					{{ submitting ? 'Gönderiliyor...' : 'Siparişi Tamamla' }}
				</button>
			</form>

			<aside class="right">
				<section class="card">
					<h2 class="card-title">Sepet ({{ items.length }} kalem)</h2>
					<ul class="item-list">
						<li v-for="i in items" :key="i.id">
							<span class="name">{{ i.product_name ?? 'Ürün' }}</span>
							<span class="qty mono">{{ i.qty }} x {{ formatMoney(i.price) }}</span>
						</li>
					</ul>
				</section>

				<section v-if="lineWarnings.length" class="card warn-card">
					<p v-for="(w, idx) in lineWarnings" :key="idx" class="warn">⚠ {{ w }}</p>
				</section>

				<section class="card totals">
					<div><span>Ara Toplam:</span><span class="mono">{{ formatMoney(totals.subtotal) }}</span></div>
					<div v-if="Number(totals.discount_amount) > 0" class="discount">
						<span>İskonto (%{{ Number(totals.discount_rate) }}):</span>
						<span class="mono">− {{ formatMoney(totals.discount_amount) }}</span>
					</div>
					<div><span>Kargo:</span><span class="mono">{{ formatMoney(totals.shipping_fee) }}</span></div>
					<div class="grand"><span>Toplam:</span><span class="mono">{{ formatMoney(totals.total) }}</span></div>
					<div v-if="dueDateLabel" class="due"><span>Vade:</span><span class="mono">{{ dueDateLabel }}</span></div>
				</section>

				<section v-if="belowMinOrder" class="card warn-card">
					<p class="warn">
						⚠ Minimum sipariş tutarı {{ formatMoney(tenant.min_order_total) }} — sipariş açılamaz.
					</p>
				</section>

				<section class="card credit-preview" :class="{ over: credit.after_order < 0 || totals.total > credit.available }">
					<h2 class="card-title">Kredi Durumu</h2>
					<div><span>Kullanılabilir:</span><span class="mono">{{ formatMoney(credit.available) }}</span></div>
					<div><span>Bu siparişten sonra:</span><span class="mono">{{ formatMoney(credit.after_order) }}</span></div>
					<p v-if="totals.total > credit.available" class="warn">
						⚠ Kredi limitiniz yetmiyor — sipariş açılamayacak.
					</p>
				</section>
			</aside>
		</div>
	</div>
</template>

<script setup>
import { reactive, ref, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	items: { type: Array, default: () => [] },
	totals: { type: Object, required: true },
	credit: { type: Object, required: true },
	shippingMethods: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)

const form = reactive({
	billing_to: 'us',
	shipping_method: props.shippingMethods[0]?.id ?? 'cargo',
	note: '',
	terms_accepted: false,
	address: {
		name: '',
		phone: props.tenant.phone ?? '',
		street: props.tenant.address ?? '',
		district: '',
		city: props.tenant.city ?? '',
		postal_code: '',
	},
})

const submitting = ref(false)

const belowMinOrder = computed(() =>
	props.tenant.min_order_total != null && Number(props.totals.subtotal) < Number(props.tenant.min_order_total),
)

const dueDateLabel = computed(() => {
	const days = Number(props.tenant.payment_term_days ?? 0)
	if (!days) return null
	const d = new Date()
	d.setDate(d.getDate() + days)
	return d.toLocaleDateString('tr-TR') + ` (${days} gün vade)`
})

const lineWarnings = computed(() => {
	const out = []
	for (const i of props.items) {
		if (i.min_order_qty != null && Number(i.qty) < Number(i.min_order_qty)) {
			out.push(`${i.product_name ?? 'Ürün'}: minimum ${i.min_order_qty} adet gerekli.`)
		}
		if (i.order_multiple != null && Number(i.order_multiple) > 0 && Number(i.qty) % Number(i.order_multiple) !== 0) {
			out.push(`${i.product_name ?? 'Ürün'}: ${i.order_multiple} katları hâlinde sipariş edilmeli.`)
		}
	}
	return out
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

function submit() {
	submitting.value = true
	router.post('/checkout', { ...form }, {
		preserveScroll: true,
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Doğrulama hatası', message: Object.values(errs)[0] || 'Form hatası.' })
		},
		onFinish: () => { submitting.value = false },
	})
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; }
.grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
.left, .right { display: flex; flex-direction: column; gap: 12px; }
.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; padding: 16px 18px; }
.card-title { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
.row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.row label { font-size: 11px; color: #555; font-weight: 600; }
.req { color: #ef4444; }
.row input, .row textarea, .row select, select, textarea { padding: 8px 12px; border: 1px solid #ebebf0; border-radius: 8px; font-size: 13px; font-family: inherit; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.radio { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; cursor: pointer; }
.muted { color: #888; font-style: normal; font-size: 12px; }
.terms { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 6px 0; cursor: pointer; }
.btn-primary { padding: 12px 24px; background: #4338ca; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.item-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.item-list li { display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid #f5f5f8; }
.name { color: #1a1a2e; }
.qty { color: #555; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.totals div { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.totals .discount { color: #16a34a; }
.totals .grand { font-weight: 700; font-size: 16px; padding-top: 8px; border-top: 1px solid #ebebf0; margin-top: 4px; }
.totals .due { color: #b45309; font-size: 12px; }
.warn-card { background: #fef3c7; border-color: #fcd34d; }
.credit-preview.over { background: #fee2e2; border-color: #fca5a5; }
.credit-preview div { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.warn { font-size: 12px; color: #b91c1c; margin: 4px 0; }
</style>
