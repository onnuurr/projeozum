<template>
	<Head title="Giysi Parça Tespiti" />
	<div class="page-scans">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Parça Tespiti' },
			]"
		/>

		<CreativeNav current="garment-scans" />

		<PageHeader title="Giysi Parça Tespiti">
			<template #subtitle>
				Taranan her giysi görseli — yaka/cep/etek gibi parçaları elle işaretleyerek
				tespit modelinin eğitim verisini büyütün.
			</template>
		</PageHeader>

		<Card>
			<EmptyState
				v-if="scans.length === 0"
				:icon="Shirt"
				title="Henüz taranmış bir giysi görseli yok"
				hint="Bir ürün giydirme üretimi tetiklendiğinde otomatik oluşur."
			/>
			<div v-else class="scan-grid">
				<Link v-for="s in scans" :key="s.id" :href="`/creative/garment-scans/${s.id}`" class="scan-card">
					<div class="scan-thumb">
						<img v-if="s.image_url" :src="s.image_url" alt="Giysi görseli" />
					</div>
					<div class="scan-body">
						<span class="scan-count">{{ s.detection_count }} parça</span>
						<span class="scan-model">{{ s.model_version || 'model yok' }}</span>
					</div>
				</Link>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Shirt } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import EmptyState from '@/Components/EmptyState.vue'
import CreativeNav from '@Modules/Creative/Resources/assets/js/Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

defineProps({
	scans: { type: Array, default: () => [] },
})
</script>

<style scoped>
.scan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.scan-card { border: 1px solid var(--color-outline-variant); border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; background: var(--color-surface-container-low); transition: box-shadow .15s; }
.scan-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.scan-thumb { width: 100%; aspect-ratio: 1; background: var(--color-surface-container-high); }
.scan-thumb img { width: 100%; height: 100%; object-fit: cover; }
.scan-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
.scan-count { font-size: 13px; font-weight: 700; color: var(--color-ink); }
.scan-model { font-size: 11px; color: var(--color-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

@media (max-width: 700px) {
	.scan-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>
