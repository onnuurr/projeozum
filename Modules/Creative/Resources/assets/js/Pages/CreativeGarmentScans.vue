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

		<div class="page-header">
			<div>
				<h1 class="page-title">Giysi Parça Tespiti</h1>
				<p class="page-subtitle">
					Taranan her giysi görseli — yaka/cep/etek gibi parçaları elle işaretleyerek
					tespit modelinin eğitim verisini büyütün.
				</p>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div v-if="scans.length === 0" class="empty-block">
					Henüz taranmış bir giysi görseli yok — bir ürün giydirme üretimi tetiklendiğinde otomatik oluşur.
				</div>
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
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '@Modules/Creative/Resources/assets/js/Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

defineProps({
	scans: { type: Array, default: () => [] },
})
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 40px 0; font-style: italic; font-size: 13px; }

.scan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.scan-card { border: 1px solid #f0f0f5; border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; background: #fafafc; transition: box-shadow .15s; }
.scan-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.scan-thumb { width: 100%; aspect-ratio: 1; background: #eee; }
.scan-thumb img { width: 100%; height: 100%; object-fit: cover; }
.scan-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
.scan-count { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.scan-model { font-size: 11px; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

@media (max-width: 700px) {
	.scan-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>
