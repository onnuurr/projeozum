<script setup>
import { ref, computed, watch } from 'vue'
import { OzDrawer, OzField, OzInput, OzCombobox, OzSwitch, OzSettingRow, OzButton, OzIconPicker, OzIcon } from '@/Components/Oz'
import { menuIconOptions, ozIconForMenu, menuIconName } from '@/ozMenuIcons.js'

// Menü öğesi ekleme / düzenleme paneli (saas Menü Yönetimi'ndeki MenuItemModal deseni, DB alanlarıyla).
// Kök menü sol şeritte, alt menüler modül sekmelerinde ve rail panelinde görünür.
const props = defineProps({
	menu: { type: Object, default: null },      // düzenlenen düğüm (yoksa yeni)
	parentId: { type: [Number, null], default: null },
	tree: { type: Array, default: () => [] },   // konum seçenekleri için
	routes: { type: Array, default: () => [] }, // RouteCatalog::forMenu (menüde olmayanlar)
	permissions: { type: Array, default: () => [] },
	busy: { type: Boolean, default: false },
	errors: { type: Object, default: () => ({}) },
})
const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['submit'])

const ROOT = '__root__'
const blank = () => ({ label: '', icon: '', route_name: '', url: '', permission: '', is_active: true, parent: ROOT })
const form = ref(blank())
const touched = ref(false)

watch(open, (v) => {
	if (!v) return
	touched.value = false
	const m = props.menu
	form.value = m
		? { label: m.label, icon: menuIconName(m.icon), route_name: m.route_name ?? '', url: m.url ?? '', permission: m.permission ?? '', is_active: !!m.is_active, parent: m.parent_id ?? ROOT }
		: { ...blank(), parent: props.parentId ?? ROOT }
})

const isEdit = computed(() => !!props.menu)
const isRoot = computed(() => form.value.parent === ROOT)

// Konum: kök ya da herhangi bir menünün altı (düzenlenen öğe ve kendi alt ağacı hariç — döngü olmasın)
function descendantIds(node, acc = new Set()) {
	for (const c of node?.children ?? []) { acc.add(c.id); descendantIds(c, acc) }
	return acc
}
const parentOptions = computed(() => {
	const blocked = props.menu ? new Set([props.menu.id, ...descendantIds(props.menu)]) : new Set()
	const out = [{ value: ROOT, label: 'Kök menü (sol şerit)' }]
	const walk = (nodes, depth) => {
		for (const n of nodes) {
			if (n.id == null || blocked.has(n.id)) continue
			out.push({ value: n.id, label: `${'— '.repeat(depth)}${n.label}`, description: n.children?.length ? `${n.children.length} alt menü` : '' })
			walk(n.children ?? [], depth + 1)
		}
	}
	walk(props.tree, 0)
	return out
})

const routeOptions = computed(() => {
	const list = props.routes.map((r) => ({ value: r.name, label: r.label, description: `${r.uri} · ${r.module}` }))
	const cur = form.value.route_name
	if (cur && !list.some((o) => o.value === cur)) list.unshift({ value: cur, label: cur, description: 'mevcut rota' })
	return [{ value: '', label: 'Rota yok', description: 'URL kullan ya da boş bırak' }, ...list]
})
const permissionOptions = computed(() => [
	{ value: '', label: 'Herkese açık' },
	...props.permissions.map((p) => ({ value: p.name, label: p.display_name ?? p.name, description: p.display_name ? p.name : '' })),
])

// Rota seçilince etiket boşsa katalogdaki adla doldur
watch(() => form.value.route_name, (name) => {
	if (!form.value.label && name) form.value.label = props.routes.find((r) => r.name === name)?.label ?? ''
})

const labelError = computed(() => (touched.value && !form.value.label.trim() ? 'Etiket gerekli' : props.errors.label ?? ''))
const urlError = computed(() => (form.value.url && !form.value.url.startsWith('/') && !/^https?:\/\//.test(form.value.url) ? 'URL / ya da http(s):// ile başlamalı' : props.errors.url ?? ''))

function submit() {
	touched.value = true
	if (!form.value.label.trim() || urlError.value) return
	const f = form.value
	emit('submit', {
		label: f.label.trim(),
		icon: f.icon || null,
		route_name: f.route_name || null,
		url: f.url || null,
		permission: f.permission || null,
		is_active: f.is_active,
		parent_id: f.parent === ROOT ? null : f.parent,
	})
}
</script>

<template>
	<OzDrawer
		v-model:open="open"
		:title="isEdit ? `“${menu.label}” düzenle` : (isRoot ? 'Yeni kök menü' : 'Yeni alt menü')"
		:subtitle="isRoot ? 'Sol şeritte ve mobil alt menüde görünür' : 'Modülün sekmelerinde ve rail panelinde görünür'"
		:icon="isEdit ? 'edit' : 'plus'"
		size="md"
		:persistent="busy"
		initial-focus="auto"
	>
		<form class="form" @submit.prevent="submit">
			<div class="preview">
				<span class="preview-ico" :class="{ child: !isRoot }"><OzIcon :name="form.icon ? ozIconForMenu(form.icon) : 'layers'" :size="18" /></span>
				<div>
					<b>{{ form.label || 'Başlıksız' }}</b>
					<span>{{ form.route_name || form.url || 'Bağlantı yok' }}</span>
				</div>
			</div>

			<OzField label="Etiket" required :error="labelError">
				<OzInput v-model="form.label" maxlength="100" placeholder="Örn. Siparişler" />
			</OzField>
			<OzField label="Konum" :error="errors.parent_id">
				<OzCombobox v-model="form.parent" :options="parentOptions" search-placeholder="Menü ara…" />
			</OzField>
			<OzField label="Rota" hint="Rota seçilirse URL'nin önüne geçer" :error="errors.route_name">
				<OzCombobox v-model="form.route_name" :options="routeOptions" placeholder="Rota yok" search-placeholder="Rota ya da sayfa ara…" />
			</OzField>
			<OzField label="URL (path)" hint="Rota yoksa kullanılır, ör. /products ya da /atelier?status=draft" :error="urlError">
				<OzInput v-model="form.url" icon="link" class="mono" maxlength="255" placeholder="/products" />
			</OzField>
			<OzField label="Kimler görür (izin)" :error="errors.permission">
				<OzCombobox v-model="form.permission" :options="permissionOptions" placeholder="Herkese açık" search-placeholder="İzin ara…" />
			</OzField>
			<OzField label="İkon" :hint="`Tüm Lucide ikonları (${menuIconOptions.length}) · İngilizce adla arayın, ör. truck, cart · seçili ikona tekrar tıklamak kaldırır`">
				<OzIconPicker v-model="form.icon" :options="menuIconOptions" :resolve="ozIconForMenu" :page-size="120" clearable max-height="220px" />
			</OzField>
			<OzSettingRow icon="eye" title="Aktif" description="Pasif menüler kimseye görünmez; kayıt silinmez.">
				<OzSwitch v-model="form.is_active" aria-label="Aktif" />
			</OzSettingRow>
			<button type="submit" hidden />
		</form>

		<template #footer>
			<OzButton :disabled="busy" @click="open = false">Vazgeç</OzButton>
			<OzButton variant="primary" :icon="isEdit ? 'check' : 'plus'" :loading="busy" @click="submit">{{ isEdit ? 'Güncelle' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.form { display: flex; flex-direction: column; gap: 14px; }
.preview { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; background: var(--oz-surface-2); border: 1px dashed var(--oz-border-strong); }
.preview-ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; background: var(--oz-accent); color: #fff; box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .3); flex-shrink: 0; }
.preview-ico.child { background: var(--oz-surface); color: var(--oz-accent); border: 1px solid var(--oz-border); box-shadow: none; }
.preview b { display: block; font-size: 13.5px; color: var(--oz-ink-soft); }
.preview span { font-size: 11.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.mono :deep(input) { font-family: ui-monospace, monospace; }
</style>
