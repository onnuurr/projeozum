<template>
	<Head title="Başarısız İşler" />
	<div class="page-failed-jobs">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Başarısız İşler' },
			]"
		/>

		<PageHeader title="Başarısız İşler">
			<template #subtitle>
				<code>queue:work</code> tarafından <code>failed_jobs</code> tablosuna yazılan, kuyrukta
				işlenirken hata veren job'lar. Son {{ jobs.length }} kayıt gösteriliyor.
			</template>
			<template #actions>
				<Button variant="ghost" size="sm" :disabled="busy || jobs.length === 0" :loading="busyAction === 'retry'" @click="retryAll">
					Tümünü Yeniden Dene
				</Button>
				<Button variant="danger" size="sm" :disabled="busy || jobs.length === 0" :loading="busyAction === 'flush'" @click="flushAll">
					Tümünü Temizle
				</Button>
			</template>
		</PageHeader>

		<Card title="Kayıtlar">
			<template #actions>
				<span class="hint">{{ jobs.length }} iş</span>
			</template>
			<EmptyState v-if="jobs.length === 0" title="Başarısız iş yok." />
			<div v-else class="rows">
				<div v-for="j in jobs" :key="j.id" class="row">
					<span class="row-job">{{ j.jobName }}</span>
					<Tooltip :text="j.connection" position="top"><Badge color="neutral" variant="tonal" :label="j.queue" /></Tooltip>
					<span class="row-date">{{ formatDate(j.failedAt) }}</span>
					<Tooltip :text="j.exception" position="top"><span class="row-error">{{ j.exception }}</span></Tooltip>
				</div>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { inject, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import Badge from '@/Components/Badge.vue'
import Tooltip from '@/Components/Tooltip.vue'
import Card from '@/Components/Card.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Button from '@/Components/Button.vue'

defineOptions({ layout: AppLayout })

defineProps({
	jobs: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const busy = ref(false)
const busyAction = ref(null)

function retryAll() {
	if (busy.value) return
	busy.value = true
	busyAction.value = 'retry'
	router.post('/superadmin/failed-jobs/retry-all', {}, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = false; busyAction.value = null },
	})
}

async function flushAll() {
	if (busy.value) return
	const ok = await $swal.dangerConfirm({
		title: 'Tüm başarısız işleri temizle',
		html: 'Bu <strong>failed_jobs</strong> tablosundaki tüm kayıtları kalıcı olarak siler. Devam edilsin mi?',
		confirmText: 'Temizle',
		cancelText: 'Vazgeç',
	})
	if (!ok) return

	busy.value = true
	busyAction.value = 'flush'
	router.post('/superadmin/failed-jobs/flush', {}, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = false; busyAction.value = null },
	})
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page-subtitle code { background: rgb(var(--color-bg)); border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.hint { font-size: 12px; color: rgb(var(--color-muted)); white-space: nowrap; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.row { display: flex; align-items: center; gap: 16px; padding: 12px 14px; border: 1px solid rgb(var(--color-border)); border-radius: 10px; background: rgb(var(--color-bg) / .5); flex-wrap: wrap; }
.row-job { font-size: 13px; font-weight: 600; color: rgb(var(--color-ink)); min-width: 200px; }
.row-date { font-size: 12.5px; color: rgb(var(--color-muted)); min-width: 150px; }
.row-error { font-size: 12px; color: rgb(var(--color-danger)); flex: 1; min-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }

@media (max-width: 700px) {
	.row { flex-wrap: wrap; }
	.row-job, .row-date { min-width: 0; width: 100%; }
}
</style>
