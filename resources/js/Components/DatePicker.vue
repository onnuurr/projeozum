<template>
	<div class="dp-wrap" ref="rootEl">
		<div class="dp-trigger" :class="{ open }" @click.stop="toggle">
			<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
				<rect x="3" y="4" width="18" height="18" rx="2" />
				<line x1="16" y1="2" x2="16" y2="6" />
				<line x1="8" y1="2" x2="8" y2="6" />
				<line x1="3" y1="10" x2="21" y2="10" />
			</svg>
			<span class="dp-trigger-text" :class="{ placeholder: !modelValue }">
				{{ modelValue ? formatDate(modelValue) : 'Tarih seçiniz' }}
			</span>
			<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
				<path d="M6 9l6 6 6-6" />
			</svg>
		</div>

		<Teleport to="body">
			<div class="dp-panel" :class="{ open }" :style="panelStyle">
				<div class="dp-header">
					<button class="dp-nav-btn" @click.stop="prevMonth">
						<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M15 18l-6-6 6-6" />
						</svg>
					</button>
					<span class="dp-month-year">{{ headerLabel }}</span>
					<button class="dp-nav-btn" @click.stop="nextMonth">
						<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M9 18l6-6-6-6" />
						</svg>
					</button>
				</div>
				<div class="dp-days-header">
					<div v-for="d in ['Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct', 'Pz']" :key="d" class="dp-day-label">{{ d }}</div>
				</div>
				<div class="dp-grid">
					<div v-for="(c, idx) in cells" :key="idx" :class="cellClass(c)" @click.stop="c.day && pick(c)">
						{{ c.day || c.fill }}
					</div>
				</div>
				<div class="dp-footer">
					<button class="dp-today-btn" @click.stop="selectToday">Bugün</button>
					<button class="dp-clear-btn" @click.stop="clear">Temizle</button>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps({
	modelValue: { default: null },
})
const emit = defineEmits(['update:modelValue'])

const MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık']

const open = ref(false)
const rootEl = ref(null)
const panelStyle = ref({})
const today = new Date()
const current = ref(new Date(today.getFullYear(), today.getMonth(), 1))

const headerLabel = computed(() => `${MONTHS[current.value.getMonth()]} ${current.value.getFullYear()}`)

const cells = computed(() => {
	const year = current.value.getFullYear()
	const month = current.value.getMonth()
	const firstDay = new Date(year, month, 1).getDay()
	const offset = (firstDay + 6) % 7
	const daysInMonth = new Date(year, month + 1, 0).getDate()
	const daysInPrev = new Date(year, month, 0).getDate()

	const arr = []
	for (let i = offset - 1; i >= 0; i--) arr.push({ otherMonth: true, fill: daysInPrev - i })
	for (let d = 1; d <= daysInMonth; d++) arr.push({ day: d })
	const total = offset + daysInMonth
	const remaining = total % 7 === 0 ? 0 : 7 - (total % 7)
	for (let d = 1; d <= remaining; d++) arr.push({ otherMonth: true, fill: d })
	return arr
})

function formatDate(d) {
	const date = d instanceof Date ? d : new Date(d)
	return `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`
}

function cellClass(c) {
	if (c.otherMonth) return 'dp-cell dp-other-month dp-empty'
	const year = current.value.getFullYear()
	const month = current.value.getMonth()
	const isToday = c.day === today.getDate() && month === today.getMonth() && year === today.getFullYear()
	const sel = props.modelValue
	const isSelected = sel && c.day === sel.getDate() && month === sel.getMonth() && year === sel.getFullYear()
	let cls = 'dp-cell'
	if (isToday) cls += ' dp-today'
	if (isSelected) cls += ' dp-selected'
	return cls
}

function pick(c) {
	const year = current.value.getFullYear()
	const month = current.value.getMonth()
	emit('update:modelValue', new Date(year, month, c.day))
	setTimeout(() => (open.value = false), 120)
}

function prevMonth() {
	const c = new Date(current.value)
	c.setMonth(c.getMonth() - 1)
	current.value = c
}
function nextMonth() {
	const c = new Date(current.value)
	c.setMonth(c.getMonth() + 1)
	current.value = c
}
function selectToday() {
	current.value = new Date(today.getFullYear(), today.getMonth(), 1)
	emit('update:modelValue', new Date(today))
	setTimeout(() => (open.value = false), 120)
}
function clear() {
	emit('update:modelValue', null)
}

function toggle() {
	open.value = !open.value
	if (open.value) nextTick(positionPanel)
}

function positionPanel() {
	const trigger = rootEl.value?.querySelector('.dp-trigger')
	if (!trigger) return
	const r = trigger.getBoundingClientRect()
	panelStyle.value = { top: r.bottom + 6 + 'px', left: r.left + 'px' }
}

function handleOutside(e) {
	if (rootEl.value && !rootEl.value.contains(e.target) && !e.target.closest('.dp-panel')) {
		open.value = false
	}
}

onMounted(() => document.addEventListener('click', handleOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleOutside))
</script>

<style scoped>
.dp-wrap { position: relative; }
.dp-trigger {
	height: 36px; padding: 0 12px;
	border: 1.5px solid #e8e8f0; border-radius: 9px;
	background: #fafafe; cursor: pointer;
	display: flex; align-items: center; justify-content: space-between; gap: 8px;
	font-family: inherit; font-size: 13px;
	transition: border-color .15s, box-shadow .15s;
	user-select: none;
}
.dp-trigger:hover { border-color: #ccc; }
.dp-trigger.open {
	border-color: #4a6cf7;
	box-shadow: 0 0 0 3px rgba(74, 108, 247, .10);
	background: #fff;
}
.dp-trigger-text { flex: 1; color: #1a1a2e; }
.dp-trigger-text.placeholder { color: #bbb; }
.dp-trigger svg { color: #aaa; flex-shrink: 0; }
</style>

<style>
.dp-panel {
	position: fixed;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 14px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, .07), 0 16px 40px rgba(0, 0, 0, .09);
	padding: 14px;
	width: 260px;
	z-index: 6000;
	opacity: 0; pointer-events: none;
	transform: translateY(-6px) scale(.97);
	transition: opacity .15s, transform .15s;
}
.dp-panel.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

.dp-header {
	display: flex; align-items: center; justify-content: space-between;
	margin-bottom: 12px;
}
.dp-nav-btn {
	width: 28px; height: 28px; border-radius: 8px;
	background: none; border: 1.5px solid #e8e8f0;
	cursor: pointer; display: flex; align-items: center; justify-content: center;
	color: #888; transition: all .15s;
}
.dp-nav-btn:hover { background: #f0f0f5; border-color: #ccc; color: #333; }
.dp-month-year {
	font-size: 13.5px; font-weight: 700; color: #1a1a2e;
	cursor: pointer; padding: 4px 8px; border-radius: 7px;
	transition: background .15s;
}
.dp-month-year:hover { background: #f0f0f5; }

.dp-days-header {
	display: grid; grid-template-columns: repeat(7, 1fr);
	margin-bottom: 4px;
}
.dp-day-label {
	text-align: center; font-size: 10.5px; font-weight: 700;
	color: #bbb; padding: 4px 0;
}

.dp-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.dp-cell {
	aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
	font-size: 12px; font-weight: 500; color: #333;
	border-radius: 8px; cursor: pointer;
	transition: background .12s, color .12s;
}
.dp-cell:hover:not(.dp-empty):not(.dp-selected) { background: #f0f0f5; }
.dp-cell.dp-empty { pointer-events: none; }
.dp-cell.dp-other-month { color: #ccc; }
.dp-cell.dp-today { color: #4a6cf7; font-weight: 700; background: #eef0ff; }
.dp-cell.dp-selected { background: #1a1a2e; color: #fff; font-weight: 700; }
.dp-cell.dp-selected.dp-today { background: #4a6cf7; }

.dp-footer {
	display: flex; justify-content: space-between; align-items: center;
	margin-top: 10px; padding-top: 10px;
	border-top: 1px solid #f0f0f5;
}
.dp-today-btn {
	font-size: 11.5px; font-weight: 600; color: #4a6cf7;
	background: none; border: none; cursor: pointer; padding: 0;
}
.dp-today-btn:hover { color: #2a4fd7; }
.dp-clear-btn {
	font-size: 11.5px; font-weight: 600; color: #aaa;
	background: none; border: none; cursor: pointer; padding: 0;
}
.dp-clear-btn:hover { color: #666; }
</style>
