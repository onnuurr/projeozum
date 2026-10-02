<script setup>
import { ref, computed, useAttrs } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzField } from './useOzField'
import { useOzSplitAttrs } from './useOzSplitAttrs'
import { numberInputMode } from './ozTouch'

defineOptions({ inheritAttrs: false })

// Metin girişi. icon: soldaki ikon, suffix: sağdaki birim (ör. "adet", "₺").
// revealable: type="password" iken sağda göster/gizle düğmesi (parola, API anahtarı).
// Diğer attribute'lar (min, max, step, autocomplete…) doğrudan <input>'a gider.
const props = defineProps({
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  icon: { type: String, default: '' },
  suffix: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
  revealable: { type: Boolean, default: false },
})

const revealed = ref(false)
const inputType = computed(() => (props.type === 'password' && revealed.value ? 'text' : props.type))

const model = defineModel({ type: [String, Number], default: '' })
const { inputId, invalid, required, describedBy } = useOzField(props)
const attrs = useAttrs()
const { rootAttrs, inputAttrs: splitInputAttrs } = useOzSplitAttrs()
// Dışarıdan gelen @input <input>'a doğrudan bağlanırsa bizim onInput'umuzdan ÖNCE çalışır;
// v-model henüz güncellenmemiş olur (ör. slug bir karakter geriden gelir). Onu ayırıp
// model güncellendikten sonra biz çağırıyoruz.
const inputAttrs = computed(() => {
  const { onInput: _onInput, ...rest } = splitInputAttrs.value
  return rest
})

// Tam sayı alanlarında mobilde rakam tuş takımı (bkz. ozTouch.js numberInputMode)
const inputMode = computed(() => numberInputMode(props.type, attrs))

const inputEl = ref(null)
defineExpose({ focus: () => inputEl.value?.focus() })

function onInput(e) {
  const v = e.target.value
  model.value = props.type === 'number' && v !== '' ? Number(v) : v
  ;[attrs.onInput].flat().forEach(fn => typeof fn === 'function' && fn(e))
}
</script>

<template>
  <div class="oz-input" :class="{ invalid, disabled }" v-bind="rootAttrs">
    <OzIcon v-if="icon" :name="icon" :size="14" class="lead" />
    <input
      :id="inputId"
      ref="inputEl"
      v-bind="inputAttrs"
      :type="inputType"
      :inputmode="inputMode"
      :value="model"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
      @input="onInput"
    >
    <span v-if="suffix" class="suffix">{{ suffix }}</span>
    <button
      v-if="revealable && type === 'password'"
      type="button"
      class="reveal"
      :aria-label="revealed ? 'Gizle' : 'Göster'"
      :aria-pressed="revealed"
      :disabled="disabled"
      @click="revealed = !revealed"
    >
      <OzIcon :name="revealed ? 'eye-off' : 'eye'" :size="15" />
    </button>
  </div>
</template>

<style scoped>
.oz-input {
  display: flex; align-items: center; gap: 8px;
  height: 36px; padding: 0 12px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 9px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.oz-input:hover { border-color: var(--oz-border-strong); }
.oz-input:focus-within { border-color: rgba(var(--oz-accent-rgb), .55); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .14); }
.oz-input.invalid { border-color: rgba(var(--oz-danger-rgb), .6); }
.oz-input.invalid:focus-within { box-shadow: 0 0 0 3px rgba(var(--oz-danger-rgb), .14); }
.oz-input.disabled { background: var(--oz-surface-2); opacity: .7; }
input {
  flex: 1; min-width: 0; height: 100%;
  border: none; outline: none; background: transparent;
  font: inherit; font-size: 12.5px; color: var(--oz-ink);
}
input::placeholder { color: var(--oz-muted-2); }
input:disabled { cursor: not-allowed; }
/* Ana projedeki @tailwindcss/forms (base) tüm input/textarea'lara kenarlık, padding ve odak halkası veriyor;
   Oz kutusu çerçeveyi kendisi çizdiği için iç alanda bunlar sıfırlanır. */
.oz-input input, .oz-input input:focus { border: 0; border-radius: 0; padding: 0; box-shadow: none; outline: none; background-color: transparent; }
.lead { color: var(--oz-muted-2); }
.oz-input:focus-within .lead { color: var(--oz-accent); }
.reveal { display: flex; margin-right: -6px; padding: 5px; border: none; border-radius: 6px; background: none; color: var(--oz-muted); cursor: pointer; }
.reveal:hover { color: var(--oz-ink); background: var(--oz-surface-2); }
.reveal:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.suffix { font-size: 11.5px; font-weight: 600; color: var(--oz-muted); white-space: nowrap; }
</style>
