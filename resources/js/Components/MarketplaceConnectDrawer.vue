<script setup>
import { reactive, computed, watch } from 'vue'
import { OzDrawer, OzField, OzInput, OzSettingRow, OzSwitch, OzButton, OzIcon } from '@/Components/Oz'

const props = defineProps({
	marketplace: { type: Object, default: null },
	category: { type: Object, default: null },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['submit'])

const scopeLabels = {
	orders:   { label: 'Siparişler', icon: 'package', description: 'Yeni siparişleri çek' },
	products: { label: 'Ürünler', icon: 'tag', description: 'Ürün katalogunu senkron tut' },
	stock:    { label: 'Stok', icon: 'chart', description: 'Stok adetlerini güncelle' },
	price:    { label: 'Fiyat', icon: 'wallet', description: 'Fiyat değişikliklerini yansıt' },
	shipment: { label: 'Kargo', icon: 'truck', description: 'Takip numarası ve durumu' },
}

function scopeLabel(key) {
	return scopeLabels[key] || { label: key, icon: 'tag', description: '' }
}

const defaultForm = () => ({
	credentials: {},
	syncScopes: [],
	autoMapAfterConnect: true,
})

const form = reactive(defaultForm())

const canSubmit = computed(() => {
	if (!props.marketplace?.fields?.length) return false
	return props.marketplace.fields.every((f) => {
		const v = form.credentials[f.key]
		return v !== undefined && v !== null && String(v).trim().length >= 3
	})
})

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
	() => [open.value, props.marketplace?.key],
	([isOpen]) => {
		if (isOpen && props.marketplace) {
			Object.assign(form, defaultForm())
			form.syncScopes = [...(props.marketplace.supports || [])]
		} else if (!isOpen) {
			// kapatma animasyonu bitince sıfırla
			setTimeout(() => Object.assign(form, defaultForm()), 280)
		}
	},
	{ immediate: true },
)
</script>

<template>
	<OzDrawer v-if="marketplace" v-model:open="open" size="lg" :subtitle="marketplace.description || 'Pazaryeri entegrasyonu'" :persistent="false">
		<template #header>
			<div class="mp-logo" :style="{ background: marketplace.color, color: '#fff' }">{{ marketplace.logoText }}</div>
			<div class="titles">
				<h3>{{ marketplace.name }}</h3>
				<p>{{ marketplace.description || 'Pazaryeri entegrasyonu' }}</p>
			</div>
		</template>

		<!-- Context banner -->
		<div class="context-banner">
			<div class="cb-icon" :style="{ background: marketplace.color }"><OzIcon name="link" :size="14" :stroke-width="2.5" /></div>
			<div class="cb-text">
				<div class="cb-title">
					<template v-if="category">
						<b>{{ category.name }}</b> için <b>{{ marketplace.name }}</b> bağlantısı kurulacak
					</template>
					<template v-else>
						<b>{{ marketplace.name }}</b> bağlantısı kurulacak
					</template>
				</div>
				<div class="cb-sub">Bağlantı kurulduktan sonra kategori eşleştirmesi yapabilir, ürünleri senkronize edebilirsiniz.</div>
			</div>
		</div>

		<!-- Credentials section -->
		<section class="dr-section">
			<div class="dr-section-head">
				<div>
					<h5>Bağlantı Bilgileri</h5>
					<p class="dr-section-sub">Bilgileri {{ marketplace.name }} satıcı panelinden alabilirsiniz</p>
				</div>
				<a v-if="marketplace.docsUrl" :href="marketplace.docsUrl" target="_blank" rel="noopener" class="docs-link">
					API Dokümantasyonu
					<OzIcon name="link" :size="10" :stroke-width="2.5" />
				</a>
			</div>

			<p v-if="!(marketplace.fields && marketplace.fields.length)" class="empty-fields">Bu pazaryeri için kimlik bilgisi alanı tanımlı değil.</p>

			<div v-else class="fields-stack">
				<OzField v-for="f in marketplace.fields" :key="f.key" :label="f.label" required :hint="f.help">
					<OzInput
						v-model="form.credentials[f.key]"
						:type="f.type === 'password' ? 'password' : 'text'"
						:revealable="f.type === 'password'"
						class="mono"
						:placeholder="f.help || f.label"
						autocomplete="off"
					/>
				</OzField>
			</div>
		</section>

		<!-- Sync scope -->
		<section v-if="marketplace.supports && marketplace.supports.length" class="dr-section">
			<div class="dr-section-head">
				<div>
					<h5>Senkronize Edilecek Veriler</h5>
					<p class="dr-section-sub">Bağlantı kurulduğunda hangi veriler güncellensin?</p>
				</div>
			</div>

			<div class="scope-grid">
				<label v-for="s in marketplace.supports" :key="s" class="scope-card" :class="{ active: form.syncScopes.includes(s) }">
					<input v-model="form.syncScopes" type="checkbox" :value="s">
					<div class="sc-content">
						<div class="sc-head">
							<OzIcon :name="scopeLabel(s).icon" :size="15" />
							<b>{{ scopeLabel(s).label }}</b>
						</div>
						<div class="sc-desc">{{ scopeLabel(s).description }}</div>
					</div>
					<span class="sc-check" aria-hidden="true"></span>
				</label>
			</div>
		</section>

		<!-- Auto-map hint when invoked from a category -->
		<section v-if="category" class="dr-section">
			<OzSettingRow icon="link" title="Bağlantıdan sonra eşleştirme ekranını aç" :description="`${category.name} kategorisini ${marketplace.name}'e eşleştirmek için pazaryeri kategori yolunu sor`">
				<OzSwitch v-model="form.autoMapAfterConnect" aria-label="Bağlantıdan sonra eşleştirme ekranını aç" />
			</OzSettingRow>
		</section>

		<template #footer>
			<OzButton @click="open = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="link" :disabled="!canSubmit" @click="submit">Bağla</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.mp-logo { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; flex-shrink: 0; }
.titles { flex: 1; min-width: 0; }
.titles h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.titles p { margin: 3px 0 0; font-size: 12px; color: var(--oz-muted); }

.mono :deep(input) { font-family: ui-monospace, monospace; font-size: 12.5px; letter-spacing: 0.01em; }

/* ── Context banner ── */
.context-banner { display: flex; gap: 12px; padding: 12px 14px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 12px; align-items: flex-start; margin-bottom: 16px; }
.cb-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cb-text { flex: 1; min-width: 0; }
.cb-title { font-size: 13px; color: var(--oz-ink-soft); line-height: 1.45; }
.cb-sub { font-size: 12px; color: var(--oz-muted); margin-top: 3px; line-height: 1.4; }

/* ── Sections ── */
.dr-section { display: flex; flex-direction: column; gap: 12px; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--oz-border-soft); }
.dr-section:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }

.dr-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.dr-section-head h5 { margin: 0; font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.dr-section-sub { font-size: 11.5px; color: var(--oz-muted); margin: 2px 0 0; line-height: 1.4; }

.docs-link { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; color: var(--oz-accent); text-decoration: none; background: var(--oz-accent-soft); padding: 5px 10px; border-radius: 7px; transition: background .15s; flex-shrink: 0; }
.docs-link:hover { background: rgba(var(--oz-accent-rgb), .18); }

/* ── Fields ── */
.fields-stack { display: flex; flex-direction: column; gap: 12px; }
.empty-fields { padding: 14px; background: var(--oz-warning-soft); border: 1px solid rgba(var(--oz-warning-rgb), .3); border-radius: 10px; font-size: 12.5px; color: var(--oz-warning); margin: 0; }

/* ── Scope cards ── */
.scope-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
@media (max-width: 720px) { .scope-grid { grid-template-columns: 1fr; } }

.scope-card { position: relative; display: flex; gap: 10px; padding: 10px 12px; background: var(--oz-surface-2); border: 1.5px solid var(--oz-border); border-radius: 10px; cursor: pointer; transition: border-color .15s, background .15s; user-select: none; }
.scope-card:hover { border-color: var(--oz-border-strong); }
.scope-card.active { background: var(--oz-accent-soft); border-color: var(--oz-accent); }
.scope-card input { display: none; }

.sc-content { flex: 1; min-width: 0; }
.sc-head { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; color: var(--oz-ink-soft); }
.sc-head b { font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); }
.sc-desc { font-size: 11px; color: var(--oz-muted); line-height: 1.4; }

.sc-check { width: 16px; height: 16px; border-radius: 5px; border: 1.5px solid var(--oz-border-strong); background: var(--oz-surface); flex-shrink: 0; margin-top: 2px; display: flex; align-items: center; justify-content: center; transition: all .15s; }
.scope-card.active .sc-check { background: var(--oz-accent); border-color: var(--oz-accent); }
.scope-card.active .sc-check::after { content: ''; display: block; width: 4px; height: 7px; border: 2px solid #fff; border-top: none; border-left: none; transform: rotate(45deg) translate(-1px, -1px); }
</style>
