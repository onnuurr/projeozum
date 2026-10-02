<script setup>
import { computed, inject, nextTick, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { isTouchPrimary } from '@/Components/Oz/ozTouch'
import {
	OzPageHeader, OzCard, OzAvatar, OzPill, OzButton, OzField, OzInput, OzAlert, OzSettingRow, OzModal, OzIcon,
} from '@/Components/Oz'

// Profilim (Oz kit). Tekil kayıt sayfası → stats satırı yok; solda özet kartı, sağda formlar.
//   Bilgiler → profile.update (PATCH), parola → password.update (PUT), hesap silme → profile.destroy (DELETE).
defineOptions({ layout: AppLayout })

const props = defineProps({
	mustVerifyEmail: { type: Boolean, default: false },
	status: { type: String, default: '' },
})

const page = usePage()
const showToast = inject('showToast', null)
const user = computed(() => page.props.auth?.user ?? {})

/* ── özet ── */
const ROLE_LABELS = { superadmin: 'Süper admin', tenant: 'İş ortağı', 'tenant-user': 'İş ortağı kullanıcısı' }
const ROLE_TONES = { superadmin: 'accent', tenant: 'info', 'tenant-user': 'neutral' }
const roles = computed(() => (page.props.auth?.roles ?? []).map((r) => ({ name: r, label: ROLE_LABELS[r] ?? r, tone: ROLE_TONES[r] ?? 'neutral' })))
const unverified = computed(() => props.mustVerifyEmail && !user.value.email_verified_at)
const fmtDate = (v) => (v ? new Date(v).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' }) : '—')
const facts = computed(() => [
	{ key: 'email', icon: 'mail', label: 'E-posta', value: user.value.email },
	{ key: 'phone', icon: 'phone', label: 'Telefon', value: user.value.phone || '—' },
	{ key: 'since', icon: 'calendar', label: 'Kayıt tarihi', value: fmtDate(user.value.created_at) },
])

/* ── profil bilgileri ── */
// Telefon maskesi: backend (ProfileUpdateRequest) aynı "0 (5xx) xxx xx xx" biçimine normalize eder.
const PHONE_MASK = '0 (###) ### ## ##'
const infoForm = useForm({ name: user.value.name ?? '', email: user.value.email ?? '', phone: user.value.phone ?? '' })
const saveInfo = () => infoForm.patch(route('profile.update'), {
	preserveScroll: true,
	onSuccess: () => showToast?.({ type: 'success', title: 'Profil güncellendi' }),
})

/* ── parola ── */
const pwForm = useForm({ current_password: '', password: '', password_confirmation: '' })
const currentPwInput = ref(null)
const newPwInput = ref(null)
const savePassword = () => pwForm.put(route('password.update'), {
	preserveScroll: true,
	onSuccess: () => {
		pwForm.reset()
		showToast?.({ type: 'success', title: 'Parola güncellendi' })
	},
	onError: () => {
		if (pwForm.errors.password) {
			pwForm.reset('password', 'password_confirmation')
			newPwInput.value?.focus()
		}
		if (pwForm.errors.current_password) {
			pwForm.reset('current_password')
			currentPwInput.value?.focus()
		}
	},
})

/* ── hesap silme ── */
const deleteOpen = ref(false)
const deleteInput = ref(null)
const deleteForm = useForm({ password: '' })
function openDelete() {
	deleteOpen.value = true
	// Dokunmatikte klavye kendiliğinden açılmasın (bkz. ozTouch.js).
	if (!isTouchPrimary()) nextTick(() => deleteInput.value?.focus())
}
function closeDelete() {
	deleteOpen.value = false
	deleteForm.clearErrors()
	deleteForm.reset()
}
const deleteAccount = () => deleteForm.delete(route('profile.destroy'), {
	preserveScroll: true,
	onSuccess: () => closeDelete(),
	onError: () => deleteInput.value?.focus(),
	onFinish: () => deleteForm.reset(),
})
</script>

<template>
	<Head title="Profilim" />

	<OzPageHeader title="Profilim" subtitle="Hesap bilgileriniz, parolanız ve hesap güvenliği" />

	<div class="layout">
		<aside class="side">
			<OzCard class="summary">
				<div class="who">
					<OzAvatar :name="user.name" :size="64" />
					<div class="who-text">
						<div class="name">{{ user.name }}</div>
						<div v-if="user.job_title" class="title">{{ user.job_title }}</div>
					</div>
					<div v-if="roles.length" class="roles">
						<OzPill v-for="r in roles" :key="r.name" :tone="r.tone" :label="r.label" />
					</div>
				</div>
				<ul class="facts">
					<li v-for="f in facts" :key="f.key">
						<span class="ico"><OzIcon :name="f.icon" :size="14" /></span>
						<span class="lbl">{{ f.label }}</span>
						<span class="val">{{ f.value }}</span>
					</li>
					<li v-if="mustVerifyEmail">
						<span class="ico"><OzIcon name="shield" :size="14" /></span>
						<span class="lbl">E-posta durumu</span>
						<span class="val">
							<OzPill :tone="unverified ? 'warning' : 'success'" :label="unverified ? 'Doğrulanmadı' : 'Doğrulandı'" />
						</span>
					</li>
				</ul>
			</OzCard>
		</aside>

		<div class="main">
			<OzCard title="Profil Bilgileri" subtitle="Adınızı ve giriş için kullandığınız e-posta adresini güncelleyin.">
				<form class="form card-form" @submit.prevent="saveInfo">
					<div class="grid">
						<OzField label="Ad Soyad" required :error="infoForm.errors.name" v-slot="{ id }">
							<OzInput :id="id" v-model="infoForm.name" icon="user" autocomplete="name" required :invalid="!!infoForm.errors.name" />
						</OzField>
						<OzField label="E-posta" required :error="infoForm.errors.email" v-slot="{ id }">
							<OzInput :id="id" v-model="infoForm.email" type="email" icon="mail" autocomplete="username" required :invalid="!!infoForm.errors.email" />
						</OzField>
						<OzField label="Telefon" :error="infoForm.errors.phone" v-slot="{ id }">
							<OzInput
								v-maska="PHONE_MASK"
								:id="id"
								v-model="infoForm.phone"
								type="tel"
								inputmode="numeric"
								icon="phone"
								autocomplete="tel-national"
								placeholder="0 (5xx) xxx xx xx"
								:invalid="!!infoForm.errors.phone"
							/>
						</OzField>
					</div>

					<OzAlert v-if="unverified" tone="warning" title="E-posta adresiniz doğrulanmadı">
						<template v-if="status === 'verification-link-sent'">Yeni doğrulama bağlantısı e-posta adresinize gönderildi.</template>
						<template v-else>Doğrulama e-postası almadıysanız yeniden gönderebilirsiniz.</template>
						<template v-if="status !== 'verification-link-sent'" #actions>
							<Link :href="route('verification.send')" method="post" as="button" class="link-btn">Yeniden gönder</Link>
						</template>
					</OzAlert>

					<div class="foot">
						<span v-if="infoForm.isDirty" class="dirty">Kaydedilmemiş değişiklik var</span>
						<OzButton type="submit" variant="primary" icon="save" :loading="infoForm.processing" :disabled="!infoForm.isDirty">Kaydet</OzButton>
					</div>
				</form>
			</OzCard>

			<OzCard title="Parola" subtitle="Hesabınızın güvenliği için uzun ve tahmin edilemez bir parola kullanın.">
				<form class="form card-form" @submit.prevent="savePassword">
					<OzField label="Mevcut Parola" required :error="pwForm.errors.current_password" v-slot="{ id }">
						<OzInput ref="currentPwInput" :id="id" v-model="pwForm.current_password" type="password" icon="lock" revealable autocomplete="current-password" :invalid="!!pwForm.errors.current_password" />
					</OzField>
					<div class="grid">
						<OzField label="Yeni Parola" required :error="pwForm.errors.password" v-slot="{ id }">
							<OzInput ref="newPwInput" :id="id" v-model="pwForm.password" type="password" icon="key" revealable autocomplete="new-password" :invalid="!!pwForm.errors.password" />
						</OzField>
						<OzField label="Yeni Parola (Tekrar)" required :error="pwForm.errors.password_confirmation" v-slot="{ id }">
							<OzInput :id="id" v-model="pwForm.password_confirmation" type="password" icon="key" revealable autocomplete="new-password" :invalid="!!pwForm.errors.password_confirmation" />
						</OzField>
					</div>
					<div class="foot">
						<OzButton type="submit" variant="primary" icon="lock" :loading="pwForm.processing" :disabled="!pwForm.current_password || !pwForm.password">Parolayı Güncelle</OzButton>
					</div>
				</form>
			</OzCard>

			<OzCard title="Hesap Silme">
				<OzSettingRow
					icon="trash"
					tone="danger"
					title="Hesabı sil"
					description="Hesabınız ve ona bağlı tüm veriler kalıcı olarak silinir. Bu işlem geri alınamaz."
				>
					<OzButton variant="danger" icon="trash" @click="openDelete">Hesabı Sil</OzButton>
				</OzSettingRow>
			</OzCard>
		</div>
	</div>

	<OzModal
		v-model:open="deleteOpen"
		title="Hesabınızı silmek istediğinize emin misiniz?"
		subtitle="Bu işlem geri alınamaz"
		icon="warning"
		tone="danger"
		size="sm"
		:persistent="deleteForm.processing"
		@close="closeDelete"
	>
		<form class="form" @submit.prevent="deleteAccount">
			<p class="modal-text">
				Hesabınız silindiğinde tüm kaynakları ve verileri kalıcı olarak silinir. Onaylamak için parolanızı girin.
			</p>
			<OzField label="Parola" required :error="deleteForm.errors.password" v-slot="{ id }">
				<OzInput ref="deleteInput" :id="id" v-model="deleteForm.password" type="password" icon="lock" revealable autocomplete="current-password" :invalid="!!deleteForm.errors.password" />
			</OzField>
		</form>
		<template #footer>
			<OzButton @click="closeDelete">Vazgeç</OzButton>
			<OzButton variant="danger" icon="trash" :loading="deleteForm.processing" :disabled="!deleteForm.password" @click="deleteAccount">Hesabı Sil</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 14px; align-items: start; }
.side { position: sticky; top: 14px; }
.main { display: flex; flex-direction: column; gap: 14px; min-width: 0; }

.who { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px; padding: 6px 0 14px; border-bottom: 1px solid var(--oz-border-soft); }
.who-text { min-width: 0; }
.name { font-size: 16px; font-weight: 700; color: var(--oz-ink); word-break: break-word; }
.title { font-size: 12px; color: var(--oz-muted); margin-top: 2px; }
.roles { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }

.facts { list-style: none; margin: 0; padding: 12px 0 0; display: flex; flex-direction: column; gap: 10px; }
.facts li { display: grid; grid-template-columns: 28px minmax(0, 1fr); grid-template-rows: auto auto; column-gap: 10px; align-items: center; }
.facts .ico {
	grid-row: span 2; width: 28px; height: 28px; border-radius: 8px;
	display: flex; align-items: center; justify-content: center;
	background: var(--oz-surface-2); color: var(--oz-muted); border: 1px solid var(--oz-border-soft);
}
.facts .lbl { font-size: 11px; color: var(--oz-muted); }
.facts .val { font-size: 13px; color: var(--oz-ink-soft); font-weight: 500; overflow-wrap: anywhere; }

.form { display: flex; flex-direction: column; gap: 14px; }
.card-form { padding: 4px 18px 18px; }
.summary { padding: 18px; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.foot { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding-top: 4px; }
.dirty { font-size: 12px; color: var(--oz-muted); }

.link-btn {
	font-size: 12px; font-weight: 600; color: var(--oz-accent); background: none; border: 0; padding: 0; cursor: pointer;
}
.link-btn:hover { text-decoration: underline; }
.modal-text { margin: 0; font-size: 13px; line-height: 1.5; color: var(--oz-ink-soft); }

@media (max-width: 1040px) {
	.layout { grid-template-columns: minmax(0, 1fr); }
	.side { position: static; }
}
@media (max-width: 640px) {
	.grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
