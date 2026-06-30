<template>
	<Teleport to="body">
		<aside
			class="drawer supplier-drawer"
			:class="{ open: modelValue }"
			role="dialog"
			aria-modal="true"
		>
			<template v-if="supplier">
				<div class="drawer-header">
					<div class="drawer-title">
						<div class="drawer-title-icon">
							<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<rect x="3" y="3" width="18" height="18" rx="2" />
								<path d="M3 9h18M9 21V9" />
							</svg>
						</div>
						<div>
							<h4>Tedarikçi Detayı</h4>
							<p>{{ supplier.shortName || supplier.name }}</p>
						</div>
					</div>
					<div class="drawer-header-actions">
						<a
							class="drawer-edit-btn"
							:href="`mailto:${supplier.email}`"
							title="E-posta gönder"
							aria-label="E-posta gönder"
						>
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
								<polyline points="22,6 12,13 2,6" />
							</svg>
						</a>
						<button class="drawer-close" @click="close" aria-label="Kapat">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
								<path d="M18 6L6 18M6 6l12 12" />
							</svg>
						</button>
					</div>
				</div>

				<div class="drawer-body">
					<!-- Profil özeti -->
					<div class="supplier-profile">
						<div class="supplier-profile-avatar" :style="{ background: supplier.avatarGradient }">
							{{ supplier.initials }}
						</div>
						<div class="supplier-profile-name">{{ supplier.name }}</div>
						<div class="supplier-profile-sub">
							<span class="category-badge" :class="`cat-${supplier.categoryClass}`">{{ supplier.category }}</span>
							<span class="supplier-status-badge" :class="`supplier-status-${supplier.statusClass}`">{{ supplier.statusText }}</span>
						</div>
						<div class="supplier-profile-rating" :title="`${supplier.rating} / 5`">
							<svg
								v-for="n in 5"
								:key="n"
								width="14"
								height="14"
								:fill="n <= supplier.rating ? '#f59e0b' : 'none'"
								:stroke="n <= supplier.rating ? '#f59e0b' : '#dcdce6'"
								stroke-width="1.8"
								viewBox="0 0 24 24"
							>
								<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
							</svg>
							<span class="rating-text">{{ supplier.rating }} / 5</span>
						</div>
					</div>

					<!-- Aktivite özeti -->
					<section class="drawer-section">
						<div class="drawer-section-title">Aktivite Özeti</div>
						<div class="stat-row">
							<div class="mini-stat">
								<div class="mini-stat-value">{{ supplier.totalOrders }}</div>
								<div class="mini-stat-label">Toplam Sipariş</div>
							</div>
							<div class="mini-stat">
								<div class="mini-stat-value mini-stat-money">{{ supplier.totalSpend }}</div>
								<div class="mini-stat-label">Toplam Ciro</div>
							</div>
							<div class="mini-stat" :class="{ 'mini-stat-danger': supplier.pendingPayment !== '₺0' }">
								<div class="mini-stat-value mini-stat-money">{{ supplier.pendingPayment }}</div>
								<div class="mini-stat-label">Bekleyen Ödeme</div>
							</div>
							<div class="mini-stat">
								<div class="mini-stat-value">{{ supplier.lastOrderDate }}</div>
								<div class="mini-stat-label">Son Sipariş</div>
							</div>
						</div>
					</section>

					<!-- 2 sütunlu bilgi alanı -->
					<div class="detail-grid">
						<!-- Genel bilgiler -->
						<section class="drawer-section">
							<div class="drawer-section-title">Genel Bilgiler</div>
							<dl class="info-list">
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
										</svg>
										Kategori
									</dt>
									<dd>{{ supplier.category }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<rect x="3" y="4" width="18" height="18" rx="2" />
											<line x1="16" y1="2" x2="16" y2="6" />
											<line x1="8" y1="2" x2="8" y2="6" />
											<line x1="3" y1="10" x2="21" y2="10" />
										</svg>
										Çalışma Başlangıcı
									</dt>
									<dd>{{ supplier.startedAt }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<circle cx="12" cy="12" r="10" />
											<polyline points="12 6 12 12 16 14" />
										</svg>
										Ödeme Vadesi
									</dt>
									<dd>{{ supplier.paymentTerm }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<rect x="3" y="11" width="18" height="11" rx="2" />
											<path d="M7 11V7a5 5 0 0110 0v4" />
										</svg>
										Tedarikçi ID
									</dt>
									<dd class="mono">#{{ String(supplier.id).padStart(4, '0') }}</dd>
								</div>
							</dl>
						</section>

						<!-- İletişim -->
						<section class="drawer-section">
							<div class="drawer-section-title">İletişim</div>
							<dl class="info-list">
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
											<circle cx="12" cy="7" r="4" />
										</svg>
										Yetkili
									</dt>
									<dd>{{ supplier.contactName }} <span class="muted">· {{ supplier.contactRole }}</span></dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
											<polyline points="22,6 12,13 2,6" />
										</svg>
										E-posta
									</dt>
									<dd>
										<a :href="`mailto:${supplier.email}`" class="link">{{ supplier.email }}</a>
									</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
										</svg>
										Telefon
									</dt>
									<dd>
										<a :href="`tel:${supplier.phone.replace(/\s/g, '')}`" class="link">{{ supplier.phone }}</a>
									</dd>
								</div>
								<div class="info-row info-row-multi">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
											<circle cx="12" cy="10" r="3" />
										</svg>
										Adres
									</dt>
									<dd class="multi-line">{{ supplier.address }}</dd>
								</div>
							</dl>
						</section>

						<!-- Vergi & Bankacılık -->
						<section class="drawer-section drawer-section-wide">
							<div class="drawer-section-title">Vergi & Bankacılık</div>
							<dl class="info-list info-list-grid">
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2zM22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
										</svg>
										Vergi Dairesi
									</dt>
									<dd>{{ supplier.taxOffice }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<rect x="3" y="3" width="18" height="18" rx="2" />
											<line x1="9" y1="9" x2="9.01" y2="9" />
											<line x1="15" y1="15" x2="15.01" y2="15" />
											<line x1="15" y1="9" x2="9" y2="15" />
										</svg>
										Vergi No
									</dt>
									<dd class="mono">{{ supplier.taxNumber }}</dd>
								</div>
								<div class="info-row info-row-multi">
									<dt>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<rect x="2" y="5" width="20" height="14" rx="2" />
											<line x1="2" y1="10" x2="22" y2="10" />
										</svg>
										IBAN
									</dt>
									<dd class="mono iban-value">{{ supplier.iban }}</dd>
								</div>
							</dl>
						</section>
					</div>

					<!-- Son siparişler -->
					<section class="drawer-section" v-if="recentOrders.length">
						<div class="drawer-section-title">
							Son Siparişler
							<span class="section-sub">son 5 hareket</span>
						</div>
						<div class="orders-card">
							<table class="orders-table">
								<thead>
									<tr>
										<th>Sipariş</th>
										<th>Tarih</th>
										<th>Ürün</th>
										<th style="text-align: right">Tutar</th>
										<th>Durum</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="o in recentOrders" :key="o.id">
										<td class="mono">{{ o.id }}</td>
										<td class="dim">{{ o.date }}</td>
										<td>{{ o.item }}</td>
										<td style="text-align: right"><strong>{{ o.amount }}</strong></td>
										<td>
											<span class="order-status" :class="`order-${o.statusClass}`">{{ o.statusText }}</span>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</section>

					<!-- Notlar -->
					<section class="drawer-section" v-if="supplier.notes">
						<div class="drawer-section-title">Notlar</div>
						<div class="notes-box">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
								<polyline points="14 2 14 8 20 8" />
								<line x1="16" y1="13" x2="8" y2="13" />
								<line x1="16" y1="17" x2="8" y2="17" />
							</svg>
							<p>{{ supplier.notes }}</p>
						</div>
					</section>
				</div>

				<div class="drawer-footer">
					<a class="btn btn-secondary" :href="`mailto:${supplier.email}`">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
							<polyline points="22,6 12,13 2,6" />
						</svg>
						İletişime Geç
					</a>
					<button class="btn btn-primary" style="flex: 1; justify-content: center" @click="close">Kapat</button>
				</div>
			</template>
		</aside>
	</Teleport>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	supplier: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

function close() {
	emit('update:modelValue', false)
}

// Tedarikçi id'sinden türetilmiş sahte son siparişler.
// Backend bağlandığında bu computed yerine props.supplier.recentOrders kullanılır.
const recentOrders = computed(() => {
	if (!props.supplier) return []
	const seed = props.supplier.id || 1
	const cat = props.supplier.category
	const itemsByCategory = {
		Kumaş: ['Pamuk Dokuma 180g', 'Penye Süprem 220g', 'Polyester Şifon 90g', 'Keten Karışım 240g'],
		İplik: ['Penye İplik Ne 30/1', 'Polyester İplik 150D', 'Akrilik İplik Ne 24/2', 'Pamuk İplik Ne 40/1'],
		Boya: ['Reaktif Boya - Mavi', 'Direkt Boya - Lacivert', 'Asit Boya - Bordo', 'Pigment - Beyaz'],
		Aksesuar: ['Düğme - Metal 18mm', 'Fermuar - 60cm Spiral', 'Etiket - Saten 30x60', 'Kart - Asma 5x10'],
		Lojistik: ['Yurtiçi Sevkiyat', 'Avrupa Sevkiyat', 'Depo Hizmeti', 'Ekspres Teslimat'],
		Kimyasal: ['Apre Maddesi 25kg', 'Yumuşatıcı 50L', 'Anti-statik 30kg', 'Su İticisi 20L'],
	}
	const items = itemsByCategory[cat] || ['Ürün']
	const statuses = [
		{ statusClass: 'delivered', statusText: 'Teslim' },
		{ statusClass: 'delivered', statusText: 'Teslim' },
		{ statusClass: 'delivered', statusText: 'Teslim' },
		{ statusClass: 'partial', statusText: 'Kısmi' },
		{ statusClass: 'delivered', statusText: 'Teslim' },
	]
	const days = ['11.05', '02.05', '18.04', '03.04', '22.03']
	return days.map((d, i) => ({
		id: 'SAP-' + (2026100 + seed * 47 - i * 5),
		date: `${d}.2026`,
		item: items[(seed + i) % items.length],
		amount: '₺' + (((seed * 13 + i * 7) % 200 + 15) * 1000).toLocaleString('tr-TR'),
		statusClass: statuses[i].statusClass,
		statusText: statuses[i].statusText,
	}))
})

// body.overflow yönetimi parent'a (Suppliers.vue) ait.
</script>

<style scoped>
.supplier-drawer {
	width: 64vw;
	max-width: calc(100vw - 24px);
}

/* Profil özeti */
.supplier-profile {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 8px 0 16px;
	border-bottom: 1px solid #f0f0f6;
	margin: -4px 0 4px;
}

.supplier-profile-avatar {
	width: 78px;
	height: 78px;
	border-radius: 18px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 24px;
	font-weight: 700;
	color: #fff;
	margin-bottom: 10px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.supplier-profile-name {
	font-size: 16px;
	font-weight: 700;
	color: #1a1a2e;
	text-align: center;
}

.supplier-profile-sub {
	display: flex;
	gap: 6px;
	margin-top: 6px;
}

.supplier-profile-rating {
	display: flex;
	align-items: center;
	gap: 3px;
	margin-top: 8px;
}
.rating-text {
	margin-left: 6px;
	font-size: 12px;
	color: #888;
	font-weight: 500;
}

/* Kategori badge */
.category-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 6px;
	font-size: 11px;
	font-weight: 600;
}
.cat-fabric    { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.cat-yarn      { background: #e0f2fe; color: #0284c7; }
.cat-dye       { background: #fce7f3; color: #be185d; }
.cat-accessory { background: #f0fdf4; color: #16a34a; }
.cat-logistics { background: #fff7ed; color: #ea580c; }
.cat-chemical  { background: #f1f5f9; color: #475569; }

.supplier-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
}
.supplier-status-badge::before {
	content: '';
	width: 5px; height: 5px;
	border-radius: 50%;
	background: currentColor;
}
.supplier-status-active      { background: #dcfce7; color: #16a34a; }
.supplier-status-on-hold     { background: #fef9c3; color: #ca8a04; }
.supplier-status-blacklisted { background: #fee2e2; color: #dc2626; }

/* 2 sütunlu grid */
.detail-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.drawer-section-wide {
	grid-column: 1 / -1;
}

@media (max-width: 900px) {
	.detail-grid { grid-template-columns: 1fr; }
}

.section-sub {
	font-size: 11px;
	font-weight: 500;
	color: #aaa;
	margin-left: 8px;
	text-transform: none;
	letter-spacing: 0;
}

/* Drawer header sağ aksiyon */
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
	transition: background .15s, border-color .15s, color .15s;
	flex-shrink: 0;
	text-decoration: none;
}
.drawer-edit-btn:hover {
	background: rgb(var(--color-primary-soft));
	border-color: #c0c8f8;
	color: rgb(var(--color-primary-hover));
}

/* Drawer section */
.drawer-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

/* Info list */
.info-list {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.info-list-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 4px;
}
.info-list-grid .info-row-multi { grid-column: 1 / -1; }

.info-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin: 2px 0;
	padding: 8px 10px;
	border-radius: 9px;
	background: #fafafe;
	border: 1px solid #f0f0f6;
}

.info-row-multi {
	flex-direction: column;
	align-items: flex-start;
	gap: 6px;
}
.info-row-multi dd { text-align: left !important; }

.info-row dt {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12.5px;
	color: #888;
	font-weight: 500;
}
.info-row dt svg { color: #aaa; flex-shrink: 0; }

.info-row dd {
	font-size: 13px;
	color: #1a1a2e;
	font-weight: 600;
	text-align: right;
}

.info-row .multi-line {
	line-height: 1.5;
	font-weight: 500;
}

.muted { color: #888; font-weight: 500; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.iban-value { font-size: 12.5px; letter-spacing: 0.02em; }

.link { color: rgb(var(--color-primary)); text-decoration: none; }
.link:hover { text-decoration: underline; }

/* Mini stats */
.stat-row {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 10px;
}

@media (max-width: 900px) {
	.stat-row { grid-template-columns: repeat(2, 1fr); }
}

.mini-stat {
	background: #fafafe;
	border: 1px solid #f0f0f6;
	border-radius: 10px;
	padding: 14px 10px;
	text-align: center;
}

.mini-stat-danger {
	background: #fef2f2;
	border-color: #fecaca;
}
.mini-stat-danger .mini-stat-value { color: #dc2626; }

.mini-stat-value {
	font-size: 18px;
	font-weight: 800;
	color: #1a1a2e;
	line-height: 1.15;
}

.mini-stat-money { font-size: 16px; }

.mini-stat-label {
	font-size: 10.5px;
	color: #888;
	font-weight: 500;
	margin-top: 4px;
}

/* Orders */
.orders-card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 12px;
	overflow: hidden;
}

.orders-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
}

.orders-table thead tr { background: #fafafe; }

.orders-table th {
	text-align: left;
	padding: 9px 12px;
	font-size: 10.5px;
	font-weight: 600;
	color: #aaa;
	border-bottom: 1px solid #f0f0f5;
	text-transform: uppercase;
	letter-spacing: .04em;
}

.orders-table td {
	padding: 10px 12px;
	font-size: 12.5px;
	color: #444;
	border-bottom: 1px solid #f5f5f8;
}

.orders-table tr:last-child td { border-bottom: none; }

.order-status {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 10.5px;
	font-weight: 600;
}
.order-delivered { background: #dcfce7; color: #16a34a; }
.order-partial   { background: #fef9c3; color: #ca8a04; }
.order-pending   { background: #dbeafe; color: #2563eb; }
.order-canceled  { background: #fee2e2; color: #dc2626; }

.dim { color: #888; }

/* Notes */
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

.drawer-footer { display: flex; gap: 8px; }
.drawer-footer .btn { flex: 1; justify-content: center; }
</style>
