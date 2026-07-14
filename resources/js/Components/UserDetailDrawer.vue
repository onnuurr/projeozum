<template>
	<Teleport to="body">
		<aside
			class="drawer user-drawer"
			:class="{ open: modelValue }"
			role="dialog"
			aria-modal="true"
		>
			<template v-if="user">
				<div class="drawer-header">
					<div class="drawer-title">
						<div class="drawer-title-icon">
							<svg
								width="16"
								height="16"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								viewBox="0 0 24 24"
							>
								<path
									d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
								/>
								<circle cx="12" cy="12" r="3" />
							</svg>
						</div>
						<div>
							<h4>Kullanıcı Detayları</h4>
							<p>{{ user.name }}</p>
						</div>
					</div>
					<div class="drawer-header-actions">
						<button
							class="drawer-edit-btn"
							@click="$emit('edit', user)"
							title="Düzenle"
							aria-label="Düzenle"
						>
							<svg
								width="14"
								height="14"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								viewBox="0 0 24 24"
							>
								<path
									d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
								/>
								<path
									d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
								/>
							</svg>
						</button>
						<button
							class="drawer-close"
							@click="close"
							aria-label="Kapat"
						>
							<svg
								width="14"
								height="14"
								fill="none"
								stroke="currentColor"
								stroke-width="2.5"
								viewBox="0 0 24 24"
							>
								<path d="M18 6L6 18M6 6l12 12" />
							</svg>
						</button>
					</div>
				</div>

				<div class="drawer-body">
					<!-- Profil özeti -->
					<div class="user-profile">
						<div
							class="user-profile-avatar"
							:style="{ background: user.avatarGradient }"
						>
							{{ user.initials }}
						</div>
						<div class="user-profile-name">{{ user.name }}</div>
						<div class="user-profile-email">{{ user.email }}</div>
						<div class="user-profile-badges">
							<span
								class="role-badge"
								:class="`role-${user.roleClass}`"
								>{{ user.role }}</span
							>
							<span
								class="user-status-badge"
								:class="`user-status-${user.statusClass}`"
								>{{ user.statusText }}</span
							>
						</div>
					</div>

					<!-- 2 sütunlu bilgi alanı -->
					<div class="detail-grid">
						<!-- Hesap bilgileri -->
						<section class="drawer-section">
							<div class="drawer-section-title">
								Hesap Bilgileri
							</div>
							<dl class="info-list">
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<rect
												x="3"
												y="4"
												width="18"
												height="18"
												rx="2"
											/>
											<line
												x1="16"
												y1="2"
												x2="16"
												y2="6"
											/>
											<line x1="8" y1="2" x2="8" y2="6" />
											<line
												x1="3"
												y1="10"
												x2="21"
												y2="10"
											/>
										</svg>
										Kayıt Tarihi
									</dt>
									<dd>{{ user.registerDate }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<circle cx="12" cy="12" r="10" />
											<polyline
												points="12 6 12 12 16 14"
											/>
										</svg>
										Son Giriş
									</dt>
									<dd>{{ user.lastLogin }}</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<rect
												x="3"
												y="11"
												width="18"
												height="11"
												rx="2"
											/>
											<path d="M7 11V7a5 5 0 0110 0v4" />
										</svg>
										Kullanıcı ID
									</dt>
									<dd class="mono">#{{ user.id }}</dd>
								</div>
							</dl>
						</section>

						<!-- İletişim -->
						<section class="drawer-section">
							<div class="drawer-section-title">İletişim</div>
							<dl class="info-list">
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<path
												d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
											/>
											<polyline points="22,6 12,13 2,6" />
										</svg>
										E-posta
									</dt>
									<dd>
										<a
											:href="`mailto:${user.email}`"
											class="link"
											>{{ user.email }}</a
										>
									</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<path
												d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"
											/>
										</svg>
										Telefon
									</dt>
									<dd class="muted">—</dd>
								</div>
								<div class="info-row">
									<dt>
										<svg
											width="13"
											height="13"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											viewBox="0 0 24 24"
										>
											<path
												d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"
											/>
											<circle cx="12" cy="10" r="3" />
										</svg>
										Departman
									</dt>
									<dd>{{ departmentLabel }}</dd>
								</div>
							</dl>
						</section>
					</div>

					<!-- Aktivite özeti -->
					<section class="drawer-section">
						<div class="drawer-section-title">Aktivite Özeti</div>
						<div class="stat-row">
							<div class="mini-stat">
								<div class="mini-stat-value">
									{{ activitySummary.openTasks }}
								</div>
								<div class="mini-stat-label">Açık Görev</div>
							</div>
							<div class="mini-stat">
								<div class="mini-stat-value">
									{{ activitySummary.completedTasks }}
								</div>
								<div class="mini-stat-label">Tamamlanan</div>
							</div>
							<div class="mini-stat">
								<div class="mini-stat-value">
									{{ activitySummary.activeOrders }}
								</div>
								<div class="mini-stat-label">Aktif Sipariş</div>
							</div>
							<div class="mini-stat">
								<div class="mini-stat-value">
									%{{ activitySummary.productivity }}
								</div>
								<div class="mini-stat-label">Üretkenlik</div>
							</div>
						</div>
					</section>
				</div>

				<div class="drawer-footer">
					<button
						class="btn btn-secondary"
						style="flex: 1; justify-content: center"
						@click="close"
					>
						Kapat
					</button>
				</div>
			</template>
		</aside>
	</Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	user: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'edit']);

function close() {
	emit('update:modelValue', false);
}

const departmentMap = {
	admin: 'Yönetim',
	manager: 'Üretim Müdürlüğü',
	operator: 'Üretim Operasyon',
	user: 'Operasyon',
};

const departmentLabel = computed(() =>
	props.user ? departmentMap[props.user.roleClass] || '—' : '—'
);

// Gerçek veri yerine kullanıcı id'sinden türetilmiş sahte aktivite özeti.
// Backend bağlandığında bu computed yerine props.user.activity kullanılır.
const activitySummary = computed(() => {
	if (!props.user)
		return {
			openTasks: 0,
			completedTasks: 0,
			activeOrders: 0,
			productivity: 0,
		};
	const seed = props.user.id || 1;
	return {
		openTasks: (seed * 3) % 12,
		completedTasks: ((seed * 7) % 50) + 5,
		activeOrders: (seed * 2) % 8,
		productivity: 60 + ((seed * 11) % 40),
	};
});

// body.overflow yönetimi parent'a (Users.vue) ait — burada yapılmıyor ki
// detail/form drawer arası geçişte scroll bar flicker etmesin.
</script>

<style scoped>
.user-drawer {
	width: 61vw;
	max-width: calc(100vw - 24px);
}

@media (max-width: 900px) {
	.user-drawer {
		width: 100vw;
	}
}

/* Geniş drawer'da bilgi bölümlerini iki sütuna al */
.detail-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

@media (max-width: 900px) {
	.detail-grid {
		grid-template-columns: 1fr;
	}
}

.drawer-header-actions {
	display: flex;
	align-items: center;
	gap: 6px;
}

.drawer-edit-btn {
	width: 30px;
	height: 30px;
	border-radius: 8px;
	border: 1.5px solid #e8e8f2;
	background: #fff;
	color: rgb(var(--color-primary));
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition:
		background 0.15s,
		border-color 0.15s,
		color 0.15s;
	flex-shrink: 0;
}
.drawer-edit-btn:hover {
	background: rgb(var(--color-primary-soft));
	border-color: #c0c8f8;
	color: rgb(var(--color-primary-hover));
}

/* ── Profil özeti ── */
.user-profile {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 8px 0 14px;
	border-bottom: 1px solid #f0f0f6;
	margin: -4px 0 4px;
}

.user-profile-avatar {
	width: 72px;
	height: 72px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 22px;
	font-weight: 700;
	color: #fff;
	margin-bottom: 8px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.user-profile-name {
	font-size: 16px;
	font-weight: 700;
	color: #1a1a2e;
}

.user-profile-email {
	font-size: 12.5px;
	color: #888;
}

.user-profile-badges {
	display: flex;
	gap: 6px;
	margin-top: 8px;
}

/* Drawer'da kullanılan badge'ler (Users sayfasıyla aynı stil) */
.role-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 6px;
	font-size: 11px;
	font-weight: 600;
}
.role-admin {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
}
.role-manager {
	background: #e0f2fe;
	color: #0284c7;
}
.role-user {
	background: #f0fdf4;
	color: #16a34a;
}
.role-operator {
	background: #fff7ed;
	color: #ea580c;
}

.user-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
}
.user-status-active {
	background: #dcfce7;
	color: #16a34a;
}
.user-status-inactive {
	background: #fee2e2;
	color: #dc2626;
}
.user-status-pending {
	background: #fef9c3;
	color: #ca8a04;
}

/* ── Bilgi listesi ── */
.drawer-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.info-list {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.info-row {
	display: flex;
	align-items: center;
	margin: 2px 0;
	justify-content: space-between;
	padding: 8px 10px;
	border-radius: 9px;
	background: #fafafe;
	border: 1px solid #f0f0f6;
}

.info-row dt {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12.5px;
	color: #888;
	font-weight: 500;
}

.info-row dt svg {
	color: #aaa;
	flex-shrink: 0;
}

.info-row dd {
	font-size: 13px;
	color: #1a1a2e;
	font-weight: 600;
	text-align: right;
}

.info-row .muted {
	color: #bbb;
	font-weight: 500;
}
.info-row .mono {
	font-family: 'SF Mono', Monaco, Consolas, monospace;
	font-size: 12px;
}

.link {
	color: rgb(var(--color-primary));
	text-decoration: none;
}
.link:hover {
	text-decoration: underline;
}

/* ── Mini istatistikler ── */
.stat-row {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 10px;
}

@media (max-width: 900px) {
	.stat-row {
		grid-template-columns: repeat(2, 1fr);
	}
}

.mini-stat {
	background: #fafafe;
	border: 1px solid #f0f0f6;
	border-radius: 10px;
	padding: 12px 8px;
	text-align: center;
}

.mini-stat-value {
	font-size: 20px;
	font-weight: 800;
	color: #1a1a2e;
	line-height: 1.1;
}

.mini-stat-label {
	font-size: 10.5px;
	color: #888;
	font-weight: 500;
	margin-top: 3px;
}
</style>
