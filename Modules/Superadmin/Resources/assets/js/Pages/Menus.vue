<template>
	<div class="menus-page">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Menü Yönetimi' },
			]"
		/>

		<PageHeader title="Menü Yönetimi" subtitle="Sürükle-bırak ile sırala ve iç içe taşı. Kökler sidebar'da, alt menüler header'da görünür.">
			<template #actions>
				<Button variant="ghost" size="sm" :disabled="!dirty" @click="resetTree">Geri Al</Button>
				<Button variant="primary" size="sm" :disabled="!dirty" :loading="saving" @click="saveOrder">
					Sıralamayı Kaydet
				</Button>
				<Button variant="primary" size="sm" with-icon @click="openCreate(null)">
					<template #leading><Plus :size="13" /></template>
					Yeni Kök Menü
				</Button>
			</template>
		</PageHeader>

		<div class="mp-body">
			<Card title="Menü Ağacı" class="mp-tree" body-class="p-3.5">
				<MenuTree
					:nodes="tree"
					:parent-id="null"
					:disabled="saving"
					@changed="dirty = true"
					@add-route="onAddRoute"
					@edit="openEdit"
					@add-child="openCreate"
					@remove="removeMenu"
				/>
			</Card>

			<RouteCatalog :routes="routesList" :disabled="saving" class="mp-catalog" />
		</div>

		<AppModal
			v-model="modalOpen"
			:title="editing ? 'Menüyü Düzenle' : 'Yeni Menü'"
			size="md"
		>
			<form id="menu-form" class="menu-form" @submit.prevent="submitForm">
				<FormField label="Etiket" required>
					<input v-model="form.label" type="text" class="form-input" maxlength="100" required />
				</FormField>

				<FormField label="İkon">
					<IconPicker v-model="form.icon" :icons="iconKeys" :render-icon="renderMenuIcon" />
				</FormField>

				<FormField label="Route ismi">
					<input v-model="form.route_name" type="text" class="form-input" maxlength="150" placeholder="products.index" />
				</FormField>

				<FormField label="URL (path)">
					<input v-model="form.url" type="text" class="form-input" maxlength="255" placeholder="/products" />
				</FormField>

				<FormField label="İzin (permission)">
					<select v-model="form.permission" class="form-input">
						<option :value="null">— Herkese açık —</option>
						<option v-for="p in permissions" :key="p.name" :value="p.name">{{ p.display_name ?? p.name }}</option>
					</select>
				</FormField>

				<Toggle v-model="form.is_active" label="Aktif" />
			</form>

			<template #footer="{ close }">
				<Button variant="ghost" @click="close">Vazgeç</Button>
				<Button type="submit" form="menu-form" variant="primary" :loading="saving">Kaydet</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, watch, inject } from 'vue'
import { Plus } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import Toggle from '@/Components/Toggle.vue'
import Card from '@/Components/Card.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import FormField from '../../../../Shared/Resources/assets/js/Components/FormField.vue'
import IconPicker from '../../../../Shared/Resources/assets/js/Components/IconPicker.vue'
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
// RouteCatalog'un kendi kopyası: create/add-route sonrası kullanılan route'u
// tam sayfa reload olmadan buradan düşürüyoruz (bkz. onAddRoute).
const routesList = ref([...props.routes])

// Sunucudan menus prop'u yenilenince (ör. tam sayfa navigasyonu/geri gelme) ağacı tazele.
watch(() => props.menus, (val) => {
	tree.value = toTree(val)
	dirty.value = false
})
watch(() => props.routes, (val) => { routesList.value = [...val] })

function resetTree() {
	clearTimeout(autoSaveTimer)
	tree.value = toTree(props.menus)
	dirty.value = false
}

// ── Yerel ağaç yardımcıları: create/edit/delete artık axios ile arka planda
// çalışıyor (bkz. saveOrder'daki yorum), bu yüzden sunucudan dönen sonucu
// tam bir Inertia reload yerine doğrudan `tree` içinde güncelliyoruz.
function findNode(nodes, id) {
	for (const node of nodes) {
		if (node.id === id) return node
		if (node.children?.length) {
			const found = findNode(node.children, id)
			if (found) return found
		}
	}
	return null
}

function childArrayFor(parentId) {
	if (parentId == null) return tree.value
	const parent = findNode(tree.value, parentId)
	if (!parent) return tree.value
	if (!parent.children) parent.children = []
	return parent.children
}

function removeNodeById(nodes, id) {
	const idx = nodes.findIndex((n) => n.id === id)
	if (idx !== -1) return nodes.splice(idx, 1)[0]
	for (const node of nodes) {
		if (node.children?.length) {
			const removed = removeNodeById(node.children, id)
			if (removed) return removed
		}
	}
	return null
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

// Create/edit de saveOrder gibi düz axios ile arka planda kaydediliyor —
// sayfa hiç yenilenmiyor, dönen kayıt doğrudan yerel ağaca yazılıyor.
function submitForm() {
	saving.value = true
	const payload = { ...form.value }
	const request = editing.value
		? window.axios.put(route('superadmin.menus.update', editing.value.id), payload)
		: window.axios.post(route('superadmin.menus.store'), payload)

	request
		.then(({ data }) => {
			if (editing.value) {
				const node = findNode(tree.value, editing.value.id)
				if (node) Object.assign(node, data.menu, { children: node.children, __collapsed: node.__collapsed })
				showToast({ type: 'success', title: 'Menü güncellendi', duration: 1800 })
			} else {
				childArrayFor(data.menu.parent_id).push({ ...data.menu, children: [] })
				showToast({ type: 'success', title: 'Menü eklendi', duration: 1800 })
			}
			modalOpen.value = false
		})
		.catch((error) => {
			showToast({
				type: 'error',
				title: editing.value ? 'Menü güncellenemedi' : 'Menü eklenemedi',
				message: error.response?.data?.message ?? 'Lütfen tekrar deneyin.',
			})
		})
		.finally(() => { saving.value = false })
}

// Sağ panelden ağaca bırakılan route'tan anında menü öğesi oluştur. Sürükleme
// anında vuedraggable, id'siz bir taslak düğümü (__route işaretli) zaten
// `childArrayFor(parentId)` dizisine yerleştirmiş oluyor; axios yanıtı gelince
// o taslağı gerçek kayıtla (id dahil) yerinde günceriyoruz — reload yok.
function onAddRoute({ route: item, parentId, index }) {
	saving.value = true
	const arr = childArrayFor(parentId)
	window.axios.post(route('superadmin.menus.store'), {
		label: item.label,
		route_name: item.name,
		url: '',
		permission: null,
		is_active: true,
		parent_id: parentId,
		sort_order: index,
	})
		.then(({ data }) => {
			const node = arr.find((n) => n.__route === item) ?? arr[index]
			if (node) {
				Object.assign(node, data.menu, { children: node.children ?? [] })
				delete node.__route
			}
			routesList.value = routesList.value.filter((r) => r.name !== item.name)
			showToast({ type: 'success', title: 'Menü eklendi', duration: 1800 })
		})
		.catch((error) => {
			// Başarısız oldu — yarım kalan taslak düğümü ağaçtan kaldır.
			const idx = arr.findIndex((n) => n.__route === item)
			if (idx !== -1) arr.splice(idx, 1)
			showToast({
				type: 'error',
				title: 'Menü eklenemedi',
				message: error.response?.data?.message ?? 'Lütfen tekrar deneyin.',
			})
		})
		.finally(() => { saving.value = false })
}

async function removeMenu(menu) {
	const ok = await $swal.dangerConfirm({ title: 'Menü silinsin mi?', html: `<b>${menu.label}</b> ve tüm alt menüleri kalıcı olarak silinecek.` })
	if (!ok) return
	window.axios.delete(route('superadmin.menus.destroy', menu.id))
		.then(() => {
			removeNodeById(tree.value, menu.id)
			showToast({ type: 'info', title: 'Menü silindi', duration: 1800 })
		})
		.catch((error) => {
			showToast({
				type: 'error',
				title: 'Menü silinemedi',
				message: error.response?.data?.message ?? 'Lütfen tekrar deneyin.',
			})
		})
}
</script>

<style scoped>
.menu-form {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.form-input {
	width: 100%;
	height: 36px;
	padding: 0 12px;
	border: 1.5px solid var(--color-outline-variant);
	border-radius: 8px;
	font-family: inherit;
	font-size: 13px;
	color: var(--color-ink);
	background: var(--color-surface-container-lowest);
	outline: none;
	transition: border-color 0.15s;
}

.form-input:focus {
	border-color: var(--color-primary);
	background: var(--color-surface);
}

.form-input::placeholder {
	color: var(--color-muted);
}

@media (max-width: 900px) {
	:deep(.mp-body) {
		grid-template-columns: 1fr;
	}
}
</style>
