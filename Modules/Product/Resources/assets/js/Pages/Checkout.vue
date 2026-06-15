<template>
	<Head title="Ödeme" />
	<div class="page-checkout">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Sepet' },
				{ label: 'Ödeme' },
			]"
		/>

		<!-- Boş sepet durumu -->
		<div v-if="!orderPlaced && cartItems.length === 0" class="empty-cart-card">
			<div class="empty-icon-lg">
				<svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
					<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
					<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
				</svg>
			</div>
			<h2>Sepetinizde ürün yok</h2>
			<p>Ödeme adımına geçmek için önce sepete ürün ekleyin.</p>
			<Link href="/products" class="btn btn-primary btn-with-icon">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" />
				</svg>
				Kataloğa Göz At
			</Link>
		</div>

		<!-- Başarı durumu -->
		<div v-else-if="orderPlaced && placedOrder" class="success-card">
			<div class="success-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
					<circle cx="12" cy="12" r="10" />
					<polyline points="9 12 11 14 15 10" />
				</svg>
			</div>
			<h1>Siparişiniz Alındı!</h1>
			<div class="success-order-no">Sipariş No: <span class="mono">{{ placedOrder.orderNumber }}</span></div>
			<p class="success-msg">
				Onay e-postası <strong>{{ userEmail }}</strong> adresine gönderildi.<br />
				Siparişiniz <strong>{{ placedOrder.eta }}</strong> tarihinde teslim edilecek.
			</p>

			<div class="success-actions">
				<Link href="/products" class="btn btn-secondary btn-with-icon">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<polyline points="15 18 9 12 15 6" />
					</svg>
					Alışverişe Devam
				</Link>
				<button class="btn btn-primary btn-with-icon" @click="trackOrder">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
						<circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
					</svg>
					Siparişi Takip Et
				</button>
			</div>

			<div class="success-summary">
				<div class="success-summary-title">Sipariş Özeti</div>
				<div class="success-items">
					<div v-for="item in placedOrder.items" :key="item.key" class="success-item">
						<img :src="item.image" :alt="item.name" />
						<div class="si-body">
							<div class="si-brand">{{ item.brand }}</div>
							<div class="si-name">{{ item.name }}</div>
							<div class="si-meta">
								<span v-if="item.size">Beden {{ item.size }}</span>
								<span v-if="item.size && item.color">·</span>
								<span v-if="item.color" class="si-color" :style="{ background: item.color }"></span>
								<span>· {{ item.qty }} adet</span>
							</div>
						</div>
						<div class="si-price">{{ formatPrice(item.price * item.qty) }}</div>
					</div>
				</div>
				<div class="success-totals">
					<div><span>Ara Toplam</span> <strong>{{ formatPrice(placedOrder.subtotal) }}</strong></div>
					<div><span>Kargo</span> <strong>{{ placedOrder.shippingFee > 0 ? formatPrice(placedOrder.shippingFee) : 'Ücretsiz' }}</strong></div>
					<div v-if="placedOrder.discount > 0"><span>İndirim</span> <strong class="text-success">−{{ formatPrice(placedOrder.discount) }}</strong></div>
					<div class="ts-total"><span>Toplam</span> <strong>{{ formatPrice(placedOrder.total) }}</strong></div>
				</div>
			</div>
		</div>

		<!-- Form -->
		<div v-else class="checkout-layout">
			<!-- Sol kolon: form -->
			<div class="checkout-main">
				<header class="checkout-header">
					<h1>Ödeme</h1>
					<p>Bilgilerinizi kontrol edip siparişi tamamlayın</p>
				</header>

				<!-- 1. Teslimat Adresi -->
				<section class="form-section">
					<div class="section-head">
						<span class="step-no">1</span>
						<h2>Teslimat Adresi</h2>
					</div>
					<div class="address-grid">
						<label
							v-for="addr in addressList"
							:key="addr.id"
							class="address-card"
							:class="{ active: selectedAddressId === addr.id }"
						>
							<input type="radio" :value="addr.id" v-model="selectedAddressId" />
							<div class="addr-head">
								<span class="addr-label">{{ addr.label }}</span>
								<div class="addr-actions">
									<svg
										v-if="selectedAddressId === addr.id"
										class="addr-check"
										width="14" height="14" fill="#16a34a" viewBox="0 0 24 24"
									>
										<path stroke="#fff" stroke-width="2" fill="#16a34a" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
									<button
										type="button"
										class="addr-action-btn"
										title="Düzenle"
										@click.prevent.stop="openEditAddress(addr)"
									>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M12 20h9" />
											<path d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z" />
										</svg>
									</button>
									<button
										v-if="addressList.length > 1"
										type="button"
										class="addr-action-btn addr-action-danger"
										title="Sil"
										@click.prevent.stop="deleteAddress(addr)"
									>
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<polyline points="3 6 5 6 21 6" />
											<path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
											<path d="M10 11v6M14 11v6" />
											<path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
										</svg>
									</button>
								</div>
							</div>
							<div class="addr-name">{{ addr.name }}</div>
							<div class="addr-phone">{{ addr.phone }}</div>
							<div class="addr-text">
								{{ addr.street }}<br />
								{{ addr.district }} / {{ addr.city }} · {{ addr.postalCode }}
							</div>
						</label>
						<button class="address-card address-add" @click="openAddAddress">
							<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M12 5v14M5 12h14" />
							</svg>
							<span>Yeni Adres Ekle</span>
						</button>
					</div>
				</section>

				<!-- 2. Teslimat Yöntemi -->
				<section class="form-section">
					<div class="section-head">
						<span class="step-no">2</span>
						<h2>Teslimat Yöntemi</h2>
					</div>
					<div class="ship-list">
						<label
							v-for="m in shippingMethods"
							:key="m.id"
							class="ship-card"
							:class="{ active: selectedShippingId === m.id }"
						>
							<input type="radio" :value="m.id" v-model="selectedShippingId" />
							<div class="ship-radio"></div>
							<div class="ship-info">
								<div class="ship-line">
									<span class="ship-label">{{ m.label }}</span>
									<span class="ship-eta">· {{ m.eta }}</span>
								</div>
								<div class="ship-desc">{{ m.description }}</div>
							</div>
							<div class="ship-price" :class="{ free: m.price === 0 }">{{ m.priceLabel }}</div>
						</label>
					</div>
				</section>

				<!-- 3. Fatura Bilgisi -->
				<section class="form-section">
					<div class="section-head">
						<span class="step-no">3</span>
						<h2>Fatura Bilgisi</h2>
					</div>
					<div class="billing-tabs">
						<button
							class="bill-tab"
							:class="{ active: billingType === 'individual' }"
							@click="billingType = 'individual'"
						>Bireysel</button>
						<button
							class="bill-tab"
							:class="{ active: billingType === 'company' }"
							@click="billingType = 'company'"
						>Kurumsal</button>
					</div>
					<label class="check-line">
						<input type="checkbox" v-model="billingSameAsShipping" />
						<span>Fatura adresi teslimat adresi ile aynı</span>
					</label>
					<div v-if="billingType === 'company'" class="company-fields">
						<div class="field">
							<label>Firma Ünvanı</label>
							<input v-model="billing.companyName" class="form-input" type="text" placeholder="Firma adı" />
						</div>
						<div class="field">
							<label>Vergi Dairesi</label>
							<input v-model="billing.taxOffice" class="form-input" type="text" placeholder="Vergi dairesi" />
						</div>
						<div class="field">
							<label>Vergi No / TCKN</label>
							<input v-model="billing.taxNumber" class="form-input" type="text" placeholder="10-11 haneli" maxlength="11" />
						</div>
					</div>
				</section>

				<!-- 4. Ödeme Yöntemi -->
				<section class="form-section">
					<div class="section-head">
						<span class="step-no">4</span>
						<h2>Ödeme Yöntemi</h2>
					</div>
					<div class="pay-tabs">
						<button
							class="pay-tab"
							:class="{ active: paymentMethod === 'card' }"
							@click="paymentMethod = 'card'"
						>
							<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<rect x="2" y="5" width="20" height="14" rx="2" /><line x1="2" y1="10" x2="22" y2="10" />
							</svg>
							Kredi / Banka Kartı
						</button>
						<button
							class="pay-tab"
							:class="{ active: paymentMethod === 'bank' }"
							@click="paymentMethod = 'bank'"
						>
							<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<line x1="3" y1="21" x2="21" y2="21" />
								<line x1="3" y1="10" x2="21" y2="10" />
								<polyline points="5 6 12 3 19 6" />
								<line x1="4" y1="10" x2="4" y2="21" /><line x1="20" y1="10" x2="20" y2="21" />
								<line x1="8" y1="14" x2="8" y2="17" /><line x1="12" y1="14" x2="12" y2="17" /><line x1="16" y1="14" x2="16" y2="17" />
							</svg>
							Havale / EFT
						</button>
						<button
							class="pay-tab"
							:class="{ active: paymentMethod === 'cod' }"
							@click="paymentMethod = 'cod'"
						>
							<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
								<circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
							</svg>
							Kapıda Ödeme
						</button>
					</div>

					<!-- Kart -->
					<div v-if="paymentMethod === 'card'" class="pay-body">
						<div v-if="savedCards.length" class="saved-cards">
							<button
								v-for="card in savedCards"
								:key="card.id"
								class="saved-card"
								:class="{ active: selectedSavedCardId === card.id }"
								@click="selectSavedCard(card.id)"
							>
								<div class="sc-brand" :class="`sc-${card.brand.toLowerCase()}`">{{ card.brand }}</div>
								<div class="sc-num">•••• {{ card.last4 }}</div>
								<div class="sc-exp">SKT {{ card.expiry }}</div>
							</button>
							<button
								class="saved-card saved-card-new"
								:class="{ active: selectedSavedCardId === null }"
								@click="selectedSavedCardId = null"
							>
								<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<path d="M12 5v14M5 12h14" />
								</svg>
								<span>Yeni Kart</span>
							</button>
						</div>

						<div v-if="selectedSavedCardId === null" class="card-form">
							<div class="card-visual">
								<div class="card-chip"></div>
								<div class="card-brand-mark">{{ detectedBrand || '••••' }}</div>
								<div class="card-number">{{ displayCardNumber }}</div>
								<div class="card-foot">
									<div>
										<div class="card-label">KART SAHİBİ</div>
										<div class="card-value">{{ newCard.holder || 'Ad Soyad' }}</div>
									</div>
									<div>
										<div class="card-label">SKT</div>
										<div class="card-value">{{ newCard.expiry || 'AA/YY' }}</div>
									</div>
								</div>
							</div>

							<div class="card-fields">
								<div class="field field-wide">
									<label>Kart Numarası</label>
									<input
										:value="newCard.number"
										@input="onCardNumberInput"
										class="form-input mono-input"
										type="text"
										inputmode="numeric"
										placeholder="0000 0000 0000 0000"
										maxlength="19"
									/>
								</div>
								<div class="field">
									<label>Son Kullanma</label>
									<input
										:value="newCard.expiry"
										@input="onExpiryInput"
										class="form-input mono-input"
										type="text"
										inputmode="numeric"
										placeholder="AA/YY"
										maxlength="5"
									/>
								</div>
								<div class="field">
									<label>CVV</label>
									<input
										v-model="newCard.cvv"
										class="form-input mono-input"
										type="text"
										inputmode="numeric"
										placeholder="000"
										maxlength="4"
									/>
								</div>
								<div class="field field-wide">
									<label>Kart Üzerindeki İsim</label>
									<input
										v-model="newCard.holder"
										class="form-input"
										type="text"
										placeholder="Ad Soyad"
									/>
								</div>
							</div>

							<label class="check-line">
								<input type="checkbox" v-model="saveCard" />
								<span>Bu kartı sonraki alışverişler için kaydet</span>
							</label>
						</div>

						<div class="installments">
							<label>Taksit Seçenekleri</label>
							<div class="install-grid">
								<button
									v-for="plan in installmentPlans"
									:key="plan.count"
									class="install-card"
									:class="{ active: installmentCount === plan.count }"
									@click="installmentCount = plan.count"
								>
									<div class="ic-count">{{ plan.label }}</div>
									<div class="ic-amount">{{ formatPrice(installmentAmount(plan)) }}</div>
									<div class="ic-note">
										<span v-if="plan.rate === 0" class="ic-free">Vade farksız</span>
										<span v-else>+ %{{ plan.rate.toFixed(1) }}</span>
									</div>
								</button>
							</div>
						</div>
					</div>

					<!-- Havale -->
					<div v-else-if="paymentMethod === 'bank'" class="pay-body bank-body">
						<div class="bank-info">
							<svg width="18" height="18" fill="none" stroke="rgb(var(--color-primary))" stroke-width="2" viewBox="0 0 24 24">
								<circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />
							</svg>
							<div>
								Aşağıdaki hesaplardan herhangi birine ödeme yapıp dekontu siparişe ekleyebilirsiniz.
								Ödemeniz 1 iş günü içinde teyit edilecektir.
							</div>
						</div>
						<div class="iban-list">
							<div class="iban-row">
								<div class="iban-bank">Garanti BBVA</div>
								<div class="iban-no mono">TR12 0006 2000 1234 0006 8888 99</div>
								<button class="iban-copy" @click="copyIban('TR12 0006 2000 1234 0006 8888 99')">Kopyala</button>
							</div>
							<div class="iban-row">
								<div class="iban-bank">İş Bankası</div>
								<div class="iban-no mono">TR45 0006 4000 0011 5678 9012 34</div>
								<button class="iban-copy" @click="copyIban('TR45 0006 4000 0011 5678 9012 34')">Kopyala</button>
							</div>
						</div>
					</div>

					<!-- Kapıda Ödeme -->
					<div v-else class="pay-body cod-body">
						<div class="cod-info">
							<svg width="18" height="18" fill="none" stroke="#ca8a04" stroke-width="2" viewBox="0 0 24 24">
								<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
								<line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
							</svg>
							<div>
								Kapıda ödeme için <strong>+₺15 hizmet bedeli</strong> uygulanır.
								Teslimat sırasında nakit veya kart ile ödeyebilirsiniz.
							</div>
						</div>
					</div>
				</section>

				<!-- 5. Onay -->
				<label class="terms-check">
					<input type="checkbox" v-model="termsAccepted" />
					<span>
						<a href="#" @click.prevent.stop="openLegal('preliminary')" class="text-link">Ön Bilgilendirme Formu</a> ve
						<a href="#" @click.prevent.stop="openLegal('distance')" class="text-link">Mesafeli Satış Sözleşmesi</a>'ni
						okudum, onaylıyorum.
					</span>
				</label>
			</div>

			<!-- Sağ kolon: özet -->
			<aside class="checkout-summary">
				<div class="summary-card">
					<div class="summary-head">
						<h3>Sipariş Özeti</h3>
						<span class="summary-count">{{ totalItems }} ürün</span>
					</div>

					<div class="summary-items">
						<div v-for="item in cartItems" :key="item.key" class="su-item">
							<div class="su-image">
								<img :src="item.image" :alt="item.name" />
								<span class="su-qty">{{ item.qty }}</span>
							</div>
							<div class="su-body">
								<div class="su-name">{{ item.name }}</div>
								<div class="su-meta">
									<span v-if="item.color" class="su-color" :style="{ background: item.color }"></span>
									<span v-if="item.size">{{ item.size }}</span>
								</div>
							</div>
							<div class="su-price">{{ formatPrice(item.price * item.qty) }}</div>
						</div>
					</div>

					<div class="promo">
						<input
							v-model="promoCode"
							class="form-input"
							type="text"
							placeholder="İndirim kodu"
							:disabled="!!promoApplied"
							@keyup.enter="applyPromo"
						/>
						<button
							class="btn btn-secondary btn-sm promo-btn"
							:disabled="!promoCode && !promoApplied"
							@click="promoApplied ? clearPromo() : applyPromo()"
						>
							{{ promoApplied ? 'Kaldır' : 'Uygula' }}
						</button>
					</div>
					<div v-if="promoApplied" class="promo-applied">
						<svg width="13" height="13" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="20 6 9 17 4 12" />
						</svg>
						<strong>{{ promoApplied.code }}</strong> uygulandı · −{{ formatPrice(promoApplied.discount) }}
					</div>

					<div class="sum-rows">
						<div class="sum-row">
							<span>Ara Toplam</span>
							<strong>{{ formatPrice(subtotal) }}</strong>
						</div>
						<div class="sum-row">
							<span>Kargo</span>
							<strong v-if="shippingFee === 0" class="text-success">Ücretsiz</strong>
							<strong v-else>{{ formatPrice(shippingFee) }}</strong>
						</div>
						<div v-if="codFee > 0" class="sum-row">
							<span>Kapıda Ödeme</span>
							<strong>{{ formatPrice(codFee) }}</strong>
						</div>
						<div v-if="installmentSurcharge > 0" class="sum-row">
							<span>Taksit farkı</span>
							<strong>{{ formatPrice(installmentSurcharge) }}</strong>
						</div>
						<div v-if="promoApplied" class="sum-row">
							<span>İndirim</span>
							<strong class="text-success">−{{ formatPrice(promoApplied.discount) }}</strong>
						</div>
					</div>

					<div class="sum-total">
						<span>Toplam</span>
						<strong>{{ formatPrice(grandTotal) }}</strong>
					</div>
					<div class="vat-note">KDV dahil</div>

					<button
						class="btn btn-primary btn-lg place-order-btn"
						:class="{ 'btn-loading': placing }"
						:disabled="!canPlaceOrder || placing"
						@click="placeOrder"
					>
						<span v-if="placing" class="btn-spinner"></span>
						<svg v-else width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<rect x="3" y="11" width="18" height="11" rx="2" />
							<path d="M7 11V7a5 5 0 0110 0v4" />
						</svg>
						{{ placing ? 'Siparişiniz oluşturuluyor…' : 'Siparişi Tamamla' }}
					</button>

					<div class="trust">
						<div class="trust-item">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<rect x="3" y="11" width="18" height="11" rx="2" />
								<path d="M7 11V7a5 5 0 0110 0v4" />
							</svg>
							SSL korumalı ödeme
						</div>
						<div class="trust-item">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
							</svg>
							3D Secure desteği
						</div>
					</div>
				</div>
			</aside>
		</div>

		<!-- Yasal belge modal'ı -->
		<AppModal
			v-model="legalModalOpen"
			size="lg"
			variant="info"
			:title="legalDocMeta.title"
			:subtitle="legalDocMeta.subtitle"
		>
			<div class="legal-doc">
				<!-- Ön Bilgilendirme Formu -->
				<template v-if="legalDocType === 'preliminary'">
					<div class="legal-meta">
						<div class="legal-meta-row">
							<span class="legal-meta-label">Düzenleme Tarihi</span>
							<strong>{{ todayLabel }}</strong>
						</div>
						<div class="legal-meta-row">
							<span class="legal-meta-label">Sürüm</span>
							<strong>v2.4 · TKHK m. 48 uyarınca</strong>
						</div>
					</div>

					<p class="legal-intro">
						İşbu Ön Bilgilendirme Formu, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve
						27.11.2014 tarihli Mesafeli Sözleşmeler Yönetmeliği kapsamında, Alıcı'nın siparişine
						konu mal/hizmetin temel nitelikleri, satış fiyatı, ödeme ve teslimat şartları
						ile cayma hakkı konusunda bilgilendirilmesi amacıyla düzenlenmiştir.
					</p>

					<section class="legal-section">
						<h3>Madde 1 — Konu ve Kapsam</h3>
						<p>
							İşbu Formun konusu, Alıcı'nın <strong>TekstilERP</strong> internet sitesi üzerinden
							elektronik ortamda satın aldığı, nitelikleri ve satış fiyatı sipariş özeti
							ekranında belirtilen mal/hizmetlerin satışı ve teslimi ile ilgili olarak
							tarafların hak ve yükümlülüklerinin belirlenmesinden ibarettir.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 2 — Satıcı Bilgileri</h3>
						<table class="legal-table">
							<tbody>
								<tr><th>Unvan</th><td>TekstilERP Sanayi ve Ticaret A.Ş.</td></tr>
								<tr><th>Adres</th><td>Büyükdere Cad. No: 123, Şişli / İstanbul</td></tr>
								<tr><th>Mersis No</th><td>0123 0456 0789 0012</td></tr>
								<tr><th>E-posta</th><td>destek@tekstilerp.com</td></tr>
								<tr><th>Telefon</th><td>+90 212 555 14 26</td></tr>
								<tr><th>KEP Adresi</th><td>tekstilerp@hs01.kep.tr</td></tr>
							</tbody>
						</table>
					</section>

					<section class="legal-section">
						<h3>Madde 3 — Sözleşme Konusu Ürün/Hizmet Bilgileri</h3>
						<p>
							Mal/hizmetin temel özellikleri, vergiler dahil toplam fiyatı, ödeme bilgileri,
							teslimat süresi ve diğer tüm masraflar ile bu masrafların ödenmemesi durumunda
							doğacak hukuki sonuçlar Alıcı tarafından sipariş onay aşamasında görülmüş ve
							kabul edilmiştir.
						</p>
						<ul class="legal-list">
							<li>Sipariş özetinde listelenen ürünler, miktarlar ve birim fiyatlar geçerlidir.</li>
							<li>Ürün fiyatlarına KDV dahildir.</li>
							<li>Kargo ücreti, sipariş tutarı 500 TL ve üzerindeyse Satıcı tarafından karşılanır.</li>
							<li>Teslimat süresi seçilen kargo yöntemine göre 1-3 iş günü arasındadır.</li>
						</ul>
					</section>

					<section class="legal-section">
						<h3>Madde 4 — Cayma Hakkı</h3>
						<p>
							Alıcı, mal satışına ilişkin sözleşmelerde, malın teslim alındığı tarihten itibaren
							<strong>14 (on dört) gün</strong> içinde hiçbir hukuki ve cezai sorumluluk
							üstlenmeksizin ve hiçbir gerekçe göstermeksizin malı reddederek sözleşmeden
							cayma hakkına sahiptir.
						</p>
						<p>
							Cayma hakkının kullanılması için aynı süre içinde Satıcı'ya yazılı olarak
							veya kalıcı veri saklayıcısı ile bildirimde bulunulması gereklidir.
							Cayma bildiriminin Satıcı'ya ulaştığı tarihten itibaren <strong>14 gün
							içerisinde</strong> ürün bedeli Alıcı'ya iade edilir.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 5 — Cayma Hakkının Kullanılamayacağı Haller</h3>
						<p>Aşağıdaki durumlarda cayma hakkı kullanılamaz:</p>
						<ul class="legal-list">
							<li>Alıcı'nın istekleri veya açıkça onun kişisel ihtiyaçları doğrultusunda
								hazırlanan, niteliği itibarıyla geri gönderilmeye elverişli olmayan ürünler.</li>
							<li>Çabuk bozulabilen veya son kullanma tarihi geçebilecek mallar.</li>
							<li>Tesliminden sonra ambalaj, bant, mühür, paket gibi koruyucu unsurları açılmış
								olan iç giyim ve mayo ürünleri.</li>
							<li>Niteliği gereği iade edilmesi mümkün olmayan dijital içerikli ürünler.</li>
						</ul>
					</section>

					<section class="legal-section">
						<h3>Madde 6 — Şikayet ve İtirazlar</h3>
						<p>
							Alıcı, şikayet ve itirazlarını ikamet ettiği yerdeki veya tüketici işleminin
							yapıldığı yerdeki Tüketici Hakem Heyetleri'ne ya da Tüketici Mahkemeleri'ne
							yapabilir. Tüketici Hakem Heyetleri'ne başvurularda parasal sınırlar her yıl
							Ticaret Bakanlığı tarafından belirlenir.
						</p>
					</section>

					<div class="legal-footnote">
						Bu form, Alıcı tarafından elektronik ortamda kabul edilmek suretiyle düzenlenmiştir.
						Form içeriği <strong>{{ todayLabel }}</strong> itibariyle yürürlükteki mevzuata
						uygundur.
					</div>
				</template>

				<!-- Mesafeli Satış Sözleşmesi -->
				<template v-else>
					<div class="legal-meta">
						<div class="legal-meta-row">
							<span class="legal-meta-label">Sözleşme Tarihi</span>
							<strong>{{ todayLabel }}</strong>
						</div>
						<div class="legal-meta-row">
							<span class="legal-meta-label">Sözleşme No</span>
							<strong class="mono">{{ contractNo }}</strong>
						</div>
					</div>

					<p class="legal-intro">
						Aşağıda kimlik bilgileri belirtilen Alıcı ile Satıcı arasında, Alıcı'nın TekstilERP
						internet sitesi üzerinden elektronik ortamda verdiği sipariş kapsamında, mal/hizmet
						alım-satımı amacıyla aşağıdaki şartlar dahilinde mesafeli satış sözleşmesi
						akdedilmiştir.
					</p>

					<section class="legal-section">
						<h3>Madde 1 — Taraflar</h3>
						<div class="legal-parties">
							<div class="party">
								<div class="party-label">SATICI</div>
								<div class="party-body">
									<strong>TekstilERP Sanayi ve Ticaret A.Ş.</strong><br />
									Büyükdere Cad. No: 123, Şişli / İstanbul<br />
									Mersis: 0123 0456 0789 0012<br />
									destek@tekstilerp.com
								</div>
							</div>
							<div class="party">
								<div class="party-label">ALICI</div>
								<div class="party-body">
									<strong>{{ selectedAddress?.name || '—' }}</strong><br />
									{{ selectedAddress?.street || '—' }}<br />
									{{ selectedAddress?.district }} / {{ selectedAddress?.city }}<br />
									{{ selectedAddress?.phone }}
								</div>
							</div>
						</div>
					</section>

					<section class="legal-section">
						<h3>Madde 2 — Sözleşmenin Konusu</h3>
						<p>
							İşbu sözleşmenin konusu, Alıcı'nın Satıcı'ya ait internet sitesinden elektronik
							ortamda siparişini yaptığı, aşağıda nitelikleri ve satış fiyatı belirtilen
							ürün(ler)in satışı ve teslimi ile ilgili olarak 6502 sayılı Tüketicinin Korunması
							Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri gereğince tarafların
							hak ve yükümlülüklerinin belirlenmesidir.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 3 — Sözleşme Konusu Mal/Hizmet</h3>
						<div v-if="cartItems.length" class="legal-order-table">
							<div class="legal-order-row legal-order-head">
								<span>Ürün</span>
								<span>Adet</span>
								<span>Birim</span>
								<span>Toplam</span>
							</div>
							<div v-for="item in cartItems" :key="item.key" class="legal-order-row">
								<span class="legal-order-name">{{ item.name }} <em v-if="item.size">· {{ item.size }}</em></span>
								<span>{{ item.qty }}</span>
								<span>{{ formatPrice(item.price) }}</span>
								<span><strong>{{ formatPrice(item.price * item.qty) }}</strong></span>
							</div>
							<div class="legal-order-foot">
								<span>Ara Toplam (KDV dahil)</span>
								<strong>{{ formatPrice(subtotal) }}</strong>
							</div>
							<div class="legal-order-foot">
								<span>Kargo</span>
								<strong>{{ shippingFee > 0 ? formatPrice(shippingFee) : 'Ücretsiz' }}</strong>
							</div>
							<div class="legal-order-foot legal-order-total">
								<span>Genel Toplam</span>
								<strong>{{ formatPrice(grandTotal) }}</strong>
							</div>
						</div>
						<p v-else class="legal-empty">Sepette ürün bulunmamaktadır.</p>
					</section>

					<section class="legal-section">
						<h3>Madde 4 — Genel Hükümler</h3>
						<ol class="legal-list">
							<li>Alıcı, sözleşme konusu ürünün temel nitelikleri, satış fiyatı ve ödeme şekli
								ile teslimata ilişkin tüm ön bilgileri okuyup kabul ettiğini beyan eder.</li>
							<li>Sözleşme konusu ürün, yasal 30 günlük süreyi aşmamak koşulu ile her bir ürün için
								Alıcı'nın yerleşim yerinin uzaklığına bağlı olarak ön bilgilerde açıklanan süre içinde
								Alıcı veya gösterdiği adresteki kişi/kuruluşa teslim edilir.</li>
							<li>Satıcı, sözleşme konusu ürünü eksiksiz, siparişte belirtilen niteliklere uygun ve
								varsa garanti belgeleri, kullanım kılavuzları ile teslim etmeyi taahhüt eder.</li>
							<li>Sözleşme konusu ürünün teslimatı için Alıcı tarafından bedelinin ödenmiş olması
								şarttır. Herhangi bir nedenle ürün bedeli ödenmez veya banka kayıtlarında iptal
								edilir ise Satıcı ürünün teslimi yükümlülüğünden kurtulmuş kabul edilir.</li>
						</ol>
					</section>

					<section class="legal-section">
						<h3>Madde 5 — Ödeme</h3>
						<p>
							Ödeme, Alıcı tarafından sipariş aşamasında seçilen yöntem üzerinden gerçekleştirilir.
							Kredi/banka kartı ile yapılan ödemeler 3D Secure güvenlik protokolü ile korunur.
							Kart bilgileri Satıcı tarafından saklanmaz, ödeme altyapısı sağlayıcısının
							PCI DSS sertifikalı sistemleri üzerinden işlenir.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 6 — Cayma Hakkı</h3>
						<p>
							Alıcı, sözleşme konusu ürünün kendisine veya gösterdiği adresteki kişi/kuruluşa
							teslim tarihinden itibaren <strong>14 (on dört) gün</strong> içinde cayma hakkına
							sahiptir. Cayma hakkının kullanılması için aynı süre içinde Satıcı'ya
							destek@tekstilerp.com üzerinden bildirimde bulunulması yeterlidir.
						</p>
						<p>
							İade edilecek ürünlerin kutusu, ambalajı, varsa standart aksesuarları eksiksiz ve
							hasarsız olarak teslim edilmesi zorunludur. Etiketi sökülmüş veya kullanılmış ürünler
							iade kapsamı dışında kalır.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 7 — Yetkili Mahkeme</h3>
						<p>
							İşbu sözleşmenin uygulanmasında, Ticaret Bakanlığı tarafından ilan edilen değere
							kadar Alıcı'nın mal/hizmeti satın aldığı veya ikametgâhının bulunduğu yerdeki
							Tüketici Hakem Heyetleri ile Tüketici Mahkemeleri yetkilidir.
						</p>
					</section>

					<section class="legal-section">
						<h3>Madde 8 — Yürürlük</h3>
						<p>
							İşbu sözleşme, Alıcı tarafından elektronik ortamda onaylandığı tarihte yürürlüğe girer
							ve tarafların sözleşme konusu yükümlülüklerini tam ve eksiksiz olarak yerine getirdiği
							tarihte sona erer.
						</p>
					</section>

					<div class="legal-signatures">
						<div class="legal-sign-block">
							<div class="legal-sign-role">SATICI</div>
							<div class="legal-sign-name">TekstilERP Sanayi ve Ticaret A.Ş.</div>
							<div class="legal-sign-meta">e-imza · {{ todayLabel }}</div>
						</div>
						<div class="legal-sign-block">
							<div class="legal-sign-role">ALICI</div>
							<div class="legal-sign-name">{{ selectedAddress?.name || '—' }}</div>
							<div class="legal-sign-meta">elektronik onay bekleniyor</div>
						</div>
					</div>
				</template>
			</div>

			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close">Kapat</button>
				<button class="btn btn-primary btn-with-icon" @click="acceptLegalAndClose(close)">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<polyline points="20 6 9 17 4 12" />
					</svg>
					Okudum, Onaylıyorum
				</button>
			</template>
		</AppModal>

		<!-- Adres ekleme / düzenleme modal'ı -->
		<AppModal
			v-model="addressModalOpen"
			size="md"
			variant="info"
			:title="editingAddressId ? 'Adresi Düzenle' : 'Yeni Adres Ekle'"
			:subtitle="editingAddressId ? 'Mevcut adres bilgilerini güncelleyin' : 'Teslimat için yeni bir adres oluşturun'"
		>
			<div class="address-form">
				<div class="field field-wide">
					<label>Adres Başlığı</label>
					<div class="addr-label-tabs">
						<button
							type="button"
							v-for="opt in addressLabelOptions"
							:key="opt"
							class="addr-label-tab"
							:class="{ active: addressForm.label === opt }"
							@click="addressForm.label = opt"
						>{{ opt }}</button>
					</div>
				</div>

				<div class="field">
					<label>Ad Soyad</label>
					<input
						v-model="addressForm.name"
						class="form-input"
						type="text"
						placeholder="Alıcının adı ve soyadı"
						:class="{ 'has-error': addressErrors.name }"
					/>
					<small v-if="addressErrors.name" class="field-error">{{ addressErrors.name }}</small>
				</div>

				<div class="field">
					<label>Telefon</label>
					<input
						v-model="addressForm.phone"
						class="form-input"
						type="tel"
						placeholder="+90 5xx xxx xx xx"
						:class="{ 'has-error': addressErrors.phone }"
					/>
					<small v-if="addressErrors.phone" class="field-error">{{ addressErrors.phone }}</small>
				</div>

				<div class="field field-wide">
					<label>Açık Adres</label>
					<textarea
						v-model="addressForm.street"
						class="form-input"
						rows="2"
						placeholder="Mahalle, sokak, bina no, daire no"
						:class="{ 'has-error': addressErrors.street }"
					></textarea>
					<small v-if="addressErrors.street" class="field-error">{{ addressErrors.street }}</small>
				</div>

				<div class="field">
					<label>İl</label>
					<input
						v-model="addressForm.city"
						class="form-input"
						type="text"
						placeholder="İl"
						:class="{ 'has-error': addressErrors.city }"
					/>
					<small v-if="addressErrors.city" class="field-error">{{ addressErrors.city }}</small>
				</div>

				<div class="field">
					<label>İlçe</label>
					<input
						v-model="addressForm.district"
						class="form-input"
						type="text"
						placeholder="İlçe"
						:class="{ 'has-error': addressErrors.district }"
					/>
					<small v-if="addressErrors.district" class="field-error">{{ addressErrors.district }}</small>
				</div>

				<div class="field">
					<label>Posta Kodu</label>
					<input
						v-model="addressForm.postalCode"
						class="form-input mono-input"
						type="text"
						inputmode="numeric"
						maxlength="5"
						placeholder="34000"
						:class="{ 'has-error': addressErrors.postalCode }"
					/>
					<small v-if="addressErrors.postalCode" class="field-error">{{ addressErrors.postalCode }}</small>
				</div>
			</div>

			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="addressSaving">İptal</button>
				<button
					class="btn btn-primary btn-with-icon"
					:class="{ 'btn-loading': addressSaving }"
					:disabled="addressSaving"
					@click="saveAddress(close)"
				>
					<span v-if="addressSaving" class="btn-spinner"></span>
					<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<polyline points="20 6 9 17 4 12" />
					</svg>
					{{ addressSaving ? 'Kaydediliyor…' : (editingAddressId ? 'Değişiklikleri Kaydet' : 'Adresi Kaydet') }}
				</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	addresses: { type: Array, default: () => [] },
	shippingMethods: { type: Array, default: () => [] },
	installmentPlans: { type: Array, default: () => [] },
	savedCards: { type: Array, default: () => [] },
	userEmail: { type: String, default: '' },
})

const cart = inject('cart')
const showToast = inject('showToast')
const $swal = inject('$swal')

/* ── Sepet (cart reactive ref) ── */
const cartItems = computed(() => cart?.items?.value ?? [])

const totalItems = computed(() => cartItems.value.reduce((acc, i) => acc + i.qty, 0))
const subtotal = computed(() => cartItems.value.reduce((acc, i) => acc + i.price * i.qty, 0))

/* ── Form state ── */
const addressList = computed(() => props.addresses)
const defaultAddressId = computed(
	() => addressList.value.find((a) => a.isDefault)?.id ?? addressList.value[0]?.id ?? null
)
const selectedAddressId = ref(defaultAddressId.value)
const selectedShippingId = ref('standard')

// Liste güncellendiğinde mevcut seçim silinmişse fallback'e geç
watch(addressList, (list) => {
	if (!list.some((a) => a.id === selectedAddressId.value)) {
		selectedAddressId.value = defaultAddressId.value
	}
})

/* ── Adres modal state ── */
const addressModalOpen = ref(false)
const editingAddressId = ref(null)
const addressSaving = ref(false)
const addressLabelOptions = ['Ev', 'İş', 'Diğer']
const emptyAddressForm = () => ({
	label: 'Ev',
	name: '',
	phone: '',
	street: '',
	district: '',
	city: '',
	postalCode: '',
})
const addressForm = ref(emptyAddressForm())
const addressErrors = ref({})

const billingType = ref('individual')
const billingSameAsShipping = ref(true)
const billing = ref({ taxOffice: '', taxNumber: '', companyName: '' })

const paymentMethod = ref('card')
const selectedSavedCardId = ref(props.savedCards[0]?.id ?? null)
const newCard = ref({ number: '', expiry: '', cvv: '', holder: '' })
const saveCard = ref(false)
const installmentCount = ref(1)

const promoCode = ref('')
const promoApplied = ref(null)

const termsAccepted = ref(false)

const placing = ref(false)
const orderPlaced = ref(false)
const placedOrder = ref(null)

/* ── Yasal belge modal'ı ── */
const legalModalOpen = ref(false)
const legalDocType = ref('preliminary') // 'preliminary' | 'distance'

const legalDocMeta = computed(() => {
	if (legalDocType.value === 'preliminary') {
		return {
			title: 'Ön Bilgilendirme Formu',
			subtitle: '6502 sayılı Tüketicinin Korunması Hakkında Kanun kapsamında',
		}
	}
	return {
		title: 'Mesafeli Satış Sözleşmesi',
		subtitle: 'Bu sözleşme tarafınızca onaylandığında geçerlilik kazanır',
	}
})

function openLegal(type) {
	legalDocType.value = type
	legalModalOpen.value = true
}

function acceptLegalAndClose(close) {
	termsAccepted.value = true
	close()
}

/* ── Yardımcılar ── */
function formatPrice(value) {
	return '₺' + Number(value).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const selectedAddress = computed(() => addressList.value.find((a) => a.id === selectedAddressId.value) ?? null)

const todayLabel = computed(() =>
	new Date().toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' })
)

// Sözleşme numarası — sayfa açılışında bir kere üretilir
const contractNo = 'MSS-2026-' + (Math.floor(Math.random() * 9000) + 1000)

function onCardNumberInput(e) {
	const digits = e.target.value.replace(/\D/g, '').slice(0, 16)
	const formatted = digits.match(/.{1,4}/g)?.join(' ') ?? ''
	newCard.value.number = formatted
	e.target.value = formatted
}

function onExpiryInput(e) {
	const digits = e.target.value.replace(/\D/g, '').slice(0, 4)
	let formatted = digits
	if (digits.length >= 3) {
		formatted = digits.slice(0, 2) + '/' + digits.slice(2)
	}
	newCard.value.expiry = formatted
	e.target.value = formatted
}

const detectedBrand = computed(() => {
	const num = newCard.value.number.replace(/\s/g, '')
	if (num.startsWith('4')) return 'VISA'
	if (/^5[1-5]/.test(num)) return 'MASTERCARD'
	if (/^3[47]/.test(num)) return 'AMEX'
	if (/^9792/.test(num) || /^65/.test(num)) return 'TROY'
	return ''
})

const displayCardNumber = computed(() => {
	const cleaned = newCard.value.number.replace(/\s/g, '')
	const padded = cleaned + '•'.repeat(Math.max(0, 16 - cleaned.length))
	return padded.match(/.{1,4}/g).join(' ')
})

function selectSavedCard(id) {
	selectedSavedCardId.value = id
}

/* ── Tutar hesaplama ── */
const selectedShipping = computed(() =>
	props.shippingMethods.find((m) => m.id === selectedShippingId.value)
)
const shippingFee = computed(() => selectedShipping.value?.price ?? 0)

const codFee = computed(() => (paymentMethod.value === 'cod' ? 15 : 0))

const selectedInstallment = computed(() =>
	props.installmentPlans.find((p) => p.count === installmentCount.value) ?? props.installmentPlans[0]
)

function installmentAmount(plan) {
	const base = subtotal.value + shippingFee.value + codFee.value - (promoApplied.value?.discount ?? 0)
	const total = base * (1 + plan.rate / 100)
	return total / plan.count
}

const installmentSurcharge = computed(() => {
	if (paymentMethod.value !== 'card') return 0
	const plan = selectedInstallment.value
	if (!plan || plan.rate === 0) return 0
	const base = subtotal.value + shippingFee.value + codFee.value - (promoApplied.value?.discount ?? 0)
	return base * (plan.rate / 100)
})

const grandTotal = computed(() => {
	const base = subtotal.value + shippingFee.value + codFee.value + installmentSurcharge.value
	return base - (promoApplied.value?.discount ?? 0)
})

/* ── İndirim kodu ── */
function applyPromo() {
	const code = promoCode.value.trim().toUpperCase()
	if (!code) return
	if (code === 'TEKSTIL10') {
		promoApplied.value = { code, discount: Math.round(subtotal.value * 0.1 * 100) / 100 }
		showToast?.({ type: 'success', title: 'İndirim Uygulandı', message: `%10 indirim eklendi.` })
	} else if (code === 'WELCOME50') {
		promoApplied.value = { code, discount: 50 }
		showToast?.({ type: 'success', title: 'İndirim Uygulandı', message: `₺50 indirim eklendi.` })
	} else {
		showToast?.({ type: 'error', title: 'Geçersiz Kod', message: `"${code}" tanımlı bir indirim kodu değil.` })
	}
}

function clearPromo() {
	promoApplied.value = null
	promoCode.value = ''
}

/* ── Form validasyon ── */
const canPlaceOrder = computed(() => {
	if (cartItems.value.length === 0) return false
	if (!selectedAddressId.value) return false
	if (!selectedShippingId.value) return false
	if (!termsAccepted.value) return false

	if (paymentMethod.value === 'card') {
		if (selectedSavedCardId.value === null) {
			const num = newCard.value.number.replace(/\s/g, '')
			if (num.length < 15) return false
			if (!/^\d{2}\/\d{2}$/.test(newCard.value.expiry)) return false
			if (newCard.value.cvv.length < 3) return false
			if (!newCard.value.holder.trim()) return false
		}
	}

	if (billingType.value === 'company') {
		if (!billing.value.companyName || !billing.value.taxNumber) return false
	}

	return true
})

/* ── Sipariş tamamla ── */
function placeOrder() {
	if (!canPlaceOrder.value || placing.value) return

	const addr = selectedAddress.value
	if (!addr) return

	const cleanedCard = newCard.value.number.replace(/\s/g, '')
	const cardPayload = paymentMethod.value === 'card' && selectedSavedCardId.value === null
		? {
			last4:  cleanedCard.slice(-4),
			brand:  detectedBrand.value || null,
			holder: newCard.value.holder || null,
		}
		: null

	placing.value = true

	router.post(
		'/checkout',
		{
			address: {
				label:       addr.label,
				name:        addr.name,
				phone:       addr.phone,
				street:      addr.street,
				district:    addr.district,
				city:        addr.city,
				postal_code: addr.postalCode,
			},
			shipping_method:   selectedShippingId.value,
			payment_method:    paymentMethod.value,
			card:              cardPayload,
			installment_count: installmentCount.value,
			billing: {
				type:               billingType.value,
				same_as_shipping:   billingSameAsShipping.value,
				company_name:       billing.value.companyName || null,
				tax_office:         billing.value.taxOffice   || null,
				tax_number:         billing.value.taxNumber   || null,
			},
			promo_code:     promoApplied.value?.code ?? null,
			terms_accepted: termsAccepted.value,
		},
		{
			preserveScroll: true,
			onError: (errs) => {
				showToast?.({
					type:    'error',
					title:   'Sipariş oluşturulamadı',
					message: Object.values(errs)[0] || 'Lütfen formdaki uyarıları kontrol edin.',
				})
			},
			onFinish: () => { placing.value = false },
		}
	)
}

/* ── Adres ekleme / düzenleme ── */
function openAddAddress() {
	editingAddressId.value = null
	addressForm.value = emptyAddressForm()
	addressErrors.value = {}
	addressModalOpen.value = true
}

function openEditAddress(addr) {
	editingAddressId.value = addr.id
	addressForm.value = {
		label:      addr.label || 'Ev',
		name:       addr.name || '',
		phone:      addr.phone || '',
		street:     addr.street || '',
		district:   addr.district || '',
		city:       addr.city || '',
		postalCode: addr.postalCode || '',
	}
	addressErrors.value = {}
	addressModalOpen.value = true
}

function validateAddressForm() {
	const errs = {}
	const f = addressForm.value
	if (!f.name.trim())        errs.name       = 'Ad soyad zorunludur.'
	if (!f.phone.trim())       errs.phone      = 'Telefon zorunludur.'
	else if (f.phone.replace(/\D/g, '').length < 10) errs.phone = 'Geçerli bir telefon girin.'
	if (!f.street.trim())      errs.street     = 'Açık adres zorunludur.'
	if (!f.city.trim())        errs.city       = 'İl zorunludur.'
	if (!f.district.trim())    errs.district   = 'İlçe zorunludur.'
	if (!f.postalCode.trim())  errs.postalCode = 'Posta kodu zorunludur.'
	else if (!/^\d{5}$/.test(f.postalCode.trim())) errs.postalCode = '5 haneli posta kodu girin.'
	addressErrors.value = errs
	return Object.keys(errs).length === 0
}

function saveAddress(close) {
	if (!validateAddressForm() || addressSaving.value) return

	const payload = {
		label:       addressForm.value.label.trim() || 'Diğer',
		name:        addressForm.value.name.trim(),
		phone:       addressForm.value.phone.trim(),
		street:      addressForm.value.street.trim(),
		district:    addressForm.value.district.trim(),
		city:        addressForm.value.city.trim(),
		postal_code: addressForm.value.postalCode.trim(),
	}

	const editingId = editingAddressId.value
	const knownIds = addressList.value.map((a) => a.id)

	const options = {
		preserveScroll: true,
		preserveState:  true,
		onStart:   () => { addressSaving.value = true },
		onSuccess: () => {
			if (!editingId) {
				const fresh = props.addresses.find((a) => !knownIds.includes(a.id))
				if (fresh) selectedAddressId.value = fresh.id
			}
			close?.()
		},
		onError: (errs) => {
			addressErrors.value = Object.fromEntries(
				Object.entries(errs).map(([k, v]) => [k === 'postal_code' ? 'postalCode' : k, v])
			)
		},
		onFinish: () => { addressSaving.value = false },
	}

	if (editingId) {
		router.put(`/checkout/addresses/${editingId}`, payload, options)
	} else {
		router.post('/checkout/addresses', payload, options)
	}
}

async function deleteAddress(addr) {
	if (addressList.value.length <= 1) return
	const ok = await $swal.dangerConfirm({ title: 'Adres silinsin mi?', html: `<b>${addr.label}</b> adresi kalıcı olarak silinecek.` })
	if (!ok) return

	router.delete(`/checkout/addresses/${addr.id}`, {
		preserveScroll: true,
		preserveState:  true,
	})
}

/* ── Diğer aksiyonlar ── */

function copyIban(iban) {
	if (navigator.clipboard?.writeText) {
		navigator.clipboard.writeText(iban)
		showToast?.({ type: 'success', title: 'IBAN Kopyalandı', message: iban })
	}
}

function trackOrder() {
	showToast?.({ type: 'info', title: 'Yakında', message: 'Sipariş takip sayfası hazırlanıyor.' })
}
</script>

<style scoped>
.page-checkout { padding-bottom: 24px; }

/* ── Empty state ── */
.empty-cart-card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 16px;
	padding: 60px 24px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.empty-icon-lg {
	width: 100px; height: 100px;
	border-radius: 50%;
	background: #f5f5fa;
	color: #c0c0d8;
	display: flex; align-items: center; justify-content: center;
	margin-bottom: 14px;
}
.empty-cart-card h2 { font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0; }
.empty-cart-card p { font-size: 13.5px; color: #888; margin: 0 0 14px 0; max-width: 360px; }

/* ── Layout ── */
.checkout-layout {
	display: grid;
	grid-template-columns: 1fr 380px;
	gap: 24px;
	align-items: flex-start;
}

@media (max-width: 1100px) {
	.checkout-layout { grid-template-columns: 1fr 320px; }
}
@media (max-width: 900px) {
	.checkout-layout { grid-template-columns: 1fr; }
}

.checkout-main {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.checkout-header h1 {
	font-size: 22px;
	font-weight: 700;
	color: #1a1a2e;
	line-height: 1.2;
}
.checkout-header p {
	font-size: 13px;
	color: #888;
	margin-top: 4px;
}

/* ── Form section ── */
.form-section {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.section-head {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 14px;
}

.step-no {
	width: 26px; height: 26px;
	background: #1a1a2e;
	color: #fff;
	border-radius: 50%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 700;
	flex-shrink: 0;
}

.section-head h2 {
	font-size: 15px;
	font-weight: 700;
	color: #1a1a2e;
	margin: 0;
}

/* ── Adres ── */
.address-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 10px;
}

.address-card {
	position: relative;
	display: block;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 12px;
	padding: 14px;
	cursor: pointer;
	transition: border-color .15s, background .15s, box-shadow .15s;
	font-family: inherit;
	text-align: left;
}
.address-card input[type="radio"] {
	position: absolute;
	opacity: 0; pointer-events: none;
}
.address-card:hover { border-color: #c0c0d8; }
.address-card.active {
	border-color: #1a1a2e;
	background: #fafafe;
	box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.05);
}

.addr-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 6px;
}

.addr-label {
	display: inline-block;
	padding: 2px 8px;
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	border-radius: 6px;
	font-size: 10.5px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.addr-name { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.addr-phone { font-size: 12px; color: #555; margin: 2px 0 6px; }
.addr-text { font-size: 12px; color: #777; line-height: 1.55; }

.addr-actions {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.addr-action-btn {
	width: 22px; height: 22px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: #f5f5fa;
	border: 1px solid transparent;
	border-radius: 6px;
	color: #666;
	cursor: pointer;
	padding: 0;
	transition: background .15s, color .15s, border-color .15s;
}
.addr-action-btn:hover {
	background: #fff;
	border-color: #d8d8e8;
	color: #1a1a2e;
}
.addr-action-btn.addr-action-danger:hover {
	color: #dc2626;
	border-color: #fecaca;
	background: #fff5f5;
}

.address-add {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	color: #888;
	font-size: 13px;
	font-weight: 600;
	border-style: dashed;
	min-height: 130px;
}
.address-add:hover { color: #1a1a2e; }

/* ── Teslimat yöntemi ── */
.ship-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.ship-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 11px;
	cursor: pointer;
	transition: border-color .15s, background .15s;
}
.ship-card input[type="radio"] {
	position: absolute;
	opacity: 0; pointer-events: none;
}
.ship-card:hover { border-color: #c0c0d8; }
.ship-card.active {
	border-color: #1a1a2e;
	background: #fafafe;
}

.ship-radio {
	width: 18px; height: 18px;
	border: 2px solid #d8d8e8;
	border-radius: 50%;
	flex-shrink: 0;
	position: relative;
	transition: border-color .15s;
}
.ship-card.active .ship-radio { border-color: #1a1a2e; }
.ship-card.active .ship-radio::after {
	content: '';
	position: absolute;
	inset: 3px;
	background: #1a1a2e;
	border-radius: 50%;
}

.ship-info { flex: 1; min-width: 0; }

.ship-line {
	display: flex;
	align-items: baseline;
	gap: 6px;
	flex-wrap: wrap;
}

.ship-label { font-size: 13.5px; font-weight: 700; color: #1a1a2e; }
.ship-eta { font-size: 12px; color: #16a34a; font-weight: 600; }
.ship-desc { font-size: 12px; color: #888; margin-top: 2px; }

.ship-price {
	font-size: 14px;
	font-weight: 800;
	color: #1a1a2e;
	flex-shrink: 0;
}
.ship-price.free { color: #16a34a; }

/* ── Fatura ── */
.billing-tabs {
	display: inline-flex;
	background: #f5f5f8;
	padding: 3px;
	border-radius: 9px;
	margin-bottom: 12px;
}

.bill-tab {
	padding: 6px 16px;
	background: none;
	border: none;
	color: #888;
	font-family: inherit;
	font-size: 12.5px;
	font-weight: 600;
	border-radius: 6px;
	cursor: pointer;
	transition: all .15s;
}
.bill-tab.active { background: #fff; color: #1a1a2e; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); }

.check-line {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #444;
	cursor: pointer;
	padding: 4px 0;
}
.check-line input { accent-color: rgb(var(--color-primary)); width: 14px; height: 14px; cursor: pointer; }

.company-fields {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;
	margin-top: 12px;
}
.company-fields .field:first-child { grid-column: 1 / -1; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label {
	font-size: 11.5px;
	font-weight: 600;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.mono-input { font-family: 'SF Mono', Menlo, Consolas, monospace; letter-spacing: 0.04em; }

/* ── Adres formu (modal) ── */
.address-form {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 14px;
}
.address-form .field-wide { grid-column: 1 / -1; }

.address-form textarea.form-input {
	resize: vertical;
	min-height: 64px;
	font-family: inherit;
	line-height: 1.5;
}

.address-form .form-input.has-error {
	border-color: #dc2626;
	background: #fff5f5;
}

.field-error {
	font-size: 11.5px;
	color: #dc2626;
	margin-top: 2px;
}

.addr-label-tabs {
	display: inline-flex;
	background: #f5f5f8;
	padding: 3px;
	border-radius: 9px;
	gap: 2px;
}

.addr-label-tab {
	padding: 6px 16px;
	background: none;
	border: none;
	color: #888;
	font-family: inherit;
	font-size: 12.5px;
	font-weight: 600;
	border-radius: 6px;
	cursor: pointer;
	transition: all .15s;
}
.addr-label-tab.active {
	background: #fff;
	color: #1a1a2e;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

@media (max-width: 560px) {
	.address-form { grid-template-columns: 1fr; }
}

/* ── Ödeme tab'ları ── */
.pay-tabs {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 8px;
	margin-bottom: 18px;
}

.pay-tab {
	display: inline-flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	padding: 14px 8px;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 11px;
	color: #666;
	font-family: inherit;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	transition: all .15s;
}
.pay-tab:hover { border-color: #c0c0d8; color: #1a1a2e; }
.pay-tab.active {
	background: #1a1a2e;
	color: #fff;
	border-color: #1a1a2e;
}
.pay-tab svg { color: currentColor; }

.pay-body { padding-top: 4px; }

/* ── Saved cards ── */
.saved-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 10px;
	margin-bottom: 14px;
}

.saved-card {
	background: linear-gradient(135deg, #1a1a2e, #2a2a4e);
	color: #fff;
	border: 2px solid transparent;
	border-radius: 11px;
	padding: 12px 14px;
	cursor: pointer;
	text-align: left;
	transition: transform .12s, box-shadow .15s;
}
.saved-card:hover { transform: translateY(-2px); }
.saved-card.active {
	box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.4);
	border-color: rgb(var(--color-primary));
}

.sc-brand {
	display: inline-block;
	font-size: 10px;
	font-weight: 800;
	letter-spacing: 0.08em;
	padding: 2px 8px;
	border-radius: 4px;
	background: rgba(255, 255, 255, 0.18);
	margin-bottom: 14px;
}
.sc-visa { background: rgb(var(--color-primary) / 0.35); }
.sc-mastercard { background: rgba(245, 158, 11, 0.35); }

.sc-num { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.sc-exp { font-size: 10.5px; color: #c0c0d8; margin-top: 6px; }

.saved-card-new {
	background: #fff;
	color: #888;
	border: 2px dashed #d8d8e8;
	display: inline-flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
}
.saved-card-new:hover { border-color: #1a1a2e; color: #1a1a2e; }
.saved-card-new.active {
	border-style: solid;
	border-color: #1a1a2e;
	color: #1a1a2e;
	box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.06);
}

/* ── Kart görseli ── */
.card-form {
	display: grid;
	grid-template-columns: 280px 1fr;
	gap: 20px;
	margin-bottom: 16px;
	align-items: flex-start;
}

@media (max-width: 760px) {
	.card-form { grid-template-columns: 1fr; }
}

.card-visual {
	aspect-ratio: 16/10;
	background: linear-gradient(135deg, #1a1a2e 0%, #2a2a4e 50%, rgb(var(--color-primary)) 100%);
	border-radius: 14px;
	padding: 18px;
	color: #fff;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 8px 20px rgba(26, 26, 46, 0.18);
	position: relative;
	overflow: hidden;
}
.card-visual::after {
	content: '';
	position: absolute;
	top: -40px; right: -40px;
	width: 140px; height: 140px;
	background: rgba(255, 255, 255, 0.06);
	border-radius: 50%;
}

.card-chip {
	width: 36px; height: 26px;
	background: linear-gradient(135deg, #fcd34d, #f59e0b);
	border-radius: 5px;
	position: relative;
}
.card-chip::after {
	content: '';
	position: absolute;
	inset: 4px;
	border: 1.5px solid rgba(180, 83, 9, 0.4);
	border-radius: 3px;
}

.card-brand-mark {
	position: absolute;
	top: 18px;
	right: 18px;
	font-size: 13px;
	font-weight: 800;
	letter-spacing: 0.12em;
	color: rgba(255, 255, 255, 0.92);
}

.card-number {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 17px;
	font-weight: 600;
	letter-spacing: 0.12em;
}

.card-foot {
	display: flex;
	justify-content: space-between;
	gap: 16px;
}

.card-label {
	font-size: 9px;
	letter-spacing: 0.12em;
	color: rgba(255, 255, 255, 0.55);
	margin-bottom: 2px;
}

.card-value {
	font-size: 12px;
	font-weight: 600;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	letter-spacing: 0.04em;
}

.card-fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}
.field-wide { grid-column: 1 / -1; }

/* ── Taksitler ── */
.installments {
	margin-top: 14px;
}

.installments > label {
	display: block;
	font-size: 11.5px;
	font-weight: 600;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-bottom: 8px;
}

.install-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
	gap: 8px;
}

.install-card {
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 10px;
	padding: 10px 12px;
	cursor: pointer;
	text-align: left;
	transition: all .12s;
}
.install-card:hover { border-color: #c0c0d8; }
.install-card.active {
	background: #fafafe;
	border-color: #1a1a2e;
	box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.05);
}

.ic-count {
	font-size: 12.5px;
	font-weight: 700;
	color: #1a1a2e;
}
.ic-amount {
	font-size: 15px;
	font-weight: 800;
	color: #1a1a2e;
	margin-top: 4px;
}
.ic-note { font-size: 10.5px; color: #888; margin-top: 2px; }
.ic-free { color: #16a34a; font-weight: 600; }

/* ── Banka ── */
.bank-body, .cod-body { padding: 4px 0; }

.bank-info, .cod-info {
	display: flex;
	gap: 10px;
	padding: 12px 14px;
	background: rgb(var(--color-primary-soft));
	border: 1px solid #e0e0ff;
	border-radius: 10px;
	font-size: 12.5px;
	color: #444;
	line-height: 1.55;
	margin-bottom: 14px;
}
.bank-info svg, .cod-info svg { flex-shrink: 0; margin-top: 2px; }

.cod-info {
	background: #fffbeb;
	border-color: #fde68a;
}
.cod-info strong { color: #1a1a2e; }

.iban-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.iban-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}

.iban-bank {
	font-size: 12px;
	font-weight: 700;
	color: #1a1a2e;
	min-width: 100px;
}

.iban-no {
	flex: 1;
	font-size: 12px;
	color: #555;
}

.iban-copy {
	background: none;
	border: none;
	color: rgb(var(--color-primary));
	font-family: inherit;
	font-size: 11.5px;
	font-weight: 600;
	cursor: pointer;
	padding: 4px 8px;
	border-radius: 6px;
	transition: background .12s;
}
.iban-copy:hover { background: rgb(var(--color-primary-soft)); }

.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }

/* ── Terms ── */
.terms-check {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 4px 4px;
	font-size: 12.5px;
	color: #555;
	line-height: 1.55;
	cursor: pointer;
}
.terms-check input {
	margin-top: 2px;
	accent-color: rgb(var(--color-primary));
	width: 14px; height: 14px;
}

.text-link { color: rgb(var(--color-primary)); text-decoration: none; }
.text-link:hover { text-decoration: underline; }

/* ── Sağ özet ── */
.checkout-summary {
	position: sticky;
	top: 12px;
}

.summary-card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 16px;
	padding: 18px 20px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.summary-head {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	padding-bottom: 12px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 12px;
}

.summary-head h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin: 0; }
.summary-count { font-size: 12px; color: #888; }

.summary-items {
	max-height: 280px;
	overflow-y: auto;
	margin: 0 -4px 14px;
	padding: 0 4px;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.summary-items::-webkit-scrollbar { width: 3px; }
.summary-items::-webkit-scrollbar-thumb { background: #ddd; border-radius: 3px; }

.su-item {
	display: grid;
	grid-template-columns: 48px 1fr auto;
	gap: 10px;
	align-items: center;
}

.su-image {
	position: relative;
	width: 48px; height: 60px;
	border-radius: 8px;
	overflow: hidden;
	background: #f5f5fa;
}
.su-image img { width: 100%; height: 100%; object-fit: cover; }
.su-qty {
	position: absolute;
	top: -4px; right: -4px;
	background: #1a1a2e;
	color: #fff;
	border-radius: 50%;
	min-width: 18px; height: 18px;
	font-size: 10px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 2px solid #fff;
	padding: 0 4px;
}

.su-body { min-width: 0; }
.su-name {
	font-size: 12px;
	font-weight: 600;
	color: #1a1a2e;
	line-height: 1.35;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.su-meta {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	color: #888;
	margin-top: 3px;
}

.su-color {
	width: 10px; height: 10px;
	border-radius: 50%;
	border: 1px solid #fff;
	box-shadow: 0 0 0 1px #e5e7eb;
}

.su-price {
	font-size: 13px;
	font-weight: 700;
	color: #1a1a2e;
	white-space: nowrap;
}

/* ── Promo ── */
.promo {
	display: flex;
	gap: 6px;
	margin-bottom: 4px;
}
.promo .form-input { flex: 1; }
.promo-btn { flex-shrink: 0; }

.promo-applied {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 8px 10px;
	background: #f0fdf4;
	border: 1px solid #bbf7d0;
	border-radius: 8px;
	font-size: 12px;
	color: #15803d;
	margin: 8px 0 6px;
}
.promo-applied strong { font-weight: 700; }

/* ── Toplamlar ── */
.sum-rows {
	margin-top: 14px;
	padding-top: 14px;
	border-top: 1px solid #f0f0f5;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.sum-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 13px;
	color: #555;
}
.sum-row strong { color: #1a1a2e; font-weight: 700; }
.text-success { color: #16a34a !important; }

.sum-total {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	padding-top: 12px;
	margin-top: 12px;
	border-top: 1px dashed #e8e8f0;
}
.sum-total span { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.sum-total strong {
	font-size: 22px;
	font-weight: 800;
	color: #1a1a2e;
}

.vat-note { font-size: 11px; color: #888; text-align: right; margin-bottom: 14px; }

.place-order-btn {
	width: 100%;
	justify-content: center;
	font-size: 14px;
	font-weight: 700;
}

.trust {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin-top: 14px;
	padding-top: 14px;
	border-top: 1px solid #f0f0f5;
}

.trust-item {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 11.5px;
	color: #888;
}
.trust-item svg { color: #16a34a; }

/* ── Success ── */
.success-card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 16px;
	padding: 40px 24px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
	max-width: 760px;
	margin: 20px auto 0;
}

.success-icon {
	width: 96px; height: 96px;
	border-radius: 50%;
	background: #f0fdf4;
	display: flex; align-items: center; justify-content: center;
	margin-bottom: 8px;
	animation: success-pop .5s ease-out;
}

@keyframes success-pop {
	0% { transform: scale(0.5); opacity: 0; }
	60% { transform: scale(1.1); }
	100% { transform: scale(1); opacity: 1; }
}

.success-card h1 {
	font-size: 26px;
	font-weight: 800;
	color: #1a1a2e;
	margin: 0;
}

.success-order-no {
	font-size: 13px;
	color: #555;
	margin-top: 6px;
}
.success-order-no .mono {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-weight: 700;
	color: #1a1a2e;
	background: #fafafe;
	padding: 2px 8px;
	border-radius: 6px;
	margin-left: 4px;
}

.success-msg {
	font-size: 13px;
	color: #666;
	line-height: 1.6;
	margin: 8px 0 16px;
}

.success-actions {
	display: flex;
	gap: 10px;
	margin-bottom: 24px;
}

.success-summary {
	width: 100%;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 12px;
	padding: 18px;
	text-align: left;
	margin-top: 8px;
}

.success-summary-title {
	font-size: 12.5px;
	font-weight: 700;
	color: #1a1a2e;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	margin-bottom: 12px;
}

.success-items {
	display: flex;
	flex-direction: column;
	gap: 10px;
	margin-bottom: 14px;
}

.success-item {
	display: grid;
	grid-template-columns: 48px 1fr auto;
	gap: 12px;
	align-items: center;
}

.success-item img {
	width: 48px; height: 60px;
	object-fit: cover;
	border-radius: 7px;
}

.si-body { min-width: 0; }
.si-brand {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
.si-name {
	font-size: 12.5px;
	font-weight: 600;
	color: #1a1a2e;
	line-height: 1.35;
	margin-top: 1px;
}
.si-meta {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	color: #888;
	margin-top: 2px;
}
.si-color {
	width: 10px; height: 10px;
	border-radius: 50%;
	border: 1px solid #fff;
	box-shadow: 0 0 0 1px #e5e7eb;
}

.si-price {
	font-size: 13px;
	font-weight: 700;
	color: #1a1a2e;
}

.success-totals {
	display: flex;
	flex-direction: column;
	gap: 5px;
	padding-top: 12px;
	border-top: 1px dashed #e8e8f0;
}
.success-totals > div {
	display: flex;
	justify-content: space-between;
	font-size: 12.5px;
	color: #555;
}
.success-totals > div strong { color: #1a1a2e; font-weight: 700; }

.ts-total {
	margin-top: 6px;
	padding-top: 8px;
	border-top: 1px dashed #e8e8f0;
	font-size: 15px !important;
}
.ts-total strong { font-size: 17px !important; font-weight: 800; }

/* ── Yasal belge modal içeriği ── */
.legal-doc {
	font-size: 13px;
	color: #444;
	line-height: 1.65;
}

.legal-meta {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
	margin-bottom: 16px;
}
.legal-meta-row {
	display: flex;
	flex-direction: column;
	gap: 2px;
	font-size: 12px;
}
.legal-meta-label {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
.legal-meta strong { color: #1a1a2e; font-weight: 700; }

.legal-intro {
	font-size: 13px;
	line-height: 1.7;
	color: #555;
	padding-bottom: 14px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 14px;
}

.legal-section {
	margin-bottom: 18px;
}

.legal-section h3 {
	font-size: 13.5px;
	font-weight: 700;
	color: #1a1a2e;
	margin: 0 0 8px;
	padding-left: 10px;
	border-left: 3px solid rgb(var(--color-primary));
}

.legal-section p {
	color: #555;
	margin: 0 0 8px;
	line-height: 1.7;
}
.legal-section p:last-child { margin-bottom: 0; }

.legal-section strong { color: #1a1a2e; font-weight: 600; }

.legal-list {
	margin: 6px 0 0 0;
	padding-left: 20px;
}
.legal-list li {
	margin-bottom: 6px;
	color: #555;
	line-height: 1.6;
}

.legal-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	margin-top: 6px;
	border-radius: 9px;
	overflow: hidden;
	border: 1px solid #f0f0f5;
}
.legal-table th, .legal-table td {
	padding: 8px 12px;
	font-size: 12.5px;
	text-align: left;
	border-bottom: 1px solid #f5f5f8;
}
.legal-table th {
	width: 160px;
	color: #888;
	font-weight: 500;
	background: #fafafe;
}
.legal-table td {
	color: #1a1a2e;
	font-weight: 500;
}
.legal-table tr:last-child th, .legal-table tr:last-child td { border-bottom: none; }

/* Tarafların kart düzeni */
.legal-parties {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}

@media (max-width: 700px) {
	.legal-parties { grid-template-columns: 1fr; }
	.legal-meta { grid-template-columns: 1fr; }
}

.party {
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
	padding: 12px 14px;
}
.party-label {
	font-size: 10.5px;
	font-weight: 700;
	color: rgb(var(--color-primary));
	text-transform: uppercase;
	letter-spacing: 0.06em;
	margin-bottom: 6px;
}
.party-body {
	font-size: 12.5px;
	color: #444;
	line-height: 1.65;
}
.party-body strong { color: #1a1a2e; font-weight: 700; }

/* Sipariş tablosu */
.legal-order-table {
	border: 1px solid #f0f0f5;
	border-radius: 10px;
	overflow: hidden;
	margin-top: 6px;
}

.legal-order-row {
	display: grid;
	grid-template-columns: 1fr 60px 90px 100px;
	gap: 8px;
	padding: 10px 12px;
	font-size: 12.5px;
	color: #444;
	border-bottom: 1px solid #f5f5f8;
	align-items: center;
}
.legal-order-row > span:nth-child(n+2) { text-align: right; }

.legal-order-head {
	background: #fafafe;
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.legal-order-name { font-weight: 600; color: #1a1a2e; }
.legal-order-name em {
	font-style: normal;
	color: #888;
	font-weight: 500;
}

.legal-order-foot {
	display: flex;
	justify-content: space-between;
	padding: 8px 12px;
	font-size: 12.5px;
	color: #555;
	background: #fafafe;
	border-top: 1px solid #f5f5f8;
}
.legal-order-foot strong { color: #1a1a2e; font-weight: 700; }

.legal-order-total {
	font-size: 14px;
	color: #1a1a2e;
	background: #fff;
	border-top: 1px dashed #d8d8e8;
}
.legal-order-total strong { font-size: 16px; font-weight: 800; }

.legal-empty {
	padding: 18px;
	text-align: center;
	color: #888;
	background: #fafafe;
	border-radius: 9px;
}

/* İmza alanı */
.legal-signatures {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	margin-top: 18px;
	padding-top: 16px;
	border-top: 1px dashed #d8d8e8;
}

@media (max-width: 700px) {
	.legal-signatures { grid-template-columns: 1fr; }
}

.legal-sign-block {
	padding: 12px 14px;
	background: #fafafe;
	border-radius: 10px;
	border: 1px solid #f0f0f5;
}

.legal-sign-role {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	margin-bottom: 6px;
}

.legal-sign-name {
	font-size: 13px;
	font-weight: 700;
	color: #1a1a2e;
	margin-bottom: 4px;
}

.legal-sign-meta {
	font-size: 11px;
	color: #888;
}

.legal-footnote {
	margin-top: 18px;
	padding: 12px 14px;
	background: #f8f7ff;
	border-left: 3px solid rgb(var(--color-primary));
	border-radius: 8px;
	font-size: 12px;
	color: rgb(var(--color-primary-hover));
	line-height: 1.6;
}
.legal-footnote strong { color: #1a1a2e; }
</style>
