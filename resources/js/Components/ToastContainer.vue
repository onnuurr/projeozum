<template>
	<Teleport to="body">
		<div class="toast-container">
			<TransitionGroup name="toast">
				<div
					v-for="t in toasts"
					:key="t.id"
					:class="`toast toast-${t.type}`"
				>
					<div class="toast-icon" v-html="icons[t.type]"></div>
					<div class="toast-body">
						<div class="toast-title">{{ t.title }}</div>
						<div v-if="t.message" class="toast-message">{{ t.message }}</div>
					</div>
					<button class="toast-close" @click="$emit('dismiss', t.id)">
						<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M18 6L6 18M6 6l12 12" />
						</svg>
					</button>
					<div class="toast-bar" :style="{ animationDuration: t.duration + 'ms' }"></div>
				</div>
			</TransitionGroup>
		</div>
	</Teleport>
</template>

<script setup>
defineProps({
	toasts: { type: Array, required: true },
})

defineEmits(['dismiss'])

const icons = {
	success: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>',
	error: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>',
	warning: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
	info: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
}
</script>

<style>
.toast-container {
	position: fixed; top: 20px; right: 20px;
	z-index: 99999;
	display: flex; flex-direction: column; gap: 8px;
	pointer-events: none; width: 312px;
}

.toast {
	background: #fff;
	border: 1px solid #e8e8f2;
	border-radius: 14px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, .06), 0 12px 36px rgba(0, 0, 0, .07);
	padding: 13px 13px 15px;
	display: flex; align-items: flex-start; gap: 11px;
	pointer-events: all; position: relative; overflow: hidden;
}

.toast-enter-active {
	animation: toastIn .32s cubic-bezier(.21, 1.02, .73, 1) both;
}
.toast-leave-active {
	animation: toastOut .2s ease forwards;
}
@keyframes toastIn {
	from { transform: translateX(calc(100% + 20px)); opacity: 0; }
	to { transform: translateX(0); opacity: 1; }
}
@keyframes toastOut {
	to { transform: translateX(calc(100% + 20px)); opacity: 0; }
}

.toast::after {
	content: ''; position: absolute;
	top: 12px; bottom: 12px; left: 0;
	width: 3px; border-radius: 0 3px 3px 0;
}
.toast-success::after { background: #22c55e; }
.toast-error::after { background: #ef4444; }
.toast-warning::after { background: #f59e0b; }
.toast-info::after { background: #3b82f6; }

.toast-icon {
	width: 34px; height: 34px; border-radius: 9px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.toast-success .toast-icon { background: #f0fdf4; color: #22c55e; }
.toast-error .toast-icon { background: #fef2f2; color: #ef4444; }
.toast-warning .toast-icon { background: #fffbeb; color: #f59e0b; }
.toast-info .toast-icon { background: #eff6ff; color: #3b82f6; }

.toast-body { flex: 1; min-width: 0; }
.toast-title { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 2px; line-height: 1.3; }
.toast-message { font-size: 11.5px; color: #888; line-height: 1.5; }

.toast-close {
	background: none; border: none; cursor: pointer;
	width: 22px; height: 22px; border-radius: 6px; padding: 0;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0; color: #ccc;
	transition: background .15s, color .15s;
}
.toast-close:hover { background: #f5f5f8; color: #666; }

.toast-bar {
	position: absolute; bottom: 0; left: 0; height: 2px;
	animation: toastBar linear forwards;
}
.toast-success .toast-bar { background: #dcfce7; }
.toast-error .toast-bar { background: #fee2e2; }
.toast-warning .toast-bar { background: #fef9c3; }
.toast-info .toast-bar { background: #dbeafe; }
@keyframes toastBar { from { width: 100%; } to { width: 0%; } }
</style>
