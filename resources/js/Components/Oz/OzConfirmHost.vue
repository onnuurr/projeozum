<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import OzModal from './OzModal.vue'
import OzButton from './OzButton.vue'
import OzInput from './OzInput.vue'
import OzField from './OzField.vue'
import { ozConfirmInternals } from './useOzConfirm'

// useOzConfirm() pencerelerini çizer. OzShell tarafından bir kez render edilir.
const { state, settle } = ozConfirmInternals

const typed = ref('')
const inputRef = ref(null)
const o = computed(() => state.opts)
const canConfirm = computed(() => !o.value.requireText || typed.value.trim() === o.value.requireText)

const openModel = computed({
  get: () => state.open,
  set: (v) => { if (!v) settle(false) },
})

watch(() => state.open, async (v) => {
  if (!v) return
  typed.value = ''
  if (o.value.requireText) { await nextTick(); inputRef.value?.focus() }
})

function accept() {
  if (canConfirm.value) settle(true)
}
</script>

<template>
  <OzModal v-model:open="openModel" size="sm" :title="o.title" :icon="o.icon" :tone="o.tone">
    <p v-if="o.message" class="msg">{{ o.message }}</p>
    <form v-if="o.requireText" class="type" @submit.prevent="accept">
      <OzField :hint="`Onaylamak için ${o.requireText} yazın`">
        <OzInput ref="inputRef" v-model="typed" :placeholder="o.requireText" autocomplete="off" />
      </OzField>
    </form>
    <template #footer>
      <OzButton @click="settle(false)">{{ o.cancelText }}</OzButton>
      <OzButton :variant="o.tone === 'danger' ? 'danger' : 'primary'" :disabled="!canConfirm" @click="accept">{{ o.confirmText }}</OzButton>
    </template>
  </OzModal>
</template>

<style scoped>
.msg { margin: 0; font-size: 13px; color: var(--oz-muted); line-height: 1.55; }
.type { margin-top: 14px; }
</style>
