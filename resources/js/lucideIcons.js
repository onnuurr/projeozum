import { shallowReactive, markRaw } from 'vue'
import {
	LayoutGrid, Users, Package, ShoppingCart, Layers, Calendar, Scissors, Workflow, ChartColumn, Send, FileText, Star, Settings,
	createLucideIcon,
} from 'lucide-vue-next'

// Sistemin ortak ikon kaydı — tüm ikon seçiciler (menü, kategori, …) ve DB'de ikon adı saklayan alanlar bunu kullanır.
// Üç kaynak, tek ad uzayı (değer = DB'de saklanan ad):
//   'shirt'          → Lucide (https://lucide.dev/icons), kebab-case ad
//   'lab:dress'      → Lucide Lab (@lucide/lab, https://lucide.dev/lab), aynı çizim stili
//   'oz:cocuk-etek'  → bizim ikonlarımız: resources/icons/custom/<ad>.svg (kurallar: o klasördeki README.md)
// Yeni bir SVG'yi o klasöre eklemek yeterli — sonraki build'de tüm seçicilerde görünür.
// Lucide/Lab ikonları ada göre tembel yüklenir (her ikon ayrı küçük parça), bizimkiler küçük olduğu için pakete gömülür.
const lucideLoaders = import.meta.glob('../../node_modules/lucide-vue-next/dist/esm/icons/*.js', { import: 'default' })
const labLoaders = import.meta.glob('../../node_modules/@lucide/lab/dist/esm/icons/*.js', { import: 'default' })
const customSources = import.meta.glob('../icons/custom/*.svg', { query: '?raw', import: 'default', eager: true })

const baseName = (path) => path.slice(path.lastIndexOf('/') + 1).replace(/\.(js|svg)$/, '')
const byFile = (loaders) => Object.entries(loaders).map(([path, v]) => [baseName(path), v]).filter(([name]) => name !== 'index')

// Lucide: modül zaten bileşen
const loaders = Object.fromEntries(byFile(lucideLoaders))
// Lab: modül bir iconNode dizisi → Lucide bileşenine sarılır
for (const [name, load] of byFile(labLoaders)) {
	loaders[`lab:${name}`] = () => load().then((node) => createLucideIcon(`lab-${name}`, node))
}

// Bizim SVG'ler → iconNode. Yalnızca düz şekil öğeleri desteklenir (Lucide kuralı: <g>, fill, transform yok).
const SHAPES = 'path|rect|circle|ellipse|line|polyline|polygon'
const ATTR_SKIP = new Set(['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'class', 'style'])
function parseSvg(raw) {
	const node = []
	for (const [, tag, attrs] of raw.matchAll(new RegExp(`<(${SHAPES})\\b([^>]*?)\\/?>`, 'g'))) {
		const props = {}
		for (const [, k, v] of attrs.matchAll(/([\w:-]+)\s*=\s*"([^"]*)"/g)) if (!ATTR_SKIP.has(k)) props[k] = v
		props.key = `${tag}-${node.length}`
		node.push([tag, props])
	}
	return node
}
// <title> varsa seçicide etiket (Türkçe arama) olarak kullanılır
export const iconLabels = {}
const custom = {}
for (const [path, raw] of Object.entries(customSources)) {
	const name = `oz:${baseName(path)}`
	custom[name] = markRaw(createLucideIcon(`oz-${baseName(path)}`, parseSvg(raw)))
	const title = raw.match(/<title>([^<]*)<\/title>/)?.[1]?.trim()
	if (title) iconLabels[name] = title
}

export const customIconNames = Object.keys(custom).sort()
export const labIconNames = Object.keys(loaders).filter((n) => n.startsWith('lab:')).sort()
export const lucideOnlyNames = Object.keys(loaders).filter((n) => !n.includes(':')).sort()
// Tüm adlar: bizimkiler → Lucide → Lab
export const lucideNames = [...customIconNames, ...lucideOnlyNames, ...labIconNames]

// Sık kullanılan menü ikonları zaten ana pakette: beklemeden çizilsin
const cache = shallowReactive(new Map([
	...Object.entries({
		'layout-grid': LayoutGrid, users: Users, package: Package, 'shopping-cart': ShoppingCart, layers: Layers, calendar: Calendar,
		scissors: Scissors, workflow: Workflow, 'chart-column': ChartColumn, send: Send, 'file-text': FileText, star: Star, settings: Settings,
	}).map(([k, v]) => [k, markRaw(v)]),
	...Object.entries(custom),
]))
const pending = new Set()

export const hasLucide = (name) => !!loaders[name] || !!custom[name]

// Bileşeni döner; henüz yüklenmediyse yüklemeyi başlatır ve null döner (reaktif: yüklenince yeniden çizilir)
export function lucideIcon(name) {
	if (!name) return null
	if (cache.has(name)) return cache.get(name)
	if (loaders[name] && !pending.has(name)) {
		pending.add(name)
		loaders[name]().then((c) => cache.set(name, markRaw(c))).finally(() => pending.delete(name))
	}
	return null
}

// OzIconPicker :options — önce `featured` (seçicinin bağlamına uygun olanlar), ardından bizim ikonlar ve tüm set.
// Yeni bir seçici eklenirse bunu kullanmalı ki yeni ikonlarımız oraya da otomatik düşsün.
// labels: featured için Türkçe etiketler { ad: 'etiket' } (arama + tooltip)
export function iconPickerOptions(featured = [], labels = {}) {
	const head = featured.filter(hasLucide)
	const seen = new Set(head)
	return [...head, ...lucideNames.filter((n) => !seen.has(n))]
		.map((name) => ({ value: name, icon: name, label: labels[name] ?? iconLabels[name] ?? name }))
}
