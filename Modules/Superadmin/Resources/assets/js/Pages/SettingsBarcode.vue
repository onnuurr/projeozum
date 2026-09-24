<script setup>
import { computed } from 'vue'
import { OzField, OzInput, OzSettingRow, OzAlert } from '@/Components/Oz'

// Ayarlar · Barkod (GS1): ülke + firma kodu sabit önek, seri numarası aralığı.
// EAN-13 = önek + seri (12 haneye sığmalı) + kontrol hanesi. Sunucu da aynı kuralı doğrular.
const props = defineProps({
	form: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})

const prefix = computed(() => `${props.form.countryCode ?? ''}${props.form.companyCode ?? ''}`)
const slotWidth = computed(() => 12 - prefix.value.length)
const fits = computed(() => prefix.value.length >= 1 && prefix.value.length <= 11 && slotWidth.value >= String(Number(props.form.serialMax) || 0).length)

function checkDigit(twelve) {
	const sum = [...twelve].reduce((acc, d, i) => acc + Number(d) * (i % 2 ? 3 : 1), 0)
	return (10 - (sum % 10)) % 10
}
const sample = computed(() => {
	if (!fits.value || !/^\d+$/.test(prefix.value)) return ''
	const twelve = prefix.value + String(Number(props.form.serialMin) || 0).padStart(slotWidth.value, '0')
	return twelve + checkDigit(twelve)
})
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Sabit önek</h4>
			<div class="form-grid">
				<OzField label="Ülke kodu" hint="Türkiye: 868–869">
					<OzInput v-model="form.countryCode" icon="hash" inputmode="numeric" maxlength="3" placeholder="869" />
				</OzField>
				<OzField label="Firma kodu" hint="GS1'den aldığınız firma kodu">
					<OzInput v-model="form.companyCode" inputmode="numeric" maxlength="8" />
				</OzField>
			</div>
		</div>
		<div class="block">
			<h4>Seri numarası aralığı</h4>
			<div class="form-grid">
				<OzField label="En küçük">
					<OzInput v-model="form.serialMin" type="number" min="0" />
				</OzField>
				<OzField label="En büyük" :error="errors['barcode.serialMax']">
					<OzInput v-model="form.serialMax" type="number" min="0" />
				</OzField>
			</div>
		</div>
		<OzAlert v-if="!fits" tone="danger" title="Önek ve seri aralığı 12 haneye sığmıyor">
			Önek {{ prefix.length }} hane; seri için {{ Math.max(slotWidth, 0) }} hane kalıyor. En büyük seri numarasını kısaltın ya da öneki gözden geçirin.
		</OzAlert>
		<OzSettingRow v-else icon="barcode" title="Örnek barkod" :description="`Önek ${prefix.length} hane · seri ${slotWidth} hane · 13. hane kontrol hanesi`">
			<code class="ean">{{ sample || '—' }}</code>
		</OzSettingRow>
	</div>
</template>

<style scoped>
.ean { font-family: ui-monospace, monospace; font-size: 14px; font-weight: 700; letter-spacing: .08em; color: var(--oz-ink-soft); }
</style>
