<script setup>
import { watch, onBeforeUnmount } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	title: { type: String, default: '' },
	subtitle: { type: String, default: '' },
	variant: { type: String, default: 'info' }, // info | danger
	width: { type: String, default: '480px' },
	iconBg: { type: String, default: null }, // örn. pazaryeri markası gibi keyfi bir renk gerektiğinde
});

const emit = defineEmits(['update:modelValue', 'close']);

function close() {
	emit('update:modelValue', false);
	emit('close');
}

function handleEsc(e) {
	if (e.key === 'Escape' && props.modelValue) close();
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) document.addEventListener('keydown', handleEsc);
		else document.removeEventListener('keydown', handleEsc);
		document.body.style.overflow = open ? 'hidden' : '';
	}
);

onBeforeUnmount(() => {
	document.removeEventListener('keydown', handleEsc);
	document.body.style.overflow = '';
});
</script>

<template>
	<Teleport to="body">
		<div class="drawer-overlay" :class="{ open: modelValue }" @click="close"></div>
		<aside
			class="drawer"
			:class="{ open: modelValue }"
			:style="{ width }"
			v-bind="$attrs"
			role="dialog"
			aria-modal="true"
		>
			<div v-if="title || $slots.title || $slots.icon" class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon" :class="`icon-${variant}`" :style="iconBg ? { background: iconBg, color: '#fff' } : null">
						<slot name="icon">
							<svg v-if="variant === 'danger'" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
								<line x1="12" y1="9" x2="12" y2="13" />
								<line x1="12" y1="17" x2="12.01" y2="17" />
							</svg>
							<svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<rect x="3" y="3" width="18" height="18" rx="3" />
								<path d="M9 9h6M9 12h6M9 15h4" />
							</svg>
						</slot>
					</div>
					<div>
						<h4><slot name="title">{{ title }}</slot></h4>
						<p v-if="subtitle || $slots.subtitle"><slot name="subtitle">{{ subtitle }}</slot></p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div class="drawer-body">
				<slot />
			</div>

			<div v-if="$slots.footer" class="drawer-footer">
				<slot name="footer" :close="close" />
			</div>
		</aside>
	</Teleport>
</template>

<style>
.drawer-overlay {
	position: fixed; inset: 0; z-index: 9000;
	background: rgba(20, 20, 40, .22);
	backdrop-filter: blur(5px);
	-webkit-backdrop-filter: blur(5px);
	opacity: 0; pointer-events: none;
	transition: opacity .22s ease;
}
.drawer-overlay.open { opacity: 1; pointer-events: all; }

.drawer {
	position: fixed;
	top: 12px; right: 12px; bottom: 12px;
	max-width: calc(100vw - 24px);
	background: #fff;
	border-radius: 18px;
	box-shadow:
		0 24px 64px rgba(0, 0, 0, .16),
		0 6px 20px rgba(0, 0, 0, .09),
		0 0 0 1px rgba(0, 0, 0, .04);
	z-index: 9001;
	display: flex; flex-direction: column;
	transform: translateX(calc(100% + 24px));
	transition: transform .32s cubic-bezier(.34, 1.28, .64, 1);
	overflow: hidden;
}
.drawer.open { transform: translateX(0); }

.drawer-header {
	display: flex; align-items: center; justify-content: space-between;
	padding: 18px 20px 16px;
	border-bottom: 1px solid #f0f0f6;
	flex-shrink: 0;
}
.drawer-title { display: flex; align-items: center; gap: 10px; }
.drawer-title-icon {
	width: 34px; height: 34px; border-radius: 9px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.drawer-title-icon.icon-info { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.drawer-title-icon.icon-danger { background: #fee2e2; color: #ef4444; }
.drawer-title h4 { font-size: 14.5px; font-weight: 600; color: #1a1a2e; }
.drawer-title p { font-size: 12px; color: #9898b0; margin-top: 1px; }

.drawer-close {
	width: 30px; height: 30px; border-radius: 8px; border: none;
	background: #f5f5f8; color: #888; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: background .15s, color .15s; flex-shrink: 0;
}
.drawer-close:hover { background: #fee2e2; color: #ef4444; }

.drawer-body {
	flex: 1; overflow-y: auto; padding: 18px 20px;
	display: flex; flex-direction: column; gap: 16px;
}
.drawer-body::-webkit-scrollbar { width: 4px; }
.drawer-body::-webkit-scrollbar-track { background: transparent; }
.drawer-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

.drawer-footer {
	padding: 14px 20px;
	border-top: 1px solid #f0f0f6;
	display: flex; gap: 8px; flex-shrink: 0;
}
.drawer-footer .btn { flex: 1; justify-content: center; }
.drawer-footer .btn-ghost { flex: 0 0 auto; }
</style>
