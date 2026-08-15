<script setup>
import { ref, computed, watch, useSlots } from 'vue';
import TablePagination from '@/Components/TablePagination.vue';
import Skeleton from '@/Components/Skeleton.vue';
import UiIcon from '@/Components/UiIcon.vue';

const props = defineProps({
	columns: { type: Array, required: true },
	data: { type: Array, default: () => [] },
	rowKeyField: { type: String, default: 'id' },
	loading: { type: Boolean, default: false },
	selectable: { type: Boolean, default: false },
	paginated: { type: Boolean, default: true },
	perPage: { type: Number, default: 20 },
	perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
	emptyIcon: { type: String, default: 'folder' },
	emptyTitle: { type: String, default: 'Kayıt bulunamadı' },
	emptyHint: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const slots = useSlots();
const hasActions = computed(() => !!slots.actions);

const selectedRows = ref([]);
const selectAll = ref(false);
const currentPage = ref(1);
const localPerPage = ref(props.perPage);
const sortKey = ref('');
const sortDir = ref('asc');

const sortedData = computed(() => {
	if (!sortKey.value) return props.data;
	const key = sortKey.value;
	const dir = sortDir.value === 'asc' ? 1 : -1;
	return [...props.data].sort((a, b) => {
		const av = a[key];
		const bv = b[key];
		if (av === bv) return 0;
		if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
		return String(av ?? '').localeCompare(String(bv ?? ''), 'tr') * dir;
	});
});

const totalItems = computed(() => sortedData.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / localPerPage.value)));
const visibleData = computed(() => {
	if (!props.paginated) return sortedData.value;
	const start = (currentPage.value - 1) * localPerPage.value;
	return sortedData.value.slice(start, start + localPerPage.value);
});

watch(() => props.data, () => { currentPage.value = 1; });
watch([totalPages], () => {
	if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

function rowKey(row, index) {
	return row[props.rowKeyField] != null ? row[props.rowKeyField] : `${currentPage.value}-${index}`;
}

function isSortable(col) {
	return col.sortable !== false;
}

function toggleSort(col) {
	if (!isSortable(col)) return;
	if (sortKey.value === col.key) {
		sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
	} else {
		sortKey.value = col.key;
		sortDir.value = 'asc';
	}
}

const toggleSelectAll = () => {
	selectedRows.value = selectAll.value ? [...visibleData.value] : [];
	emit('select', selectedRows.value);
};

const toggleRowSelection = (row) => {
	const index = selectedRows.value.indexOf(row);
	if (index > -1) {
		selectedRows.value.splice(index, 1);
	} else {
		selectedRows.value.push(row);
	}
	selectAll.value = selectedRows.value.length === visibleData.value.length && visibleData.value.length > 0;
	emit('select', selectedRows.value);
};

const isSelected = (row) => selectedRows.value.includes(row);

function alignClass(align) {
	return {
		'text-left': align === 'left' || !align,
		'text-center': align === 'center',
		'text-right': align === 'right',
	};
}

const firstColStickyClass = computed(() => `sticky z-20 bg-surface group-hover:bg-canvas ${props.selectable ? 'left-8' : 'left-0'}`);
const firstColStickyHeaderClass = computed(() => `sticky z-40 bg-canvas ${props.selectable ? 'left-8' : 'left-0'}`);
</script>

<template>
	<div class="bg-surface rounded-lg border border-line overflow-hidden">
		<div v-if="loading" class="p-4 space-y-3">
			<div v-for="i in 5" :key="i" class="flex items-center gap-3">
				<Skeleton variant="circle" width="2rem" height="2rem" />
				<Skeleton :width="['60%', '90%', '45%', '75%', '55%'][i % 5]" />
			</div>
		</div>

		<div v-else class="relative">
			<div class="overflow-x-auto">
				<table class="w-full text-left border-collapse text-xs min-w-[560px]">
					<thead class="sticky top-0 z-30">
						<tr class="bg-canvas border-b border-line">
							<th v-if="selectable" class="p-2 w-8 sticky left-0 z-40 bg-canvas">
								<input
									type="checkbox"
									:checked="selectAll"
									:disabled="visibleData.length === 0"
									class="w-3.5 h-3.5 rounded border-line dt-checkbox cursor-pointer disabled:cursor-not-allowed"
									@change="toggleSelectAll"
								/>
							</th>
							<th
								v-for="(col, colIndex) in columns"
								:key="col.key"
								class="p-2 font-medium text-muted uppercase tracking-wider whitespace-nowrap"
								:class="[alignClass(col.align), colIndex === 0 ? firstColStickyHeaderClass : '']"
							>
								<button
									v-if="isSortable(col)"
									type="button"
									class="inline-flex items-center gap-0.5 uppercase tracking-wider hover:text-primary transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded"
									:class="sortKey === col.key ? 'text-primary' : ''"
									@click="toggleSort(col)"
								>
									{{ col.label }}
									<UiIcon v-if="sortKey === col.key" :name="sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward'" :size="13" />
								</button>
								<span v-else>{{ col.label }}</span>
							</th>
							<th v-if="hasActions" class="p-2 w-28" />
						</tr>
					</thead>
					<TransitionGroup tag="tbody" name="table-row" class="divide-y divide-line">
						<tr
							v-for="(row, rowIndex) in visibleData"
							:key="rowKey(row, rowIndex)"
							class="hover:bg-canvas transition-colors group"
						>
							<td v-if="selectable" class="p-2 sticky left-0 z-20 bg-surface group-hover:bg-canvas">
								<input
									type="checkbox"
									:checked="isSelected(row)"
									class="w-3.5 h-3.5 rounded border-line dt-checkbox cursor-pointer"
									@change="toggleRowSelection(row)"
								/>
							</td>
							<td
								v-for="(col, colIndex) in columns"
								:key="col.key"
								class="p-2"
								:class="[alignClass(col.align), colIndex === 0 ? firstColStickyClass : '']"
							>
								<slot :name="col.key" :row="row" :value="row[col.key]">
									{{ row[col.key] }}
								</slot>
							</td>
							<td v-if="hasActions" class="p-2">
								<div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 max-md:opacity-100 transition-opacity">
									<slot name="actions" :row="row" />
								</div>
							</td>
						</tr>
					</TransitionGroup>
				</table>
			</div>

			<div v-if="visibleData.length === 0" class="py-10 flex flex-col items-center justify-center gap-2">
				<span class="w-12 h-12 rounded-full bg-canvas flex items-center justify-center">
					<UiIcon :name="emptyIcon" :size="20" class="text-muted" />
				</span>
				<p class="text-xs font-medium text-ink">{{ emptyTitle }}</p>
				<p v-if="emptyHint" class="text-2xs text-muted">{{ emptyHint }}</p>
			</div>

			<TablePagination
				v-if="paginated && totalItems > 0"
				v-model:current-page="currentPage"
				v-model:per-page="localPerPage"
				:per-page-options="perPageOptions"
				:total="totalItems"
			/>
		</div>
	</div>
</template>

<style scoped>
.dt-checkbox {
	accent-color: rgb(var(--color-primary));
}
.table-row-enter-active {
	transition: opacity 0.15s ease, transform 0.15s ease;
}
.table-row-enter-from {
	opacity: 0;
	transform: translateY(6px);
}
@media (prefers-reduced-motion: reduce) {
	.table-row-enter-active { transition: none; }
	.table-row-enter-from { transform: none; }
}
</style>
