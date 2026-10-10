<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { OzIcon } from '@/Components/Oz'

// Dönen döviz kuru göstergesi: her `interval` ms'de bir sonraki kura kayarak geçer
// (USD → EUR → GBP …). Fare üstündeyken ya da odaktayken durur. Ekran okuyucular dönen
// metni değil, gizli tam listeyi okur (her 5 sn'de bir duyuru yapılmasın diye).
//   <ExchangeRateTicker :rates="[{ code: 'USD', rate: 49.0582, change: 0.12, date: '2026-10-02' }]" />
const props = defineProps({
	rates: { type: Array, default: () => [] },
	interval: { type: Number, default: 5000 },
})

const index = ref(0)
const paused = ref(false)
let timer = null

const current = computed(() => props.rates[index.value % Math.max(1, props.rates.length)] ?? null)

const SYMBOLS = { USD: '$', EUR: '€', GBP: '£', CHF: 'Fr', JPY: '¥' }
const fmtRate = (v) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 4, maximumFractionDigits: 4 }).format(v)
const fmtChange = (v) => `${v > 0 ? '+' : ''}${new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)}%`
const fmtDate = (d) => new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${d}T00:00:00`))

function next() {
	if (!paused.value && props.rates.length > 1) index.value = (index.value + 1) % props.rates.length
}

onMounted(() => { timer = setInterval(next, props.interval) })
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
	<div
		v-if="current"
		class="fx-ticker"
		:title="`TCMB döviz satış kuru · ${fmtDate(current.date)} bülteni`"
		tabindex="0"
		@mouseenter="paused = true"
		@mouseleave="paused = false"
		@focusin="paused = true"
		@focusout="paused = false"
	>
		<span class="fx-label">Kur</span>
		<span class="fx-window" aria-hidden="true">
			<Transition name="fx-slide" mode="out-in">
				<span :key="current.code" class="fx-item">
					<span class="fx-code"><span class="fx-symbol">{{ SYMBOLS[current.code] ?? '' }}</span>{{ current.code }}</span>
					<span class="fx-rate">{{ fmtRate(current.rate) }} ₺</span>
					<span v-if="current.change !== null" class="fx-change" :class="current.change > 0 ? 'up' : current.change < 0 ? 'down' : 'flat'">
						<OzIcon :name="current.change > 0 ? 'trending-up' : current.change < 0 ? 'trending-down' : 'minus'" :size="12" />
						{{ fmtChange(current.change) }}
					</span>
				</span>
			</Transition>
		</span>
		<span v-if="rates.length > 1" class="fx-dots" aria-hidden="true">
			<span v-for="(r, i) in rates" :key="r.code" class="fx-dot" :class="{ active: i === index % rates.length }" />
		</span>

		<ul class="sr-only">
			<li v-for="r in rates" :key="r.code">{{ r.code }} {{ fmtRate(r.rate) }} TL<template v-if="r.change !== null">, önceki güne göre {{ fmtChange(r.change) }}</template></li>
		</ul>
	</div>
</template>

<style scoped>
.fx-ticker {
	display: inline-flex; align-items: center; gap: 10px;
	height: 36px; padding: 0 12px;
	border: 1px solid var(--oz-border); border-radius: 10px;
	background: var(--oz-surface);
	font-size: 12.5px; color: var(--oz-ink-soft);
	outline: none;
}
.fx-ticker:focus-visible { box-shadow: 0 0 0 2px var(--oz-accent-soft); border-color: var(--oz-accent); }
.fx-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--oz-muted); }
.fx-window { position: relative; display: inline-flex; overflow: hidden; height: 20px; min-width: 190px; }
.fx-item { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; line-height: 20px; }
.fx-code { display: inline-flex; align-items: baseline; gap: 3px; font-weight: 800; color: var(--oz-ink); }
.fx-symbol { color: var(--oz-accent); }
.fx-rate { font-family: ui-monospace, monospace; font-weight: 700; color: var(--oz-ink); }
.fx-change { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 700; font-family: ui-monospace, monospace; }
/* TL karşısında döviz yükseldi = TL değer kaybetti → uyarı tonu */
.fx-change.up { color: var(--oz-danger); }
.fx-change.down { color: var(--oz-success); }
.fx-change.flat { color: var(--oz-muted); }
.fx-dots { display: inline-flex; gap: 4px; }
.fx-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--oz-border-strong); transition: background 0.3s, transform 0.3s; }
.fx-dot.active { background: var(--oz-accent); transform: scale(1.25); }

.fx-slide-enter-active, .fx-slide-leave-active { transition: transform 0.35s ease, opacity 0.35s ease; }
.fx-slide-enter-from { transform: translateY(100%); opacity: 0; }
.fx-slide-leave-to { transform: translateY(-100%); opacity: 0; }
@media (prefers-reduced-motion: reduce) {
	.fx-slide-enter-active, .fx-slide-leave-active { transition: opacity 0.2s; }
	.fx-slide-enter-from, .fx-slide-leave-to { transform: none; }
}

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 640px) { .fx-window { min-width: 160px; } .fx-dots { display: none; } }
</style>
