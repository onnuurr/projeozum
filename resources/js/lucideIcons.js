import { shallowReactive, markRaw } from 'vue'
import {
	LayoutGrid, Users, Package, ShoppingCart, Layers, Calendar, Scissors, Workflow, ChartColumn, Send, FileText, Star, Settings,
} from 'lucide-vue-next'

// Tüm Lucide ikonları, ada göre tembel yükleme. Her ikon ayrı küçük parça: yalnızca çizilen ikon indirilir.
// Ad = Lucide'ın kebab-case adı (https://lucide.dev/icons), ör. 'shopping-cart'. Menü ikonları (superadmin_menus.icon) bunu kullanır.
const loaders = import.meta.glob('../../node_modules/lucide-vue-next/dist/esm/icons/*.js', { import: 'default' })
const byName = Object.fromEntries(
	Object.entries(loaders)
		.map(([path, load]) => [path.slice(path.lastIndexOf('/') + 1, -3), load])
		.filter(([name]) => name !== 'index'),
)

export const lucideNames = Object.keys(byName).sort()

// Sık kullanılan menü ikonları zaten ana pakette: beklemeden çizilsin
const cache = shallowReactive(new Map(Object.entries({
	'layout-grid': LayoutGrid, users: Users, package: Package, 'shopping-cart': ShoppingCart, layers: Layers, calendar: Calendar,
	scissors: Scissors, workflow: Workflow, 'chart-column': ChartColumn, send: Send, 'file-text': FileText, star: Star, settings: Settings,
}).map(([k, v]) => [k, markRaw(v)])))
const pending = new Set()

export const hasLucide = (name) => !!byName[name]

// Bileşeni döner; henüz yüklenmediyse yüklemeyi başlatır ve null döner (reaktif: yüklenince yeniden çizilir)
export function lucideIcon(name) {
	if (!name) return null
	if (cache.has(name)) return cache.get(name)
	if (byName[name] && !pending.has(name)) {
		pending.add(name)
		byName[name]().then((c) => cache.set(name, markRaw(c))).finally(() => pending.delete(name))
	}
	return null
}
