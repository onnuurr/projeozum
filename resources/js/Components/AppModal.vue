<template>
	<Teleport to="body">
		<div class="modal-overlay" :class="{ open: modelValue }" @click.self="close">
			<div class="modal" :class="`modal-${size}`" role="dialog" aria-modal="true">
				<div class="modal-header">
					<div class="modal-title">
						<div class="modal-title-icon" :class="`icon-${variant}`">
							<slot name="icon">
								<svg v-if="variant === 'danger'" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
									<line x1="12" y1="9" x2="12" y2="13" />
									<line x1="12" y1="17" x2="12.01" y2="17" />
								</svg>
								<svg v-else width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<rect x="3" y="3" width="18" height="18" rx="3" />
									<path d="M9 9h6M9 12h6M9 15h4" />
								</svg>
							</slot>
						</div>
						<div>
							<h4>{{ title }}</h4>
							<p>{{ subtitle }}</p>
						</div>
					</div>
					<button class="modal-close" @click="close" aria-label="Kapat">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M18 6L6 18M6 6l12 12" />
						</svg>
					</button>
				</div>
				<div class="modal-body">
					<slot />
				</div>
				<div class="modal-divider"></div>
				<div class="modal-footer">
					<slot name="footer" :close="close">
						<button class="btn btn-ghost" @click="close">İptal</button>
					</slot>
				</div>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	title: { type: String, default: '' },
	subtitle: { type: String, default: '' },
	variant: { type: String, default: 'info' },
	size: { type: String, default: 'sm' }, // sm | md | lg
})

const emit = defineEmits(['update:modelValue', 'close'])

function close() {
	emit('update:modelValue', false)
	emit('close')
}

function handleEsc(e) {
	if (e.key === 'Escape' && props.modelValue) close()
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) document.addEventListener('keydown', handleEsc)
		else document.removeEventListener('keydown', handleEsc)
		document.body.style.overflow = open ? 'hidden' : ''
	}
)

onBeforeUnmount(() => {
	document.removeEventListener('keydown', handleEsc)
	document.body.style.overflow = ''
})
</script>

<style>
.modal-overlay {
	position: fixed; inset: 0; z-index: 9000;
	background: rgba(30, 30, 50, .35);
	backdrop-filter: blur(6px);
	-webkit-backdrop-filter: blur(6px);
	display: flex; align-items: center; justify-content: center;
	padding: 20px;
	opacity: 0; pointer-events: none;
	transition: opacity .2s ease;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal {
	background: #fff;
	border-radius: 18px;
	box-shadow: 0 8px 40px rgba(0, 0, 0, .14), 0 2px 8px rgba(0, 0, 0, .08);
	width: 100%; max-width: 480px;
	display: flex; flex-direction: column;
	transform: scale(.95) translateY(10px);
	transition: transform .22s cubic-bezier(.34, 1.56, .64, 1), opacity .2s ease;
	opacity: 0;
	overflow: hidden;
}
.modal-overlay.open .modal { transform: scale(1) translateY(0); opacity: 1; }

.modal.modal-md { max-width: 640px; }

.modal.modal-lg {
	width: 60vw;
	max-width: 60vw;
	height: 80vh;
	max-height: 80vh;
}
.modal.modal-lg .modal-body {
	flex: 1;
	overflow-y: auto;
	min-height: 0;
}
.modal.modal-lg .modal-body::-webkit-scrollbar { width: 5px; }
.modal.modal-lg .modal-body::-webkit-scrollbar-track { background: transparent; margin: 8px 0; }
.modal.modal-lg .modal-body::-webkit-scrollbar-thumb { background: #d8d8e8; border-radius: 5px; }
.modal.modal-lg .modal-body::-webkit-scrollbar-thumb:hover { background: #b8b8c8; }

@media (max-width: 900px) {
	.modal.modal-lg { width: 92vw; max-width: 92vw; height: 86vh; max-height: 86vh; }
}

.modal-header {
	display: flex; align-items: center; justify-content: space-between;
	padding: 20px 22px 0;
}
.modal-title { display: flex; align-items: center; gap: 10px; }
.modal-title-icon {
	width: 36px; height: 36px; border-radius: 10px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.modal-title-icon.icon-info { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.modal-title-icon.icon-danger { background: #fee2e2; color: #ef4444; }
.modal-title h4 { font-size: 15px; font-weight: 600; color: #1a1a2e; }
.modal-title p { font-size: 12px; color: #9898b0; margin-top: 1px; }

.modal-close {
	width: 30px; height: 30px; border-radius: 8px; border: none;
	background: #f5f5f8; color: #888; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: background .15s, color .15s;
	flex-shrink: 0;
}
.modal-close:hover { background: #fee2e2; color: #ef4444; }

.modal-body {
	padding: 18px 22px;
	font-size: 13.5px; color: #4a4a6a; line-height: 1.65;
}
.modal-body .modal-info-row {
	display: flex; align-items: center; gap: 10px;
	padding: 10px 12px; border-radius: 10px;
	background: #f8f7ff; border: 1px solid rgb(var(--color-primary-soft));
	margin-bottom: 14px;
	font-size: 13px; color: rgb(var(--color-primary-hover));
}
.modal-body .modal-info-row.danger-row {
	background: #fff8f8; border-color: #fecaca; color: #991b1b;
}
.modal-body .modal-info-row svg { flex-shrink: 0; }
.modal-body p { color: #606080; }

.modal-divider { height: 1px; background: #f0f0f6; margin: 0 22px; }
.modal-footer {
	display: flex; align-items: center; justify-content: flex-end;
	gap: 8px; padding: 16px 22px;
}
</style>
