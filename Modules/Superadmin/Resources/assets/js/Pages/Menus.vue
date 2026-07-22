<template>
	<div class="menus-page">
		<header class="mp-header">
			<div>
				<h1>Menü Yönetimi</h1>
				<p>Sürükle-bırak ile sırala ve iç içe taşı. Kökler sidebar'da, alt menüler header'da görünür.</p>
			</div>
			<div class="mp-header-actions">
				<button class="btn-ghost" :disabled="!dirty" @click="resetTree">Geri Al</button>
				<button class="btn-primary" :disabled="!dirty || saving" @click="saveOrder">
					{{ saving ? 'Kaydediliyor…' : 'Sıralamayı Kaydet' }}
				</button>
				<button class="btn-primary" @click="openCreate(null)">+ Yeni Kök Menü</button>
			</div>
		</header>

		<div class="mp-body">
			<MenuTree
				:nodes="tree"
				:parent-id="null"
				:disabled="saving"
				class="mp-tree"
				@changed="dirty = true"
				@add-route="onAddRoute"
				@edit="openEdit"
				@add-child="openCreate"
				@remove="removeMenu"
			/>

			<RouteCatalog :routes="routes" :disabled="saving" class="mp-catalog" />
		</div>

		<AppModal
			v-model="modalOpen"
			:title="editing ? 'Menüyü Düzenle' : 'Yeni Menü'"
			size="md"
		>
			<form id="menu-form" class="mp-form" @submit.prevent="submitForm">
				<label>Etiket *
					<input v-model="form.label" type="text" maxlength="100" required />
				</label>

				<label>İkon
					<div class="mp-icon-picker">
						<button
							v-for="key in iconKeys"
							:key="key"
							type="button"
							class="mp-icon"
							:class="{ active: form.icon === key }"
							:title="key"
							@click="form.icon = (form.icon === key ? null : key)"
							v-html="renderMenuIcon(key, 16)"
						></button>
					</div>
				</label>

				<label>Route ismi
					<input v-model="form.route_name" type="text" maxlength="150" placeholder="products.index" />
				</label>

				<label>URL (path)
					<input v-model="form.url" type="text" maxlength="255" placeholder="/products" />
				</label>

				<label>İzin (permission)
					<select v-model="form.permission">
						<option :value="null">— Herkese açık —</option>
						<option v-for="p in permissions" :key="p.name" :value="p.name">{{ p.display_name ?? p.name }}</option>
					</select>
				</label>

				<label class="mp-check">
					<input v-model="form.is_active" type="checkbox" /> Aktif
				</label>
			</form>

			<template #footer="{ close }">
				<button type="button" class="btn btn-ghost" @click="close">Vazgeç</button>
				<button type="submit" form="menu-form" class="btn btn-primary" :disabled="saving">Kaydet</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { useToast } from '@/composables/useToast.js'
import { menuIconKeys, renderMenuIcon } from '@/menuIcons.js'
import MenuTree from '../Components/MenuTree.vue'
import RouteCatalog from '../Components/RouteCatalog.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')
const { showToast } = useToast()

const props = defineProps({
	menus: { type: Array, required: true },
	permissions: { type: Array, required: true },
	routes: { type: Array, default: () => [] },
})

const iconKeys = menuIconKeys

// Düz listeyi ağaca çevir (server düz gönderiyor).
function toTree(flat) {
	const byId = new Map(flat.map((m) => [m.id, { ...m, children: [] }]))
	const roots = []
	for (const node of byId.values()) {
		if (node.parent_id && byId.has(node.parent_id)) {
			byId.get(node.parent_id).children.push(node)
		} else {
			roots.push(node)
		}
	}
	// Açılışta tüm alt menülü düğümler kapalı gelsin.
		for (const node of byId.values()) {
			if (node.children.length) node.__collapsed = true
		}
		return roots
}

const tree = ref(toTree(props.menus))
const dirty = ref(false)
const saving = ref(false)

// Sunucudan menus prop'u yenilenince (create/edit/delete sonrası reload) ağacı tazele.
watch(() => props.menus, (val) => {
	tree.value = toTree(val)
	dirty.value = false
})

function resetTree() {
	clearTimeout(autoSaveTimer)
	tree.value = toTree(props.menus)
	dirty.value = false
}

// Ağacı reorder payload'ına düzleştir: her düğüm için id/parent_id/sort_order.
function flatten(nodes, parentId, acc) {
	nodes.forEach((node, index) => {
		acc.push({ id: node.id, parent_id: parentId, sort_order: index })
		if (node.children?.length) flatten(node.children, node.id, acc)
	})
	return acc
}

// WordPress'in menü editörü gibi: sürükle-bırak sonrası sayfayı yenilemeden,
// düz axios ile arka planda kaydeder. Inertia'nın router.post + back() akışı
// her kayıtta tüm sayfayı (route kataloğu taraması + cart/menu/notifications
// shared prop'ları dahil) yeniden render ettiği için kayıt gözle görülür
// yavaştı; axios ile sadece bu küçük JSON isteği gider, sayfa hiç yenilenmez.
let autoSaveTimer = null
let pendingSave = false

function saveOrder() {
	clearTimeout(autoSaveTimer)
	if (saving.value) {
		pendingSave = true
		return
	}
	saving.value = true
	const items = flatten(tree.value, null, [])
	window.axios.post(route('superadmin.menus.reorder'), { items })
		.then(() => {
			dirty.value = false
			showToast({ type: 'success', title: 'Menü sıralaması kaydedildi', duration: 1800 })
		})
		.catch((error) => {
			showToast({
				type: 'error',
				title: 'Sıralama kaydedilemedi',
				message: error.response?.data?.message ?? 'Lütfen tekrar deneyin.',
			})
		})
		.finally(() => {
			saving.value = false
			if (pendingSave) {
				pendingSave = false
				saveOrder()
			}
		})
}

// Her sürükle-bırakta otomatik kaydet (kısa bir debounce ile, art arda
// taşımalarda tek istek atılsın diye); "Sıralamayı Kaydet" butonu manuel/anında
// tetiklemek isteyenler için hâlâ duruyor.
watch(dirty, (isDirty) => {
	if (!isDirty) return
	clearTimeout(autoSaveTimer)
	autoSaveTimer = setTimeout(saveOrder, 600)
})

/* ── Form (create/edit) ── */
const modalOpen = ref(false)
const editing = ref(null) // düzenlenen menü ya da null
const form = ref(emptyForm())

function emptyForm() {
	return { label: '', icon: null, route_name: '', url: '', permission: null, is_active: true, parent_id: null }
}

function openCreate(parent) {
	editing.value = null
	form.value = { ...emptyForm(), parent_id: parent?.id ?? null }
	modalOpen.value = true
}

function openEdit(menu) {
	editing.value = menu
	form.value = {
		label: menu.label,
		icon: menu.icon,
		route_name: menu.route_name ?? '',
		url: menu.url ?? '',
		permission: menu.permission ?? null,
		is_active: !!menu.is_active,
		parent_id: menu.parent_id ?? null,
	}
	modalOpen.value = true
}

function submitForm() {
	saving.value = true
	const payload = { ...form.value }
	const opts = {
		preserveScroll: true,
		onSuccess: () => { modalOpen.value = false; router.reload({ only: ['menus'] }) },
		onFinish: () => { saving.value = false },
	}
	if (editing.value) {
		router.put(route('superadmin.menus.update', editing.value.id), payload, opts)
	} else {
		router.post(route('superadmin.menus.store'), payload, opts)
	}
}

// Sağ panelden ağaca bırakılan route'tan anında menü öğesi oluştur.
// `saving`, POST + ardından gelen reload tamamlanana kadar true kalmalı;
// aksi halde reload sürerken başlayan yeni bir sürükleme Inertia'nın tek
// aktif visit'ini iptal edip ağacı tutarsız/donmuş bir durumda bırakıyordu.
function onAddRoute({ route: item, parentId, index }) {
	saving.value = true
	router.post(route('superadmin.menus.store'), {
		label: item.label,
		route_name: item.name,
		url: '',
		permission: null,
		is_active: true,
		parent_id: parentId,
		sort_order: index,
	}, {
		preserveScroll: true,
		onSuccess: () => {
			router.reload({
				only: ['menus', 'routes'],
				onFinish: () => { saving.value = false },
			})
		},
		onError: () => { saving.value = false },
	})
}

async function removeMenu(menu) {
	const ok = await $swal.dangerConfirm({ title: 'Menü silinsin mi?', html: `<b>${menu.label}</b> ve tüm alt menüleri kalıcı olarak silinecek.` })
	if (!ok) return
	router.delete(route('superadmin.menus.destroy', menu.id), {
		preserveScroll: true,
		onSuccess: () => router.reload({ only: ['menus'] }),
	})
}
</script>

<style scoped>
.menus-page { max-width: 100%; }
.mp-body { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
.mp-tree { min-width: 0; }
@media (max-width: 900px) {
	.mp-body { grid-template-columns: 1fr; }
}
.mp-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; flex-wrap: wrap; }
.mp-header h1 { font-size: 20px; font-weight: 700; color: #1a1a2e; }
.mp-header p { font-size: 13px; color: #888; margin-top: 4px; }
.mp-header-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-primary { background: #1a1a2e; color: #fff; border: none; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary:disabled { opacity: .5; cursor: default; }
.btn-ghost { background: #fff; color: #555; border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 14px; font-size: 13px; cursor: pointer; }
.btn-ghost:disabled { opacity: .5; cursor: default; }
.mp-form { display: flex; flex-direction: column; gap: 12px; }
.mp-form label { display: flex; flex-direction: column; gap: 5px; font-size: 12.5px; font-weight: 600; color: #444; }
.mp-form input[type=text], .mp-form select { border: 1px solid #e0e0ea; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
.mp-check { flex-direction: row !important; align-items: center; gap: 8px; }
.mp-icon-picker { display: grid; grid-template-columns: repeat(8, 1fr); gap: 6px; }
.mp-icon { width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border: 1px solid #e8e8f0; border-radius: 8px; background: #fff; color: #666; cursor: pointer; }
.mp-icon.active { border-color: rgb(var(--color-primary)); color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }

@media (max-width: 640px) {
	.mp-icon-picker { grid-template-columns: repeat(5, 1fr); }
}
</style>
