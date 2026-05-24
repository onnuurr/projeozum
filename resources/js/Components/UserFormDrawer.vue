<template>
	<Teleport to="body">
		<aside class="drawer user-form-drawer" :class="{ open: modelValue }" role="dialog" aria-modal="true">
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon" :class="isEdit ? 'icon-edit' : 'icon-add'">
						<svg v-if="isEdit" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
							<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
						</svg>
						<svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
							<circle cx="8.5" cy="7" r="4" />
							<line x1="20" y1="8" x2="20" y2="14" />
							<line x1="23" y1="11" x2="17" y2="11" />
						</svg>
					</div>
					<div>
						<h4>{{ isEdit ? 'Kullanıcıyı Düzenle' : 'Yeni Kullanıcı Ekle' }}</h4>
						<p>{{ isEdit ? user.name : 'Sisteme yeni bir kullanıcı kaydedin' }}</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div class="drawer-body">
				<div v-if="!isEdit" class="info-banner">
					<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="10" />
						<line x1="12" y1="8" x2="12" y2="12" />
						<line x1="12" y1="16" x2="12.01" y2="16" />
					</svg>
					Davet bağlantısı kullanıcının e-posta adresine gönderilecektir.
				</div>
				<div v-else class="info-banner edit-banner">
					<div class="edit-banner-avatar" :style="{ background: user.avatarGradient }">{{ user.initials }}</div>
					<div class="edit-banner-text">
						<div class="edit-banner-name">{{ user.name }}</div>
						<div class="edit-banner-email">{{ user.email }}</div>
					</div>
				</div>

				<div class="form-section form-grid-2">
					<div class="form-group">
						<label class="form-label">Ad Soyad <span class="required">*</span></label>
						<div class="form-input-wrap">
							<svg class="form-input-icon" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
								<circle cx="12" cy="7" r="4" />
							</svg>
							<input
								v-model="form.name"
								class="form-input"
								type="text"
								placeholder="örn. Ali Yılmaz"
								@keydown.enter="submit"
							/>
						</div>
					</div>

					<div class="form-group">
						<label class="form-label">E-posta <span class="required">*</span></label>
						<div class="form-input-wrap">
							<svg class="form-input-icon" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
								<polyline points="22,6 12,13 2,6" />
							</svg>
							<input
								v-model="form.email"
								class="form-input"
								type="email"
								placeholder="ornek@tekstilerp.com"
								@keydown.enter="submit"
							/>
						</div>
					</div>

					<CustomSelect
						label="Rol"
						v-model="form.role"
						:options="roleOptions"
						placeholder="Rol seçiniz..."
					/>

					<CustomSelect
						label="Durum"
						v-model="form.status"
						:options="statusOptions"
						placeholder="Durum seçiniz..."
					/>
				</div>

				<div class="form-section">
					<div class="drawer-section-title">Yetkiler</div>
					<div class="permission-list permission-grid">
						<label v-for="perm in permissions" :key="perm.key" class="perm-row">
							<input type="checkbox" v-model="form.permissions[perm.key]" />
							<span class="perm-check"></span>
							<div class="perm-text">
								<span class="perm-label">{{ perm.label }}</span>
								<span class="perm-desc">{{ perm.desc }}</span>
							</div>
						</label>
					</div>
				</div>
			</div>

			<div class="drawer-footer">
				<button class="btn btn-ghost" @click="close">İptal</button>
				<button class="btn btn-primary btn-with-icon" @click="submit">
					<svg v-if="isEdit" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M20 6L9 17l-5-5" />
					</svg>
					<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{{ isEdit ? 'Değişiklikleri Kaydet' : 'Kullanıcı Ekle' }}
				</button>
			</div>
		</aside>
	</Teleport>
</template>

<script setup>
import { reactive, computed, watch } from 'vue'
import CustomSelect from './CustomSelect.vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	user: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const isEdit = computed(() => !!props.user)

const roleOptions = [
	{ value: 'admin', label: 'Yönetici' },
	{ value: 'manager', label: 'Müdür' },
	{ value: 'operator', label: 'Operatör' },
	{ value: 'user', label: 'Kullanıcı' },
]

const statusOptions = [
	{ value: 'active', label: 'Aktif', dot: '#16a34a' },
	{ value: 'pending', label: 'Beklemede', dot: '#ca8a04' },
	{ value: 'inactive', label: 'Pasif', dot: '#dc2626' },
]

const permissions = [
	{ key: 'orders', label: 'Sipariş Yönetimi', desc: 'Sipariş oluşturma ve düzenleme' },
	{ key: 'production', label: 'Üretim İş Akışı', desc: 'İş emirleri ve üretim takibi' },
	{ key: 'reports', label: 'Raporlar', desc: 'Üretim ve satış raporlarını görüntüleme' },
	{ key: 'admin', label: 'Sistem Yönetimi', desc: 'Kullanıcı ve sistem ayarları' },
]

const defaultForm = () => ({
	name: '',
	email: '',
	role: null,
	status: 'active',
	permissions: { orders: true, production: true, reports: false, admin: false },
})

const form = reactive(defaultForm())

function resetForm() {
	Object.assign(form, defaultForm())
}

function close() {
	emit('update:modelValue', false)
}

function submit() {
	emit('submit', {
		...form,
		permissions: { ...form.permissions },
		id: props.user?.id ?? null,
		mode: isEdit.value ? 'edit' : 'create',
	})
}

// Drawer açıldığında: edit modunda user verisini forma yükle, ekleme modunda sıfırla.
// Kapandığında: animasyon biterken formu temizle.
// body.overflow yönetimi parent'a (Users.vue) ait.
watch(
	() => props.modelValue,
	(open) => {
		if (open) {
			if (props.user) {
				Object.assign(form, {
					name: props.user.name || '',
					email: props.user.email || '',
					role: props.user.roleClass || null,
					status: props.user.statusClass || 'active',
					permissions: {
						orders: props.user.permissions?.orders ?? true,
						production: props.user.permissions?.production ?? true,
						reports: props.user.permissions?.reports ?? false,
						admin: props.user.permissions?.admin ?? false,
					},
				})
			} else {
				resetForm()
			}
		} else {
			setTimeout(resetForm, 250)
		}
	}
)
</script>

<style scoped>
.user-form-drawer {
	width: 61vw;
	max-width: calc(100vw - 24px);
}

.icon-add {
	background: #eef0ff !important;
	color: #4a6cf7 !important;
}
.icon-edit {
	background: #fef3c7 !important;
	color: #d97706 !important;
}

.info-banner {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border-radius: 10px;
	background: #f0f4ff;
	border: 1px solid #e0e7ff;
	font-size: 12.5px;
	color: #4338ca;
}
.info-banner svg { flex-shrink: 0; color: #6366f1; }

.info-banner.edit-banner {
	background: #fffbeb;
	border-color: #fde68a;
	gap: 12px;
}

.edit-banner-avatar {
	width: 38px; height: 38px;
	border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	font-size: 13px; font-weight: 700; color: #fff;
	flex-shrink: 0;
}

.edit-banner-text { flex: 1; min-width: 0; }
.edit-banner-name { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.edit-banner-email { font-size: 11.5px; color: #888; }

.form-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.form-grid-2 {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}

.permission-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 8px;
}

.form-group {
	display: flex;
	flex-direction: column;
	gap: 5px;
}

.required { color: #ef4444; margin-left: 2px; }

.form-input-wrap { position: relative; }
.form-input-wrap .form-input { padding-left: 34px; width: 100%; }
.form-input-icon {
	position: absolute;
	left: 10px;
	top: 50%;
	transform: translateY(-50%);
	color: #bbb;
	pointer-events: none;
}

/* ── İzinler ── */
.permission-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.perm-row {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	padding: 10px 12px;
	border-radius: 10px;
	background: #fafafe;
	border: 1px solid #f0f0f6;
	cursor: pointer;
	transition: border-color .15s, background .15s;
	user-select: none;
}
.perm-row:hover { border-color: #d0d8f8; background: #f4f6ff; }
.perm-row input[type=checkbox] { display: none; }

.perm-check {
	width: 16px; height: 16px;
	border-radius: 5px;
	border: 1.5px solid #d0d0e0;
	background: #fff;
	flex-shrink: 0;
	margin-top: 2px;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all .15s;
}
.perm-row input:checked + .perm-check {
	background: #4a6cf7;
	border-color: #4a6cf7;
}
.perm-row input:checked + .perm-check::after {
	content: '';
	display: block;
	width: 4px; height: 7px;
	border: 2px solid #fff;
	border-top: none; border-left: none;
	transform: rotate(45deg) translate(-1px, -1px);
}

.perm-text {
	display: flex;
	flex-direction: column;
	gap: 2px;
	flex: 1;
}

.perm-label {
	font-size: 12.5px;
	font-weight: 600;
	color: #1a1a2e;
}

.perm-desc {
	font-size: 11px;
	color: #888;
	line-height: 1.4;
}
</style>
