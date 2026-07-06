<template>
	<Head title="Pazaryerleri" />
	<div class="hub">
		<h1 class="page-title">Pazaryeri Bağlantıları</h1>
		<p class="page-subtitle">Ürünlerinizi farklı pazaryerlerine push edip satışlarını çekin.</p>

		<div class="grid">
			<Link
				v-for="p in providers"
				:key="p.code"
				:href="`/marketplace/${p.code}`"
				class="card"
			>
				<div class="head">
					<span class="logo">{{ p.label.charAt(0) }}</span>
					<div>
						<div class="label">{{ p.label }}</div>
						<div class="state">
							<span v-if="p.connected" :class="['dot', p.active ? 'on' : 'off']"></span>
							<span v-if="p.connected">{{ p.store_name ?? 'Bağlı' }}</span>
							<span v-else class="dim">Bağlı değil</span>
						</div>
					</div>
				</div>
				<div class="badge" :class="p.live_ready ? 'live' : 'soon'">
					{{ p.live_ready ? 'Live' : 'Yakında' }}
				</div>
			</Link>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	providers: { type: Array, default: () => [] },
})
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin: 4px 0 18px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.card { background: #fff; border: 1px solid #ebebf0; border-radius: 14px; padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; }
.card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.06); transform: translateY(-1px); }
.head { display: flex; align-items: center; gap: 12px; }
.logo { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; }
.label { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.state { font-size: 11px; color: #666; display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot.on { background: #10b981; }
.dot.off { background: #f59e0b; }
.dim { color: #aaa; }
.badge { padding: 3px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; }
.badge.live { background: #dcfce7; color: #15803d; }
.badge.soon { background: #fef3c7; color: #b45309; }
</style>
