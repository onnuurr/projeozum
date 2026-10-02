<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { OzCard, OzField, OzInput, OzTextarea, OzButton, OzFileDrop, OzAlert, OzIcon } from '@/Components/Oz'
// --oz-* token'ları OzShell'in global stilinde; kabuksuz sayfada yalnızca stil için yüklenir
import '@/Components/Oz/OzShell.vue'
import { isValidTaxNumber } from '../utils/turkishTaxNumber'

// Herkese açık iş ortağı başvuru formu (/tenant-basvuru, girişsiz, Oz kit).
// Uygulama kabuğu yok: Oz token'ları için kendi .oz-root sarmalayıcısı var; html kaydırması Oz'da kapalı
// olduğundan sayfa bu sarmalayıcının içinde kayar. Başarıda flash.toast mesajı gösterilir, form gizlenir.
const page = usePage()
const toast = computed(() => page.props.flash?.toast ?? null)
const appName = computed(() => page.props.app?.name || 'OzumServer')

const DOCS = [
	{ key: 'vergi_levhasi', label: 'Vergi levhası', required: true },
	{ key: 'ticaret_sicil_gazetesi', label: 'Ticaret sicil gazetesi' },
	{ key: 'imza_sirkuleri', label: 'İmza sirküleri' },
]
const MAX_SIZE = 10 * 1024 * 1024

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
	// OzFileDrop dizi tutar; gönderimde tek dosyaya çevrilir
	vergi_levhasi: [],
	ticaret_sicil_gazetesi: [],
	imza_sirkuleri: [],
})

// null: henüz sorgulanmadı, true/false: VKN/TCKN checksum sonucu (dış servise gitmeden)
const taxCheck = ref(null)
const taxHint = computed(() => (taxCheck.value === true ? '✓ Biçim geçerli' : '10 haneli VKN ya da 11 haneli TCKN'))
const taxError = computed(() => form.errors.tax_number || (taxCheck.value === false ? 'Geçersiz VKN/TCKN — lütfen kontrol edin' : ''))

const fileErrors = ref({})
function onReject(key, list) {
	const r = list[0]
	fileErrors.value[key] = r?.reason === 'size' ? 'Dosya 10 MB\'tan büyük olamaz' : 'Yalnızca PDF, JPG ya da PNG'
}
function onFiles(key) {
	fileErrors.value[key] = ''
	form.clearErrors(key)
}

function submit() {
	form
		.transform((d) => ({
			...d,
			vergi_levhasi: d.vergi_levhasi[0] ?? null,
			ticaret_sicil_gazetesi: d.ticaret_sicil_gazetesi[0] ?? null,
			imza_sirkuleri: d.imza_sirkuleri[0] ?? null,
		}))
		.post(route('tenant-applications.store'), { forceFormData: true, preserveScroll: true })
}
</script>

<template>
	<Head title="İş Ortağı Başvurusu" />

	<div class="oz-root apply" data-theme="light">
		<div class="wrap">
			<Link href="/" class="brand">
				<span class="brand-mark"><OzIcon name="layers" :stroke-width="2.5" /></span>
				<span class="brand-name">{{ appName }}<small>İş ortağı başvurusu</small></span>
			</Link>

			<OzCard class="card">
				<div class="intro">
					<h1>İş ortağı başvuru formu</h1>
					<p>Platforma iş ortağı olarak katılmak için firma bilgilerinizi girin ve doğrulama belgelerinizi yükleyin. Başvurunuz incelendikten sonra sizinle iletişime geçilecektir.</p>
				</div>

				<OzAlert v-if="toast" :tone="toast.type === 'error' ? 'danger' : (toast.type || 'success')" :title="toast.title || 'Başvurunuz alındı'">
					{{ toast.message }}
				</OzAlert>

				<form v-else class="form" novalidate @submit.prevent="submit">
					<div class="section">Firma</div>
					<div class="grid-2">
						<OzField label="Firma / marka adı" required :error="form.errors.name">
							<OzInput v-model="form.name" v-autofocus maxlength="191" autocomplete="organization" />
						</OzField>
						<OzField label="Yasal unvan" :error="form.errors.legal_name">
							<OzInput v-model="form.legal_name" maxlength="255" />
						</OzField>
						<OzField label="Vergi no (VKN/TCKN)" required :error="taxError" :hint="taxHint">
							<div class="inline">
								<OzInput v-model="form.tax_number" class="mono" inputmode="numeric" maxlength="11" @update:model-value="taxCheck = null" />
								<OzButton icon="check" @click="taxCheck = isValidTaxNumber(form.tax_number)">Sorgula</OzButton>
							</div>
						</OzField>
						<OzField label="Vergi dairesi" :error="form.errors.tax_office">
							<OzInput v-model="form.tax_office" maxlength="191" />
						</OzField>
					</div>

					<div class="section">İletişim</div>
					<div class="grid-2">
						<OzField label="Yetkili kişi" required :error="form.errors.contact_person">
							<OzInput v-model="form.contact_person" icon="user" maxlength="191" autocomplete="name" />
						</OzField>
						<OzField label="E-posta" required :error="form.errors.contact_email">
							<OzInput v-model="form.contact_email" type="email" icon="mail" maxlength="191" autocomplete="email" />
						</OzField>
						<OzField label="Telefon" :error="form.errors.contact_phone">
							<OzInput v-model="form.contact_phone" type="tel" icon="phone" maxlength="32" autocomplete="tel" />
						</OzField>
						<OzField label="Posta kodu" :error="form.errors.postal_code">
							<OzInput v-model="form.postal_code" maxlength="16" autocomplete="postal-code" />
						</OzField>
					</div>
					<OzField label="Adres" :error="form.errors.address">
						<OzTextarea v-model="form.address" :rows="2" :maxlength="1000" />
					</OzField>
					<div class="grid-2">
						<OzField label="İl" :error="form.errors.city"><OzInput v-model="form.city" maxlength="100" /></OzField>
						<OzField label="İlçe" :error="form.errors.district"><OzInput v-model="form.district" maxlength="100" /></OzField>
					</div>
					<OzField label="Mesajınız" :error="form.errors.message">
						<OzTextarea v-model="form.message" :rows="3" :maxlength="2000" />
					</OzField>

					<div class="section">Doğrulama belgeleri</div>
					<p class="section-hint">Kimlik ve unvan doğrulaması bu belgeler üzerinden yapılır (PDF, JPG, PNG · en fazla 10 MB).</p>
					<div class="docs">
						<OzField v-for="d in DOCS" :key="d.key" :label="d.label" :required="d.required" :error="fileErrors[d.key] || form.errors[d.key]">
							<OzFileDrop
								v-model="form[d.key]"
								accept=".pdf,.jpg,.jpeg,.png"
								:max-size="MAX_SIZE"
								title="Dosya seçin ya da sürükleyin"
								@reject="(l) => onReject(d.key, l)"
								@update:model-value="onFiles(d.key)"
							/>
						</OzField>
					</div>

					<!-- Bot koruması: gizli alan, insan kullanıcıda boş kalmalı -->
					<div class="hp" aria-hidden="true">
						<label for="website">Website</label>
						<input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off">
					</div>

					<div class="foot">
						<span class="req-note"><b>*</b> zorunlu alan</span>
						<OzButton type="submit" variant="primary" icon="send" :loading="form.processing">Başvuruyu gönder</OzButton>
					</div>
				</form>
			</OzCard>
		</div>
	</div>
</template>

<style scoped>
/* Oz kabuğunda html/body kaymaz; bu sayfa kendi kaydırma kabı */
.apply { position: fixed; inset: 0; overflow-y: auto; background: var(--oz-page-bg); }
.wrap { max-width: 760px; margin: 0 auto; padding: 36px 16px 56px; }
.brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 22px; font-weight: 800; font-size: 16px; color: var(--oz-ink-soft); text-decoration: none; }
.brand-mark {
	width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
	background: linear-gradient(135deg, var(--oz-accent), var(--oz-accent-hover));
	color: #fff;
	box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .35);
}
.brand-name small { display: block; font-size: 9.5px; font-weight: 600; color: var(--oz-muted); letter-spacing: .04em; text-transform: uppercase; margin-top: 1px; }
.card { padding: 22px 24px; }
.intro { margin-bottom: 18px; }
.intro h1 { margin: 0 0 6px; font-size: 19px; font-weight: 800; color: var(--oz-ink); }
.intro p { margin: 0; font-size: 13px; line-height: 1.55; color: var(--oz-muted); }

.form { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.section { margin-top: 4px; padding-top: 12px; border-top: 1px solid var(--oz-border-soft); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.section:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
.section-hint { margin: -6px 0 0; font-size: 12px; color: var(--oz-muted); }
.inline { display: flex; gap: 8px; }
.inline > :first-child { flex: 1; min-width: 0; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; }
.docs { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
.hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-top: 14px; border-top: 1px solid var(--oz-border-soft); }
.req-note { font-size: 12px; color: var(--oz-muted); }
.req-note b { color: var(--oz-danger); }

@media (max-width: 560px) {
	.wrap { padding-top: 22px; }
	.card { padding: 18px 16px; }
	.grid-2 { grid-template-columns: minmax(0, 1fr); }
	.foot { flex-direction: column-reverse; align-items: stretch; }
}
</style>
