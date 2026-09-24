<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzCard, OzField, OzInput, OzButton, OzAlert, OzIcon } from '@/Components/Oz'

// Süper admin · log erişim kapısı (Oz kit). Doğru şifre → oturuma 30 dk erişim yazılır, /superadmin/logs'a yönlenir.
defineOptions({ layout: AppLayout })

defineProps({
	passwordSet: { type: Boolean, required: true },
})

const form = useForm({ password: '' })

function submit() {
	if (!form.password || form.processing) return
	form.post(route('superadmin.logs.unlock.post'), {
		preserveScroll: true,
		onError: () => form.reset('password'),
	})
}
</script>

<template>
	<Head title="Log Erişim Kapısı · Süper Admin" />

	<div class="wrap">
		<OzCard class="gate">
			<div class="body">
				<span class="lock"><OzIcon name="lock" :size="26" /></span>
				<h1>Log erişim kapısı</h1>
				<p class="sub">Sistem loglarını görüntülemek için log erişim şifresini girin. Erişim <b>30 dakika</b> geçerlidir.</p>

				<OzAlert v-if="!passwordSet" tone="warning" title="Log erişim şifresi belirlenmemiş" class="full">
					Önce <Link :href="`${route('superadmin.settings')}?section=security`" class="link">Ayarlar › Güvenlik</Link> bölümünden bir şifre belirleyin.
				</OzAlert>

				<form v-else class="form" @submit.prevent="submit">
					<OzField label="Şifre" :error="form.errors.password">
						<OzInput
							v-model="form.password"
							type="password"
							revealable
							autocomplete="current-password"
							placeholder="Log erişim şifresi"
							autofocus
						/>
					</OzField>
					<OzButton type="submit" variant="primary" icon="key" :loading="form.processing" :disabled="!form.password" class="go">Kilidi aç</OzButton>
				</form>
			</div>
		</OzCard>
	</div>
</template>

<style scoped>
.wrap { display: flex; justify-content: center; padding: 48px 0 40px; }
.gate { width: 100%; max-width: 420px; }
.body { padding: 32px 28px 28px; display: flex; flex-direction: column; align-items: center; text-align: center; }
.lock { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; background: var(--oz-accent-soft); color: var(--oz-accent); margin-bottom: 14px; }
h1 { margin: 0 0 6px; font-size: 18px; font-weight: 800; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.sub { margin: 0 0 22px; font-size: 13px; line-height: 1.55; color: var(--oz-muted); }
.full { width: 100%; text-align: left; }
.link { color: var(--oz-accent); font-weight: 700; text-decoration: none; }
.link:hover { text-decoration: underline; }
.form { width: 100%; display: flex; flex-direction: column; gap: 14px; text-align: left; }
.go { width: 100%; justify-content: center; }
@media (max-width: 640px) { .wrap { padding-top: 16px; } .body { padding: 26px 18px 20px; } }
</style>
