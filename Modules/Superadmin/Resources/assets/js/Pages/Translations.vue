<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzAlert, OzTabs, OzCombobox, OzField,
	OzInput, OzTextarea, OzTable, OzSwitch, OzCheckbox, OzModal, OzPill, OzEmptyState, useOzConfirm, useOzToast,
} from '@/Components/Oz'

// Süper admin · Dil Dosyaları (Oz kit). SaaS (lang/ + Modules/*/lang) ve Bagisto (paket lang'ları,
// lang/vendor override'ları) PHP dil dosyalarını düzenler, yeni anahtar ekler.
//   Seçim (kaynak/grup/dosya/diller) URL sorgusunda; değişiklikler sayfada birikir, "Kaydet" ile
//   tek PUT'ta yazılır. Yeni anahtar modalı kendi PUT'unu hemen gönderir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	sources: { type: Array, required: true },
	groups: { type: Array, default: () => [] },
	selection: { type: Object, required: true },
	rows: { type: Array, default: null },
	isNewFile: { type: Boolean, default: false },
	maxLocales: { type: Number, default: 4 },
	error: { type: String, default: null },
})

const page = usePage()
const confirm = useOzConfirm()
const toast = useOzToast()

const KEY_RE = /^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/
const FILE_RE = /^[A-Za-z0-9_-]{1,64}$/

/* ── seçim ── */
const source = computed(() => props.sources.find((s) => s.key === props.selection.source) ?? props.sources[0])
const group = computed(() => props.groups.find((g) => g.key === props.selection.group) ?? null)
const locales = computed(() => props.selection.locales ?? [])

const sourceTabs = computed(() => props.sources.map((s) => ({ key: s.key, label: s.label })))
const groupOptions = computed(() => props.groups.map((g) => ({
	value: g.key,
	label: g.label,
	description: `${g.files.length} dosya · ${g.locales.length} dil${g.hint ? ` · ${g.hint}` : ''}`,
})))
const fileOptions = computed(() => {
	const files = group.value?.files ?? []
	const list = files.map((f) => ({ value: f, label: `${f}.php` }))
	if (props.isNewFile && props.selection.file) list.push({ value: props.selection.file, label: `${props.selection.file}.php (yeni)` })
	return list
})
const localeOptions = computed(() => (group.value?.locales ?? []).map((l) => ({ value: l, label: l })))

const sourceModel = computed({ get: () => props.selection.source, set: (v) => go({ source: v, group: null, file: null, locales: null }) })
const groupModel = computed({ get: () => props.selection.group ?? '', set: (v) => go({ group: v, file: null }) })
const fileModel = computed({ get: () => props.selection.file ?? '', set: (v) => go({ file: v }) })
const localesModel = computed({
	get: () => locales.value,
	set: (v) => {
		if (!v.length) return
		if (v.length > props.maxLocales) { toast.warning('Çok fazla dil', `Aynı anda en fazla ${props.maxLocales} dil gösterilir.`); return }
		go({ locales: v })
	},
})

// Yeni dosya (yalnız SaaS): dosya kutusunda listede olmayan bir ad yazılınca "“x” ekle"
function createFile(name) {
	const clean = name.trim().replace(/\.php$/i, '')
	if (!FILE_RE.test(clean)) {
		toast.danger('Geçersiz dosya adı', 'Yalnız harf, rakam, "-" ve "_" kullanın.')
		return null
	}
	go({ file: clean })
	return { value: clean, label: `${clean}.php (yeni)` }
}

async function go(patch) {
	if (dirtyCount.value && !(await confirm({
		title: 'Kaydedilmemiş değişiklikler var',
		message: `${dirtyCount.value} değişiklik kaybolacak. Devam edilsin mi?`,
		tone: 'warning',
		confirmText: 'Değişiklikleri at',
	}))) return

	const next = { ...props.selection, ...patch }
	clearDrafts()
	router.get(route('superadmin.translations.index'), {
		source: next.source,
		group: next.group || undefined,
		file: next.file || undefined,
		locales: next.locales?.length ? next.locales.join(',') : undefined,
	}, { preserveScroll: false })
}

/* ── taslaklar (kaydedilmemiş değişiklikler) ── */
// drafts[locale][key] = yeni değer; orijinaliyle aynıya dönerse silinir
const drafts = reactive({})
const original = computed(() => {
	const map = {}
	for (const r of props.rows ?? []) map[r.key] = r.values
	return map
})
const originalOf = (locale, key) => original.value[key]?.[locale] ?? null
const valueOf = (locale, key) => (drafts[locale] && key in drafts[locale] ? drafts[locale][key] : originalOf(locale, key) ?? '')
const isDirty = (locale, key) => !!drafts[locale] && key in drafts[locale]

function setDraft(locale, key, value) {
	if (value === (originalOf(locale, key) ?? '')) {
		if (drafts[locale]) delete drafts[locale][key]
		return
	}
	;(drafts[locale] ??= {})[key] = value
}
function clearDrafts() {
	for (const l of Object.keys(drafts)) delete drafts[l]
}
const dirtyCount = computed(() => Object.values(drafts).reduce((n, d) => n + Object.keys(d).length, 0))

/* ── tablo ── */
const onlyMissing = ref(false)
const search = ref('')
// Bir satırda seçili dillerden en az birinde değer yok/boş mu (kaydedilmiş hâline göre)
const isMissing = (r) => locales.value.some((l) => r.values[l] == null || r.values[l] === '')
const tableRows = computed(() => (props.rows ?? [])
	.filter((r) => !onlyMissing.value || isMissing(r))
	.map((r) => ({ key: r.key, editable: r.editable, missing: isMissing(r), ...Object.fromEntries(locales.value.map((l) => [`loc_${l}`, r.values[l] ?? ''])) })))
const columns = computed(() => [
	{ key: 'key', label: 'Anahtar', sortable: true, width: '30%' },
	...locales.value.map((l) => ({ key: `loc_${l}`, label: l.toUpperCase(), sortable: false })),
])
const rowsAutosize = (v) => Math.min(6, Math.max(1, String(v ?? '').split('\n').length, Math.ceil(String(v ?? '').length / 70)))

/* ── özet ── */
const missingCount = computed(() => (props.rows ?? []).filter(isMissing).length)
const stats = computed(() => [
	{ key: 'keys', label: 'Anahtar', value: props.rows ? props.rows.length : '—', icon: 'key', tone: 'info', delta: props.selection.file ? `${props.selection.file}.php` : '' },
	{ key: 'missing', label: `Eksik çeviri · ${locales.value.join(', ')}`, value: props.rows ? missingCount.value : '—', icon: 'warning', tone: missingCount.value ? 'warning' : 'success', delta: missingCount.value ? 'en az bir dilde boş' : 'hepsi dolu', trend: missingCount.value ? 'warn' : 'up' },
	{ key: 'dirty', label: 'Kaydedilmemiş', value: dirtyCount.value, icon: 'edit', tone: dirtyCount.value ? 'accent' : 'info', delta: dirtyCount.value ? 'Kaydet ile yazılır' : '' },
	{ key: 'locales', label: 'Gruptaki dil', value: group.value?.locales.length ?? '—', icon: 'languages', tone: 'info', delta: group.value?.label ?? '' },
])

/* ── kaydet ── */
const saving = ref(false)
function put(changes, fillMissing, onDone) {
	saving.value = true
	router.put(route('superadmin.translations.update'), {
		source: props.selection.source,
		group: props.selection.group,
		file: props.selection.file,
		changes,
		fill_missing_locales: fillMissing,
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => { if (page.props.flash?.toast?.type !== 'error') onDone?.() },
		onFinish: () => { saving.value = false },
	})
}
function save() {
	if (!dirtyCount.value || saving.value) return
	put(JSON.parse(JSON.stringify(drafts)), false, clearDrafts)
}
async function discard() {
	if (await confirm({ title: 'Değişiklikler atılsın mı?', message: `${dirtyCount.value} kaydedilmemiş değişiklik geri alınacak.`, tone: 'warning', confirmText: 'At' })) clearDrafts()
}

/* ── yeni anahtar ── */
const addOpen = ref(false)
const add = reactive({ key: '', values: {}, fill: false, error: '' })
function openAdd() {
	// Arama kutusundaki metin bir anahtar ön ekine benziyorsa başlangıç değeri yapılır
	add.key = KEY_RE.test(search.value.trim()) ? `${search.value.trim().replace(/\.$/, '')}.` : ''
	add.values = Object.fromEntries(locales.value.map((l) => [l, '']))
	add.fill = source.value?.key === 'bagisto'
	add.error = ''
	addOpen.value = true
}
function submitAdd() {
	const key = add.key.trim()
	if (!KEY_RE.test(key)) { add.error = 'Harf, rakam, "-" ve "_"; gruplar nokta ile: sayfa.baslik'; return }
	if (original.value[key]) { add.error = 'Bu anahtar zaten var — tabloda düzenleyin.'; return }
	const changes = {}
	for (const [l, v] of Object.entries(add.values)) if (v !== '') changes[l] = { [key]: v }
	if (!Object.keys(changes).length) { add.error = 'En az bir dilde değer girin.'; return }
	put(changes, add.fill, () => { addOpen.value = false; search.value = key })
}

const canEdit = computed(() => !!props.rows && !!props.selection.file)
const readonlyCount = computed(() => (props.rows ?? []).filter((r) => !r.editable).length)

// Sayfadan ayrılırken kaydedilmemiş değişiklik uyarısı (sekme kapatma / yenileme)
function beforeUnload(e) { if (dirtyCount.value) { e.preventDefault(); e.returnValue = '' } }
watch(dirtyCount, (n, o) => {
	if (n && !o) window.addEventListener('beforeunload', beforeUnload)
	if (!n && o) window.removeEventListener('beforeunload', beforeUnload)
})
</script>

<template>
	<Head title="Dil Dosyaları · Süper Admin" />

	<OzPageHeader title="Dil Dosyaları">
		<template #subtitle>
			SaaS modüllerinin ve Bagisto paketlerinin <code>lang/&lt;dil&gt;/&lt;dosya&gt;.php</code> çevirilerini düzenleyin, yeni anahtar ekleyin
		</template>
		<template v-if="canEdit" #actions>
			<OzButton icon="plus" :disabled="saving" @click="openAdd">Anahtar ekle</OzButton>
			<OzButton variant="primary" icon="check" :loading="saving" :disabled="!dirtyCount || saving" @click="save">
				Kaydet<template v-if="dirtyCount"> ({{ dirtyCount }})</template>
			</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="error" tone="danger" title="Dil dosyaları okunamadı" class="block">{{ error }}</OzAlert>

	<OzCard class="block picker-card">
		<div class="picker">
			<OzField label="Kaynak" class="f-source">
				<OzTabs v-model="sourceModel" :tabs="sourceTabs" variant="segment" aria-label="Dil kaynağı" />
			</OzField>
			<OzField label="Grup" :hint="group?.hint ?? ''" class="f-group">
				<OzCombobox v-model="groupModel" :options="groupOptions" placeholder="Modül / paket seçin" search-placeholder="Grup ara…" :disabled="!groups.length" />
			</OzField>
			<OzField label="Dosya" :hint="source?.allowsNewFiles ? 'Yeni dosya için adını yazın' : ''" class="f-file">
				<OzCombobox
					v-model="fileModel"
					:options="fileOptions"
					placeholder="Dosya seçin"
					search-placeholder="Dosya ara…"
					:create="source?.allowsNewFiles ? createFile : null"
					create-text="dosyasını oluştur"
					:disabled="!group"
				/>
			</OzField>
			<OzField label="Diller" :hint="`En fazla ${maxLocales}`" class="f-locales">
				<OzCombobox v-model="localesModel" :options="localeOptions" multiple :max-tags="maxLocales" placeholder="Dil seçin" :disabled="!group" />
			</OzField>
		</div>
		<p v-if="source?.key === 'bagisto' && selection.group?.startsWith('packages/')" class="note">
			Paket dosyasını düzenliyorsunuz. Aynı anahtar <b>lang/vendor</b> override grubunda varsa orası geçerlidir.
			Bagisto güncellemesinde paket dosyaları çakışabilir.
		</p>
	</OzCard>

	<OzStatStrip :items="stats" />

	<OzCard v-if="rows" :title="`${selection.file}.php`" :subtitle="group?.label ?? ''" class="block">
		<template #actions>
			<OzPill v-if="isNewFile" tone="info">Yeni dosya — ilk kayıtta oluşur</OzPill>
			<OzPill v-if="readonlyCount" tone="neutral" :title="'Anahtarında nokta/boşluk olan ya da metin olmayan değerler buradan düzenlenemez'">{{ readonlyCount }} salt-okunur</OzPill>
		</template>

		<OzEmptyState
			v-if="!rows.length"
			icon="languages"
			:title="isNewFile ? 'Dosya henüz yok' : 'Bu dosyada anahtar yok'"
			description="“Anahtar ekle” ile ilk çeviriyi ekleyin."
			compact
		>
			<template #actions><OzButton variant="primary" icon="plus" @click="openAdd">Anahtar ekle</OzButton></template>
		</OzEmptyState>

		<OzTable
			v-else
			v-model:search="search"
			:columns="columns"
			:rows="tableRows"
			row-key="key"
			:page-size="50"
			searchable
			search-placeholder="Anahtar veya metin ara…"
			min-width="720px"
			empty-title="Eşleşen çeviri yok"
		>
			<template #toolbar>
				<OzSwitch v-model="onlyMissing" label="Yalnız eksikler" />
			</template>

			<template #cell-key="{ row }">
				<span class="key" :class="{ missing: row.missing }">{{ row.key }}</span>
			</template>

			<template v-for="l in locales" :key="l" #[`cell-loc_${l}`]="{ row }">
				<textarea
					v-if="row.editable"
					class="cell"
					:class="{ dirty: isDirty(l, row.key), empty: valueOf(l, row.key) === '' }"
					:rows="rowsAutosize(valueOf(l, row.key))"
					:value="valueOf(l, row.key)"
					:placeholder="originalOf(l, row.key) == null ? 'eksik' : 'boş'"
					:aria-label="`${row.key} · ${l}`"
					@input="setDraft(l, row.key, $event.target.value)"
				/>
				<span v-else class="ro">{{ valueOf(l, row.key) || '—' }}</span>
			</template>
		</OzTable>
	</OzCard>

	<OzCard v-else-if="!error" class="block">
		<OzEmptyState icon="languages" title="Dosya seçin" description="Yukarıdan bir grup ve dil dosyası seçin." compact />
	</OzCard>

	<!-- Kaydedilmemiş değişiklik çubuğu -->
	<Transition name="bar">
		<div v-if="dirtyCount" class="savebar" role="status">
			<span><b>{{ dirtyCount }}</b> kaydedilmemiş değişiklik</span>
			<OzButton size="sm" :disabled="saving" @click="discard">Vazgeç</OzButton>
			<OzButton size="sm" variant="primary" icon="check" :loading="saving" :disabled="saving" @click="save">Kaydet</OzButton>
		</div>
	</Transition>

	<OzModal v-model:open="addOpen" title="Yeni anahtar" :subtitle="`${group?.label ?? ''} · ${selection.file}.php`" icon="plus" :persistent="saving">
		<form class="add-form" @submit.prevent="submitAdd">
			<OzField label="Anahtar" hint="Gruplar nokta ile ayrılır: sayfa.baslik" :error="add.error" required>
				<OzInput v-model="add.key" v-autofocus placeholder="ornek.anahtar" autocomplete="off" spellcheck="false" />
			</OzField>
			<OzField v-for="l in locales" :key="l" :label="l.toUpperCase()">
				<OzTextarea v-model="add.values[l]" :rows="2" />
			</OzField>
			<OzCheckbox
				v-if="(group?.locales.length ?? 0) > locales.length"
				v-model="add.fill"
				label="Diğer dillere de ekle"
				:description="`Gruptaki diğer ${group.locales.length - locales.length} dilin dosyalarına, anahtar yoksa ilk girilen değerle eklenir.`"
			/>
		</form>
		<template #footer>
			<OzButton :disabled="saving" @click="addOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="plus" :loading="saving" @click="submitAdd">Ekle</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
code { font-family: ui-monospace, monospace; font-size: .92em; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.block { margin-bottom: 14px; }

.picker { display: grid; grid-template-columns: auto minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); gap: 12px 14px; align-items: start; padding: 16px 18px; }
.note { margin: -4px 18px 14px; font-size: 12px; color: var(--oz-muted); }

.key { font-family: ui-monospace, monospace; font-size: 12px; color: var(--oz-ink); word-break: break-all; }
.key.missing { color: var(--oz-warning); }
.cell {
	display: block; width: 100%; min-width: 160px; resize: vertical;
	padding: 6px 8px; border: 1px solid transparent; border-radius: 7px;
	background: transparent; color: var(--oz-ink); font: inherit; font-size: 12.5px; line-height: 1.45;
	transition: border-color .15s, background .15s;
}
.cell:hover { border-color: var(--oz-border); }
.cell:focus { outline: none; border-color: rgba(var(--oz-accent-rgb), .55); background: var(--oz-surface); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .12); }
.cell.empty { background: rgba(var(--oz-warning-rgb), .07); }
.cell.dirty { border-color: rgba(var(--oz-accent-rgb), .45); background: rgba(var(--oz-accent-rgb), .06); }
.cell::placeholder { color: var(--oz-warning); font-style: italic; }
.ro { font-size: 12.5px; color: var(--oz-muted); }

.savebar {
	position: sticky; bottom: 12px; z-index: 5; margin: 0 auto; width: fit-content;
	display: flex; align-items: center; gap: 10px; padding: 8px 10px 8px 16px;
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 12px;
	box-shadow: 0 12px 32px -8px rgba(15, 23, 42, .25); font-size: 12.5px;
}
.bar-enter-active, .bar-leave-active { transition: opacity .15s, transform .15s; }
.bar-enter-from, .bar-leave-to { opacity: 0; transform: translateY(8px); }

.add-form { display: flex; flex-direction: column; gap: 12px; }

@media (max-width: 1100px) {
	.picker { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
	.f-source { grid-column: 1 / -1; }
}
@media (max-width: 640px) {
	.picker { grid-template-columns: minmax(0, 1fr); }
}
</style>
