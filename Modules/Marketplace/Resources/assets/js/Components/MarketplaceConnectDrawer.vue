<template>
	<Teleport to="body">
		<div
			class="drawer-overlay"
			:class="{ open: modelValue }"
			@click="close"
		></div>
		<aside
			class="drawer marketplace-connect-drawer"
			:class="{ open: modelValue }"
			role="dialog"
			aria-modal="true"
			aria-labelledby="mcd-title"
		>
			<template v-if="marketplace">
				<div class="drawer-header">
					<div class="drawer-title">
						<div
							class="drawer-title-icon mp-logo"
							:style="{ background: marketplace.color, color: '#fff' }"
						>
							{{ marketplace.logoText }}
						</div>
						<div>
							<h4 id="mcd-title">{{ marketplace.name }}</h4>
							<p>{{ marketplace.description || 'Pazaryeri entegrasyonu' }}</p>
						</div>
					</div>
					<button class="drawer-close" @click="close" aria-label="Kapat">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M18 6L6 18M6 6l12 12" />
						</svg>
					</button>
				</div>

				<div class="drawer-body">
					<!-- Context banner -->
					<div class="context-banner">
						<div class="cb-icon" :style="{ background: marketplace.color }">
							<svg width="14" height="14" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24">
								<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
								<path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
							</svg>
						</div>
						<div class="cb-text">
							<div class="cb-title">
								<template v-if="category">
									<strong>{{ category.name }}</strong> için <strong>{{ marketplace.name }}</strong> bağlantısı kurulacak
								</template>
								<template v-else>
									<strong>{{ marketplace.name }}</strong> bağlantısı kurulacak
								</template>
							</div>
							<div class="cb-sub">
								Bağlantı kurulduktan sonra kategori eşleştirmesi yapabilir, ürünleri senkronize edebilirsiniz.
							</div>
						</div>
					</div>

					<!-- Credentials section -->
					<section class="dr-section">
						<div class="dr-section-head">
							<div>
								<h5>Bağlantı Bilgileri</h5>
								<p class="dr-section-sub">
									Bilgileri {{ marketplace.name }} satıcı panelinden alabilirsiniz
								</p>
							</div>
							<a
								v-if="marketplace.docsUrl"
								:href="marketplace.docsUrl"
								target="_blank"
								rel="noopener"
								class="docs-link"
							>
								API Dokümantasyonu
								<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
									<path d="M7 17L17 7M17 7H8M17 7v9" />
								</svg>
							</a>
						</div>

						<div v-if="!(marketplace.fields && marketplace.fields.length)" class="empty-fields">
							Bu pazaryeri için kimlik bilgisi alanı tanımlı değil.
						</div>

						<div v-else class="fields-stack">
							<div v-for="f in marketplace.fields" :key="f.key" class="field">
								<label :for="`mcd-${f.key}`">
									{{ f.label }}
									<span class="required">*</span>
								</label>
								<div class="input-wrap">
									<input
										:id="`mcd-${f.key}`"
										v-model="form.credentials[f.key]"
										:type="resolveInputType(f)"
										class="form-input mono-input"
										:placeholder="f.help || f.label"
										autocomplete="off"
									/>
									<button
										v-if="f.type === 'password'"
										type="button"
										class="reveal-btn"
										@click="toggleReveal(f.key)"
										:aria-label="revealed[f.key] ? 'Gizle' : 'Göster'"
										:title="revealed[f.key] ? 'Gizle' : 'Göster'"
									>
										<svg v-if="revealed[f.key]" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
											<line x1="1" y1="1" x2="23" y2="23" />
										</svg>
										<svg v-else width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
											<circle cx="12" cy="12" r="3" />
										</svg>
									</button>
								</div>
								<div v-if="f.help" class="field-help">{{ f.help }}</div>
							</div>
						</div>
					</section>

					<!-- Sync scope -->
					<section v-if="marketplace.supports && marketplace.supports.length" class="dr-section">
						<div class="dr-section-head">
							<div>
								<h5>Senkronize Edilecek Veriler</h5>
								<p class="dr-section-sub">
									Bağlantı kurulduğunda hangi veriler güncellensin?
								</p>
							</div>
						</div>

						<div class="scope-grid">
							<label
								v-for="s in marketplace.supports"
								:key="s"
								class="scope-card"
								:class="{ active: form.syncScopes.includes(s) }"
							>
								<input type="checkbox" :value="s" v-model="form.syncScopes" />
								<div class="sc-content">
									<div class="sc-head">
										<span class="sc-icon">{{ scopeLabel(s).icon }}</span>
										<strong>{{ scopeLabel(s).label }}</strong>
									</div>
									<div class="sc-desc">{{ scopeLabel(s).description }}</div>
								</div>
								<span class="sc-check" aria-hidden="true"></span>
							</label>
						</div>
					</section>

					<!-- Auto-map hint when invoked from a category -->
					<section v-if="category" class="dr-section">
						<label class="auto-map-toggle">
							<input type="checkbox" v-model="form.autoMapAfterConnect" />
							<span class="amt-check" aria-hidden="true"></span>
							<span class="amt-text">
								<strong>Bağlantıdan sonra eşleştirme ekranını aç</strong>
								<span class="amt-sub">{{ category.name }} kategorisini {{ marketplace.name }}'e eşleştirmek için pazaryeri kategori yolunu sor</span>
							</span>
						</label>
					</section>
				</div>

				<div class="drawer-footer">
					<button class="btn btn-ghost" @click="close">Vazgeç</button>
					<button
						class="btn btn-primary btn-with-icon"
						:disabled="!canSubmit"
						@click="submit"
					>
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
							<path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
						</svg>
						Bağla
					</button>
				</div>
			</template>
		</aside>
	</Teleport>
</template>

<script setup>
import { reactive, computed, watch } from 'vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	marketplace: { type: Object, default: null },
	category: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const scopeLabels = {
	orders:   { label: 'Siparişler',   icon: '📦', description: 'Yeni siparişleri çek' },
	products: { label: 'Ürünler',      icon: '🏷️', description: 'Ürün katalogunu senkron tut' },
	stock:    { label: 'Stok',          icon: '📊', description: 'Stok adetlerini güncelle' },
	price:    { label: 'Fiyat',         icon: '💰', description: 'Fiyat değişikliklerini yansıt' },
	shipment: { label: 'Kargo',         icon: '🚚', description: 'Takip numarası ve durumu' },
}

function scopeLabel(key) {
	return scopeLabels[key] || { label: key, icon: '•', description: '' }
}

const defaultForm = () => ({
	credentials: {},
	syncScopes: [],
	autoMapAfterConnect: true,
})

const form = reactive(defaultForm())
const revealed = reactive({})

function resolveInputType(f) {
	if (f.type === 'password' && !revealed[f.key]) return 'password'
	return 'text'
}

function toggleReveal(key) {
	revealed[key] = !revealed[key]
}

const canSubmit = computed(() => {
	if (!props.marketplace?.fields?.length) return false
	return props.marketplace.fields.every((f) => {
		const v = form.credentials[f.key]
		return v !== undefined && v !== null && String(v).trim().length >= 3
	})
})

function close() {
	emit('update:modelValue', false)
}

function submit() {
	if (!canSubmit.value) return
	emit('submit', {
		marketplaceKey: props.marketplace.key,
		categoryId: props.category?.id ?? null,
		credentials: { ...form.credentials },
		syncScopes: [...form.syncScopes],
		autoMapAfterConnect: !!form.autoMapAfterConnect,
	})
}

// Drawer açıldığında: marketplace değişirse formu sıfırla ve default scope'ları doldur.
watch(
	() => [props.modelValue, props.marketplace?.key],
	([open]) => {
		if (open && props.marketplace) {
			Object.assign(form, defaultForm())
			form.syncScopes = [...(props.marketplace.supports || [])]
			// reveal flags reset
			Object.keys(revealed).forEach((k) => delete revealed[k])
		} else if (!open) {
			// kapatma animasyonu bitince sıfırla
			setTimeout(() => Object.assign(form, defaultForm()), 280)
		}
	},
	{ immediate: true }
)
</script>

<style scoped>
.marketplace-connect-drawer {
	width: 56vw;
	max-width: calc(100vw - 24px);
	min-width: 460px;
}

@media (max-width: 900px) {
	.marketplace-connect-drawer {
		width: 100vw;
		min-width: 0;
	}
}

.mp-logo {
	font-size: 11px;
	font-weight: 800;
	letter-spacing: 0.04em;
}

/* ── Context banner ── */
.context-banner {
	display: flex;
	gap: 12px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 12px;
	align-items: flex-start;
}
.cb-icon {
	width: 30px; height: 30px;
	border-radius: 8px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.cb-text { flex: 1; min-width: 0; }
.cb-title { font-size: 13px; color: #1a1a2e; line-height: 1.45; }
.cb-title strong { font-weight: 700; }
.cb-sub { font-size: 12px; color: #888; margin-top: 3px; line-height: 1.4; }

/* ── Sections ── */
.dr-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 16px;
	border-bottom: 1px solid #f5f5f8;
}
.dr-section:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.dr-section-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}
.dr-section-head h5 {
	font-size: 13px;
	font-weight: 700;
	color: #1a1a2e;
}
.dr-section-sub {
	font-size: 11.5px;
	color: #888;
	margin-top: 2px;
	line-height: 1.4;
}

.docs-link {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 11.5px;
	font-weight: 600;
	color: rgb(var(--color-primary));
	text-decoration: none;
	background: rgb(var(--color-primary-soft));
	padding: 5px 10px;
	border-radius: 7px;
	transition: background .15s;
	flex-shrink: 0;
}
.docs-link:hover { background: #dde2ff; }

/* ── Fields ── */
.fields-stack {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.field { display: flex; flex-direction: column; gap: 5px; }
.field label {
	font-size: 11.5px;
	font-weight: 600;
	color: #555;
}
.required { color: #ef4444; margin-left: 1px; }

.input-wrap {
	position: relative;
}

.form-input {
	width: 100%;
	height: 36px;
	padding: 0 12px;
	border: 1.5px solid #e8e8f0;
	border-radius: 8px;
	background: #fff;
	color: #1a1a2e;
	font-family: inherit;
	font-size: 13px;
	transition: border-color .15s, box-shadow .15s;
}
.form-input:focus {
	outline: none;
	border-color: rgb(var(--color-primary));
	box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.12);
}

.mono-input {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 12.5px;
	letter-spacing: 0.01em;
}

.reveal-btn {
	position: absolute;
	right: 6px;
	top: 50%;
	transform: translateY(-50%);
	width: 26px; height: 26px;
	border: none;
	background: transparent;
	color: #aaa;
	border-radius: 6px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background .15s, color .15s;
}
.reveal-btn:hover { background: #f0f0f5; color: #555; }

.input-wrap:has(.reveal-btn) .form-input {
	padding-right: 36px;
}

.field-help {
	font-size: 11px;
	color: #888;
	line-height: 1.4;
}

.empty-fields {
	padding: 14px;
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	font-size: 12.5px;
	color: #92400e;
}

/* ── Scope cards ── */
.scope-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 8px;
}
@media (max-width: 720px) {
	.scope-grid { grid-template-columns: 1fr; }
}

.scope-card {
	position: relative;
	display: flex;
	gap: 10px;
	padding: 10px 12px;
	background: #fafafe;
	border: 1.5px solid #ebebf0;
	border-radius: 10px;
	cursor: pointer;
	transition: border-color .15s, background .15s;
	user-select: none;
}
.scope-card:hover { border-color: #c8d0f8; }
.scope-card.active {
	background: rgb(var(--color-primary-soft));
	border-color: rgb(var(--color-primary));
}
.scope-card input { display: none; }

.sc-content { flex: 1; min-width: 0; }
.sc-head {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 2px;
}
.sc-icon { font-size: 13px; }
.sc-head strong {
	font-size: 12.5px;
	font-weight: 700;
	color: #1a1a2e;
}
.sc-desc {
	font-size: 11px;
	color: #888;
	line-height: 1.4;
}

.sc-check {
	width: 16px; height: 16px;
	border-radius: 5px;
	border: 1.5px solid #d0d0e0;
	background: #fff;
	flex-shrink: 0;
	margin-top: 2px;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all .15s;
}
.scope-card.active .sc-check {
	background: rgb(var(--color-primary));
	border-color: rgb(var(--color-primary));
}
.scope-card.active .sc-check::after {
	content: '';
	display: block;
	width: 4px; height: 7px;
	border: 2px solid #fff;
	border-top: none;
	border-left: none;
	transform: rotate(45deg) translate(-1px, -1px);
}

/* ── Auto-map toggle ── */
.auto-map-toggle {
	display: flex;
	gap: 10px;
	padding: 12px 14px;
	background: #fffbeb;
	border: 1.5px solid #fde68a;
	border-radius: 10px;
	cursor: pointer;
	align-items: flex-start;
	user-select: none;
}
.auto-map-toggle input { display: none; }

.amt-check {
	width: 16px; height: 16px;
	border-radius: 5px;
	border: 1.5px solid #d0a020;
	background: #fff;
	flex-shrink: 0;
	margin-top: 2px;
	transition: all .15s;
}
.auto-map-toggle input:checked + .amt-check {
	background: #d97706;
	border-color: #d97706;
	position: relative;
}
.auto-map-toggle input:checked + .amt-check::after {
	content: '';
	position: absolute;
	inset: 0;
	display: block;
	width: 4px; height: 7px;
	border: 2px solid #fff;
	border-top: none;
	border-left: none;
	transform: rotate(45deg) translate(4px, 2px);
}

.amt-text { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.amt-text strong {
	font-size: 12.5px;
	font-weight: 700;
	color: #92400e;
}
.amt-sub {
	font-size: 11px;
	color: #a16207;
	line-height: 1.4;
}

/* ── Footer ── */
.drawer-footer { display: flex; gap: 8px; }
.drawer-footer .btn { flex: 1; justify-content: center; }
.drawer-footer .btn-ghost { flex: 0 0 auto; }
</style>
