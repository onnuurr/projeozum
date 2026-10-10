<script setup>
import { computed, inject, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import Button from '@/Components/Button.vue'

// Köşe detayı düzenleyici (bkz. MockupDetailInset): ürün fotoğrafını seç, detay kutusunu sürükleyerek çiz.
// Kutu 0..1 normalize; kaydedince sunucu temiz görselden ücretsiz yeniden çizer.
const props = defineProps({ r: { type: Object, required: true } })
const emit = defineEmits(['close', 'saved'])
const showToast = inject('showToast', null)

const inset = computed(() => props.r.detail_inset)
const photo = ref(inset.value?.photo ?? inset.value?.photos?.[0]?.path ?? null)
const box = ref(inset.value?.box ? [...inset.value.box] : null)
const busy = ref(false)
const stage = ref(null)
let start = null

const photoUrl = computed(() => inset.value?.photos?.find((p) => p.path === photo.value)?.url)
const boxStyle = computed(() => box.value && {
	left: `${box.value[0] * 100}%`, top: `${box.value[1] * 100}%`,
	width: `${(box.value[2] - box.value[0]) * 100}%`, height: `${(box.value[3] - box.value[1]) * 100}%`,
})

function point(e) {
	const r = stage.value.getBoundingClientRect()
	return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))]
}
function down(e) {
	start = point(e)
	box.value = [...start, ...start]
	e.target.setPointerCapture?.(e.pointerId)
}
function move(e) {
	if (!start) return
	const [x, y] = point(e)
	box.value = [Math.min(start[0], x), Math.min(start[1], y), Math.max(start[0], x), Math.max(start[1], y)]
}
function up() { start = null }

function pickPhoto(path) {
	if (path === photo.value) return
	photo.value = path
	box.value = null
}

function send(data, okTitle) {
	busy.value = true
	router.put(`/creative/tryon/${props.r.id}/inset`, data, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => { showToast?.({ type: 'success', title: okTitle }); emit('saved') },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] }),
		onFinish: () => { busy.value = false },
	})
}
const tooSmall = computed(() => !box.value || box.value[2] - box.value[0] < 0.02 || box.value[3] - box.value[1] < 0.02)
const save = () => send({ photo: photo.value, box: box.value }, 'Köşe detayı güncellendi')
const remove = () => send({ off: true }, 'Köşe detayı kaldırıldı')
</script>

<template>
	<div class="inset-overlay" @click.self="emit('close')">
		<div class="inset-modal">
			<div class="head">
				<strong>Köşe detayı<span v-if="inset?.label"> · {{ inset.label }}</span></strong>
				<button type="button" class="close" title="Kapat" @click="emit('close')"><X :size="16" /></button>
			</div>
			<p class="hint">Detayın alınacağı fotoğrafı seçin, sonra fotoğraf üzerinde sürükleyerek detay alanını çizin.</p>
			<div v-if="inset?.photos?.length > 1" class="photos">
				<button v-for="p in inset.photos" :key="p.path" type="button" class="ph" :class="{ on: p.path === photo }" @click="pickPhoto(p.path)">
					<img :src="p.url" alt="" />
				</button>
			</div>
			<div v-if="photoUrl" ref="stage" class="stage" @pointerdown.prevent="down" @pointermove="move" @pointerup="up" @pointercancel="up">
				<img :src="photoUrl" alt="" draggable="false" />
				<div v-if="box" class="box" :style="boxStyle" />
			</div>
			<p v-else class="hint">Bu ürünün kullanılabilir fotoğrafı yok.</p>
			<div class="actions">
				<Button variant="danger" size="sm" :disabled="busy || inset?.off" @click="remove">Detayı kaldır</Button>
				<span class="spacer" />
				<Button size="sm" variant="secondary" :disabled="busy" @click="emit('close')">Vazgeç</Button>
				<Button size="sm" :disabled="busy || tooSmall || !photo" @click="save">Kaydet</Button>
			</div>
		</div>
	</div>
</template>

<style scoped>
.inset-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; padding: 16px; }
.inset-modal { background: var(--color-surface); border-radius: 14px; padding: 14px; width: min(460px, 100%); max-height: 92vh; overflow: auto; box-shadow: 0 16px 48px rgba(0,0,0,.25); }
.head { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; }
.close { border: none; background: none; cursor: pointer; color: var(--color-muted); display: inline-flex; }
.hint { font-size: 12px; color: var(--color-muted); margin: 6px 0 10px; }
.photos { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.ph { border: 2px solid transparent; border-radius: 8px; padding: 0; background: none; cursor: pointer; width: 54px; height: 72px; overflow: hidden; }
.ph.on { border-color: var(--color-primary); }
.ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
.stage { position: relative; user-select: none; touch-action: none; cursor: crosshair; border-radius: 8px; overflow: hidden; background: var(--color-surface-container-low); }
.stage img { display: block; width: 100%; height: auto; pointer-events: none; }
.box { position: absolute; border: 2px solid #fff; box-shadow: 0 0 0 9999px rgba(0,0,0,.35), 0 0 0 1px rgba(0,0,0,.4) inset; border-radius: 4px; }
.actions { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.spacer { flex: 1; }
</style>
