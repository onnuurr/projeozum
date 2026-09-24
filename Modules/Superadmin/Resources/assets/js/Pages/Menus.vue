<script setup>
import { ref, computed, watch, inject, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzButton, OzPill, OzEmptyState, useOzConfirm } from '@/Components/Oz'
import MenuTree from '../Components/MenuTree.vue'
import RouteCatalog from '../Components/RouteCatalog.vue'
import MenuItemDrawer from '../Components/MenuItemDrawer.vue'

// Süper admin · menü yönetimi (Oz kit). Sol şerit, modül sekmeleri, mobil menü ve Ctrl K paleti bu ağaçtan beslenir.
//   Sıralama / taşıma: sürükle-bırak ya da ⋯ menüsü → 600 ms sonra arka planda POST superadmin.menus.reorder
//   Ekle / düzenle / sil / aktiflik: JSON istekleri (MenuController wantsJson) → ağaç + sol şerit yenilenir
defineOptions({ layout: AppLayout })

const props = defineProps({
	menus: { type: Array, required: true },
	permissions: { type: Array, required: true },
	routes: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const confirm = useOzConfirm()

/* ── ağaç (sunucu düz liste gönderir) ── */
function toTree(flat, openIds = new Set()) {
	const byId = new Map(flat.map((m) => [m.id, { ...m, children: [] }]))
	const roots = []
	for (const node of byId.values()) {
		if (node.parent_id && byId.has(node.parent_id)) byId.get(node.parent_id).children.push(node)
		else roots.push(node)
	}
	// Açılışta alt menülü düğümler kapalı; yenilemeden sonra açık olanlar açık kalır
	for (const node of byId.values()) if (node.children.length) node.__collapsed = !openIds.has(node.id)
	return roots
}
function openIdsOf(nodes, acc = new Set()) {
	for (const n of nodes) {
		if (n.children?.length && !n.__collapsed) acc.add(n.id)
		openIdsOf(n.children ?? [], acc)
	}
	return acc
}
const tree = ref(toTree(props.menus))
watch(() => props.menus, (val) => { tree.value = toTree(val, openIdsOf(tree.value)) })

const stats = computed(() => ({
	roots: props.menus.filter((m) => !m.parent_id).length,
	total: props.menus.length,
	inactive: props.menus.filter((m) => !m.is_active).length,
	restricted: props.menus.filter((m) => m.permission).length,
}))

// Ağaç + sol şerit (paylaşılan `menu` prop'u) + katalog yenilenir
function refresh(onFinish) {
	router.reload({ only: ['menus', 'routes', 'menu'], preserveScroll: true, preserveState: true, onFinish })
}

/* ── sıralama: otomatik arka plan kaydı ── */
const saveState = ref('idle') // idle | pending | saving | saved | error
let timer = null
let queued = false
function flatten(nodes, parentId, acc = []) {
	nodes.forEach((n, i) => {
		acc.push({ id: n.id, parent_id: parentId, sort_order: i })
		if (n.children?.length) flatten(n.children, n.id, acc)
	})
	return acc
}
function scheduleSave() {
	saveState.value = 'pending'
	clearTimeout(timer)
	timer = setTimeout(saveOrder, 600)
}
async function saveOrder() {
	clearTimeout(timer)
	if (saveState.value === 'saving') { queued = true; return }
	saveState.value = 'saving'
	try {
		await window.axios.post(route('superadmin.menus.reorder'), { items: flatten(tree.value, null) })
		saveState.value = 'saved'
		router.reload({ only: ['menu'], preserveScroll: true, preserveState: true })
	} catch (e) {
		saveState.value = 'error'
		showToast?.({ type: 'error', title: 'Sıralama kaydedilemedi', message: e.response?.data?.message ?? 'Lütfen tekrar deneyin.' })
		refresh()
	} finally {
		if (queued) { queued = false; saveOrder() }
	}
}
onBeforeUnmount(() => clearTimeout(timer))
const busy = computed(() => saveState.value === 'saving' || drawerBusy.value)
const saveLabel = computed(() => ({
	pending: { tone: 'neutral', text: 'Kaydedilecek…' },
	saving: { tone: 'info', text: 'Kaydediliyor…' },
	saved: { tone: 'success', text: 'Sıralama kaydedildi' },
	error: { tone: 'danger', text: 'Kaydedilemedi' },
})[saveState.value])

/* ── ekle / düzenle ── */
const drawerOpen = ref(false)
const drawerBusy = ref(false)
const editing = ref(null)
const createParent = ref(null)
const formErrors = ref({})
function openCreate(parent = null) {
	editing.value = null
	createParent.value = parent?.id ?? null
	formErrors.value = {}
	drawerOpen.value = true
}
function openEdit(menu) {
	editing.value = menu
	formErrors.value = {}
	drawerOpen.value = true
}
async function submit(payload) {
	drawerBusy.value = true
	formErrors.value = {}
	try {
		if (editing.value) await window.axios.put(route('superadmin.menus.update', editing.value.id), payload)
		else await window.axios.post(route('superadmin.menus.store'), payload)
		const title = editing.value ? 'Menü güncellendi' : 'Menü eklendi'
		refresh(() => {
			drawerBusy.value = false
			drawerOpen.value = false
			showToast?.({ type: 'success', title, message: payload.label })
		})
	} catch (e) {
		drawerBusy.value = false
		const errs = e.response?.data?.errors ?? {}
		formErrors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
		// Alan hataları panelde gösterilir; toast yalnızca alana bağlanamayan hatalar için (panel altbilgisini örtmesin)
		const shown = ['label', 'parent_id', 'route_name', 'url', 'permission']
		const other = Object.entries(formErrors.value).find(([k]) => !shown.includes(k))
		if (other || !Object.keys(formErrors.value).length) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: other?.[1] ?? e.response?.data?.message ?? 'Sunucu hatası.' })
	}
}

// Katalogdan sürüklenen ya da + ile eklenen rota → doğrudan menü öğesi
async function addRoute({ route: item, parentId = null, index = null }) {
	drawerBusy.value = true
	try {
		await window.axios.post(route('superadmin.menus.store'), {
			label: item.label, route_name: item.name, url: null, permission: null, is_active: true,
			parent_id: parentId, sort_order: index ?? tree.value.length,
		})
		refresh(() => {
			drawerBusy.value = false
			showToast?.({ type: 'success', title: 'Menüye eklendi', message: item.label })
		})
	} catch (e) {
		drawerBusy.value = false
		showToast?.({ type: 'error', title: 'Eklenemedi', message: e.response?.data?.message ?? 'Sunucu hatası.' })
		refresh()
	}
}

async function toggleActive(menu) {
	try {
		await window.axios.put(route('superadmin.menus.update', menu.id), {
			label: menu.label, icon: menu.icon, route_name: menu.route_name, url: menu.url,
			permission: menu.permission, parent_id: menu.parent_id, is_active: !menu.is_active,
		})
		refresh()
	} catch (e) {
		showToast?.({ type: 'error', title: 'Güncellenemedi', message: e.response?.data?.message ?? 'Sunucu hatası.' })
	}
}

async function remove(menu) {
	const count = (nodes) => (nodes ?? []).reduce((t, c) => t + 1 + count(c.children), 0)
	const n = count(menu.children)
	const ok = await confirm.danger('Menü silinsin mi?', `“${menu.label}”${n ? ` ve altındaki ${n} menü` : ''} kalıcı olarak silinecek. Sayfalar çalışmaya devam eder.`, { confirmText: 'Sil' })
	if (!ok) return
	try {
		await window.axios.delete(route('superadmin.menus.destroy', menu.id))
		refresh(() => showToast?.({ type: 'warning', title: 'Menü silindi', message: menu.label }))
	} catch (e) {
		showToast?.({ type: 'error', title: 'Silinemedi', message: e.response?.data?.message ?? 'Sunucu hatası.' })
	}
}
</script>

<template>
	<Head title="Menü Yönetimi · Süper Admin" />

	<OzPageHeader title="Menü Yönetimi" subtitle="Sol şerit, modül sekmeleri, mobil menü ve arama paletindeki menüleri düzenleyin">
		<template #actions>
			<OzButton variant="primary" icon="plus" @click="openCreate()">Yeni kök menü</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<div class="stat"><b>{{ stats.roots }}</b><span>kök menü</span></div>
		<div class="stat"><b>{{ stats.total }}</b><span>toplam öğe</span></div>
		<div class="stat"><b>{{ stats.inactive }}</b><span>pasif</span></div>
		<div class="stat"><b>{{ stats.restricted }}</b><span>izne bağlı</span></div>
	</div>

	<div class="layout">
		<OzCard title="Menü ağacı" subtitle="sürükleyin ya da ⋯ menüsünden taşıyın">
			<template #actions>
				<OzPill v-if="saveLabel" :tone="saveLabel.tone">{{ saveLabel.text }}</OzPill>
			</template>
			<div class="tree">
				<OzEmptyState v-if="!tree.length" icon="layers" title="Menü boş" description="Yeni kök menü ekleyin ya da katalogdan bir rota sürükleyin." compact />
				<MenuTree
					:nodes="tree"
					:parent-id="null"
					:disabled="busy"
					@changed="scheduleSave"
					@add-route="addRoute"
					@edit="openEdit"
					@add-child="openCreate"
					@remove="remove"
					@toggle-active="toggleActive"
				/>
			</div>
		</OzCard>

		<RouteCatalog :routes="routes" :disabled="busy" class="catalog" @add="(r) => addRoute({ route: r })" />
	</div>

	<MenuItemDrawer
		v-model:open="drawerOpen"
		:menu="editing"
		:parent-id="createParent"
		:tree="tree"
		:routes="routes"
		:permissions="permissions"
		:busy="drawerBusy"
		:errors="formErrors"
		@submit="submit"
	/>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.stat { display: flex; align-items: baseline; gap: 6px; padding: 12px 14px; border-radius: 12px; background: var(--oz-surface); border: 1px solid var(--oz-border); box-shadow: var(--oz-shadow-sm); }
.stat b { font-size: 20px; font-weight: 800; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
.stat span { font-size: 12px; color: var(--oz-muted); }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 14px; align-items: start; }
.tree { padding: 0 16px 16px; }
.catalog { position: sticky; top: 0; }
@media (max-width: 1100px) { .layout { grid-template-columns: minmax(0, 1fr); } .catalog { position: static; } }
@media (max-width: 640px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tree { padding: 0 10px 12px; } }
</style>
