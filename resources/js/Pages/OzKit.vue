<script setup>
import { ref, reactive, computed } from 'vue'
import OzAppLayout from '@/Layouts/OzAppLayout.vue'
import {
  OzPageHeader, OzButton, OzStatTile, OzCard, OzLineChart, OzTable, OzPill,
  OzListItem, OzProgress, OzTimeline, OzIcon, OzAvatar, OzAvatarGroup, ozIcons, useOzTheme,
  OzTabs, OzModal, OzField, OzInput, OzTextarea, OzSelect, OzCheckbox, OzSwitch, OzRadioGroup,
  OzDropdown, OzTooltip, OzEmptyState, OzSkeleton, useOzToast, useOzPalette, OzDrawer,
  OzAlert, OzModuleTabs, OzSpinner, useOzLoadingBar, OzBreadcrumbs, OzAccordion, OzStepper, OzCombobox, OzFileDrop, OzBarChart, OzDonut, useOzConfirm,
} from '@/Components/Oz'
import WorkOrderModal from '@/Components/OzDemo/WorkOrderModal.vue'

// Oz kit vitrini: bileşenlerin tüm varyantları tek sayfada.

const { theme, toggleTheme } = useOzTheme()

/* ── form ── */
const partners = ['Yıldız Tekstil', 'Marmara Konfeksiyon', 'Ege Giyim', 'Anadolu Moda', 'Karya Tekstil']
const workshopOptions = [
  { value: 'dikim-1', label: 'Dikim Atölyesi 1' },
  { value: 'dikim-2', label: 'Dikim Atölyesi 2' },
  { value: 'kesim', label: 'Kesim & Baskı' },
  { value: 'kk', label: 'Kalite Kontrol', disabled: true },
]
const priorityOptions = [
  { value: 'normal', label: 'Normal', description: 'Sıradaki boş kapasiteye' },
  { value: 'high', label: 'Yüksek', description: 'Planın öne alınır' },
  { value: 'urgent', label: 'Acil', description: '24 saat içinde başlar' },
]

const demo = reactive({ search: '', amount: 58500, note: '', notify: true, archive: false, view: 'list', priority: 'high' })

/* ── tabs ── */
const tabsUnderline = [
  { key: 'genel', label: 'Genel', icon: 'home' },
  { key: 'siparisler', label: 'Siparişler', count: 12 },
  { key: 'onaylar', label: 'Onaylar', count: 3 },
  { key: 'arsiv', label: 'Arşiv', disabled: true },
]
const tabUnderline = ref('genel')
const tabsSegment = [
  { key: 'gun', label: 'Gün' },
  { key: 'hafta', label: 'Hafta' },
  { key: 'ay', label: 'Ay' },
]
const tabSegment = ref('hafta')

/* ── modal ── */
const toast = useOzToast()
const workOrderOpen = ref(false)
const confirmOpen = ref(false)
const confirmTarget = ref('#SP-10474')

function askDelete(id) {
  confirmTarget.value = id
  confirmOpen.value = true
}

// Demo: API yok, kaydetme gecikmesini taklit eder
function onWorkOrderSave(payload, done) {
  setTimeout(() => {
    done()
    toast.success('İş emri oluşturuldu', `${payload.partner} · ${payload.product} · ${payload.qty} adet`)
  }, 900)
}

function confirmDelete() {
  confirmOpen.value = false
  const id = confirmTarget.value
  toast.info('Sipariş silindi', `${id} listeden kaldırıldı`, {
    action: { label: 'Geri al', onClick: () => toast.success('Geri alındı', `${id} yeniden listede`) },
  })
}

/* ── toast ── */
const toastDemos = [
  { tone: 'success', label: 'Başarılı', title: 'Kaydedildi', description: 'Değişiklikler kaydedildi' },
  { tone: 'info', label: 'Bilgi', title: 'Senkronizasyon başladı', description: 'Trendyol ürünleri güncelleniyor' },
  { tone: 'warning', label: 'Uyarı', title: 'Kapasite %92', description: 'Dikim Atölyesi 1 dolmak üzere' },
  { tone: 'danger', label: 'Hata (kalıcı)', title: 'Bağlantı hatası', description: 'Sunucuya ulaşılamadı. Kapatana kadar ekranda kalır.', duration: 0 },
]

/* ── dropdown ── */
const rowActions = [
  { key: 'view', label: 'Görüntüle', icon: 'search' },
  { key: 'edit', label: 'Düzenle', icon: 'pen', hint: 'E' },
  { key: 'export', label: 'Dışa aktar', icon: 'download' },
  { divider: true },
  { key: 'archive', label: 'Arşivle (yetki yok)', icon: 'wallet', disabled: true },
  { key: 'delete', label: 'Sil', icon: 'x', danger: true },
]

function onRowAction(key, row) {
  if (key === 'delete') askDelete(row?.id ?? '#SP-10474')
  else if (key === 'view') openDetail(row ?? bigRows[0])
  else toast.info(`${rowActions.find((a) => a.key === key).label}`, row ? `${row.id} · ${row.partner}` : '')
}

/* ── gelişmiş tablo ── */
const statusMap = {
  production: { label: 'Üretimde', tone: 'info' },
  qc: { label: 'Kalite Kontrol', tone: 'warning' },
  ready: { label: 'Sevkiyata Hazır', tone: 'accent' },
  done: { label: 'Tamamlandı', tone: 'success' },
  late: { label: 'Gecikti', tone: 'danger' },
}
const demoProducts = ['Pamuklu Gömlek', 'Kadın Elbise', 'Erkek Ceket', 'Çocuk Tulum', 'Polar Mont', 'Denim Pantolon']
const demoStatuses = Object.keys(statusMap)
const bigRows = Array.from({ length: 23 }, (_, i) => ({
  id: `#SP-${10500 - i}`,
  partner: partners[(i * 3) % partners.length],
  product: demoProducts[(i * 5) % demoProducts.length],
  status: demoStatuses[(i * 7) % demoStatuses.length],
  amount: 12000 + ((i * 7919) % 70000),
  date: `2026-09-${String(22 - Math.floor(i / 2)).padStart(2, '0')}`,
}))
const bigColumns = [
  { key: 'id', label: 'Sipariş', class: 'oz-cell-id', sortable: true },
  { key: 'partner', label: 'İş Ortağı', class: 'oz-cell-strong', sortable: true, filter: { type: 'select', multiple: true } },
  { key: 'product', label: 'Ürün', sortable: true, filter: { type: 'select' } },
  {
    key: 'status', label: 'Durum', sortable: true, sortValue: (r) => statusMap[r.status].label,
    filter: { type: 'select', multiple: true, options: Object.entries(statusMap).map(([value, s]) => ({ value, label: s.label })) },
  },
  { key: 'date', label: 'Tarih', sortable: true, filter: { type: 'date' } },
  { key: 'amount', label: 'Tutar', align: 'right', class: 'oz-cell-amount', sortable: true, filter: { type: 'range', suffix: '₺', format: (n) => money(Number(n)) } },
  { key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]
const tableFilters = ref({})
const tableSearch = ref('')
const fmtDate = (iso) => new Date(iso).toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
const tableState = ref('data')
const tableStates = [
  { key: 'data', label: 'Veri' },
  { key: 'loading', label: 'Yükleniyor' },
  { key: 'empty', label: 'Boş' },
]
function money(n) { return `₺${n.toLocaleString('tr-TR')}` }

/* ── drawer ── */
const detailOpen = ref(false)
const detail = ref(null)
const detailTab = ref('summary')
const detailTabs = [
  { key: 'summary', label: 'Özet' },
  { key: 'history', label: 'Geçmiş', count: 4 },
]
const detailActions = [
  { key: 'edit', label: 'Düzenle', icon: 'pen' },
  { key: 'export', label: 'Dışa aktar', icon: 'download' },
  { divider: true },
  { key: 'delete', label: 'Sil', icon: 'x', danger: true },
]
const detailHistory = [
  { text: 'Sipariş oluşturuldu', sub: 'Elif Yalçın · 3 gün önce' },
  { text: 'Kalıp onaylandı', sub: 'Model K-204 · 2 gün önce' },
  { text: 'Kesime alındı', sub: 'Kesim & Baskı · dün' },
  { text: 'Dikim başladı', sub: 'Dikim Atölyesi 1 · 4 sa önce' },
]

function openDetail(row) {
  detail.value = row
  detailTab.value = 'summary'
  detailOpen.value = true
}

function onDetailAction(key) {
  if (key === 'delete') askDelete(detail.value.id)
  else toast.info(detailActions.find((a) => a.key === key).label, detail.value.id)
}

function shipDetail() {
  detailOpen.value = false
  toast.success('Sevkiyata hazırlandı', `${detail.value.id} · ${detail.value.partner}`)
}

const filtersOpen = ref(false)
const emptyFilters = () => ({ status: '', partners: [], from: '', to: '', lateOnly: false })
const filters = reactive(emptyFilters())
const statusFilterOptions = Object.entries(statusMap).map(([value, s]) => ({ value, label: s.label }))

function togglePartner(name, on) {
  const i = filters.partners.indexOf(name)
  if (on && i === -1) filters.partners.push(name)
  if (!on && i !== -1) filters.partners.splice(i, 1)
}

function applyFilters() {
  const n = bigRows.filter((r) =>
    (!filters.status || r.status === filters.status)
    && (!filters.partners.length || filters.partners.includes(r.partner))
    && (!filters.lateOnly || r.status === 'late'),
  ).length
  filtersOpen.value = false
  toast.info('Filtre uygulandı', `${n} sipariş eşleşti`)
}

/* ── palet ── */
const { openPalette } = useOzPalette()
const kitCommands = [
  {
    label: 'Vitrin',
    items: [
      { key: 'kit-toast', label: 'Örnek bildirim göster', icon: 'bell', keywords: 'toast', run: () => toast.success('Paletten geldi', 'Komutlar sayfaya özel eklenebilir') },
      { key: 'kit-order', label: 'Yeni iş emri formu', icon: 'plus', keywords: 'modal form', run: () => { workOrderOpen.value = true } },
    ],
  },
]

const tokens = [
  'accent', 'accent-soft', 'ink', 'ink-soft', 'muted', 'muted-2',
  'surface', 'surface-2', 'page-bg', 'border',
  'success', 'warning', 'danger', 'info',
]

const pillTones = ['info', 'warning', 'accent', 'success', 'danger', 'neutral']
const iconNames = Object.keys(ozIcons)
const iconQuery = ref('')
const filteredIcons = computed(() => {
  const q = iconQuery.value.trim().toLowerCase()
  return q ? iconNames.filter((n) => n.includes(q)) : iconNames
})

/* ── avatar ── */
const team = [
  { name: 'Elif Yalçın', status: 'online' },
  { name: 'Mehmet Kaya' },
  { name: 'Ayşe Demir' },
  { name: 'Can Öztürk' },
  { name: 'Zeynep Arslan' },
  { name: 'Burak Şahin' },
  { name: 'İrem Çelik' },
]
// Örnek fotoğraf: harici servise bağımlı olmamak için satır içi SVG
const demoPhoto = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c7d2fe"/><stop offset="1" stop-color="#818cf8"/></linearGradient></defs><rect width="80" height="80" fill="url(#g)"/><circle cx="40" cy="31" r="14" fill="#eef2ff"/><path d="M14 80c2-17 13-26 26-26s24 9 26 26z" fill="#eef2ff"/></svg>')

const chartLabels = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz']
const chartValues = ref([42, 58, 51, 77, 69, 88, 94])

function shuffleChart() {
  chartValues.value = chartValues.value.map(() => Math.round(30 + Math.random() * 80))
}

const tableColumns = [
  { key: 'id', label: 'Kod', class: 'oz-cell-id' },
  { key: 'name', label: 'Ad', class: 'oz-cell-strong' },
  { key: 'status', label: 'Durum' },
  { key: 'amount', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
]
const tableRows = [
  { id: '#A-101', name: 'Örnek satır', status: 'success', amount: '₺1.250' },
  { id: '#A-102', name: 'İkinci satır', status: 'warning', amount: '₺980' },
]

/* ── geri bildirim / gezinme ── */
const crumbs = [
  { label: 'Ana sayfa', to: '/dashboard', icon: 'home' },
  { label: 'Bileşenler', to: '/component-showcase' },
  { label: 'İş emirleri', href: '#' },
  { label: '#IE-2292' },
]
const confirm = useOzConfirm()
async function demoConfirm(kind) {
  let ok
  if (kind === 'plain') ok = await confirm({ title: 'Sevkiyata hazırlansın mı?', message: '#SP-10474 için kargo etiketi oluşturulacak.', confirmText: 'Hazırla' })
  else if (kind === 'danger') ok = await confirm.danger('Sipariş silinsin mi?', '#SP-10474 kalıcı olarak silinecek. Bu işlem geri alınamaz.')
  else ok = await confirm.danger('Partner kaldırılsın mı?', 'Yıldız Tekstil ve bağlı 12 sipariş kaldırılacak.', { requireText: 'SİL', confirmText: 'Kalıcı olarak sil' })
  ok ? toast.success('Onaylandı', 'await confirm(...) → true') : toast.info('Vazgeçildi', 'await confirm(...) → false')
}

/* ── stepper / accordion ── */
const orderSteps = [
  { key: 'model', label: 'Model', description: 'Kalıp ve beden' },
  { key: 'kumas', label: 'Kumaş', description: 'Tedarik' },
  { key: 'uretim', label: 'Üretim', description: 'Kesim · dikim' },
  { key: 'sevk', label: 'Sevkiyat', description: 'Paket · kargo' },
]
const orderStep = ref('kumas')
const stepIndex = computed(() => orderSteps.findIndex((s) => s.key === orderStep.value))
const stepBy = (d) => { orderStep.value = orderSteps[Math.min(Math.max(stepIndex.value + d, 0), orderSteps.length - 1)].key }
const faqItems = [
  { key: 'kargo', title: 'Kargo entegrasyonu', subtitle: 'Yurtiçi, Aras, MNG', icon: 'truck' },
  { key: 'odeme', title: 'Ödeme ve vade', subtitle: 'Partner bazında vade tanımı', icon: 'wallet' },
  { key: 'yetki', title: 'Kullanıcı yetkileri', icon: 'lock' },
  { key: 'kapali', title: 'Pazaryeri (yakında)', icon: 'store', disabled: true },
]
const faqOpen = ref(['kargo'])

/* ── combobox / dosya ── */
const partnerOptions = partners.map((p, i) => ({ value: p, label: p, description: ['İstanbul', 'Bursa', 'İzmir', 'Denizli', 'Muğla'][i] }))
const cbPartner = ref('')
const cbTags = ref(['Pamuk', 'Organik'])
const tagOptions = ['Pamuk', 'Keten', 'Polyester', 'Organik', 'Geri dönüştürülmüş', 'Yıkamalı', 'Oversize', 'Slim fit', 'Kışlık', 'Yazlık']
const files = ref([])
function onReject(list) {
  const why = { type: 'desteklenmeyen tür', size: 'boyut sınırı aşıldı', count: 'dosya sayısı sınırı' }
  toast.warning(`${list.length} dosya eklenmedi`, list.map((r) => `${r.file.name}: ${why[r.reason]}`).join(' · '))
}

/* ── grafikler ── */
const barLabels = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl']
const barValues = ref([320, 410, 380, 520, 610, 480, 700, 655, 590])
const shuffleBars = () => { barValues.value = barValues.value.map(() => Math.round(200 + Math.random() * 600)) }
const donutSegments = ref([
  { label: 'Üretimde', value: 42, tone: 'info' },
  { label: 'Kalite Kontrol', value: 18, tone: 'warning' },
  { label: 'Sevkiyata Hazır', value: 24, tone: 'accent' },
  { label: 'Tamamlandı', value: 61, tone: 'success' },
  { label: 'Gecikti', value: 7, tone: 'danger' },
])
const shuffleDonut = () => { donutSegments.value = donutSegments.value.map((s) => ({ ...s, value: Math.round(5 + Math.random() * 60) })) }

/* ── yükleyiciler ── */
const saving = ref(false)
function demoSave() {
  saving.value = true
  setTimeout(() => { saving.value = false; toast.success('Kaydedildi', 'OzButton :loading') }, 1600)
}
const loadingBar = useOzLoadingBar()
function demoLoadingBar() {
  loadingBar.start()
  setTimeout(() => loadingBar.done(), 1800)
}
const progressDemo = ref([
  { label: 'Dikim Atölyesi 1', value: 92 },
  { label: 'Dikim Atölyesi 2', value: 68 },
  { label: 'Kesim & Baskı', value: 104 },
])
const shuffleProgress = () => { progressDemo.value = progressDemo.value.map((p) => ({ ...p, value: Math.round(20 + Math.random() * 90) })) }

const usage = `import { OzCard, OzButton, OzPill } from '@/Components/Oz'
import OzAppLayout from '@/Layouts/OzAppLayout.vue'

<OzAppLayout active="overview">
  <OzPageHeader title="Başlık" subtitle="Alt başlık" />
  <OzCard title="Kart" meta="3 kayıt">...</OzCard>
</OzAppLayout>`
</script>

<template>
  <OzAppLayout :commands="kitCommands">
    <OzPageHeader title="Oz Bileşen Kütüphanesi" subtitle="Stitch Üretim Kontrol Paneli tasarım dilinden çıkarılan bileşenler · resources/js/components/oz">
      <template #actions>
        <OzButton :icon="theme === 'dark' ? 'sun' : 'moon'" @click="toggleTheme">
          {{ theme === 'dark' ? 'Açık Tema' : 'Koyu Tema' }}
        </OzButton>
      </template>
    </OzPageHeader>
    <OzModuleTabs />

    <div class="kit-grid">
      <OzCard title="Renk Token'ları" subtitle="--oz-*">
        <div class="swatches">
          <div v-for="t in tokens" :key="t" class="swatch">
            <span class="chip" :style="{ background: `var(--oz-${t})` }" />
            <code>--oz-{{ t }}</code>
          </div>
        </div>
      </OzCard>

      <OzCard title="OzButton" subtitle="variant · size · icon">
        <div class="row">
          <OzButton variant="primary" icon="plus">Birincil</OzButton>
          <OzButton icon="download">Ghost</OzButton>
          <OzButton variant="primary" disabled>Pasif</OzButton>
          <OzButton variant="danger">Tehlikeli</OzButton>
        </div>
        <div class="row">
          <OzButton variant="primary" size="sm">Onayla</OzButton>
          <OzButton size="sm">İncele</OzButton>
        </div>
      </OzCard>

      <OzCard title="OzPill" subtitle="tone">
        <div class="row">
          <OzPill v-for="t in pillTones" :key="t" :tone="t" :label="t" />
        </div>
      </OzCard>

      <OzCard title="OzAvatar · OzAvatarGroup" subtitle="name · src · tone · status · shape">
        <div class="avatar-demo">
          <div class="demo-row">
            <span class="demo-label">Boyut</span>
            <OzAvatar name="Elif Yalçın" :size="24" />
            <OzAvatar name="Elif Yalçın" :size="32" />
            <OzAvatar name="Elif Yalçın" :size="40" />
            <OzAvatar name="Elif Yalçın" :size="52" />
          </div>
          <div class="demo-row">
            <span class="demo-label">tone=auto</span>
            <OzAvatar v-for="u in team.slice(0, 6)" :key="u.name" :name="u.name" tone="auto" :size="34" />
          </div>
          <div class="demo-row">
            <span class="demo-label">Fotoğraf</span>
            <OzAvatar name="Elif Yalçın" :src="demoPhoto" :size="40" />
            <OzAvatar name="Kırık Bağlantı" src="/yok/foto.jpg" tone="auto" :size="40" />
            <span class="demo-hint">yüklenemezse baş harfler</span>
          </div>
          <div class="demo-row">
            <span class="demo-label">Durum</span>
            <OzAvatar name="Elif Yalçın" status="online" :size="36" />
            <OzAvatar name="Mehmet Kaya" tone="auto" status="away" :size="36" />
            <OzAvatar name="Ayşe Demir" tone="auto" status="busy" :size="36" />
            <OzAvatar name="Can Öztürk" tone="neutral" status="offline" :size="36" />
            <OzAvatar name="Ekip Hesabı" tone="auto" shape="square" status="online" :size="36" />
          </div>
          <div class="demo-row">
            <span class="demo-label">Grup</span>
            <OzAvatarGroup :users="team" :max="4" />
            <OzAvatarGroup :users="team.slice(0, 5)" :total="18" :max="3" :size="36" />
          </div>
          <div class="demo-row">
            <span class="demo-label">Kartta</span>
            <div class="team-chip">
              <OzAvatarGroup :users="team.slice(0, 3)" :size="24" />
              <span><b>Dikim Atölyesi 1</b> · 12 kişi</span>
            </div>
          </div>
        </div>
      </OzCard>
    </div>

    <OzCard title="OzIcon" subtitle="Lucide · components/oz/icons.js" class="mb">
      <template #actions>
        <OzInput v-model="iconQuery" icon="search" placeholder="İkon ara…" class="icon-search" />
      </template>
      <div class="icons">
        <div v-for="name in filteredIcons" :key="name" class="icon-cell" :title="name">
          <OzIcon :name="name" :size="18" />
          <span>{{ name }}</span>
        </div>
      </div>
      <p class="note">Kayıtta olmayan bir Lucide ikonu doğrudan da verilebilir: <code>import { Rocket } from 'lucide-vue-next'</code> → <code>&lt;OzIcon :name="Rocket" /&gt;</code></p>
    </OzCard>

    <div class="stats">
      <OzStatTile label="tone=accent · trend=up" value="128" icon="tag-plain" delta="+12" context="bu hafta" />
      <OzStatTile label="tone=info" value="34" icon="bag" tone="info" delta="%8" context="düne göre" />
      <OzStatTile label="tone=warning · trend=warn" value="9" icon="clock" tone="warning" delta="3 acil" trend="warn" />
      <OzStatTile label="tone=danger · trend=down" value="%4,2" icon="alert" tone="danger" delta="%1,1" context="geçen haftaya göre" trend="down" />
    </div>

    <div class="kit-grid">
      <OzCard title="OzLineChart" subtitle="values · labels · unit">
        <template #actions><OzButton size="sm" @click="shuffleChart">Veriyi değiştir</OzButton></template>
        <OzLineChart :values="chartValues" :labels="chartLabels" unit="adet" />
      </OzCard>

      <OzCard title="OzTable" link-text="Tümünü gör">
        <OzTable :columns="tableColumns" :rows="tableRows" min-width="0">
          <template #cell-status="{ value }"><OzPill :tone="value" :label="value" /></template>
        </OzTable>
      </OzCard>

      <OzCard title="OzListItem" meta="2 kayıt">
        <div class="pad-list">
          <OzListItem title="Kalıp Onayı — Model K-204" subtitle="Yıldız Tekstil · Kadın Elbise" meta="2 saat önce" icon="pen">
            <template #actions>
              <OzButton variant="primary" size="sm">Onayla</OzButton>
              <OzButton size="sm">İncele</OzButton>
            </template>
          </OzListItem>
          <OzListItem title="tone=info, aksiyonsuz" subtitle="Açıklama" icon="bell" tone="info" />
        </div>
      </OzCard>

      <OzCard title="OzProgress · OzTimeline">
        <div class="pad">
          <OzProgress label="ok (< 85)" :value="55" />
          <OzProgress label="warn (≥ 85)" :value="92" />
          <OzProgress label="over (> 100)" :value="104" />
          <OzTimeline :items="[{ text: 'İlk olay', sub: '12 dk önce' }, { text: 'İkinci olay', sub: '1 sa önce' }]" />
        </div>
      </OzCard>
    </div>

    <div class="kit-grid">
      <OzCard title="Form" subtitle="OzField · OzInput · OzSelect · OzTextarea">
        <div class="form-grid">
          <OzField label="Arama" hint="icon prop'u ile öndeki ikon">
            <OzInput v-model="demo.search" icon="search" placeholder="Sipariş no, ürün…" />
          </OzField>
          <OzField label="Tutar" hint="suffix prop'u ile birim">
            <OzInput v-model="demo.amount" type="number" suffix="₺" min="0" />
          </OzField>
          <OzField label="Hatalı alan" error="Bu alan zorunlu" required>
            <OzInput placeholder="Boş bırakıldı" />
          </OzField>
          <OzField label="Pasif alan">
            <OzInput model-value="Düzenlenemez" disabled />
          </OzField>
          <OzField label="Atölye" class="span-2">
            <OzSelect :options="workshopOptions" placeholder="Atölye seçin" />
          </OzField>
          <OzField label="Not" hint="maxlength verilince sayaç çıkar" class="span-2">
            <OzTextarea v-model="demo.note" :maxlength="200" placeholder="İş emrine not ekleyin…" />
          </OzField>
        </div>
      </OzCard>

      <OzCard title="Seçim kontrolleri" subtitle="OzCheckbox · OzSwitch · OzRadioGroup">
        <div class="pad">
          <OzCheckbox v-model="demo.archive" label="Tamamlananları arşivle" description="30 günden eski siparişler" />
          <OzCheckbox label="Pasif seçenek" disabled />
          <OzSwitch v-model="demo.notify" label="E-posta bildirimi" description="Onay beklendiğinde haber ver" />
          <OzField label="Görünüm (list, inline)">
            <OzRadioGroup v-model="demo.view" :options="[{ value: 'list', label: 'Liste' }, { value: 'grid', label: 'Izgara' }]" inline aria-label="Görünüm" />
          </OzField>
          <OzField label="Öncelik (cards)">
            <OzRadioGroup v-model="demo.priority" :options="priorityOptions" variant="cards" aria-label="Öncelik" />
          </OzField>
        </div>
      </OzCard>
    </div>

    <div class="kit-grid">
      <OzCard title="OzTabs" subtitle="variant=underline · panel slot'ları">
        <OzTabs v-model="tabUnderline" :tabs="tabsUnderline" aria-label="Örnek sekmeler">
          <template #genel><p class="tab-panel">Genel sekmesinin içeriği. Ok tuşları, Home ve End ile sekmeler arasında gezinebilirsiniz.</p></template>
          <template #siparisler><p class="tab-panel">12 sipariş listelenecek.</p></template>
          <template #onaylar><p class="tab-panel">3 onay bekliyor.</p></template>
        </OzTabs>
      </OzCard>

      <OzCard title="OzTabs" subtitle="variant=segment · yalnızca çubuk">
        <template #actions>
          <OzTabs v-model="tabSegment" :tabs="tabsSegment" variant="segment" aria-label="Zaman aralığı" />
        </template>
        <p class="tab-panel">Seçili aralık: <b>{{ tabsSegment.find((t) => t.key === tabSegment).label }}</b></p>
      </OzCard>
    </div>

    <OzCard title="OzModal" subtitle="size · icon · tone · persistent · footer slot" class="mb">
      <div class="row">
        <OzButton variant="primary" icon="plus" @click="workOrderOpen = true">Yeni İş Emri (form)</OzButton>
        <OzButton icon="alert" @click="askDelete('#SP-10474')">Onay penceresi (sm)</OzButton>
      </div>
    </OzCard>

    <WorkOrderModal v-model:open="workOrderOpen" :partners="partners" :workshops="workshopOptions" @save="onWorkOrderSave" />

    <OzModal v-model:open="confirmOpen" title="Siparişi sil?" icon="alert" tone="danger" size="sm">
      <p class="confirm-text"><b>{{ confirmTarget }}</b> kalıcı olarak silinecek. Bu işlem geri alınamaz.</p>
      <template #footer>
        <OzButton @click="confirmOpen = false">Vazgeç</OzButton>
        <OzButton variant="danger" @click="confirmDelete">Sil</OzButton>
      </template>
    </OzModal>

    <OzCard title="OzDrawer" subtitle="side · size · header-actions · sub-header · footer" class="mb">
      <div class="row">
        <OzButton variant="primary" icon="search" @click="openDetail(bigRows[0])">Sipariş detayı (sağ, md)</OzButton>
        <OzButton icon="settings" @click="filtersOpen = true">Filtreler (sol, sm)</OzButton>
      </div>
      <p class="note">Tablodaki ⋯ → Görüntüle de detayı açar. Drawer içinden “Sil” onay modalını üstüne açar; Esc önce yalnızca en üstteki pencereyi kapatır.</p>
    </OzCard>

    <OzDrawer v-model:open="detailOpen" :title="detail?.id" :subtitle="detail ? `${detail.partner} · ${detail.product}` : ''" icon="cart">
      <template #header-actions>
        <OzDropdown :items="detailActions" @select="onDetailAction" />
      </template>
      <template #sub-header>
        <OzTabs v-model="detailTab" :tabs="detailTabs" aria-label="Sipariş detayı" />
      </template>

      <template v-if="detail && detailTab === 'summary'">
        <dl class="kv">
          <dt>Durum</dt><dd><OzPill :tone="statusMap[detail.status].tone" :label="statusMap[detail.status].label" /></dd>
          <dt>İş ortağı</dt><dd>{{ detail.partner }}</dd>
          <dt>Ürün</dt><dd>{{ detail.product }}</dd>
          <dt>Tutar</dt><dd class="strong">{{ money(detail.amount) }}</dd>
          <dt>Tarih</dt><dd>{{ fmtDate(detail.date) }}</dd>
        </dl>
        <div class="drawer-section">
          <h4>Üretim ilerlemesi</h4>
          <OzProgress label="Kesim" :value="100" tone="ok" />
          <OzProgress label="Dikim" :value="64" />
          <OzProgress label="Kalite kontrol" :value="0" />
        </div>
        <OzField label="İç not" hint="Yalnızca ekip görür">
          <OzTextarea placeholder="Bu siparişle ilgili not ekleyin…" :rows="3" />
        </OzField>
      </template>
      <OzTimeline v-else-if="detail" :items="detailHistory" />

      <template #footer>
        <OzButton @click="detailOpen = false">Kapat</OzButton>
        <OzButton variant="primary" icon="check" @click="shipDetail">Sevkiyata hazırla</OzButton>
      </template>
    </OzDrawer>

    <OzDrawer v-model:open="filtersOpen" title="Filtreler" subtitle="Sipariş listesini daraltın" icon="settings" side="left" size="sm" initial-focus="auto">
      <div class="filter-stack">
        <OzField label="Durum">
          <OzSelect v-model="filters.status" :options="[{ value: '', label: 'Tümü' }, ...statusFilterOptions]" />
        </OzField>
        <OzField label="İş ortağı">
          <div class="check-list">
            <OzCheckbox
              v-for="p in partners"
              :key="p"
              :model-value="filters.partners.includes(p)"
              :label="p"
              @update:model-value="(on) => togglePartner(p, on)"
            />
          </div>
        </OzField>
        <div class="two">
          <OzField label="Başlangıç"><OzInput v-model="filters.from" type="date" /></OzField>
          <OzField label="Bitiş"><OzInput v-model="filters.to" type="date" /></OzField>
        </div>
        <OzSwitch v-model="filters.lateOnly" label="Yalnızca gecikenler" />
      </div>
      <template #footer>
        <OzButton @click="Object.assign(filters, emptyFilters())">Temizle</OzButton>
        <OzButton variant="primary" @click="applyFilters">Uygula</OzButton>
      </template>
    </OzDrawer>

    <div class="kit-grid">
      <OzCard title="Bildirim (toast)" subtitle="useOzToast()">
        <div class="row">
          <OzButton v-for="t in toastDemos" :key="t.tone" size="sm" @click="toast({ tone: t.tone, title: t.title, description: t.description, duration: t.duration })">
            {{ t.label }}
          </OzButton>
          <OzButton size="sm" @click="toast.info('Sipariş arşivlendi', '#SP-10471', { action: { label: 'Geri al', onClick: () => toast.success('Geri alındı') } })">
            Aksiyonlu
          </OzButton>
        </div>
        <p class="note">Sağ altta çıkar, üzerine gelince süre durur. En fazla 4 bildirim aynı anda görünür.</p>
      </OzCard>

      <OzCard title="OzDropdown · OzTooltip">
        <div class="row">
          <OzDropdown :items="rowActions" @select="(k) => onRowAction(k)" />
          <OzDropdown :items="rowActions.slice(0, 3)" placement="bottom-start" @select="(k) => onRowAction(k)">
            <template #trigger="{ toggle, attrs, open }">
              <OzButton icon="chevron-down" v-bind="attrs" :class="{ 'is-open': open }" @click.stop="toggle">Özel tetikleyici</OzButton>
            </template>
          </OzDropdown>
          <OzTooltip text="Siparişleri CSV olarak indir">
            <OzButton icon="download">Tooltip (üst)</OzButton>
          </OzTooltip>
          <OzTooltip text="Alta açılan ipucu" placement="bottom">
            <OzButton icon="bell">Tooltip (alt)</OzButton>
          </OzTooltip>
        </div>
        <p class="note">Menü ok tuşları, Home/End, Enter ve Esc ile kullanılır. Tablo ve kart içinde kırpılmaz.</p>
      </OzCard>
    </div>

    <OzCard title="OzTable" subtitle="searchable · column.filter · sortable · pageSize · loading · #empty" class="mb">
      <template #actions>
        <OzTabs v-model="tableState" :tabs="tableStates" variant="segment" aria-label="Tablo durumu" />
      </template>
      <OzTable
        :columns="bigColumns"
        :rows="tableState === 'empty' ? [] : bigRows"
        :loading="tableState === 'loading'"
        :page-size="8"
        :default-sort="{ key: 'id', dir: 'desc' }"
        searchable
        search-placeholder="Sipariş, iş ortağı, ürün ara…"
        v-model:search="tableSearch"
        v-model:filters="tableFilters"
      >
        <template #toolbar>
          <OzButton icon="download" @click="toast.info('Dışa aktarılıyor', 'Filtrelenmiş liste CSV olarak')">Dışa aktar</OzButton>
        </template>
        <template #cell-date="{ value }">{{ fmtDate(value) }}</template>
        <template #cell-status="{ value }">
          <OzPill :tone="statusMap[value].tone" :label="statusMap[value].label" />
        </template>
        <template #cell-amount="{ value }">{{ money(value) }}</template>
        <template #cell-actions="{ row }">
          <OzDropdown :items="rowActions" @select="(k) => onRowAction(k, row)" />
        </template>
        <template #empty="{ filtered, clear }">
          <OzEmptyState v-if="filtered" icon="filter" title="Filtrelere uyan sipariş yok" description="Aramayı değiştirin ya da bazı filtreleri kaldırın." compact>
            <template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
          </OzEmptyState>
          <OzEmptyState v-else icon="cart" title="Henüz sipariş yok" description="Filtrelere uyan sipariş bulunamadı. Filtreleri temizleyin veya yeni bir iş emri oluşturun.">
            <template #actions>
              <OzButton size="sm" @click="tableState = 'data'">Filtreyi temizle</OzButton>
              <OzButton variant="primary" size="sm" @click="workOrderOpen = true">Yeni İş Emri</OzButton>
            </template>
          </OzEmptyState>
        </template>
      </OzTable>
    </OzCard>

    <div class="kit-grid">
      <OzCard title="OzSkeleton" subtitle="text · rect · circle">
        <div class="pad">
          <div class="sk-row">
            <OzSkeleton variant="circle" width="40px" />
            <div class="sk-lines">
              <OzSkeleton width="55%" />
              <OzSkeleton width="35%" />
            </div>
          </div>
          <OzSkeleton variant="rect" />
        </div>
      </OzCard>

      <OzCard title="Arama paleti" subtitle="Ctrl K / ⌘K">
        <OzEmptyState icon="search" title="Her sayfada Ctrl K" description="Üst bardaki arama kutusu da paleti açar. Sayfalar kendi komutlarını :commands ile ekleyebilir — bu sayfa “Vitrin” grubunu ekliyor." compact>
          <template #actions>
            <OzButton size="sm" icon="search" @click="openPalette">Paleti aç</OzButton>
          </template>
        </OzEmptyState>
      </OzCard>
    </div>

    <OzCard title="OzAlert" subtitle="tone · title · dismissible · actions" class="mb">
      <div class="pad">
        <OzAlert tone="info" title="Senkronizasyon sürüyor">Trendyol ürünleri arka planda güncelleniyor, bu birkaç dakika sürebilir.</OzAlert>
        <OzAlert tone="warning" title="Kapasite %92" dismissible>
          Dikim Atölyesi 1 dolmak üzere. Yeni iş emirleri 2. atölyeye yönlendirilebilir.
          <template #actions><OzButton size="sm">Yönlendir</OzButton></template>
        </OzAlert>
        <OzAlert tone="success" title="Tüm faturalar gönderildi" dismissible />
        <OzAlert tone="danger" title="Ödeme alınamadı">Marmara Konfeksiyon · ₺58.500 · kart reddedildi.</OzAlert>
      </div>
    </OzCard>

    <div class="kit-grid">
      <OzCard title="OzBreadcrumbs · OzStepper" subtitle="to · href · clickable">
        <div class="pad">
          <OzBreadcrumbs :items="crumbs" />
          <OzStepper v-model="orderStep" :steps="orderSteps" clickable />
          <div class="demo-row">
            <OzButton size="sm" :disabled="stepIndex === 0" @click="stepBy(-1)">Geri</OzButton>
            <OzButton size="sm" variant="primary" :disabled="stepIndex === orderSteps.length - 1" @click="stepBy(1)">İleri</OzButton>
            <span class="demo-hint">Tamamlanan adımlara tıklanabilir</span>
          </div>
        </div>
      </OzCard>

      <OzCard title="Onay penceresi" subtitle="useOzConfirm() · Promise<boolean>">
        <div class="pad">
          <p class="note flush">Sayfaya modal eklemeden <code>await confirm(...)</code> ile onay alınır. <code>requireText</code> ile yazarak onay istenebilir.</p>
          <div class="demo-row">
            <OzButton icon="truck" @click="demoConfirm('plain')">Standart</OzButton>
            <OzButton variant="danger" icon="trash" @click="demoConfirm('danger')">Silme</OzButton>
            <OzButton icon="lock" @click="demoConfirm('typed')">Yazarak onay</OzButton>
          </div>
          <OzStepper v-model="orderStep" :steps="orderSteps" orientation="vertical" />
        </div>
      </OzCard>
    </div>

    <div class="kit-grid">
      <OzCard title="OzCombobox" subtitle="aranabilir · multiple · clearable">
        <div class="pad">
          <OzField label="İş ortağı" hint="Türkçe karakter duyarsız arama: “yildiz”, “IZMIR”">
            <OzCombobox v-model="cbPartner" :options="partnerOptions" placeholder="İş ortağı seçin" clearable />
          </OzField>
          <OzField label="Etiketler">
            <OzCombobox v-model="cbTags" :options="tagOptions" multiple placeholder="Etiket ekleyin" :max-tags="3" />
          </OzField>
          <code>partner: {{ cbPartner || '—' }} · etiketler: {{ cbTags.join(', ') || '—' }}</code>
        </div>
      </OzCard>

      <OzCard title="OzFileDrop" subtitle="accept · maxSize · multiple · @reject">
        <div class="pad">
          <OzField label="Teknik föy / görseller">
            <OzFileDrop v-model="files" accept=".pdf,image/*" :max-size="5 * 1024 * 1024" :max-files="4" multiple @reject="onReject" />
          </OzField>
        </div>
      </OzCard>
    </div>

    <OzCard title="OzAccordion" subtitle="multiple=false · ikon · disabled" class="mb">
      <div class="pad">
        <OzAccordion v-model="faqOpen" :items="faqItems">
          <template #kargo>Kargo firmaları ayarlar sayfasından bağlanır. Sevkiyata hazır siparişler için etiket otomatik oluşturulur ve takip numarası partnere e-posta ile iletilir.</template>
          <template #odeme>Her iş ortağı için ayrı vade (gün) ve limit tanımlanabilir. Vadesi geçen ödemeler genel bakışta uyarı olarak görünür.</template>
          <template #yetki>Roller: süper admin, yönetici, operatör. Operatörler yalnızca kendi atölyelerinin iş emirlerini görür.</template>
        </OzAccordion>
      </div>
    </OzCard>

    <div class="kit-grid">
      <OzCard title="OzBarChart" subtitle="values · labels · tone · unit">
        <template #actions><OzButton size="sm" icon="refresh" @click="shuffleBars">Karıştır</OzButton></template>
        <div class="pad">
          <OzBarChart :values="barValues" :labels="barLabels" unit="adet" />
        </div>
      </OzCard>

      <OzCard title="OzDonut" subtitle="segments · centerLabel">
        <template #actions><OzButton size="sm" icon="refresh" @click="shuffleDonut">Karıştır</OzButton></template>
        <div class="pad">
          <OzDonut :segments="donutSegments" center-label="Sipariş" />
        </div>
      </OzCard>
    </div>

    <div class="kit-grid">
      <OzCard title="Yükleyiciler" subtitle="OzSpinner · OzButton :loading · useOzLoadingBar()">
        <div class="pad">
          <div class="demo-row">
            <OzSpinner :size="16" />
            <OzSpinner :size="22" />
            <OzSpinner :size="30" />
            <OzSpinner :size="22" tone="muted" />
            <span class="demo-hint">motion-v ile döner, yay kısalıp uzar</span>
          </div>
          <div class="demo-row">
            <OzButton variant="primary" icon="check" :loading="saving" @click="demoSave">{{ saving ? 'Kaydediliyor…' : 'Kaydet' }}</OzButton>
            <OzButton icon="refresh" :loading="saving">Yenile</OzButton>
            <OzButton size="sm" :loading="saving">Onayla</OzButton>
          </div>
          <div class="demo-row">
            <OzButton icon="zap" @click="demoLoadingBar">Üst yükleme çubuğunu göster</OzButton>
            <span class="demo-hint">Sayfa geçişlerinde kendiliğinden çıkar</span>
          </div>
          <OzProgress label="Süresi belirsiz (value yok)" />
        </div>
      </OzCard>

      <OzCard title="Animasyonlu barlar" subtitle="OzProgress · delay ile kademeli">
        <template #actions><OzButton size="sm" icon="refresh" @click="shuffleProgress">Karıştır</OzButton></template>
        <div class="pad">
          <OzProgress v-for="(p, i) in progressDemo" :key="p.label" :label="p.label" :value="p.value" :delay="i * 0.08" />
        </div>
      </OzCard>
    </div>

    <OzCard title="Kullanım">
      <pre class="code">{{ usage }}</pre>
    </OzCard>
  </OzAppLayout>
</template>

<style scoped>
.kit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; align-items: start; }
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
.row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 18px 14px; }
.pad { display: flex; flex-direction: column; gap: 14px; padding: 4px 18px 18px; }
.pad-list { padding: 6px 8px 12px; }

.swatches { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; padding: 0 18px 18px; }
.swatch { display: flex; align-items: center; gap: 10px; }
.chip { width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--oz-border); flex-shrink: 0; }
code { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; }

.icons { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 4px; padding: 0 18px 12px; }
.icon-search { width: 200px; }
.note code { font-size: 11px; color: var(--oz-ink-soft); background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 5px; padding: 1px 5px; }
.avatar-demo { display: flex; flex-direction: column; gap: 14px; padding: 2px 18px 18px; }
.demo-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.demo-label { width: 76px; flex-shrink: 0; font-size: 11px; font-weight: 600; color: var(--oz-muted); }
.demo-hint { font-size: 11px; color: var(--oz-muted-2); }
.demo-row .oz-avatar-group + .oz-avatar-group { margin-left: 14px; }
@media (max-width: 520px) { .demo-row .oz-avatar-group + .oz-avatar-group { margin-left: 0; } }
.team-chip { display: inline-flex; align-items: center; gap: 10px; padding: 6px 12px 6px 8px; border-radius: 999px; border: 1px solid var(--oz-border); background: var(--oz-surface-2); font-size: 12px; color: var(--oz-muted); }
.team-chip b { color: var(--oz-ink-soft); font-weight: 700; }
.icon-cell { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 10px 4px; border-radius: 9px; color: var(--oz-ink-soft); }
.icon-cell:hover { background: var(--oz-accent-soft); color: var(--oz-accent); }
.icon-cell span { font-size: 10px; color: var(--oz-muted); text-align: center; word-break: break-all; }

.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.kit-grid .form-grid { padding: 0 18px 18px; }
.span-2 { grid-column: 1 / -1; }
.tab-panel { margin: 0; padding: 16px 18px 18px; font-size: 12.5px; color: var(--oz-muted); line-height: 1.5; }
.tab-panel b { color: var(--oz-ink-soft); }
.confirm-text { margin: 0; font-size: 13px; color: var(--oz-muted); line-height: 1.55; }
.confirm-text b { color: var(--oz-ink-soft); }
.mb { margin-bottom: 14px; }
.note.flush { padding: 0; }
.note { margin: 0; padding: 0 18px 16px; font-size: 11.5px; color: var(--oz-muted); line-height: 1.5; }
.sk-row { display: flex; align-items: center; gap: 12px; }
.kv { display: grid; grid-template-columns: 110px 1fr; gap: 12px 14px; margin: 0 0 20px; align-items: center; }
.kv dt { font-size: 11.5px; color: var(--oz-muted); }
.kv dd { margin: 0; font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.kv dd.strong { font-size: 15px; font-weight: 800; font-variant-numeric: tabular-nums; }
.drawer-section { display: flex; flex-direction: column; gap: 12px; padding: 16px 0 20px; margin-bottom: 18px; border-top: 1px solid var(--oz-border-soft); border-bottom: 1px solid var(--oz-border-soft); }
.drawer-section h4 { margin: 0; font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); }
.filter-stack { display: flex; flex-direction: column; gap: 18px; }
.check-list { display: flex; flex-direction: column; gap: 10px; padding-top: 2px; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.sk-lines { flex: 1; display: flex; flex-direction: column; gap: 8px; }

.code { margin: 0 18px 18px; padding: 14px; border-radius: 9px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); font-size: 11.5px; color: var(--oz-ink-soft); overflow-x: auto; }

@media (max-width: 1040px) { .kit-grid { grid-template-columns: 1fr; } }
@media (max-width: 960px) { .stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .stats { gap: 10px; } .swatches { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } }
</style>
