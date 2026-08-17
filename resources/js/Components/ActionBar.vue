<!--
	OzumServer Dashboard tasarımına geçiş — sayfa başlığı + aktif kökün alt menüsü (navItems)
	için yatay sekme şeridi. `tabs` prop'unun 3. seviye (children) taşıması gerekiyorsa,
	eski TopNav.vue'da zaten çalışan iki kademeli flyout mekanizması (toggleSubdropdown/
	positionDropdowns/.nav-subdropdown) buraya taşındı — sıfırdan tasarlanmadı
	(bkz. /root/.claude/plans/hazy-enchanting-thunder.md sürtünme notu #4).
-->
<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
	title: { type: String, default: '' },
	icon: { type: String, default: '' },
	tabs: { type: Array, default: () => [] },
});

const scrollRef = ref(null);
const tabEls = ref([]);
const wrapRef = ref(null);

const activeFlyout = ref(null);
const activeSubFlyout = ref(null);
const flyoutPositions = ref({});

function tabKey(tab) {
	return tab.to || tab.name;
}

function toggleFlyout(tab) {
	const key = tabKey(tab);
	if (activeFlyout.value === key) {
		activeFlyout.value = null;
		activeSubFlyout.value = null;
		return;
	}
	activeFlyout.value = key;
	activeSubFlyout.value = null;
	nextTick(() => positionFlyouts());
}

function toggleSubFlyout(child) {
	activeSubFlyout.value = activeSubFlyout.value === child.label ? null : child.label;
}

function positionFlyouts() {
	const positions = {};
	tabEls.value.forEach((el, idx) => {
		if (!el) return;
		const tab = props.tabs[idx];
		if (!tab) return;
		const rect = el.getBoundingClientRect();
		positions[tabKey(tab)] = { top: rect.bottom + 6 + 'px', left: rect.left + 'px' };
	});
	flyoutPositions.value = positions;
}

function flyoutStyle(tab) {
	return flyoutPositions.value[tabKey(tab)] || {};
}

function onDocumentClick(e) {
	if (!e.target.closest('.ab-tab-item')) activeFlyout.value = null;
	if (!e.target.closest('.ab-flyout-entry')) activeSubFlyout.value = null;
}

/* ── Sekme şeridi sürükle-kaydır (saas-frontend ActionBar.vue'dan, davranış aynı) ── */
const dragging = ref(false);
let startX = 0;
let startScrollLeft = 0;
let didDrag = false;

function onMouseDown(e) {
	const el = scrollRef.value;
	if (!el || e.button !== 0) return;
	dragging.value = true;
	didDrag = false;
	startX = e.clientX;
	startScrollLeft = el.scrollLeft;
	document.body.classList.add('select-none');
}
function onMouseMove(e) {
	if (!dragging.value) return;
	const el = scrollRef.value;
	if (!el) return;
	el.scrollLeft = startScrollLeft - (e.clientX - startX);
	if (Math.abs(e.clientX - startX) > 5) didDrag = true;
}
function stopDrag() {
	if (!dragging.value) return;
	dragging.value = false;
	document.body.classList.remove('select-none');
}
function onClickCapture(e) {
	if (didDrag) {
		e.preventDefault();
		e.stopPropagation();
		didDrag = false;
	}
}
function onWheel(e) {
	const el = e.currentTarget;
	if (el.scrollWidth <= el.clientWidth) return;
	if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
	e.preventDefault();
	el.scrollLeft += e.deltaY;
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
	<div ref="wrapRef" class="bg-surface h-11 sm:h-14 px-2 sm:px-3 md:px-6 flex items-center justify-between gap-2 sm:gap-6 border-b border-line">
		<div v-if="title" class="hidden sm:flex items-center gap-2 flex-shrink-0">
			<div v-if="icon" class="w-6 h-6 rounded-md bg-primary/10 flex items-center justify-center flex-shrink-0">
				<span class="w-3.5 h-3.5 text-primary" v-html="icon"></span>
			</div>
			<h1 class="text-md font-bold text-ink tracking-tight leading-none whitespace-nowrap">{{ title }}</h1>
		</div>

		<div
			ref="scrollRef"
			:class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
			:style="dragging ? { scrollSnapType: 'none', scrollBehavior: 'auto' } : undefined"
			class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto no-scrollbar scroll-snap-x whitespace-nowrap overscroll-x-contain select-none"
			@wheel="onWheel"
			@mousedown="onMouseDown"
			@mousemove="onMouseMove"
			@mouseup="stopDrag"
			@mouseleave="stopDrag"
			@click.capture="onClickCapture"
		>
			<div v-for="(tab, idx) in tabs" :key="tabKey(tab)" ref="tabEls" class="ab-tab-item relative flex-shrink-0">
				<Link
					v-if="tab.to && !tab.children?.length"
					:href="tab.to"
					:class="[
						'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-150',
						tab.active ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-canvas hover:text-ink',
					]"
				>
					{{ tab.name }}
					<span
						v-if="tab.count != null"
						class="px-1.5 py-0.5 rounded-full text-2xs font-medium"
						:class="tab.active ? 'bg-primary text-white' : 'bg-canvas text-muted'"
					>
						{{ tab.count }}
					</span>
				</Link>
				<button
					v-else
					type="button"
					:class="[
						'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-150',
						tab.active || activeFlyout === tabKey(tab) ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-canvas hover:text-ink',
					]"
					@click.stop="toggleFlyout(tab)"
				>
					{{ tab.name }}
					<span
						v-if="tab.count != null"
						class="px-1.5 py-0.5 rounded-full text-2xs font-medium"
						:class="tab.active || activeFlyout === tabKey(tab) ? 'bg-primary text-white' : 'bg-canvas text-muted'"
					>
						{{ tab.count }}
					</span>
				</button>

				<div
					v-if="tab.children?.length"
					class="ab-flyout fixed bg-white rounded-xl shadow-xl border border-line p-1.5 z-50 min-w-[200px]"
					:class="activeFlyout === tabKey(tab) ? 'block' : 'hidden'"
					:style="flyoutStyle(tab)"
					@click.stop
				>
					<div v-for="child in tab.children" :key="child.label" class="ab-flyout-entry relative">
						<Link
							v-if="child.to && !child.children?.length"
							:href="child.to"
							class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
							@click="activeFlyout = null"
						>
							{{ child.label }}
						</Link>
						<button
							v-else
							type="button"
							class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
							:class="activeSubFlyout === child.label ? 'bg-surface' : ''"
							@click.stop="toggleSubFlyout(child)"
						>
							<span class="flex-1 text-left">{{ child.label }}</span>
						</button>

						<div
							v-if="child.children?.length && activeSubFlyout === child.label"
							class="absolute left-full top-0 ml-1 bg-white rounded-xl shadow-xl border border-line p-1.5 z-50 min-w-[180px]"
						>
							<p class="px-2.5 pt-1 pb-1.5 text-2xs font-semibold text-muted uppercase tracking-wide">{{ child.label }}</p>
							<template v-for="sub in child.children" :key="sub.label">
								<Link
									v-if="sub.to"
									:href="sub.to"
									class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
									@click="activeFlyout = null; activeSubFlyout = null"
								>
									{{ sub.label }}
								</Link>
							</template>
						</div>
					</div>
				</div>
			</div>
		</div>

		<slot name="actions" />
	</div>
</template>
