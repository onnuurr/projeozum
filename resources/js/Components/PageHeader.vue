<script setup>
defineProps({
	badge: { type: [String, Object], default: null },
	title: { type: String, required: true },
	subtitle: { type: String, default: '' },
});
</script>

<template>
	<div class="page-header">
		<div>
			<div v-if="badge || $slots.badge" class="sa-badge">
				<slot name="badge">{{ typeof badge === 'string' ? badge : badge?.label }}</slot>
			</div>
			<h1 class="page-title">{{ title }}</h1>
			<p v-if="subtitle || $slots.subtitle" class="page-subtitle">
				<slot name="subtitle">{{ subtitle }}</slot>
			</p>
		</div>
		<div v-if="$slots.actions" class="header-actions">
			<slot name="actions" />
		</div>
	</div>
</template>

<style scoped>
.page-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin: 14px 0 20px;
	flex-wrap: wrap;
}
.sa-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	font-weight: 600;
	color: rgb(var(--color-primary));
	background: rgb(var(--color-primary) / 0.1);
	padding: 3px 9px;
	border-radius: 999px;
	margin-bottom: 8px;
}
.page-title {
	font-size: 22px;
	font-weight: 700;
	margin: 0 0 4px;
	color: rgb(var(--color-ink));
}
.page-subtitle {
	font-size: 13px;
	color: rgb(var(--color-muted));
	margin: 0;
}
.header-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

@media (max-width: 640px) {
	.header-actions { width: 100%; flex-wrap: wrap; }
}
</style>
