<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    length: { type: [Number, String], default: 6 },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    autofocus: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'complete']);

const len = computed(() => Number(props.length));
const digits = ref([]);
const inputs = ref([]);

function syncFromValue(val) {
    const arr = [];
    for (let i = 0; i < len.value; i++) {
        arr.push((val || '').charAt(i) || '');
    }
    digits.value = arr;
}

syncFromValue(props.modelValue);

watch(
    () => props.modelValue,
    (val) => {
        if ((val || '') !== digits.value.join('')) {
            syncFromValue(val);
        }
    }
);

function setInputRef(el, idx) {
    if (el) inputs.value[idx] = el;
}

function emitValue() {
    const value = digits.value.join('');
    emit('update:modelValue', value);
    if (value.length === len.value && digits.value.every((d) => d !== '')) {
        emit('complete', value);
    }
}

function onInput(e, idx) {
    const raw = e.target.value;
    const digit = raw.replace(/\D/g, '').slice(-1);
    digits.value[idx] = digit;
    e.target.value = digit;
    emitValue();
    if (digit && idx < len.value - 1) {
        nextTick(() => inputs.value[idx + 1]?.focus());
    }
}

function onKeydown(e, idx) {
    if (e.key === 'Backspace') {
        if (digits.value[idx]) {
            digits.value[idx] = '';
            emitValue();
        } else if (idx > 0) {
            e.preventDefault();
            digits.value[idx - 1] = '';
            emitValue();
            nextTick(() => inputs.value[idx - 1]?.focus());
        }
    } else if (e.key === 'ArrowLeft' && idx > 0) {
        e.preventDefault();
        inputs.value[idx - 1]?.focus();
    } else if (e.key === 'ArrowRight' && idx < len.value - 1) {
        e.preventDefault();
        inputs.value[idx + 1]?.focus();
    }
}

function onPaste(e) {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text');
    const onlyDigits = text.replace(/\D/g, '').slice(0, len.value);
    if (!onlyDigits) return;
    for (let i = 0; i < len.value; i++) {
        digits.value[i] = onlyDigits.charAt(i) || '';
    }
    emitValue();
    const focusIdx = Math.min(onlyDigits.length, len.value - 1);
    nextTick(() => inputs.value[focusIdx]?.focus());
}

function onFocus(e) {
    e.target.select();
}

function focus() {
    nextTick(() => {
        const firstEmpty = digits.value.findIndex((d) => !d);
        const idx = firstEmpty === -1 ? len.value - 1 : firstEmpty;
        inputs.value[idx]?.focus();
    });
}

defineExpose({ focus });

onMounted(() => {
    if (props.autofocus) focus();
});
</script>

<template>
    <div class="form-otp" :class="{ 'has-error': !!error }">
        <input
            v-for="(d, i) in digits"
            :key="i"
            :ref="(el) => setInputRef(el, i)"
            type="text"
            inputmode="numeric"
            maxlength="1"
            class="form-otp-box"
            :value="d"
            :disabled="disabled"
            autocomplete="one-time-code"
            @input="onInput($event, i)"
            @keydown="onKeydown($event, i)"
            @paste="onPaste"
            @focus="onFocus"
        />
    </div>
</template>

<style scoped>
.form-otp {
    display: flex;
    gap: 10px;
    width: 100%;
}
.form-otp-box {
    flex: 1;
    width: 100%;
    min-width: 0;
    height: 52px;
    text-align: center;
    font-size: 20px;
    font-weight: 600;
    border: 1.5px solid #e8e8f0;
    border-radius: 10px;
    background: #fafafe;
    color: #1a1a2e;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    font-family: inherit;
}
.form-otp-box:focus {
    border-color: #4a6cf7;
    box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.12);
    background: #fff;
}
.form-otp-box:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.form-otp.has-error .form-otp-box {
    border-color: #ef4444;
    background: #fef2f2;
}
</style>
