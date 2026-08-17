<template>
	<Head title="Log Erişim Kapısı · Süper Admin" />
	<div class="page-log-unlock">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Log Görüntüleyici' },
				{ label: 'Erişim Kapısı' },
			]"
		/>

		<div class="unlock-wrap">
			<Card class="unlock-card" body-class="unlock-card-body">
				<div class="unlock-icon">
					<Lock :size="28" :stroke-width="1.8" />
				</div>

				<h1 class="unlock-title">Log Erişim Kapısı</h1>
				<p class="unlock-sub">
					Sistem loglarını görüntülemek için log erişim şifrenizi girin.
					Oturum <strong>30 dakika</strong> süreliğine geçerlidir.
				</p>

				<Alert v-if="!passwordSet" variant="warning">
					Henüz log erişim şifresi belirlenmemiş.
					<Link href="/superadmin/settings" class="unlock-settings-link">
						Ayarlar &rsaquo; Güvenlik
					</Link>
					bölümünden bir şifre belirleyin.
				</Alert>

				<form v-else @submit.prevent="submit" class="unlock-form">
					<div class="field">
						<label class="form-label" for="log-password">Şifre</label>
						<div class="password-input">
							<input
								id="log-password"
								v-model="form.password"
								:type="showPassword ? 'text' : 'password'"
								class="form-input"
								:class="{ 'input-error': form.errors.password }"
								placeholder="Log erişim şifrenizi girin"
								autocomplete="current-password"
								autofocus
							/>
							<button type="button" class="pw-toggle" @click="showPassword = !showPassword">
								<EyeOff v-if="showPassword" :size="13" />
								<Eye v-else :size="13" />
							</button>
						</div>
						<div v-if="form.errors.password" class="field-error">
							{{ form.errors.password }}
						</div>
					</div>

					<Button
						type="submit"
						variant="primary"
						class="unlock-btn"
						:loading="form.processing"
						:disabled="!form.password"
						with-icon
					>
						<template #leading><LockOpen :size="13" /></template>
						Kilidi Aç
					</Button>
				</form>
			</Card>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { LockOpen, Lock, Eye, EyeOff } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import Alert from '@/Components/Alert.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	passwordSet: { type: Boolean, required: true },
})

const showPassword = ref(false)

const form = useForm({
	password: '',
})

function submit() {
	form.post(route('superadmin.logs.unlock.post'), {
		preserveScroll: true,
		onError: () => {
			form.password = ''
		},
	})
}
</script>

<style scoped>
.page-log-unlock {
	padding: 0 0 40px;
}

.unlock-wrap {
	display: flex;
	justify-content: center;
	align-items: flex-start;
	padding-top: 40px;
}

.unlock-card {
	width: 100%;
	max-width: 420px;
}

.unlock-card :deep(.unlock-card-body) {
	padding: 36px 32px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.unlock-icon {
	width: 56px;
	height: 56px;
	border-radius: 14px;
	background: var(--color-primary-soft);
	color: var(--color-primary);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 16px;
}

.unlock-title {
	font-size: 18px;
	font-weight: 700;
	margin: 0 0 8px;
	color: var(--color-ink);
	text-align: center;
}

.unlock-sub {
	font-size: 13px;
	color: var(--color-muted);
	text-align: center;
	margin: 0 0 24px;
	line-height: 1.6;
}

.unlock-settings-link {
	color: var(--color-primary);
	text-decoration: underline;
	font-weight: 600;
}

.unlock-form {
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.unlock-btn {
	width: 100%;
	justify-content: center;
}

.password-input {
	position: relative;
}

.password-input .form-input {
	padding-right: 38px;
}

.pw-toggle {
	position: absolute;
	right: 10px;
	top: 50%;
	transform: translateY(-50%);
	background: none;
	border: none;
	cursor: pointer;
	color: var(--color-muted);
	padding: 2px;
	display: flex;
	align-items: center;
}

.pw-toggle:hover {
	color: var(--color-ink);
}

.input-error {
	border-color: var(--color-danger) !important;
}

.field-error {
	font-size: 12px;
	color: var(--color-danger);
	margin-top: 4px;
}

@media (max-width: 640px) {
	.unlock-wrap { padding-top: 20px; }
	.unlock-card :deep(.unlock-card-body) { padding: 24px 18px; }
}
</style>
