<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzButton, OzAvatar, OzEmptyState,
	OzInput, OzCheckbox, OzAlert, OzTooltip, useOzConfirm,
} from '@/Components/Oz'

// Ürün × tüm aktif iş ortakları matrisi (Oz kit, yalnızca sistem yöneticisi).
//   Her satır kendi taslağını tutar; "Kaydet" → POST /products/{id}/tenants/{tenant} (override upsert),
//   sıfırla → DELETE (override silinir, iş ortağı kurala / varsayılana döner).
defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, required: true },
	rows: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const fmtPrice = (v) => `₺${Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const SOURCE = {
	default: { label: 'Varsayılan', tone: 'neutral' },
	rule_block: { label: 'Marka/kategori kuralı', tone: 'warning' },
	override_block: { label: 'Ürün override', tone: 'accent' },
	override_allow: { label: 'Ürün override', tone: 'accent' },
}

/* ── özet ── */
const openCount = computed(() => props.rows.filter((r) => r.effective === 'allowed').length)
const overrideCount = computed(() => props.rows.filter((r) => r.override_id).length)
const stats = computed(() => [
	{ key: 'total', label: 'Aktif iş ortağı', value: props.rows.length, icon: 'building', tone: 'accent' },
	{ key: 'open', label: 'Görebilen', value: openCount.value, icon: 'eye', tone: 'success' },
	{ key: 'hidden', label: 'Gizli', value: props.rows.length - openCount.value, icon: 'eye-off', tone: props.rows.length - openCount.value ? 'danger' : 'success' },
	{ key: 'override', label: 'Override', value: overrideCount.value, icon: 'layers', tone: 'info' },
])

/* ── satır taslakları ── */
// Taban durum etkin görünürlükten gelir: kuralla gizli bir satırda fiyat girmek ürünü sessizce açmasın.
const baseline = (r) => ({
	is_blocked: r.override_id ? !!r.override_is_blocked : !!r.rule_blocked,
	custom_price: r.custom_price ?? null,
	notes: r.notes ?? '',
})
const drafts = reactive({})
const busy = reactive({})
const draftOf = (r) => drafts[r.tenant_id] ?? baseline(r)
function setDraft(r, key, value) {
	drafts[r.tenant_id] = { ...draftOf(r), [key]: value }
}
function isDirty(r) {
	const d = drafts[r.tenant_id]
	if (!d) return false
	const b = baseline(r)
	return d.is_blocked !== b.is_blocked || (d.custom_price ?? null) !== (b.custom_price ?? null) || (d.notes ?? '') !== b.notes
}
const dirtyCount = computed(() => props.rows.filter(isDirty).length)
const onPrice = (r, v) => setDraft(r, 'custom_price', v === '' || v == null ? null : Number(v))

/* ── tablo ── */
const tableRows = computed(() => props.rows.map((r) => ({ ...r, id: r.tenant_id, type_label: r.tenant_type ?? '' })))
const columns = [
	{ key: 'tenant_name', label: 'İş ortağı', sortable: true, searchValue: (r) => `${r.tenant_name} ${r.tenant_code} ${r.tenant_type ?? ''}` },
	{ key: 'type_label', label: 'Tip', sortable: true, filter: { type: 'select', multiple: true } },
	{
		key: 'effective', label: 'Durum', sortable: true, searchable: false,
		filter: { type: 'select', options: [{ value: 'allowed', label: 'Açık' }, { value: 'blocked', label: 'Gizli' }] },
	},
	{ key: 'type_price', label: 'Tip fiyatı', sortable: true, align: 'right', searchable: false },
	{ key: 'custom_price', label: 'Özel fiyat', searchable: false, width: '150px' },
	{ key: 'visibility', label: 'Görünürlük', searchable: false },
	{ key: 'actions', label: '', align: 'right', searchable: false },
]

function save(r) {
	if (busy[r.tenant_id] || !isDirty(r)) return
	busy[r.tenant_id] = true
	const d = drafts[r.tenant_id]
	router.post(`/products/${props.product.id}/tenants/${r.tenant_id}`, {
		is_blocked: d.is_blocked,
		custom_price: d.is_blocked ? null : d.custom_price,
		notes: d.notes,
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			delete drafts[r.tenant_id]
			showToast?.({ type: 'success', title: 'Kaydedildi', message: `${r.tenant_name} için ayarlar uygulandı.` })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy[r.tenant_id] = false },
	})
}

function discard(r) {
	delete drafts[r.tenant_id]
}

async function reset(r) {
	const ok = await confirm.warning(
		'Override sıfırlansın mı?',
		`“${r.tenant_name}” için override silinecek; iş ortağı marka/kategori kuralına ve varsayılan fiyata döner.`,
		{ confirmText: 'Sıfırla' },
	)
	if (!ok) return
	router.delete(`/products/${props.product.id}/tenants/${r.tenant_id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			delete drafts[r.tenant_id]
			showToast?.({ type: 'warning', title: 'Override silindi', message: r.tenant_name })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}
</script>

<template>
	<Head :title="`İş Ortağı Ayarları · ${product.name}`" />

	<OzPageHeader :title="product.name">
		<template #subtitle>
			İş ortağı ayarları · <span class="mono">{{ product.sku }}</span>
			<template v-if="product.brand_name"> · {{ product.brand_name }}</template>
			<template v-if="product.category_name"> · {{ product.category_name }}</template>
			· baz fiyat <b>{{ fmtPrice(product.default_price) }}</b>
		</template>
		<template #actions>
			<OzButton icon="chevron-left" @click="router.visit('/products')">Katalog</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzAlert tone="info" title="Yalnızca sistem yöneticisi düzenleyebilir" class="block">
		Tüm aktif iş ortakları listelenir; durum etiketi kaynağı gösterir (varsayılan, marka/kategori kuralı ya da ürün override).
		Satırda değişiklik yapıp <b>Kaydet</b>'e basınca o iş ortağı için ürün override'ı yazılır.
	</OzAlert>

	<OzCard title="İş ortakları" :meta="dirtyCount ? '' : `${rows.length} aktif iş ortağı`" class="block">
		<template v-if="dirtyCount" #actions>
			<OzPill tone="warning">{{ dirtyCount }} satırda kaydedilmemiş değişiklik</OzPill>
		</template>
		<OzTable
			:columns="columns"
			:rows="tableRows"
			:page-size="25"
			:default-sort="{ key: 'tenant_name', dir: 'asc' }"
			searchable
			search-placeholder="İş ortağı ara…"
			min-width="940px"
		>
			<template #cell-tenant_name="{ row }">
				<div class="who">
					<OzAvatar :name="row.tenant_name" :size="28" tone="auto" shape="square" />
					<div class="who-text">
						<b>{{ row.tenant_name }} <span v-if="isDirty(row)" class="dot" title="Kaydedilmemiş değişiklik" /></b>
						<span class="mono">{{ row.tenant_code }}</span>
					</div>
				</div>
			</template>
			<template #cell-type_label="{ value }">
				<OzPill v-if="value" tone="info">{{ value }}</OzPill>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-effective="{ row }">
				<div class="status">
					<OzPill :tone="row.effective === 'allowed' ? 'success' : 'danger'">{{ row.effective === 'allowed' ? 'Açık' : 'Gizli' }}</OzPill>
					<span class="src" :class="`is-${SOURCE[row.source]?.tone ?? 'neutral'}`">{{ SOURCE[row.source]?.label ?? row.source }}</span>
				</div>
			</template>
			<template #cell-type_price="{ value }">
				<span v-if="value !== null" class="num">{{ fmtPrice(value) }}</span>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-custom_price="{ row }">
				<OzInput
					type="number"
					min="0"
					step="0.01"
					suffix="₺"
					class="price"
					:model-value="draftOf(row).custom_price ?? ''"
					:placeholder="Number(row.type_price ?? row.default_price ?? 0).toFixed(2)"
					:disabled="draftOf(row).is_blocked"
					:aria-label="`${row.tenant_name} özel fiyat`"
					@update:model-value="(v) => onPrice(row, v)"
					@keydown.enter="save(row)"
				/>
			</template>
			<template #cell-visibility="{ row }">
				<OzCheckbox :model-value="draftOf(row).is_blocked" label="Gizle" @update:model-value="(v) => setDraft(row, 'is_blocked', v)" />
			</template>
			<template #cell-actions="{ row }">
				<div class="acts">
					<OzTooltip v-if="isDirty(row)" text="Değişikliği geri al">
						<OzButton size="sm" icon="undo" aria-label="Değişikliği geri al" @click="discard(row)" />
					</OzTooltip>
					<OzTooltip v-else-if="row.override_id" text="Override'ı sil (varsayılana dön)">
						<OzButton size="sm" icon="refresh" aria-label="Override'ı sıfırla" @click="reset(row)" />
					</OzTooltip>
					<OzButton size="sm" variant="primary" icon="check" :disabled="!isDirty(row)" :loading="!!busy[row.tenant_id]" @click="save(row)">Kaydet</OzButton>
				</div>
			</template>
			<template #empty="{ filtered, clear }">
				<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen iş ortağı yok" compact>
					<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
				</OzEmptyState>
				<OzEmptyState v-else icon="building" title="Aktif iş ortağı bulunamadı" compact />
			</template>
		</OzTable>
	</OzCard>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim { color: var(--oz-muted-2); }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--oz-ink-soft); }
.who { display: flex; align-items: center; gap: 10px; min-width: 0; }
.who-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.who-text b { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; }
.who-text span { font-size: 11px; color: var(--oz-muted); }
.dot { width: 7px; height: 7px; border-radius: 50%; background: var(--oz-warning); }
.status { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; }
.src { font-size: 11px; font-weight: 600; white-space: nowrap; color: var(--oz-muted); }
.src.is-warning { color: var(--oz-warning); }
.src.is-accent { color: var(--oz-accent); }
.price { width: 140px; }
.acts { display: flex; justify-content: flex-end; align-items: center; gap: 6px; }

@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
