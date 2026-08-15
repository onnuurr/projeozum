<script setup>
import { computed } from 'vue';
import UiIcon from '@/Components/UiIcon.vue';

const props = defineProps({
	currentPage: { type: Number, default: 1 },
	perPage: { type: Number, default: 20 },
	total: { type: Number, default: 0 },
	perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
});

const emit = defineEmits(['update:currentPage', 'update:perPage']);

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));

const rangeStart = computed(() => (props.total === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1));
const rangeEnd = computed(() => Math.min(props.currentPage * props.perPage, props.total));

const pageNumbers = computed(() => {
	const pages = [];
	const total = totalPages.value;
	const current = props.currentPage;
	const windowSize = 1;

	const addPage = (p) => pages.push(p);
	const addEllipsis = () => pages.push('...');

	addPage(1);
	if (current - windowSize > 2) addEllipsis();
	for (let p = Math.max(2, current - windowSize); p <= Math.min(total - 1, current + windowSize); p++) {
		addPage(p);
	}
	if (current + windowSize < total - 1) addEllipsis();
	if (total > 1) addPage(total);

	return pages;
});

function goToPage(p) {
	if (p !== props.currentPage && p >= 1 && p <= totalPages.value) {
		emit('update:currentPage', p);
	}
}

const goToFirst = () => goToPage(1);
const goToLast = () => goToPage(totalPages.value);
const goToPrevious = () => goToPage(props.currentPage - 1);
const goToNext = () => goToPage(props.currentPage + 1);

function updatePerPage(e) {
	emit('update:perPage', parseInt(e.target.value, 10));
	emit('update:currentPage', 1);
}
</script>

<template>
	<div class="p-2 bg-canvas flex flex-wrap items-center justify-between gap-2 border-t border-line">
		<div class="flex items-center gap-2 text-2xs text-muted">
			<span>{{ rangeStart }}–{{ rangeEnd }} / {{ total }}</span>
			<select
				:value="perPage"
				class="bg-white border border-line rounded-full py-1 px-2.5 text-2xs focus:border-primary focus:ring-0 cursor-pointer max-sm:text-2xs max-sm:py-0.5 max-sm:px-2"
				@change="updatePerPage"
			>
				<option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
			</select>
		</div>
		<div class="flex items-center gap-0.5 sm:gap-1">
			<button
				type="button"
				:disabled="currentPage === 1"
				class="hidden sm:flex w-7 h-7 items-center justify-center rounded-full bg-white border border-line text-muted disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary/5 hover:text-primary hover:border-primary/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
				@click="goToFirst"
			>
				<UiIcon name="first_page" :size="12" />
			</button>
			<button
				type="button"
				:disabled="currentPage === 1"
				class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-line text-muted disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary/5 hover:text-primary hover:border-primary/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
				@click="goToPrevious"
			>
				<UiIcon name="chevron_left" :size="12" />
			</button>

			<template v-for="(p, i) in pageNumbers" :key="i">
				<span
					v-if="p === '...'"
					class="w-5 sm:w-7 flex items-center justify-center text-xs text-muted"
				>
					…
				</span>
				<button
					v-else
					type="button"
					:class="[
						'min-w-[24px] sm:min-w-[28px] h-6 sm:h-7 px-1 sm:px-1.5 rounded-full text-2xs sm:text-xs font-medium transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
						p === currentPage
							? 'bg-primary text-white shadow-sm'
							: 'bg-white border border-line text-muted hover:bg-primary/5 hover:text-primary hover:border-primary/30',
					]"
					@click="goToPage(p)"
				>
					{{ p }}
				</button>
			</template>

			<button
				type="button"
				:disabled="currentPage >= totalPages"
				class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-line text-muted disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary/5 hover:text-primary hover:border-primary/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
				@click="goToNext"
			>
				<UiIcon name="chevron_right" :size="12" />
			</button>
			<button
				type="button"
				:disabled="currentPage >= totalPages"
				class="hidden sm:flex w-7 h-7 items-center justify-center rounded-full bg-white border border-line text-muted disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary/5 hover:text-primary hover:border-primary/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
				@click="goToLast"
			>
				<UiIcon name="last_page" :size="12" />
			</button>
		</div>
	</div>
</template>
