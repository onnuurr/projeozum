<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { isValidTaxNumber } from '../utils/turkishTaxNumber'

const page = usePage()
const toast = computed(() => page.props.flash?.toast ?? null)

const form = useForm({
	name: '',
	legal_name: '',
	tax_number: '',
	tax_office: '',
	contact_person: '',
	contact_email: '',
	contact_phone: '',
	address: '',
	city: '',
	district: '',
	postal_code: '',
	message: '',
	website: '', // honeypot — insan kullanıcıda hep boş kalır
	vergi_levhasi: null,
	ticaret_sicil_gazetesi: null,
	imza_sirkuleri: null,
})

// null: henüz sorgulanmadı, true/false: sorgu sonucu (VKN/TCKN checksum, dış servise gitmeden)
const taxNumberCheck = ref(null)

function checkTaxNumber() {
	taxNumberCheck.value = isValidTaxNumber(form.tax_number)
}

function onTaxNumberInput() {
	// Kullanıcı yeniden yazmaya başlayınca eski sonucu geçersizleştir.
	taxNumberCheck.value = null
}

function onFileChange(field, event) {
	form[field] = event.target.files[0] ?? null
}

function submit() {
	form.post(route('tenant-applications.store'), {
		forceFormData: true,
	})
}
</script>

<template>
	<div class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:pt-10">
		<Head title="Tenant Başvurusu" />

		<Link href="/">
			<ApplicationLogo class="h-16 w-16 fill-current text-gray-500" />
		</Link>

		<div class="mt-6 w-full max-w-2xl overflow-hidden bg-white px-6 py-6 shadow-md sm:rounded-lg">
		<div class="mb-4">
			<h1 class="text-lg font-semibold text-gray-900">Tenant Başvuru Formu</h1>
			<p class="mt-1 text-sm text-gray-600">
				Bayi/tenant olarak platforma katılmak için firma bilgilerinizi girin ve doğrulama
				belgelerinizi yükleyin. Başvurunuz incelendikten sonra sizinle iletişime geçilecektir.
			</p>
		</div>

		<div v-if="toast" class="mb-4 rounded-md border border-green-200 bg-green-50 p-4">
			<p class="text-sm font-medium text-green-800">{{ toast.message }}</p>
		</div>

		<form v-else class="space-y-5" @submit.prevent="submit">
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<InputLabel for="name" value="Firma / Marka Adı *" />
					<TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
					<InputError class="mt-1" :message="form.errors.name" />
				</div>
				<div>
					<InputLabel for="legal_name" value="Yasal Ünvan" />
					<TextInput id="legal_name" v-model="form.legal_name" type="text" class="mt-1 block w-full" />
					<InputError class="mt-1" :message="form.errors.legal_name" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<InputLabel for="tax_number" value="Vergi No (VKN/TCKN) *" />
					<div class="mt-1 flex gap-2">
						<TextInput
							id="tax_number"
							v-model="form.tax_number"
							type="text"
							class="block w-full font-mono"
							maxlength="11"
							required
							@input="onTaxNumberInput"
						/>
						<SecondaryButton type="button" @click="checkTaxNumber">Sorgula</SecondaryButton>
					</div>
					<p v-if="taxNumberCheck === true" class="mt-1 text-sm text-green-700">✓ Biçim geçerli.</p>
					<p v-else-if="taxNumberCheck === false" class="mt-1 text-sm text-red-600">✗ Geçersiz VKN/TCKN — lütfen kontrol edin.</p>
					<InputError class="mt-1" :message="form.errors.tax_number" />
				</div>
				<div>
					<InputLabel for="tax_office" value="Vergi Dairesi" />
					<TextInput id="tax_office" v-model="form.tax_office" type="text" class="mt-1 block w-full" />
					<InputError class="mt-1" :message="form.errors.tax_office" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<InputLabel for="contact_person" value="Yetkili Kişi *" />
					<TextInput id="contact_person" v-model="form.contact_person" type="text" class="mt-1 block w-full" required />
					<InputError class="mt-1" :message="form.errors.contact_person" />
				</div>
				<div>
					<InputLabel for="contact_email" value="E-posta *" />
					<TextInput id="contact_email" v-model="form.contact_email" type="email" class="mt-1 block w-full" required />
					<InputError class="mt-1" :message="form.errors.contact_email" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<InputLabel for="contact_phone" value="Telefon" />
					<TextInput id="contact_phone" v-model="form.contact_phone" type="text" class="mt-1 block w-full" />
					<InputError class="mt-1" :message="form.errors.contact_phone" />
				</div>
				<div>
					<InputLabel for="postal_code" value="Posta Kodu" />
					<TextInput id="postal_code" v-model="form.postal_code" type="text" class="mt-1 block w-full" />
					<InputError class="mt-1" :message="form.errors.postal_code" />
				</div>
			</div>

			<div>
				<InputLabel for="address" value="Adres" />
				<textarea
					id="address"
					v-model="form.address"
					rows="2"
					class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
				/>
				<InputError class="mt-1" :message="form.errors.address" />
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<InputLabel for="city" value="İl" />
					<TextInput id="city" v-model="form.city" type="text" class="mt-1 block w-full" />
				</div>
				<div>
					<InputLabel for="district" value="İlçe" />
					<TextInput id="district" v-model="form.district" type="text" class="mt-1 block w-full" />
				</div>
			</div>

			<div>
				<InputLabel for="message" value="Mesajınız" />
				<textarea
					id="message"
					v-model="form.message"
					rows="3"
					class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
				/>
				<InputError class="mt-1" :message="form.errors.message" />
			</div>

			<div class="border-t border-gray-200 pt-4">
				<h2 class="text-sm font-semibold text-gray-900">Doğrulama Belgeleri</h2>
				<p class="mt-1 text-xs text-gray-500">
					Kimlik/unvan doğrulaması bu belgeler üzerinden yapılır (PDF/JPG/PNG, en fazla 10MB).
				</p>

				<div class="mt-3 grid grid-cols-1 gap-4">
					<div>
						<InputLabel for="vergi_levhasi" value="Vergi Levhası *" />
						<input
							id="vergi_levhasi"
							type="file"
							accept=".pdf,.jpg,.jpeg,.png"
							class="mt-1 block w-full text-sm text-gray-600"
							@change="onFileChange('vergi_levhasi', $event)"
						/>
						<InputError class="mt-1" :message="form.errors.vergi_levhasi" />
					</div>
					<div>
						<InputLabel for="ticaret_sicil_gazetesi" value="Ticaret Sicil Gazetesi" />
						<input
							id="ticaret_sicil_gazetesi"
							type="file"
							accept=".pdf,.jpg,.jpeg,.png"
							class="mt-1 block w-full text-sm text-gray-600"
							@change="onFileChange('ticaret_sicil_gazetesi', $event)"
						/>
						<InputError class="mt-1" :message="form.errors.ticaret_sicil_gazetesi" />
					</div>
					<div>
						<InputLabel for="imza_sirkuleri" value="İmza Sirküleri" />
						<input
							id="imza_sirkuleri"
							type="file"
							accept=".pdf,.jpg,.jpeg,.png"
							class="mt-1 block w-full text-sm text-gray-600"
							@change="onFileChange('imza_sirkuleri', $event)"
						/>
						<InputError class="mt-1" :message="form.errors.imza_sirkuleri" />
					</div>
				</div>
			</div>

			<!-- Bot koruması: gizli alan, insan kullanıcıda boş kalmalı -->
			<div class="hidden" aria-hidden="true">
				<label for="website">Website</label>
				<input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
			</div>

			<div class="flex justify-end">
				<PrimaryButton :disabled="form.processing">Başvuruyu Gönder</PrimaryButton>
			</div>
		</form>
		</div>
	</div>
</template>
