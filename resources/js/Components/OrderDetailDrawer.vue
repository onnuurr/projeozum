<template>
	<Teleport to="body">
		<aside
			class="drawer order-drawer"
			:class="{ open: modelValue }"
			role="dialog"
			aria-modal="true"
		>
			<template v-if="order">
				<div class="drawer-header">
					<div class="drawer-title">
						<div class="drawer-title-icon">
							<svg
								width="16"
								height="16"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								viewBox="0 0 24 24"
							>
								<path
									d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"
								/>
								<polyline
									points="3.27 6.96 12 12.01 20.73 6.96"
								/>
								<line x1="12" y1="22.08" x2="12" y2="12" />
							</svg>
						</div>
						<div>
							<h4>Sipariş Detayı</h4>
							<p class="mono">{{ order.orderNo }}</p>
						</div>
					</div>
					<div class="drawer-header-actions">
						<button
							class="drawer-edit-btn"
							@click="createInvoice"
							title="Fatura Oluştur"
							aria-label="Fatura yazdır"
						>
							<svg
								width="14"
								height="14"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
							>
								<path
									d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
								></path>
								<polyline points="14 2 14 8 20 8"></polyline>
								<line x1="12" y1="18" x2="12" y2="12"></line>
								<line x1="9" y1="15" x2="15" y2="15"></line>
							</svg>
						</button>
						<button
							class="drawer-edit-btn"
							@click="printInvoice"
							title="Fatura yazdır"
							aria-label="Fatura yazdır"
						>
							<svg
								width="14"
								height="14"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								viewBox="0 0 24 24"
							>
								<polyline points="6 9 6 2 18 2 18 9" />
								<path
									d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"
								/>
								<rect x="6" y="14" width="12" height="8" />
							</svg>
						</button>
						<button
							class="drawer-close"
							@click="close"
							aria-label="Kapat"
						>
							<svg
								width="14"
								height="14"
								fill="none"
								stroke="currentColor"
								stroke-width="2.5"
								viewBox="0 0 24 24"
							>
								<path d="M18 6L6 18M6 6l12 12" />
							</svg>
						</button>
					</div>
				</div>

				<div class="drawer-body">
					<!-- Hero -->
					<section class="order-hero">
						<div class="hero-left">
							<div class="hero-meta-row">
								<span
									class="order-status-badge"
									:class="`os-${order.status}`"
								>
									<span class="dot"></span>
									{{ order.statusLabel }}
								</span>
								<span
									class="payment-status"
									:class="`ps-${order.paymentStatus}`"
								>
									<svg
										v-if="order.paymentStatus === 'paid'"
										width="11"
										height="11"
										fill="none"
										stroke="currentColor"
										stroke-width="2.5"
										viewBox="0 0 24 24"
									>
										<polyline points="20 6 9 17 4 12" />
									</svg>
									<svg
										v-else-if="
											order.paymentStatus === 'pending'
										"
										width="11"
										height="11"
										fill="none"
										stroke="currentColor"
										stroke-width="2.5"
										viewBox="0 0 24 24"
									>
										<circle cx="12" cy="12" r="10" />
										<polyline points="12 6 12 12 16 14" />
									</svg>
									<svg
										v-else
										width="11"
										height="11"
										fill="none"
										stroke="currentColor"
										stroke-width="2.5"
										viewBox="0 0 24 24"
									>
										<line x1="18" y1="6" x2="6" y2="18" />
										<line x1="6" y1="6" x2="18" y2="18" />
									</svg>
									{{
										paymentStatusLabel(order.paymentStatus)
									}}
								</span>
								<span
									class="channel-badge"
									:class="`ch-${order.channel}`"
									>{{ channelLabel(order.channel) }}</span
								>
							</div>
							<div class="hero-date">
								{{ order.createdLabel }}
							</div>
						</div>
						<div class="hero-right">
							<div class="hero-total-label">Sipariş Tutarı</div>
							<div class="hero-total">
								{{ formatPrice(order.total) }}
							</div>
							<div class="hero-items-count">
								{{ totalItems }} ürün ·
								{{ order.items.length }} kalem
							</div>
						</div>
					</section>

					<!-- Status progress + güncelleme -->
					<section class="status-section">
						<div
							class="status-progress"
							:class="{ terminated: isTerminated }"
						>
							<div
								v-for="(step, idx) in progressSteps"
								:key="step.key"
								class="status-step"
								:class="{
									done: stepIndex >= idx,
									current: stepIndex === idx,
								}"
							>
								<div class="status-dot">
									<svg
										v-if="stepIndex > idx"
										width="10"
										height="10"
										fill="none"
										stroke="#fff"
										stroke-width="3"
										viewBox="0 0 24 24"
									>
										<polyline points="20 6 9 17 4 12" />
									</svg>
									<span v-else>{{ idx + 1 }}</span>
								</div>
								<div class="status-label">{{ step.label }}</div>
							</div>
							<div v-if="isTerminated" class="status-terminated">
								<svg
									width="14"
									height="14"
									fill="none"
									stroke="currentColor"
									stroke-width="2"
									viewBox="0 0 24 24"
								>
									<circle cx="12" cy="12" r="10" />
									<line x1="12" y1="8" x2="12" y2="12" />
									<line x1="12" y1="16" x2="12.01" y2="16" />
								</svg>
								{{
									order.status === 'returned'
										? 'Sipariş iade edildi'
										: 'Sipariş iptal edildi'
								}}
							</div>
						</div>

						<div class="status-actions">
							<span class="status-actions-label"
								>Durumu güncelle:</span
							>
							<div class="status-chips">
								<button
									v-for="s in statusFlow"
									:key="s.key"
									class="status-chip"
									:class="[
										`status-chip-${s.key}`,
										{ active: order.status === s.key },
									]"
									:disabled="order.status === s.key"
									@click="
										$emit('status-change', order.id, s.key)
									"
								>
									{{ s.label }}
								</button>
							</div>
						</div>
					</section>

					<!-- Müşteri -->
					<section class="drawer-section">
						<div class="drawer-section-title">Müşteri</div>
						<div class="customer-card">
							<div
								class="customer-avatar"
								:style="{ background: order.customer.gradient }"
							>
								{{ order.customer.initials }}
							</div>
							<div class="customer-info">
								<div class="customer-name">
									{{ order.customer.name }}
								</div>
								<div class="customer-meta">
									<a
										:href="`mailto:${order.customer.email}`"
										class="link"
										>{{ order.customer.email }}</a
									>
									<span class="sep">·</span>
									<a
										:href="`tel:${order.customer.phone}`"
										class="link"
										>{{ order.customer.phone }}</a
									>
								</div>
								<div class="customer-stats">
									<span
										><strong>{{
											order.customer.totalOrders
										}}</strong>
										sipariş</span
									>
									<span class="sep">·</span>
									<span
										>Üyelik:
										{{ order.customer.memberSince }}</span
									>
								</div>
							</div>
						</div>
					</section>

					<!-- Ürünler -->
					<section class="drawer-section">
						<div class="drawer-section-title">
							Sipariş İçeriği
							<span class="section-sub"
								>{{ order.items.length }} kalem ·
								{{ totalItems }} adet</span
							>
						</div>
						<div class="items-list">
							<article
								v-for="(item, idx) in order.items"
								:key="idx"
								class="order-item"
							>
								<img
									:src="`https://picsum.photos/seed/tek-p${item.productId}/120/150`"
									:alt="item.name"
									loading="lazy"
								/>
								<div class="order-item-body">
									<div class="oi-brand">{{ item.brand }}</div>
									<div class="oi-name">{{ item.name }}</div>
									<div class="oi-meta">
										<span
											v-if="item.color"
											class="oi-color"
											:style="{ background: item.color }"
										></span>
										<span v-if="item.size"
											>Beden:
											<strong>{{
												item.size
											}}</strong></span
										>
										<span class="sep">·</span>
										<span
											>{{ item.qty }} ×
											{{ formatPrice(item.price) }}</span
										>
									</div>
								</div>
								<div class="oi-total">
									{{ formatPrice(item.qty * item.price) }}
								</div>
							</article>
						</div>

						<div class="items-totals">
							<div class="it-row">
								<span>Ara Toplam</span
								><strong>{{
									formatPrice(order.subtotal)
								}}</strong>
							</div>
							<div class="it-row">
								<span>Kargo</span
								><strong
									v-if="order.shipping === 0"
									class="text-success"
									>Ücretsiz</strong
								><strong v-else>{{
									formatPrice(order.shipping)
								}}</strong>
							</div>
							<div v-if="order.discount > 0" class="it-row">
								<span>İndirim</span
								><strong class="text-success"
									>−{{ formatPrice(order.discount) }}</strong
								>
							</div>
							<div class="it-row it-total">
								<span>Toplam</span
								><strong>{{ formatPrice(order.total) }}</strong>
							</div>
						</div>
					</section>

					<!-- Teslimat & Ödeme yan yana -->
					<div class="detail-grid">
						<section class="drawer-section">
							<div class="drawer-section-title">Teslimat</div>
							<div class="kv-block">
								<div class="kv-row">
									<span class="kv-label">Adres</span>
									<div class="kv-value">
										<span class="addr-tag">{{
											order.shippingAddress.label
										}}</span>
										<div class="addr-text">
											{{ order.shippingAddress.street
											}}<br />
											{{ order.shippingAddress.district }}
											/ {{ order.shippingAddress.city }} ·
											{{
												order.shippingAddress.postalCode
											}}
										</div>
									</div>
								</div>
								<div class="kv-row">
									<span class="kv-label">Kargo</span>
									<span class="kv-value">{{
										shippingMethodLabel(
											order.shippingMethod
										)
									}}</span>
								</div>
								<div class="kv-row" v-if="order.eta">
									<span class="kv-label">Teslim ETA</span>
									<span class="kv-value text-success">{{
										order.eta
									}}</span>
								</div>
								<div class="kv-row" v-if="order.trackingNo">
									<span class="kv-label">Takip No</span>
									<span class="kv-value mono">{{
										order.trackingNo
									}}</span>
								</div>
								<div class="kv-row" v-if="order.carrier">
									<span class="kv-label">Taşıyıcı</span>
									<span class="kv-value">{{
										order.carrier
									}}</span>
								</div>
							</div>
						</section>

						<section class="drawer-section">
							<div class="drawer-section-title">Ödeme</div>
							<div class="kv-block">
								<div class="kv-row">
									<span class="kv-label">Yöntem</span>
									<span class="kv-value pay-method-row">
										<span
											class="pay-icon"
											:class="`pay-${order.paymentMethod}`"
										>
											<svg
												v-if="
													order.paymentMethod ===
													'card'
												"
												width="13"
												height="13"
												fill="none"
												stroke="currentColor"
												stroke-width="2"
												viewBox="0 0 24 24"
											>
												<rect
													x="2"
													y="5"
													width="20"
													height="14"
													rx="2"
												/>
												<line
													x1="2"
													y1="10"
													x2="22"
													y2="10"
												/>
											</svg>
											<svg
												v-else-if="
													order.paymentMethod ===
													'bank'
												"
												width="13"
												height="13"
												fill="none"
												stroke="currentColor"
												stroke-width="2"
												viewBox="0 0 24 24"
											>
												<line
													x1="3"
													y1="21"
													x2="21"
													y2="21"
												/>
												<line
													x1="3"
													y1="10"
													x2="21"
													y2="10"
												/>
												<polyline
													points="5 6 12 3 19 6"
												/>
												<line
													x1="4"
													y1="10"
													x2="4"
													y2="21"
												/>
												<line
													x1="20"
													y1="10"
													x2="20"
													y2="21"
												/>
											</svg>
											<svg
												v-else
												width="13"
												height="13"
												fill="none"
												stroke="currentColor"
												stroke-width="2"
												viewBox="0 0 24 24"
											>
												<rect
													x="1"
													y="3"
													width="15"
													height="13"
												/>
												<polygon
													points="16 8 20 8 23 11 23 16 16 16 16 8"
												/>
												<circle
													cx="5.5"
													cy="18.5"
													r="2.5"
												/>
												<circle
													cx="18.5"
													cy="18.5"
													r="2.5"
												/>
											</svg>
										</span>
										{{
											paymentMethodLabel(
												order.paymentMethod
											)
										}}
									</span>
								</div>
								<div class="kv-row" v-if="order.paymentBrand">
									<span class="kv-label">Kart</span>
									<span class="kv-value"
										>{{ order.paymentBrand }} ••••
										{{ order.paymentLast4 }}</span
									>
								</div>
								<div
									class="kv-row"
									v-if="order.paymentMethod === 'card'"
								>
									<span class="kv-label">Taksit</span>
									<span class="kv-value">{{
										order.installments === 1
											? 'Tek Çekim'
											: order.installments + ' Taksit'
									}}</span>
								</div>
								<div class="kv-row">
									<span class="kv-label">Tahsilat</span>
									<span class="kv-value">
										<span
											class="payment-status"
											:class="`ps-${order.paymentStatus}`"
										>
											{{
												paymentStatusLabel(
													order.paymentStatus
												)
											}}
										</span>
									</span>
								</div>
							</div>
						</section>
					</div>

					<!-- Timeline -->
					<section class="drawer-section">
						<div class="drawer-section-title">Sipariş Geçmişi</div>
						<ol class="timeline">
							<li
								v-for="(t, idx) in [
									...order.timeline,
								].reverse()"
								:key="idx"
								class="timeline-item"
								:class="`tl-${t.type}`"
							>
								<div class="tl-dot">
									<span>{{ t.icon }}</span>
								</div>
								<div class="tl-body">
									<div class="tl-label">{{ t.label }}</div>
									<div class="tl-time">
										{{ formatTime(t.at) }}
									</div>
								</div>
							</li>
						</ol>
					</section>

					<!-- Notlar -->
					<section v-if="order.notes" class="drawer-section">
						<div class="drawer-section-title">İç Notlar</div>
						<div class="notes-box">
							<svg
								width="14"
								height="14"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								viewBox="0 0 24 24"
							>
								<path
									d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
								/>
								<polyline points="14 2 14 8 20 8" />
								<line x1="16" y1="13" x2="8" y2="13" />
								<line x1="16" y1="17" x2="8" y2="17" />
							</svg>
							<p>{{ order.notes }}</p>
						</div>
					</section>
				</div>

				<div class="drawer-footer">
					<button
						class="btn btn-secondary btn-with-icon"
						@click="printShippingLabel"
					>
						<svg
							width="13"
							height="13"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							viewBox="0 0 24 24"
						>
							<polyline points="6 9 6 2 18 2 18 9" />
							<path
								d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"
							/>
							<rect x="6" y="14" width="12" height="8" />
						</svg>
						Kargo Etiketi
					</button>
					<button
						v-if="order.status === 'delivered'"
						class="btn btn-outline-danger btn-with-icon"
						@click="$emit('status-change', order.id, 'returned')"
					>
						<svg
							width="13"
							height="13"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							viewBox="0 0 24 24"
						>
							<polyline points="1 4 1 10 7 10" />
							<path d="M3.51 15a9 9 0 102.13-9.36L1 10" />
						</svg>
						İade Başlat
					</button>
					<button
						v-else-if="!isTerminated"
						class="btn btn-ghost btn-with-icon danger-text"
						@click="$emit('status-change', order.id, 'cancelled')"
					>
						<svg
							width="13"
							height="13"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							viewBox="0 0 24 24"
						>
							<circle cx="12" cy="12" r="10" />
							<line x1="15" y1="9" x2="9" y2="15" />
							<line x1="9" y1="9" x2="15" y2="15" />
						</svg>
						İptal Et
					</button>
					<button
						class="btn btn-primary btn-with-icon"
						style="flex: 1; justify-content: center"
						@click="close"
					>
						Kapat
					</button>
				</div>
			</template>
		</aside>
	</Teleport>
</template>

<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	order: { type: Object, default: null },
	statusFlow: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'status-change']);

const showToast = inject('showToast');

const progressSteps = computed(() => [
	{ key: 'new', label: 'Sipariş Alındı' },
	{ key: 'preparing', label: 'Hazırlanıyor' },
	{ key: 'shipped', label: 'Kargoda' },
	{ key: 'delivered', label: 'Teslim Edildi' },
]);

const stepIndex = computed(() => {
	if (!props.order) return -1;
	const map = {
		new: 0,
		preparing: 1,
		shipped: 2,
		delivered: 3,
		returned: 3,
		cancelled: 0,
	};
	return map[props.order.status] ?? 0;
});

const isTerminated = computed(
	() =>
		props.order?.status === 'returned' ||
		props.order?.status === 'cancelled'
);

const totalItems = computed(
	() => props.order?.items.reduce((acc, i) => acc + i.qty, 0) ?? 0
);

function close() {
	emit('update:modelValue', false);
}

function formatPrice(value) {
	return (
		'₺' +
		Number(value).toLocaleString('tr-TR', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		})
	);
}

function formatTime(at) {
	const d = new Date(at.replace(' ', 'T'));
	return d.toLocaleString('tr-TR', {
		day: '2-digit',
		month: 'short',
		hour: '2-digit',
		minute: '2-digit',
	});
}

function paymentStatusLabel(s) {
	return (
		{ paid: 'Ödendi', pending: 'Beklemede', failed: 'Başarısız' }[s] ?? s
	);
}

function paymentMethodLabel(m) {
	return (
		{
			card: 'Kredi/Banka Kartı',
			bank: 'Havale / EFT',
			cod: 'Kapıda Ödeme',
		}[m] ?? m
	);
}

function shippingMethodLabel(m) {
	return (
		{
			standard: 'Standart Kargo',
			express: 'Express Kargo',
			'same-day': 'Aynı Gün Teslimat',
		}[m] ?? m
	);
}

function channelLabel(c) {
	return { web: 'Web', mobile: 'Mobil', b2b: 'B2B' }[c] ?? c;
}

function printInvoice() {
	showToast?.({
		type: 'info',
		title: 'Fatura Yazdır',
		message: `${props.order?.orderNo} faturası hazırlanıyor.`,
	});
}
function createInvoice() {
	showToast?.({
		type: 'info',
		title: 'Fatura oluşturuluyor',
		message: `${props.order?.orderNo} faturası hazırlanıyor.`,
	});
}
function printShippingLabel() {
	showToast?.({
		type: 'info',
		title: 'Kargo Etiketi',
		message: `${props.order?.orderNo} için etiket oluşturuluyor.`,
	});
}
</script>

<style scoped>
.order-drawer {
	width: 70vw;
	max-width: calc(100vw - 24px);
}

@media (max-width: 900px) {
	.order-drawer {
		width: 100vw;
	}
}

/* ── Hero ── */
.order-hero {
	display: flex;
	justify-content: space-between;
	gap: 20px;
	padding: 14px 16px;
	background: linear-gradient(135deg, #fafafe 0%, #f4f4fb 100%);
	border: 1px solid #f0f0f5;
	border-radius: 14px;
	margin-bottom: 4px;
}

.hero-left {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.hero-meta-row {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.hero-date {
	font-size: 12.5px;
	color: #777;
}

.hero-right {
	text-align: right;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.hero-total-label {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.hero-total {
	font-size: 24px;
	font-weight: 800;
	color: #1a1a2e;
	letter-spacing: -0.01em;
	line-height: 1.1;
}

.hero-items-count {
	font-size: 11.5px;
	color: #888;
	margin-top: 2px;
}

/* ── Status badges ── */
.order-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
}
.order-status-badge .dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: currentColor;
}
.os-new {
	background: #dbeafe;
	color: #2563eb;
}
.os-preparing {
	background: #fef3c7;
	color: #b45309;
}
.os-shipped {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary-hover));
}
.os-delivered {
	background: #dcfce7;
	color: #16a34a;
}
.os-returned {
	background: #f1f5f9;
	color: #475569;
}
.os-cancelled {
	background: #fee2e2;
	color: #dc2626;
}

.payment-status {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 3px 9px;
	border-radius: 6px;
	font-size: 10.5px;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
}
.ps-paid {
	background: #f0fdf4;
	color: #16a34a;
}
.ps-pending {
	background: #fffbeb;
	color: #ca8a04;
}
.ps-failed {
	background: #fef2f2;
	color: #dc2626;
}

.channel-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 5px;
	font-size: 10px;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
}
.ch-web {
	background: rgb(var(--color-primary-soft));
	color: #2563eb;
}
.ch-mobile {
	background: #fdf2f8;
	color: #be185d;
}
.ch-b2b {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
}

/* ── Status progress ── */
.status-section {
	background: #fff;
	border: 1px solid #f0f0f5;
	border-radius: 12px;
	padding: 16px 18px;
}

.status-progress {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 0;
	position: relative;
	margin-bottom: 14px;
}

.status-step {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	position: relative;
	z-index: 1;
}

.status-step::before {
	content: '';
	position: absolute;
	top: 12px;
	left: 50%;
	width: 100%;
	height: 2px;
	background: #ebebf0;
	z-index: -1;
}
.status-step:last-child::before {
	display: none;
}
.status-step.done::before {
	background: #16a34a;
}

.status-dot {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: #f5f5fa;
	border: 2px solid #ebebf0;
	color: #888;
	font-size: 11px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
}

.status-step.done .status-dot {
	background: #16a34a;
	border-color: #16a34a;
	color: #fff;
}

.status-step.current .status-dot {
	background: #fff;
	border-color: #16a34a;
	color: #16a34a;
	box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

.status-label {
	font-size: 11px;
	font-weight: 600;
	color: #888;
	text-align: center;
	line-height: 1.3;
}
.status-step.done .status-label,
.status-step.current .status-label {
	color: #1a1a2e;
}

.status-progress.terminated {
	opacity: 0.5;
}

.status-terminated {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	color: #dc2626;
	font-size: 12.5px;
	font-weight: 700;
	background: rgba(255, 255, 255, 0.85);
	opacity: 1;
}

.status-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
	padding-top: 12px;
	border-top: 1px dashed #e8e8f0;
}

.status-actions-label {
	font-size: 11.5px;
	font-weight: 600;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.status-chips {
	display: flex;
	gap: 4px;
	flex-wrap: wrap;
}

.status-chip {
	background: #fff;
	border: 1.5px solid #e8e8f0;
	color: #555;
	font-family: inherit;
	font-size: 11px;
	font-weight: 600;
	padding: 4px 10px;
	border-radius: 999px;
	cursor: pointer;
	transition: all 0.12s;
}
.status-chip:hover:not(:disabled) {
	border-color: #c0c0d8;
}
.status-chip:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}
.status-chip.active {
	background: #1a1a2e;
	color: #fff;
	border-color: #1a1a2e;
}

/* ── Sections ── */
.drawer-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.section-sub {
	font-size: 11px;
	font-weight: 500;
	color: #aaa;
	margin-left: 8px;
	text-transform: none;
	letter-spacing: 0;
}

.detail-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}

@media (max-width: 900px) {
	.detail-grid {
		grid-template-columns: 1fr;
	}
}

/* ── Müşteri ── */
.customer-card {
	display: flex;
	gap: 14px;
	padding: 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 12px;
}

.customer-avatar {
	width: 52px;
	height: 52px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-size: 16px;
	font-weight: 700;
	flex-shrink: 0;
}

.customer-info {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}
.customer-name {
	font-size: 14px;
	font-weight: 700;
	color: #1a1a2e;
}
.customer-meta {
	font-size: 12px;
	color: #666;
}
.customer-stats {
	font-size: 11.5px;
	color: #888;
	margin-top: 4px;
}
.customer-stats strong {
	color: #1a1a2e;
	font-weight: 700;
}

.link {
	color: rgb(var(--color-primary));
	text-decoration: none;
}
.link:hover {
	text-decoration: underline;
}
.sep {
	color: #ccc;
	margin: 0 4px;
}

/* ── Order Items ── */
.items-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.order-item {
	display: grid;
	grid-template-columns: 60px 1fr auto;
	gap: 12px;
	padding: 10px 12px;
	background: #fff;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}

.order-item img {
	width: 60px;
	height: 75px;
	object-fit: cover;
	border-radius: 7px;
	background: #f5f5fa;
}

.order-item-body {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}
.oi-brand {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
.oi-name {
	font-size: 13px;
	font-weight: 600;
	color: #1a1a2e;
	line-height: 1.35;
}
.oi-meta {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 11.5px;
	color: #666;
	margin-top: 4px;
	flex-wrap: wrap;
}
.oi-meta strong {
	color: #1a1a2e;
	font-weight: 700;
}
.oi-color {
	width: 11px;
	height: 11px;
	border-radius: 50%;
	border: 1.5px solid #fff;
	box-shadow: 0 0 0 1px #e5e7eb;
}

.oi-total {
	font-size: 14px;
	font-weight: 800;
	color: #1a1a2e;
	white-space: nowrap;
	align-self: center;
}

/* Totals */
.items-totals {
	margin-top: 10px;
	padding: 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}

.it-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 12.5px;
	color: #555;
	margin-bottom: 5px;
}
.it-row strong {
	color: #1a1a2e;
	font-weight: 700;
}
.it-row.it-total {
	margin: 8px 0 0;
	padding-top: 10px;
	border-top: 1px dashed #d8d8e8;
	font-size: 14px;
}
.it-row.it-total strong {
	font-size: 16px;
	font-weight: 800;
}

.text-success {
	color: #16a34a !important;
}

/* ── KV blocks ── */
.kv-block {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}

.kv-row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	padding: 5px 0;
	font-size: 12.5px;
}

.kv-label {
	color: #888;
	font-weight: 500;
	flex-shrink: 0;
}

.kv-value {
	color: #1a1a2e;
	font-weight: 600;
	text-align: right;
	max-width: 65%;
}

.addr-tag {
	display: inline-block;
	padding: 2px 8px;
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	border-radius: 5px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-bottom: 4px;
}

.addr-text {
	font-size: 12px;
	color: #444;
	font-weight: 500;
	line-height: 1.55;
}

.mono {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 11.5px;
}

.pay-method-row {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.pay-icon {
	width: 22px;
	height: 22px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}
.pay-card {
	background: rgb(var(--color-primary-soft));
	color: #2563eb;
}
.pay-bank {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
}
.pay-cod {
	background: #fffbeb;
	color: #ca8a04;
}

/* ── Timeline ── */
.timeline {
	list-style: none;
	padding: 0;
	margin: 0;
	position: relative;
}

.timeline::before {
	content: '';
	position: absolute;
	left: 16px;
	top: 8px;
	bottom: 8px;
	width: 2px;
	background: #ebebf0;
}

.timeline-item {
	display: grid;
	grid-template-columns: 34px 1fr;
	gap: 10px;
	padding: 8px 0;
	position: relative;
}

.tl-dot {
	width: 34px;
	height: 34px;
	border-radius: 50%;
	background: #fff;
	border: 2px solid #ebebf0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
	z-index: 1;
	position: relative;
}

.timeline-item:first-child .tl-dot {
	border-color: #16a34a;
	background: #f0fdf4;
}

.tl-cancelled .tl-dot,
.tl-failed .tl-dot {
	border-color: #dc2626;
	background: #fef2f2;
}
.tl-return-requested .tl-dot,
.tl-return .tl-dot {
	border-color: #f59e0b;
	background: #fffbeb;
}

.tl-body {
	padding-top: 4px;
}
.tl-label {
	font-size: 12.5px;
	font-weight: 600;
	color: #1a1a2e;
	line-height: 1.45;
}
.tl-time {
	font-size: 11px;
	color: #888;
	margin-top: 2px;
}

/* ── Notes ── */
.notes-box {
	display: flex;
	gap: 10px;
	padding: 12px 14px;
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
}
.notes-box svg {
	color: #ca8a04;
	flex-shrink: 0;
	margin-top: 2px;
}
.notes-box p {
	font-size: 12.5px;
	color: #78350f;
	line-height: 1.55;
	margin: 0;
}

/* ── Footer ── */
.drawer-footer {
	display: flex;
	gap: 8px;
}

.drawer-header-actions {
	display: flex;
	align-items: center;
	gap: 6px;
}

.drawer-edit-btn {
	width: 30px;
	height: 30px;
	border-radius: 8px;
	border: 1.5px solid #e8e8f2;
	background: #fff;
	color: rgb(var(--color-primary));
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.15s;
}
.drawer-edit-btn:hover {
	background: rgb(var(--color-primary-soft));
	border-color: #c0c8f8;
}

.danger-text {
	color: #dc2626 !important;
}
.danger-text:hover {
	background: #fef2f2 !important;
}
</style>
