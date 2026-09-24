<script setup>
import { AnimatePresence, motion } from 'motion-v'
import { ozLoadingBarState as state } from './useOzLoadingBar'

// Sayfanın üst kenarındaki ilerleme çubuğu (rota geçişi, uzun işlemler). OzShell bir kez çizer.
</script>

<template>
  <AnimatePresence>
    <motion.div
      v-if="state.active || state.progress === 1"
      key="oz-loading-bar"
      class="oz-loading-bar"
      role="progressbar"
      aria-label="Sayfa yükleniyor"
      :initial="{ opacity: 1 }"
      :exit="{ opacity: 0, transition: { duration: 0.25, delay: 0.15 } }"
    >
      <motion.div
        class="fill"
        :initial="{ scaleX: 0 }"
        :animate="{ scaleX: state.progress }"
        :transition="{ type: 'spring', stiffness: 90, damping: 20 }"
        @animation-complete="state.progress === 1 && (state.progress = 0)"
      />
    </motion.div>
  </AnimatePresence>
</template>

<style scoped>
.oz-loading-bar { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 200; pointer-events: none; }
.fill {
  height: 100%; transform-origin: 0 50%;
  background: linear-gradient(90deg, var(--oz-accent), var(--oz-info));
  box-shadow: 0 0 10px rgba(var(--oz-accent-rgb), .6);
  border-radius: 0 3px 3px 0;
}
</style>
