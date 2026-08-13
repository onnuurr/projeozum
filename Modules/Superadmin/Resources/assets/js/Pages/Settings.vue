<template>
	<Head title="Sistem Ayarları · Süper Admin" />
	<div class="page-sa-settings">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Sistem Ayarları' },
			]"
		/>

		<PageHeader badge="Süper Admin" title="Sistem Ayarları" subtitle="Tüm SaaS platformunu etkileyen genel ayarlar. Değişiklikler tüm tenant'lara yansır.">
			<template #actions>
				<Button v-if="form.isDirty" variant="ghost" :disabled="form.processing" @click="resetForm">
					Değişiklikleri İptal Et
				</Button>
				<Button
					variant="primary"
					with-icon
					:disabled="!form.isDirty"
					:loading="form.processing"
					@click="save"
				>
					<template #leading><Check :size="13" /></template>
					Kaydet
				</Button>
			</template>
		</PageHeader>

		<div class="sa-layout">
			<!-- Mobil (telefon): sol nav yerine hamburger buton, tıklayınca nav açılır/kapanır -->
			<button
				class="sa-mobile-nav-toggle"
				:class="{ open: mobileNavOpen }"
				@click="mobileNavOpen = !mobileNavOpen"
			>
				<span class="sa-mobile-nav-current">
					<span class="sa-nav-icon" v-html="activeSectionObj?.icon"></span>
					{{ activeSectionObj?.label }}
				</span>
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
					<line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
				</svg>
			</button>
			<div v-if="mobileNavOpen" class="sa-mobile-nav-backdrop" @click="mobileNavOpen = false"></div>

			<!-- Sol nav -->
			<aside class="sa-nav" :class="{ 'sa-nav-mobile-open': mobileNavOpen }">
				<button
					v-for="s in sections"
					:key="s.key"
					class="sa-nav-item"
					:class="{ active: activeSection === s.key }"
					@click="activeSection = s.key; mobileNavOpen = false"
				>
					<span class="sa-nav-icon" v-html="s.icon"></span>
					<div class="sa-nav-text">
						<div class="sa-nav-label">{{ s.label }}</div>
						<div class="sa-nav-sub">{{ s.sub }}</div>
					</div>
					<svg
						v-if="dirtyKeys.includes(s.key)"
						class="sa-nav-dirty"
						width="8"
						height="8"
						viewBox="0 0 8 8"
					>
						<circle cx="4" cy="4" r="4" fill="rgb(var(--color-warning))" />
					</svg>
				</button>

				<div class="sa-nav-footer">
					<div class="sa-version">
						<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<circle cx="12" cy="12" r="10" />
							<polyline points="12 6 12 12 16 14" />
						</svg>
						Son güncelleme: 13.05.2026
					</div>
				</div>
			</aside>

			<!-- Sağ form -->
			<main class="sa-content">
				<!-- 1. GENEL -->
				<Card v-if="activeSection === 'general'" title="Genel Ayarlar" body-class="sa-section">
					<p class="section-sub">Sistem adı, dil, zaman dilimi gibi temel platform ayarları</p>

					<div class="field-row">
						<div class="field">
							<label>Sistem Adı</label>
							<input v-model="form.general.systemName" class="form-input" type="text" />
							<div class="field-help">Üst menüde, e-postalarda ve faturada görünür</div>
						</div>
						<div class="field">
							<label>Sistem URL</label>
							<input v-model="form.general.systemUrl" class="form-input" type="url" />
						</div>
					</div>

					<div class="field-row">
						<div class="field">
							<label>Destek E-posta</label>
							<input v-model="form.general.supportEmail" class="form-input" type="email" />
						</div>
						<div class="field">
							<label>Logo URL</label>
							<input v-model="form.general.logoUrl" class="form-input" type="url" placeholder="https://..." />
						</div>
					</div>

					<div class="field-row">
						<div class="field">
							<label>Varsayılan Dil</label>
							<CustomSelect v-model="form.general.defaultLanguage" :options="options.languages" :show-label="false" />
						</div>
						<div class="field">
							<label>Zaman Dilimi</label>
							<CustomSelect v-model="form.general.defaultTimezone" :options="options.timezones" :show-label="false" />
						</div>
						<div class="field">
							<label>Tarih Formatı</label>
							<CustomSelect v-model="form.general.dateFormat" :options="options.dateFormats" :show-label="false" />
						</div>
						<div class="field">
							<label>Para Birimi</label>
							<CustomSelect v-model="form.general.defaultCurrency" :options="options.currencies" :show-label="false" />
						</div>
					</div>

					<div class="sa-divider"></div>

					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Yeni Kayıt Açık</div>
							<div class="toggle-sub">Kapatırsan yeni firma kaydı yapılamaz, mevcut tenant'lar etkilenmez.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.general.allowSignup" />
							<span class="slider"></span>
						</label>
					</div>

					<div class="toggle-card" :class="{ 'toggle-danger': form.general.maintenanceMode }">
						<div class="toggle-info">
							<div class="toggle-label">
								Bakım Modu
								<span v-if="form.general.maintenanceMode" class="danger-pill">AKTİF</span>
							</div>
							<div class="toggle-sub">Açıkken sadece süper admin giriş yapabilir, diğer kullanıcılara bakım sayfası gösterilir.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.general.maintenanceMode" />
							<span class="slider"></span>
						</label>
					</div>

					<div v-if="form.general.maintenanceMode" class="field">
						<label>Bakım Mesajı</label>
						<textarea v-model="form.general.maintenanceMessage" class="form-textarea" rows="3"></textarea>
					</div>
				</Card>

				<!-- 2. GÜVENLİK -->
				<Card v-if="activeSection === 'security'" title="Güvenlik" body-class="sa-section">
					<p class="section-sub">Parola politikası, iki faktörlü kimlik, oturum yönetimi</p>

					<h3 class="sa-subhead">Parola Politikası</h3>
					<div class="field-row">
						<div class="field">
							<label>Minimum Uzunluk</label>
							<input v-model.number="form.security.passwordMinLength" type="number" min="6" max="64" class="form-input" />
						</div>
						<div class="field">
							<label>Geçerlilik Süresi (gün)</label>
							<input v-model.number="form.security.passwordExpiryDays" type="number" min="0" max="365" class="form-input" />
							<div class="field-help">0 = süresiz</div>
						</div>
					</div>

					<div class="check-group">
						<label class="check-card">
							<input type="checkbox" v-model="form.security.passwordRequireUppercase" />
							<span class="check-text">
								<strong>Büyük harf zorunlu</strong>
								<span class="check-sub">En az 1 büyük harf (A-Z)</span>
							</span>
						</label>
						<label class="check-card">
							<input type="checkbox" v-model="form.security.passwordRequireNumbers" />
							<span class="check-text">
								<strong>Rakam zorunlu</strong>
								<span class="check-sub">En az 1 rakam (0-9)</span>
							</span>
						</label>
						<label class="check-card">
							<input type="checkbox" v-model="form.security.passwordRequireSpecial" />
							<span class="check-text">
								<strong>Özel karakter</strong>
								<span class="check-sub">En az 1 özel karakter (!@#$ vb.)</span>
							</span>
						</label>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">İki Faktörlü Kimlik (2FA)</h3>
					<div class="field">
						<label>Zorunluluk Düzeyi</label>
						<div class="radio-card-grid">
							<label
								v-for="o in options.twoFactorOptions"
								:key="o.value"
								class="radio-card"
								:class="{ active: form.security.twoFactorRequired === o.value }"
							>
								<input type="radio" :value="o.value" v-model="form.security.twoFactorRequired" />
								<div class="rc-content">
									<div class="rc-label">{{ o.label }}</div>
									<div class="rc-sub">
										<template v-if="o.value === 'optional'">Kullanıcılar isteğe bağlı aktive eder.</template>
										<template v-else-if="o.value === 'admins'">Sadece tenant yöneticileri zorunlu.</template>
										<template v-else>Tüm kullanıcılar girişte 2FA yapmak zorunda.</template>
									</div>
								</div>
							</label>
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Oturum & Giriş</h3>
					<div class="field-row">
						<div class="field">
							<label>Oturum Süresi (dakika)</label>
							<input v-model.number="form.security.sessionTimeoutMinutes" type="number" min="5" class="form-input" />
						</div>
						<div class="field">
							<label>Max Başarısız Giriş</label>
							<input v-model.number="form.security.maxLoginAttempts" type="number" min="1" max="20" class="form-input" />
						</div>
						<div class="field">
							<label>Kilitlenme Süresi (dakika)</label>
							<input v-model.number="form.security.lockoutMinutes" type="number" min="1" class="form-input" />
						</div>
					</div>

					<div class="field">
						<label>IP Whitelist (admin paneli)</label>
						<textarea
							v-model="form.security.ipWhitelist"
							class="form-textarea"
							rows="3"
							placeholder="Her satıra bir IP veya CIDR. Boş bırakırsan kısıt yok."
						></textarea>
						<div class="field-help">Örnek: 192.168.1.0/24 veya 85.105.22.18</div>
					</div>

					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Denetim Logu (Audit Log)</div>
							<div class="toggle-sub">Tüm kritik işlemleri (kullanıcı oluşturma, ayar değişikliği vb.) kaydet.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.security.enableAuditLog" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">reCAPTCHA</div>
							<div class="toggle-sub">Giriş ve kayıt formlarında bot koruması.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.security.enableCaptcha" />
							<span class="slider"></span>
						</label>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Log Erişim Şifresi</h3>
					<div class="field">
						<label class="form-label">Log erişim şifresi (belirle / değiştir)</label>
						<div class="password-input">
							<input
								v-model="form.security.logAccessPassword"
								:type="showLogAccessPassword ? 'text' : 'password'"
								class="form-input"
								placeholder="Boş bırakırsan mevcut şifre korunur"
								autocomplete="new-password"
							/>
							<button type="button" class="pw-toggle" @click="showLogAccessPassword = !showLogAccessPassword">
								<svg v-if="showLogAccessPassword" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
									<line x1="1" y1="1" x2="23" y2="23" />
								</svg>
								<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
									<circle cx="12" cy="12" r="3" />
								</svg>
							</button>
						</div>
						<div v-if="props.settings.security.logAccessPasswordSet" class="field-help" style="color: rgb(var(--color-success));">
							<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="vertical-align: -1px;">
								<polyline points="20 6 9 17 4 12" />
							</svg>
							Log erişim şifresi belirlenmiş. Değiştirmek için yeni şifreyi girin.
						</div>
						<div v-else class="field-help">
							Henüz şifre belirlenmemiş. Log görüntüleyiciye erişim için buradan bir şifre belirleyin.
						</div>
					</div>
				</Card>

				<!-- 2.5 ROLLER & İZİNLER -->
				<Card v-if="activeSection === 'roles'" title="Roller & İzinler" body-class="sa-section">
					<template #actions>
						<div class="role-view-toggle">
							<button class="rv-btn" :class="{ active: roleView === 'list' }" @click="roleView = 'list'">
								<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" />
									<rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" />
								</svg>
								Roller
							</button>
							<button class="rv-btn" :class="{ active: roleView === 'matrix' }" @click="roleView = 'matrix'">
								<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<rect x="3" y="3" width="18" height="18" />
									<line x1="3" y1="9" x2="21" y2="9" /><line x1="3" y1="15" x2="21" y2="15" />
									<line x1="9" y1="3" x2="9" y2="21" /><line x1="15" y1="3" x2="15" y2="21" />
								</svg>
								İzin Matrisi
							</button>
							<button class="rv-btn" :class="{ active: roleView === 'permissions' }" @click="roleView = 'permissions'">
								<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<polyline points="9 11 12 14 22 4" />
									<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
								</svg>
								İzinler
							</button>
						</div>
					</template>
					<p class="section-sub">Kullanıcı rollerini ve her rolün izin matrisini yönet</p>

					<!-- Liste görünümü -->
					<div v-if="roleView === 'list'">
						<div class="role-list-head">
							<div class="role-search">
								<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
								</svg>
								<input v-model="roleSearch" type="text" placeholder="Rol ara..." class="role-search-input" />
							</div>
							<Button v-if="can('rbac.manage')" variant="primary" size="sm" with-icon @click="openAddRole">
								<template #leading><Plus :size="12" /></template>
								Yeni Rol
							</Button>
						</div>

						<div v-if="filteredRoles.length === 0" class="role-empty">
							<svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
								<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
							</svg>
							<p>"{{ roleSearch }}" için sonuç yok</p>
						</div>

						<div v-else class="role-grid">
							<div v-for="r in filteredRoles" :key="r.key" class="role-card">
								<div class="role-icon-bg" :style="{ background: r.color }">
									{{ r.name.charAt(0).toUpperCase() }}
								</div>
								<div class="role-card-body">
									<div class="role-card-title">
										<h4>{{ r.name }}</h4>
										<span v-if="r.system" class="system-pill">SİSTEM</span>
									</div>
									<p class="role-card-desc">{{ r.desc }}</p>
									<div class="role-card-meta">
										<span class="rc-meta-item">
											<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
												<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" />
												<path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />
											</svg>
											{{ r.userCount }} kullanıcı
										</span>
										<span class="rc-meta-item">
											<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
												<polyline points="9 11 12 14 22 4" />
												<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
											</svg>
											{{ getPermLabel(r) }}
										</span>
									</div>
								</div>
								<div v-if="can('rbac.manage')" class="role-card-actions">
									<button class="rc-action" @click="openEditRole(r)" :disabled="r.key === 'superadmin'" :title="r.key === 'superadmin' ? 'Süper Admin rolü düzenlenemez' : 'Düzenle'">
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
											<path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
										</svg>
									</button>
									<button class="rc-action rc-danger" @click="confirmDeleteRole(r)" :disabled="r.system" :title="r.system ? 'Sistem rolü silinemez' : 'Sil'">
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<polyline points="3 6 5 6 21 6" />
											<path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
										</svg>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- İzinler CRUD -->
					<div v-else-if="roleView === 'permissions'">
						<div class="role-list-head">
							<div class="role-search">
								<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
								</svg>
								<input v-model="permSearch" type="text" placeholder="İzin ara..." class="role-search-input" />
							</div>
							<Button v-if="can('rbac.manage')" variant="primary" size="sm" with-icon @click="openAddPermission">
								<template #leading><Plus :size="12" /></template>
								Yeni İzin
							</Button>
						</div>

						<div v-if="flatPermissions.length === 0" class="role-empty">
							<p>Henüz izin tanımlanmamış. "Yeni İzin" ile başlayın.</p>
						</div>

						<div v-else class="perm-list">
							<div v-for="m in filteredPermissionModules" :key="m.key" class="perm-list-module">
								<div class="perm-list-module-head">
									<strong>{{ m.name }}</strong>
									<span class="pmh-count">{{ m.permissions.length }} izin</span>
								</div>
								<div class="perm-list-rows">
									<div v-for="p in m.permissions" :key="p.id" class="perm-list-row">
										<div class="perm-list-info">
											<div class="perm-list-name">{{ p.name }}</div>
											<div class="perm-list-key">{{ p.key }}</div>
										</div>
										<div v-if="can('rbac.manage')" class="perm-list-actions">
											<button class="rc-action" title="Düzenle" @click="openEditPermission(p)">
												<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
													<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
													<path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
												</svg>
											</button>
											<button class="rc-action rc-danger" title="Sil" @click="deletePermission(p)">
												<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
													<polyline points="3 6 5 6 21 6" />
													<path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
												</svg>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- İzin Matrisi -->
					<div v-else class="matrix-wrap">
						<div class="matrix-legend">
							<span class="ml-text">İpucu: Hücreye tıklayarak izni anında değiştirebilirsiniz. Süper Admin tüm izinlere sahiptir, değiştirilemez.</span>
						</div>
						<div class="matrix-scroll">
							<table class="perm-matrix">
								<thead>
									<tr>
										<th class="m-perm-col">İzin</th>
										<th v-for="r in roles" :key="r.key" class="m-role-col">
											<div class="m-role-head">
												<span class="m-role-dot" :style="{ background: r.color }"></span>
												<span class="m-role-name">{{ r.name }}</span>
											</div>
										</th>
									</tr>
								</thead>
								<tbody>
									<template v-for="m in modules" :key="m.key">
										<tr class="m-module-row">
											<td :colspan="roles.length + 1">
												<span class="m-module-name">{{ m.name }}</span>
												<span class="m-module-count">{{ m.permissions.length }} izin</span>
											</td>
										</tr>
										<tr v-for="p in m.permissions" :key="p.key" class="m-perm-row">
											<td class="m-perm-cell">
												<div class="m-perm-name">{{ p.name }}</div>
												<div class="m-perm-key">{{ p.key }}</div>
											</td>
											<td v-for="r in roles" :key="r.key" class="m-cell" @click="togglePermission(r, p.key)">
												<label class="m-check" @click.stop>
													<input
														type="checkbox"
														:checked="hasPermission(r, p.key)"
														:disabled="r.key === 'superadmin' || !can('rbac.manage')"
														@change="togglePermission(r, p.key)"
													/>
													<span class="m-checkbox">
														<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
															<polyline points="20 6 9 17 4 12" />
														</svg>
													</span>
												</label>
											</td>
										</tr>
									</template>
								</tbody>
							</table>
						</div>
					</div>
				</Card>

				<!-- 3. E-POSTA -->
				<Card v-if="activeSection === 'mail'" title="E-posta (SMTP)" body-class="sa-section">
					<p class="section-sub">Sistem e-postalarının gönderim altyapısı</p>

					<div class="field-row">
						<div class="field">
							<label>Sürücü</label>
							<CustomSelect v-model="form.mail.driver" :options="options.mailDrivers" :show-label="false" />
						</div>
						<div class="field">
							<label>Şifreleme</label>
							<CustomSelect v-model="form.mail.encryption" :options="options.mailEncryption" :show-label="false" />
						</div>
					</div>

					<div class="field-row">
						<div class="field flex-2">
							<label>SMTP Host</label>
							<input v-model="form.mail.host" type="text" class="form-input" />
						</div>
						<div class="field">
							<label>Port</label>
							<input v-model.number="form.mail.port" type="number" class="form-input" />
						</div>
					</div>

					<div class="field-row">
						<div class="field">
							<label>Kullanıcı Adı</label>
							<input v-model="form.mail.username" type="text" class="form-input" />
						</div>
						<div class="field">
							<label>Parola</label>
							<div class="password-input">
								<input
									v-model="form.mail.password"
									:type="showMailPassword ? 'text' : 'password'"
									class="form-input"
									@focus="clearMask('mail', 'password')"
								/>
								<button type="button" class="pw-toggle" @click="showMailPassword = !showMailPassword">
									<svg v-if="showMailPassword" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
										<line x1="1" y1="1" x2="23" y2="23" />
									</svg>
									<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
										<circle cx="12" cy="12" r="3" />
									</svg>
								</button>
							</div>
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Gönderici Bilgileri</h3>
					<div class="field-row">
						<div class="field">
							<label>Gönderici E-posta</label>
							<input v-model="form.mail.fromAddress" type="email" class="form-input" />
						</div>
						<div class="field">
							<label>Gönderici Adı</label>
							<input v-model="form.mail.fromName" type="text" class="form-input" />
						</div>
					</div>

					<div class="test-block">
						<div>
							<div class="test-status" :class="`test-${form.mail.lastTestResult}`">
								<svg v-if="form.mail.lastTestResult === 'success'" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
									<polyline points="20 6 9 17 4 12" />
								</svg>
								<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
									<circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
								</svg>
								{{ form.mail.lastTestResult === 'success' ? 'Son test başarılı' : 'Son test başarısız' }}
							</div>
							<div class="test-time">{{ form.mail.lastTestedAt }}</div>
						</div>
						<Button variant="secondary" size="sm" @click="testMail">Test E-postası Gönder</Button>
					</div>
				</Card>

				<!-- 4. BİLDİRİMLER -->
				<Card v-if="activeSection === 'notifications'" title="Bildirimler" body-class="sa-section">
					<p class="section-sub">Sistem olaylarına bağlı e-posta, Slack ve push bildirimleri</p>

					<h3 class="sa-subhead">E-posta Bildirimleri</h3>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Yeni tenant kaydı</div>
							<div class="toggle-sub">Bir firma sisteme kaydolduğunda admin'lere e-posta gönder.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.emailNewTenant" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Yeni sipariş</div>
							<div class="toggle-sub">Her yeni sipariş için müşteriye ve operasyona bilgi.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.emailNewOrder" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Ödeme başarısızlığı</div>
							<div class="toggle-sub">Abonelik veya sipariş ödemesi başarısız olduğunda uyarı.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.emailPaymentFailure" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Sistem hatası</div>
							<div class="toggle-sub">5xx hatalar ve kritik exception'lar süper admin'e gider.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.emailSystemError" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Haftalık özet raporu</div>
							<div class="toggle-sub">Her Pazartesi 09:00'da süper admin'lere haftalık özet.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.emailWeeklyReport" />
							<span class="slider"></span>
						</label>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Slack Entegrasyonu</h3>
					<div class="field-row">
						<div class="field flex-2">
							<label>Webhook URL</label>
							<input v-model="form.notifications.slackWebhookUrl" type="url" class="form-input mono-input" placeholder="https://hooks.slack.com/services/..." />
						</div>
						<div class="field">
							<label>Kanal</label>
							<input v-model="form.notifications.slackChannel" type="text" class="form-input" placeholder="#kanal-adı" />
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Diğer Kanallar</h3>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Push Bildirimleri</div>
							<div class="toggle-sub">Mobil/web push üzerinden anlık bildirimler.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.enablePushNotifications" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Uygulama İçi Bildirimler</div>
							<div class="toggle-sub">Üst menüde çan ikonunda görünen bildirimler.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.notifications.enableInAppNotifications" />
							<span class="slider"></span>
						</label>
					</div>
				</Card>

				<!-- 5. ÖDEME -->
				<Card v-if="activeSection === 'billing'" title="Ödeme & Faturalama" body-class="sa-section">
					<p class="section-sub">Abonelik tahsilatı ve fatura ayarları</p>

					<div class="field">
						<label>Ödeme Sağlayıcısı</label>
						<CustomSelect v-model="form.billing.paymentProvider" :options="options.paymentProviders" :show-label="false" />
					</div>

					<template v-if="form.billing.paymentProvider === 'iyzico'">
						<h3 class="sa-subhead">iyzico API</h3>
						<div class="field-row">
							<div class="field">
								<label>API Key</label>
								<input v-model="form.billing.iyzicoApiKey" type="text" class="form-input mono-input" @focus="clearMask('billing', 'iyzicoApiKey')" />
							</div>
							<div class="field">
								<label>Secret Key</label>
								<input v-model="form.billing.iyzicoSecretKey" type="password" class="form-input mono-input" @focus="clearMask('billing', 'iyzicoSecretKey')" />
							</div>
						</div>
					</template>

					<template v-if="form.billing.paymentProvider === 'stripe'">
						<h3 class="sa-subhead">Stripe API</h3>
						<div class="field-row">
							<div class="field">
								<label>Publishable Key</label>
								<input v-model="form.billing.stripePublicKey" type="text" class="form-input mono-input" placeholder="pk_live_..." @focus="clearMask('billing', 'stripePublicKey')" />
							</div>
							<div class="field">
								<label>Secret Key</label>
								<input v-model="form.billing.stripeSecretKey" type="password" class="form-input mono-input" placeholder="sk_live_..." @focus="clearMask('billing', 'stripeSecretKey')" />
							</div>
						</div>
					</template>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Fatura</h3>
					<div class="field-row">
						<div class="field">
							<label>Para Birimi</label>
							<CustomSelect v-model="form.billing.currency" :options="options.currencies" :show-label="false" />
						</div>
						<div class="field">
							<label>KDV Oranı (%)</label>
							<input v-model.number="form.billing.vatRate" type="number" min="0" max="100" step="0.5" class="form-input" />
						</div>
						<div class="field">
							<label>Deneme Süresi (gün)</label>
							<input v-model.number="form.billing.trialDays" type="number" min="0" class="form-input" />
						</div>
					</div>
					<div class="field-row">
						<div class="field">
							<label>Fatura Numarası Öneki</label>
							<input v-model="form.billing.invoicePrefix" type="text" class="form-input mono-input" />
						</div>
					</div>
					<div class="field">
						<label>Fatura Alt Bilgi</label>
						<textarea v-model="form.billing.invoiceFooter" class="form-textarea" rows="2"></textarea>
					</div>

					<div class="toggle-card" :class="{ 'toggle-warning': form.billing.sandboxMode }">
						<div class="toggle-info">
							<div class="toggle-label">
								Sandbox Modu
								<span v-if="form.billing.sandboxMode" class="warning-pill">TEST</span>
							</div>
							<div class="toggle-sub">Açıkken gerçek tahsilat yapılmaz, test kartları kabul edilir.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.billing.sandboxMode" />
							<span class="slider"></span>
						</label>
					</div>
				</Card>

				<!-- 6. DEPOLAMA -->
				<Card v-if="activeSection === 'storage'" title="Depolama & Yedekleme" body-class="sa-section">
					<p class="section-sub">Dosya depolama altyapısı ve otomatik yedek planı</p>

					<h3 class="sa-subhead">Dosya Depolama</h3>
					<div class="field">
						<label>Sürücü</label>
						<CustomSelect v-model="form.storage.driver" :options="options.storageDrivers" :show-label="false" />
					</div>
					<div class="field-row">
						<div class="field">
							<label>Bucket</label>
							<input v-model="form.storage.bucket" type="text" class="form-input mono-input" />
						</div>
						<div class="field">
							<label>Region</label>
							<input v-model="form.storage.region" type="text" class="form-input mono-input" />
						</div>
					</div>
					<div class="field-row">
						<div class="field">
							<label>Access Key</label>
							<input v-model="form.storage.accessKey" type="text" class="form-input mono-input" @focus="clearMask('storage', 'accessKey')" />
						</div>
						<div class="field">
							<label>Secret Key</label>
							<input v-model="form.storage.secretKey" type="password" class="form-input mono-input" @focus="clearMask('storage', 'secretKey')" />
						</div>
					</div>
					<div class="field">
						<label>CDN URL</label>
						<input v-model="form.storage.cdnUrl" type="url" class="form-input" />
					</div>
					<div class="field-row">
						<div class="field">
							<label>Max Yükleme Boyutu (MB)</label>
							<input v-model.number="form.storage.maxUploadMB" type="number" min="1" class="form-input" />
						</div>
						<div class="field flex-2">
							<label>İzin Verilen Uzantılar</label>
							<input v-model="form.storage.allowedExtensions" type="text" class="form-input mono-input" placeholder="jpg,png,pdf..." />
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Otomatik Yedekleme</h3>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Yedekleme Aktif</div>
							<div class="toggle-sub">Veritabanı + dosyalar zamanlanmış olarak yedeklenir.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.storage.backupEnabled" />
							<span class="slider"></span>
						</label>
					</div>

					<div class="field-row" v-if="form.storage.backupEnabled">
						<div class="field">
							<label>Sıklık</label>
							<CustomSelect v-model="form.storage.backupSchedule" :options="options.backupSchedules" :show-label="false" />
						</div>
						<div class="field">
							<label>Saat (UTC+3)</label>
							<input v-model="form.storage.backupTime" type="time" class="form-input" />
						</div>
						<div class="field">
							<label>Saklama Süresi (gün)</label>
							<input v-model.number="form.storage.backupRetentionDays" type="number" min="1" class="form-input" />
						</div>
					</div>

					<div class="info-row">
						<div class="ir-block">
							<div class="ir-label">Son Yedek</div>
							<div class="ir-value">{{ formatBackupDate(form.storage.lastBackupAt) }}</div>
						</div>
						<div class="ir-block">
							<div class="ir-label">Boyut</div>
							<div class="ir-value">{{ form.storage.lastBackupSize ?? '—' }}</div>
						</div>
						<a href="/superadmin/backups" class="btn btn-secondary btn-sm">Tüm Geçmiş</a>
						<Button v-if="can('backups.manage')" variant="secondary" size="sm" :loading="backupBusy" @click="runBackup">
							Şimdi Yedekle
						</Button>
					</div>
				</Card>

				<!-- 8. API -->
				<Card v-if="activeSection === 'api'" title="API & Geliştirici" body-class="sa-section">
					<p class="section-sub">Public API ve webhook ayarları</p>

					<div class="field-row">
						<div class="field">
							<label>Rate Limit (istek / dakika)</label>
							<input v-model.number="form.api.rateLimitPerMinute" type="number" min="1" class="form-input" />
						</div>
						<div class="field">
							<label>API Versiyonu</label>
							<input v-model="form.api.apiVersion" type="text" class="form-input mono-input" />
						</div>
					</div>

					<div class="field">
						<label>Webhook İmzalama Anahtarı</label>
						<div class="copy-input">
							<input v-model="form.api.webhookSecret" type="text" class="form-input mono-input" readonly />
							<button class="copy-btn" @click="copy(form.api.webhookSecret, 'Webhook Secret')">Kopyala</button>
							<button class="copy-btn" @click="regenerateWebhookSecret">Yenile</button>
						</div>
						<div class="field-help">Gelen webhook isteklerinin imzasını doğrulamak için kullanılır.</div>
					</div>

					<div class="field">
						<label>İzinli Origin'ler (CORS)</label>
						<textarea v-model="form.api.allowedOrigins" class="form-textarea mono-input" rows="3"></textarea>
						<div class="field-help">Her satıra bir origin. * tüm origin'lere izin verir.</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Cloudflare</h3>
					<div class="field-row">
						<div class="field">
							<label>Zone ID</label>
							<input v-model="form.api.cloudflareZoneId" type="text" class="form-input mono-input" placeholder="a1b2c3d4e5f6..." />
						</div>
						<div class="field">
							<label>API Token</label>
							<input v-model="form.api.cloudflareApiToken" type="password" class="form-input mono-input" @focus="clearMask('api', 'cloudflareApiToken')" />
						</div>
					</div>
					<div class="field-help">"Önbelleği Temizle" (Cache & CDN) yetkisine sahip bir Cloudflare API token gerekir. Aşağıdaki "Laravel + Cloudflare Önbelleğini Temizle" butonu bu bilgileri kullanır.</div>

					<div class="sa-divider"></div>

					<div class="toggle-card" :class="{ 'toggle-warning': form.api.sandboxMode }">
						<div class="toggle-info">
							<div class="toggle-label">
								Sandbox API
								<span v-if="form.api.sandboxMode" class="warning-pill">TEST</span>
							</div>
							<div class="toggle-sub">Açıkken API gerçek veriyi değiştirmez, sahte yanıt döner.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.api.sandboxMode" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">API Key Zorunlu</div>
							<div class="toggle-sub">Tüm API çağrıları için geçerli API key gerekir.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.api.requireApiKey" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Swagger Dokümantasyon</div>
							<div class="toggle-sub">/api/docs adresinde otomatik API dokümantasyonu.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.api.enableSwagger" />
							<span class="slider"></span>
						</label>
					</div>
				</Card>

				<!-- 9. PERFORMANS -->
				<Card v-if="activeSection === 'performance'" title="Performans & Cache" body-class="sa-section">
					<p class="section-sub">Önbellek, kuyruk ve log altyapısı</p>

					<div class="field-row">
						<div class="field">
							<label>Cache Driver</label>
							<CustomSelect v-model="form.performance.cacheDriver" :options="options.cacheDrivers" :show-label="false" />
						</div>
						<div class="field">
							<label>Cache TTL (dakika)</label>
							<input v-model.number="form.performance.cacheTtlMinutes" type="number" min="1" class="form-input" />
						</div>
					</div>

					<div class="field-row">
						<div class="field">
							<label>Queue Driver</label>
							<CustomSelect v-model="form.performance.queueDriver" :options="options.queueDrivers" :show-label="false" />
						</div>
						<div class="field">
							<label>Worker Sayısı</label>
							<input v-model.number="form.performance.queueWorkers" type="number" min="1" max="32" class="form-input" />
						</div>
						<div class="field">
							<label>Session Driver</label>
							<CustomSelect v-model="form.performance.sessionDriver" :options="options.cacheDrivers" :show-label="false" />
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Loglama</h3>
					<div class="field-row">
						<div class="field">
							<label>Log Seviyesi</label>
							<CustomSelect v-model="form.performance.logLevel" :options="options.logLevels" :show-label="false" />
						</div>
						<div class="field">
							<label>Log Saklama (gün)</label>
							<input v-model.number="form.performance.logRetentionDays" type="number" min="1" class="form-input" />
						</div>
					</div>

					<div class="toggle-card" :class="{ 'toggle-warning': form.performance.enableDebugBar }">
						<div class="toggle-info">
							<div class="toggle-label">
								Debug Bar
								<span v-if="form.performance.enableDebugBar" class="warning-pill">DİKKAT</span>
							</div>
							<div class="toggle-sub">Geliştirici araç çubuğu — production'da kapalı olmalı.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.performance.enableDebugBar" />
							<span class="slider"></span>
						</label>
					</div>
					<div class="toggle-card">
						<div class="toggle-info">
							<div class="toggle-label">Query Log</div>
							<div class="toggle-sub">Tüm SQL sorgularını logla. Yüksek I/O — sadece debug için.</div>
						</div>
						<label class="switch">
							<input type="checkbox" v-model="form.performance.enableQueryLog" />
							<span class="slider"></span>
						</label>
					</div>

					<div class="sa-divider"></div>

					<div class="cache-actions">
						<Button variant="primary" size="sm" :disabled="!!cacheBusy" :loading="cacheBusy === 'all'" @click="cacheAction('all')">
							Laravel + Cloudflare Önbelleğini Temizle
						</Button>
						<Button variant="secondary" size="sm" :disabled="!!cacheBusy" :loading="cacheBusy === 'clear'" @click="cacheAction('clear')">Cache Temizle</Button>
						<Button variant="secondary" size="sm" :disabled="!!cacheBusy" :loading="cacheBusy === 'config'" @click="cacheAction('config')">Config Cache Yenile</Button>
						<Button variant="secondary" size="sm" :disabled="!!cacheBusy" :loading="cacheBusy === 'route'" @click="cacheAction('route')">Route Cache Yenile</Button>
						<Button variant="secondary" size="sm" :disabled="!!cacheBusy" :loading="cacheBusy === 'view'" @click="cacheAction('view')">View Cache Yenile</Button>
					</div>
				</Card>

				<!-- 9b. BARKOD (GS1) -->
				<Card v-if="activeSection === 'barcode'" title="Barkod (GS1)" body-class="sa-section">
					<p class="section-sub">Ürün barkodu otomatik üretimi için sabit önek ve seri aralığı</p>

					<div class="field-row">
						<div class="field">
							<label>Ülke Kodu</label>
							<input v-model="form.barcode.countryCode" type="text" maxlength="3" class="form-input" placeholder="869" />
						</div>
						<div class="field">
							<label>Firma Kodu</label>
							<input v-model="form.barcode.companyCode" type="text" maxlength="8" class="form-input" placeholder="GS1'den aldığınız firma kodu" />
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Seri No Aralığı</h3>
					<div class="field-row">
						<div class="field">
							<label>Min</label>
							<input v-model.number="form.barcode.serialMin" type="number" min="0" class="form-input" />
						</div>
						<div class="field">
							<label>Max</label>
							<input v-model.number="form.barcode.serialMax" type="number" min="0" class="form-input" />
						</div>
					</div>
					<p class="sa-hint">Ülke kodu + firma kodu sabit önek olarak kullanılır; barkodun geri kalan haneleri bu aralıktan rastgele seçilen bir seri numarasıyla doldurulur (13. hane GS1 kontrol hanesidir).</p>
				</Card>

				<!-- 9c. ARAYÜZ (UI tema flag'i) -->
				<Card v-if="activeSection === 'ui'" title="Arayüz" body-class="sa-section">
					<p class="section-sub">Admin panelinin hangi kabukla (tema) render olacağını belirler — deploy gerektirmez, anında etkili olur.</p>

					<div class="field">
						<label>Admin Panel Kabuğu</label>
						<div class="radio-card-grid">
							<label
								v-for="o in adminThemeOptions"
								:key="o.value"
								class="radio-card"
								:class="{ active: form.ui.adminTheme === o.value }"
							>
								<input type="radio" :value="o.value" v-model="form.ui.adminTheme" />
								<div class="rc-content">
									<div class="rc-label">{{ o.label }}</div>
									<div class="rc-sub">{{ o.sub }}</div>
								</div>
							</label>
						</div>
					</div>
					<p class="sa-hint">V2 şu an yalnızca bir mimari iskelet — TopNav/Sidebar aynı, yalnızca ayrı bir kabuk ve CSS token namespace'i üzerinden render olur. Gerçek görsel tasarım netleşince bu ekrandan risk almadan geri "Klasik"e dönülebilir.</p>
				</Card>

				<!-- 10. SİSTEM BİLGİSİ -->
				<Card v-if="activeSection === 'system'" title="Sistem Bilgisi" body-class="sa-section">
					<p class="section-sub">Donanım, yazılım sürümleri ve canlı metrikler (salt okunur)</p>

					<p class="sa-hint sa-hint-error" v-if="systemFetchError">Canlı veri alınamadı, son bilinen değerler gösteriliyor.</p>

					<h3 class="sa-subhead">Kullanım Metrikleri</h3>
					<div class="metric-grid">
						<div class="metric-card">
							<div class="metric-head">
								<span class="metric-label">CPU</span>
								<strong class="metric-value">%{{ cpuPct }}</strong>
							</div>
							<ProgressBar :value="cpuPct" :color="metricColor(cpuPct)" />
						</div>
						<div class="metric-card">
							<div class="metric-head">
								<span class="metric-label">RAM</span>
								<strong class="metric-value">{{ ramPct }}%</strong>
							</div>
							<ProgressBar :value="ramPct" :color="metricColor(ramPct)" />
							<div class="metric-sub">{{ liveSystem.memoryUsageMB }} / {{ liveSystem.memoryTotalMB }} MB</div>
						</div>
						<div class="metric-card">
							<div class="metric-head">
								<span class="metric-label">Disk</span>
								<strong class="metric-value">{{ diskPct }}%</strong>
							</div>
							<ProgressBar :value="diskPct" :color="metricColor(diskPct)" />
							<div class="metric-sub">{{ liveSystem.diskUsageGB }} / {{ liveSystem.diskTotalGB }} GB</div>
						</div>
						<div class="metric-card">
							<div class="metric-head">
								<span class="metric-label">Çalışma Süresi</span>
								<strong class="metric-value">{{ liveSystem.uptime }}</strong>
							</div>
							<div class="metric-sub">Son yeniden başlatma: 28.03.2026</div>
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Yazılım Sürümleri</h3>
					<div class="versions-grid">
						<div class="version-row">
							<span class="vr-label">PHP</span>
							<span class="vr-value mono">{{ liveSystem.phpVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Laravel</span>
							<span class="vr-value mono">{{ liveSystem.laravelVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Inertia.js</span>
							<span class="vr-value mono">{{ liveSystem.inertiaVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Vue.js</span>
							<span class="vr-value mono">{{ liveSystem.vueVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Veritabanı</span>
							<span class="vr-value mono">{{ liveSystem.mysqlVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Redis</span>
							<span class="vr-value mono">{{ liveSystem.redisVersion }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">Web Server</span>
							<span class="vr-value mono">{{ liveSystem.webServer }}</span>
						</div>
						<div class="version-row">
							<span class="vr-label">İşletim Sistemi</span>
							<span class="vr-value mono">{{ liveSystem.serverOs }}</span>
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Servisler</h3>
					<div class="service-grid">
						<div class="service-card">
							<div class="service-head">
								<div class="service-icon">
									<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<path d="M5 12.55a11 11 0 0114 0" />
										<path d="M1.42 9a16 16 0 0121.16 0" />
										<path d="M8.53 16.11a6 6 0 016.95 0" />
										<line x1="12" y1="20" x2="12.01" y2="20" />
									</svg>
								</div>
								<div class="service-meta">
									<div class="service-title">Reverb (WebSocket)</div>
									<div class="service-sub mono">{{ reverbInfo.host }}:{{ reverbInfo.port }}</div>
								</div>
								<Badge :color="reverbInfo.running ? 'success' : 'danger'" variant="tonal" :label="reverbInfo.running ? 'Çalışıyor' : 'Durmuş'" />
							</div>
							<div class="service-body">
								<div v-if="reverbInfo.running" class="service-note">
									Realtime broadcasting aktif. <code class="mono">BROADCAST_CONNECTION=reverb</code>
								</div>
								<div v-else class="service-note service-note-warn">
									Başlatmak için terminalde çalıştır:
									<code class="mono">php artisan reverb:start</code>
									<span v-if="reverbInfo.error" class="service-error">· {{ reverbInfo.error }}</span>
								</div>
							</div>
						</div>
					</div>

					<div class="sa-divider"></div>

					<h3 class="sa-subhead">Platform Sayaçları</h3>
					<div class="counter-grid">
						<StatWidget :icon="Building2" :value="liveSystem.totalTenants.toLocaleString('tr-TR')" title="Toplam Tenant" color="primary" />
						<StatWidget :icon="UsersRound" :value="liveSystem.totalUsers.toLocaleString('tr-TR')" title="Toplam Kullanıcı" color="success" />
						<StatWidget :icon="ShoppingCart" :value="liveSystem.totalOrders.toLocaleString('tr-TR')" title="Toplam Sipariş" color="primary" />
						<StatWidget :icon="Clock" :value="liveSystem.queueJobsPending.toLocaleString('tr-TR')" title="Kuyrukta Bekleyen" color="warning" />
						<Link href="/superadmin/failed-jobs" class="counter-link">
							<StatWidget :icon="AlertCircle" :value="liveSystem.queueJobsFailed.toLocaleString('tr-TR')" title="Başarısız İş" :color="liveSystem.queueJobsFailed > 0 ? 'danger' : 'neutral'" />
						</Link>
					</div>
				</Card>
			</main>
		</div>

		<!-- ── Rol Düzenleme / Ekleme Modalı ── -->
		<AppModal
			v-model="showRoleModal"
			:title="editingRole && editingRole.isNew ? 'Yeni Rol Oluştur' : 'Rolü Düzenle'"
			:subtitle="editingRole && editingRole.isNew ? 'Ad, açıklama ve izinleri belirleyin' : 'Rol detaylarını ve izinleri güncelleyin'"
			size="lg"
		>
			<template #icon>
				<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" />
					<path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />
				</svg>
			</template>

			<div v-if="editingRole" class="role-modal-body">
				<div class="field-row">
					<div class="field">
						<label>Rol Adı *</label>
						<input v-model="editingRole.name" type="text" class="form-input" placeholder="Örn. Depo Sorumlusu" maxlength="40" />
					</div>
					<div class="field">
						<label>Sistem Anahtarı *</label>
						<input v-model="editingRole.key" type="text" class="form-input mono-input" placeholder="warehouse_manager" :disabled="!editingRole.isNew" />
						<div class="field-help">{{ editingRole.isNew ? 'küçük harf, alt çizgi kullanın' : 'Oluşturulduktan sonra değiştirilemez' }}</div>
					</div>
				</div>

				<div class="field">
					<label>Açıklama</label>
					<textarea v-model="editingRole.desc" class="form-textarea" rows="2" maxlength="160" placeholder="Bu rol ne yapar?"></textarea>
				</div>

				<div class="field">
					<label>Renk</label>
					<div class="color-picker">
						<button
							v-for="c in roleColors"
							:key="c"
							type="button"
							class="color-swatch"
							:class="{ active: editingRole.color === c }"
							:style="{ background: c }"
							@click="editingRole.color = c"
							:aria-label="c"
						></button>
					</div>
				</div>

				<div class="sa-divider"></div>

				<div class="perm-modal-head">
					<h3 class="sa-subhead">İzinler</h3>
					<div class="perm-stats">
						<strong>{{ editingRole.permissions.length }}</strong> / {{ totalPermissionCount }} seçili
					</div>
				</div>

				<div class="perm-modules">
					<div v-for="m in modules" :key="m.key" class="perm-module">
						<div class="perm-module-head">
							<div class="pmh-info">
								<strong>{{ m.name }}</strong>
								<span class="pmh-count">{{ countModulePermsInRole(m) }} / {{ m.permissions.length }}</span>
							</div>
							<label class="perm-module-toggle">
								<input
									type="checkbox"
									:checked="isModuleFullySelected(m)"
									@change="toggleAllInModule(m, $event.target.checked)"
								/>
								<span>{{ isModuleFullySelected(m) ? 'Tümünü Kaldır' : 'Tümünü Seç' }}</span>
							</label>
						</div>
						<div class="perm-grid">
							<label v-for="p in m.permissions" :key="p.key" class="perm-check">
								<input type="checkbox" :value="p.key" v-model="editingRole.permissions" />
								<div class="perm-check-body">
									<div class="perm-check-name">{{ p.name }}</div>
									<div class="perm-check-desc">{{ p.desc }}</div>
								</div>
							</label>
						</div>
					</div>
				</div>
			</div>

			<template #footer="{ close }">
				<Button variant="ghost" :disabled="roleBusy" @click="close">İptal</Button>
				<Button variant="primary" :disabled="!isRoleFormValid" :loading="roleBusy" @click="saveRole">
					{{ editingRole && editingRole.isNew ? 'Rolü Oluştur' : 'Değişiklikleri Kaydet' }}
				</Button>
			</template>
		</AppModal>

		<!-- ── İzin Modalı ── -->
		<AppModal
			v-model="showPermModal"
			:title="editingPermission && editingPermission.isNew ? 'Yeni İzin' : 'İzni Düzenle'"
			subtitle="Sistem adı (key) ve görünen ad belirleyin"
			size="md"
		>
			<template #icon>
				<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<polyline points="9 11 12 14 22 4" />
					<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
				</svg>
			</template>

			<div v-if="editingPermission">
				<div class="field">
					<label>Sistem Anahtarı *</label>
					<input v-model="editingPermission.name" type="text" class="form-input mono-input" placeholder="örn. users.view" />
					<div class="field-help">Modül için önek + nokta kullanın: <code>users.view</code>, <code>orders.create</code></div>
				</div>
				<div class="field">
					<label>Görünen Ad</label>
					<input v-model="editingPermission.display_name" type="text" class="form-input" placeholder="Örn. Kullanıcıları Görüntüle" />
				</div>
			</div>

			<template #footer="{ close }">
				<Button variant="ghost" :disabled="permBusy" @click="close">İptal</Button>
				<Button variant="primary" :disabled="!isPermFormValid" :loading="permBusy" @click="savePermission">
					{{ editingPermission && editingPermission.isNew ? 'Oluştur' : 'Güncelle' }}
				</Button>
			</template>
		</AppModal>

		<!-- ── Rol Silme Onay Modalı ── -->
		<AppModal
			v-model="showDeleteModal"
			:title="deletingRole ? `«${deletingRole.name}» rolünü sil?` : 'Sil'"
			subtitle="Bu işlem geri alınamaz."
			variant="danger"
			size="sm"
		>
			<div v-if="deletingRole">
				<p class="delete-msg">
					Bu role atanmış <strong>{{ deletingRole.userCount }} kullanıcı</strong> varsayılan
					<strong>Salt Okunur</strong> rolüne taşınacak.
				</p>
				<div class="delete-detail">
					<div class="dd-row">
						<span class="dd-label">İzin Sayısı</span>
						<span class="dd-value">{{ deletingRole.permissions.length }}</span>
					</div>
					<div class="dd-row">
						<span class="dd-label">Etkilenecek Kullanıcı</span>
						<span class="dd-value">{{ deletingRole.userCount }}</span>
					</div>
				</div>
			</div>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="roleBusy" @click="close">Vazgeç</Button>
				<Button variant="danger" :loading="roleBusy" @click="deleteRole">Evet, Sil</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, reactive, watch, onBeforeUnmount } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { Building2, UsersRound, ShoppingCart, Clock, AlertCircle, Check, Plus } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import AppModal from '@/Components/AppModal.vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import ProgressBar from '@/Components/ProgressBar.vue'
import StatWidget from '@/Components/StatWidget.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	settings: { type: Object, required: true },
	options: { type: Object, required: true },
})

const sections = [
	{ key: 'general',        label: 'Genel',                sub: 'Sistem adı, dil, zaman dilimi',  icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>' },
	{ key: 'security',       label: 'Güvenlik',             sub: 'Parola, 2FA, oturum',           icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>' },
	{ key: 'roles',          label: 'Roller & İzinler',     sub: 'Roller, izin matrisi',          icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>' },
	{ key: 'mail',           label: 'E-posta',              sub: 'SMTP ve gönderici',             icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' },
	{ key: 'notifications',  label: 'Bildirimler',          sub: 'E-posta, Slack, push',          icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>' },
	{ key: 'billing',        label: 'Ödeme & Faturalama',   sub: 'iyzico, Stripe, KDV',           icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>' },
	{ key: 'storage',        label: 'Depolama & Yedek',     sub: 'S3, CDN, backup',               icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6"/></svg>' },
	{ key: 'api',            label: 'API & Geliştirici',    sub: 'Rate limit, webhook',           icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>' },
	{ key: 'performance',    label: 'Performans',           sub: 'Cache, queue, log',             icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
	{ key: 'barcode',        label: 'Barkod (GS1)',         sub: 'EAN-13 önek ve seri aralığı',   icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1"/><line x1="7" y1="6" x2="7" y2="18"/><line x1="10" y1="6" x2="10" y2="18"/><line x1="14" y1="6" x2="14" y2="18"/><line x1="17" y1="6" x2="17" y2="18"/></svg>' },
	{ key: 'ui',             label: 'Arayüz',               sub: 'Admin panel kabuğu (tema)',     icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>' },
	{ key: 'system',         label: 'Sistem Bilgisi',       sub: 'Sürümler, metrikler',           icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' },
]

const adminThemeOptions = [
	{ value: 'classic', label: 'Klasik', sub: 'Mevcut, üretimde çalışan arayüz.' },
	{ value: 'v2',      label: 'Yeni SaaS Tasarımı (Beta)', sub: 'Faz 1 iskelet — görsel tasarım henüz doldurulmadı.' },
]

const activeSection = ref('general')
const activeSectionObj = computed(() => sections.find((s) => s.key === activeSection.value))
const mobileNavOpen = ref(false)
const showMailPassword = ref(false)
const showLogAccessPassword = ref(false)

/* ── Form ── */
const form = useForm({
	general: { ...props.settings.general },
	security: { ...props.settings.security, logAccessPassword: '' },
	mail: { ...props.settings.mail },
	notifications: { ...props.settings.notifications },
	billing: { ...props.settings.billing },
	storage: { ...props.settings.storage },
	api: { ...props.settings.api },
	performance: { ...props.settings.performance },
	barcode: { ...props.settings.barcode },
	ui: { ...props.settings.ui },
})

/* Dirty section tespiti */
const dirtyKeys = computed(() => {
	const dirty = []
	for (const k of Object.keys(form.data())) {
		const current = form[k]
		const original = props.settings[k]
		if (!original) continue
		for (const f of Object.keys(original)) {
			if (current[f] !== original[f]) {
				dirty.push(k)
				break
			}
		}
	}
	return dirty
})

/* ── KVKK: hassas alan maskesi ──
 * Backend hassas alanların değerini '••••••••' olarak gönderir; kullanıcı
 * o alana odaklanır odaklanmaz maskeyi temizleyerek '••••••••yeni' gibi
 * karma string oluşmasını engelliyoruz. Alan boş bırakılırsa mevcut
 * şifreli değer backend'te korunur (mask geri gönderilmiş gibi).
 */
const SENSITIVE_MASK = '••••••••'
function clearMask(group, key) {
	if (form[group]?.[key] === SENSITIVE_MASK) {
		form[group][key] = ''
	}
}

function save() {
	const dirty = dirtyKeys.value
	if (dirty.length === 0) return

	form
		.transform((data) => {
			const onlyDirty = {}
			for (const k of dirty) {
				if (k in data) onlyDirty[k] = data[k]
			}
			return onlyDirty
		})
		.post('/superadmin/settings', {
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				form.defaults()
				showToast?.({ type: 'success', title: 'Kaydedildi', message: 'Sistem ayarları güncellendi.' })
			},
			onError: () => {
				showToast?.({ type: 'error', title: 'Kaydedilemedi', message: 'Lütfen alanları kontrol edin.' })
			},
		})
}

function resetForm() {
	form.reset()
}

/* ── Test/aksiyon mock'ları ── */
import { inject } from 'vue'
const showToast = inject('showToast')
const $swal = inject('$swal')

function testMail() {
	showToast?.({ type: 'info', title: 'Test E-postası', message: 'SMTP testi başlatıldı, sonuç birkaç saniye içinde…' })
	setTimeout(() => {
		showToast?.({ type: 'success', title: 'SMTP Test Başarılı', message: `${form.mail.fromAddress} adresine test mesajı gönderildi.` })
	}, 1200)
}

function copy(text, label) {
	if (navigator.clipboard?.writeText) {
		navigator.clipboard.writeText(text)
		showToast?.({ type: 'success', title: 'Kopyalandı', message: `${label} panoya kopyalandı.` })
	}
}

function regenerateWebhookSecret() {
	form.api.webhookSecret = 'whsec_' + Array.from({ length: 32 }, () => Math.floor(Math.random() * 16).toString(16)).join('')
	showToast?.({ type: 'warning', title: 'Yeni Anahtar Üretildi', message: 'Mevcut webhook tüketicilerinin güncellenmesi gerekiyor.' })
}

const backupBusy = ref(false)

function runBackup() {
	if (backupBusy.value) return
	backupBusy.value = true
	router.post('/superadmin/backups/run', {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'info', title: 'Yedekleme Kuyruğa Alındı', message: 'Kuyruk işçisi çalıştığında başlayacak — ilerlemeyi "Tüm Geçmiş" sayfasından izleyebilirsiniz.' }),
		onError: () => showToast?.({ type: 'error', title: 'Yedekleme Tetiklenemedi', message: 'Bir hata oluştu, tekrar deneyin.' }),
		onFinish: () => { backupBusy.value = false },
	})
}

function formatBackupDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

const cacheBusy = ref(false)

function cacheAction(type) {
	if (cacheBusy.value) return
	cacheBusy.value = type
	router.post('/superadmin/settings/cache/purge', { type }, {
		preserveScroll: true,
		preserveState: true,
		onFinish: () => { cacheBusy.value = false },
	})
}

/* ── Roller & İzinler ── */
const roleView = ref('list')
const roleSearch = ref('')
const showRoleModal = ref(false)
const showDeleteModal = ref(false)
const editingRole = ref(null)
const deletingRole = ref(null)

const roleColors = ['#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#4a6cf7', '#7c3aed', '#db2777', '#6b7280', '#1a1a2e']
const roleBusy = ref(false)

const roles = computed(() => props.settings.roles?.list ?? [])
const modules = computed(() => props.settings.roles?.modules ?? [])

const totalPermissionCount = computed(() =>
	modules.value.reduce((sum, m) => sum + m.permissions.length, 0)
)

const filteredRoles = computed(() => {
	const q = roleSearch.value.trim().toLowerCase()
	if (!q) return roles.value
	return roles.value.filter(
		(r) => r.name.toLowerCase().includes(q) || r.key.toLowerCase().includes(q) || (r.desc || '').toLowerCase().includes(q)
	)
})

function getPermLabel(role) {
	if (role.permissions?.includes('*')) return 'Tüm izinler'
	const n = role.permissions?.length || 0
	return `${n} izin`
}

function hasPermission(role, key) {
	return role.permissions?.includes('*') || role.permissions?.includes(key)
}

function reloadRoles(onDone) {
	router.reload({
		only: ['settings'],
		preserveScroll: true,
		preserveState: true,
		onFinish: () => onDone?.(),
	})
}

async function togglePermission(role, key) {
	if (role.key === 'superadmin' || roleBusy.value || !can('rbac.manage')) return
	const next = hasPermission(role, key)
		? role.permissions.filter((k) => k !== key)
		: [...(role.permissions || []), key]
	roleBusy.value = true
	try {
		await window.axios.post(`/superadmin/roles/${role.id}/permissions`, { permissions: next })
		reloadRoles()
	} catch (e) {
		showToast?.({ type: 'error', title: 'İzin Güncellenemedi', message: e?.response?.data?.error || 'Sunucu hatası.' })
	} finally {
		roleBusy.value = false
	}
}

function countModulePermsInRole(m) {
	if (!editingRole.value) return 0
	return m.permissions.filter((p) => editingRole.value.permissions.includes(p.key)).length
}

function isModuleFullySelected(m) {
	if (!editingRole.value) return false
	return m.permissions.every((p) => editingRole.value.permissions.includes(p.key))
}

function toggleAllInModule(m, checked) {
	if (!editingRole.value) return
	const keys = m.permissions.map((p) => p.key)
	if (checked) {
		const merged = new Set([...editingRole.value.permissions, ...keys])
		editingRole.value.permissions = [...merged]
	} else {
		editingRole.value.permissions = editingRole.value.permissions.filter((k) => !keys.includes(k))
	}
}

function openAddRole() {
	editingRole.value = reactive({
		isNew: true,
		id: null,
		key: '',
		name: '',
		desc: '',
		color: roleColors[5],
		userCount: 0,
		system: false,
		permissions: [],
	})
	showRoleModal.value = true
}

function openEditRole(role) {
	editingRole.value = reactive({
		isNew: false,
		id: role.id,
		originalKey: role.key,
		key: role.key,
		name: role.name,
		desc: role.desc,
		color: role.color,
		userCount: role.userCount,
		system: role.system,
		permissions: [...(role.permissions || [])],
	})
	showRoleModal.value = true
}

const isRoleFormValid = computed(() => {
	if (!editingRole.value) return false
	const r = editingRole.value
	if (!r.name?.trim() || !r.key?.trim()) return false
	if (!/^[a-z][a-z0-9_]*$/.test(r.key)) return false
	if (r.isNew && roles.value.some((x) => x.key === r.key)) return false
	return true
})

async function syncRolePermissions(roleId, perms) {
	await window.axios.post(`/superadmin/roles/${roleId}/permissions`, { permissions: perms })
}

function saveRole() {
	if (!isRoleFormValid.value || roleBusy.value) return
	const r = editingRole.value
	const displayName = r.name.trim()
	const sysKey = r.key.trim()
	const perms = [...r.permissions]
	roleBusy.value = true

	const afterRoleSaved = async (targetId) => {
		try {
			if (targetId) await syncRolePermissions(targetId, perms)
			reloadRoles(() => {
				roleBusy.value = false
				showRoleModal.value = false
				editingRole.value = null
				showToast?.({
					type: 'success',
					title: r.isNew ? 'Rol Oluşturuldu' : 'Rol Güncellendi',
					message: `«${displayName}» için değişiklikler kaydedildi.`,
				})
			})
		} catch (e) {
			roleBusy.value = false
			showToast?.({ type: 'error', title: 'İzinler Kaydedilemedi', message: e?.response?.data?.error || 'Sunucu hatası.' })
		}
	}

	if (r.isNew) {
		router.post('/superadmin/roles', { role: sysKey, display_name: displayName }, {
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				const created = (props.settings.roles?.list ?? []).find((x) => x.key === sysKey)
				afterRoleSaved(created?.id)
			},
			onError: (errors) => {
				roleBusy.value = false
				showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: Object.values(errors)[0] || 'Doğrulama hatası.' })
			},
		})
	} else {
		router.put(`/superadmin/roles/${r.id}`, { name: sysKey, display_name: displayName }, {
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => afterRoleSaved(r.id),
			onError: (errors) => {
				roleBusy.value = false
				showToast?.({ type: 'error', title: 'Güncelleme Başarısız', message: Object.values(errors)[0] || 'Doğrulama hatası.' })
			},
		})
	}
}

function confirmDeleteRole(role) {
	if (role.system) return
	deletingRole.value = role
	showDeleteModal.value = true
}

function deleteRole() {
	if (!deletingRole.value || roleBusy.value) return
	const target = deletingRole.value
	roleBusy.value = true
	router.delete(`/superadmin/roles/${target.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Rol Silindi', message: `«${target.name}» rolü kaldırıldı.` })
			showDeleteModal.value = false
			deletingRole.value = null
		},
		onError: (errors) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errors)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => {
			roleBusy.value = false
		},
	})
}

/* ── İzin CRUD ── */
const permSearch = ref('')
const showPermModal = ref(false)
const editingPermission = ref(null)
const permBusy = ref(false)

const flatPermissions = computed(() =>
	modules.value.flatMap((m) => m.permissions.map((p) => ({ ...p, moduleKey: m.key, moduleName: m.name })))
)

const filteredPermissionModules = computed(() => {
	const q = permSearch.value.trim().toLowerCase()
	if (!q) return modules.value
	return modules.value
		.map((m) => ({
			...m,
			permissions: m.permissions.filter((p) => p.key.toLowerCase().includes(q) || p.name.toLowerCase().includes(q)),
		}))
		.filter((m) => m.permissions.length > 0)
})

const isPermFormValid = computed(() => {
	if (!editingPermission.value) return false
	const p = editingPermission.value
	if (!p.name?.trim()) return false
	if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/.test(p.name.trim())) return false
	if (p.isNew && flatPermissions.value.some((x) => x.key === p.name.trim())) return false
	return true
})

function openAddPermission() {
	editingPermission.value = reactive({ isNew: true, id: null, name: '', display_name: '' })
	showPermModal.value = true
}

function openEditPermission(p) {
	editingPermission.value = reactive({
		isNew: false,
		id: p.id,
		name: p.key,
		display_name: p.name === p.key ? '' : p.name,
	})
	showPermModal.value = true
}

function savePermission() {
	if (!isPermFormValid.value || permBusy.value) return
	const p = editingPermission.value
	const payload = { name: p.name.trim(), display_name: p.display_name?.trim() || null }
	permBusy.value = true
	const url = p.isNew ? '/superadmin/permissions' : `/superadmin/permissions/${p.id}`
	const method = p.isNew ? 'post' : 'put'
	router[method](url, payload, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showPermModal.value = false
			editingPermission.value = null
			showToast?.({ type: 'success', title: p.isNew ? 'İzin Eklendi' : 'İzin Güncellendi', message: payload.name })
		},
		onError: (errors) => {
			showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: Object.values(errors)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => {
			permBusy.value = false
		},
	})
}

async function deletePermission(p) {
	if (permBusy.value) return
	const ok = await $swal.dangerConfirm({ title: 'İzin silinsin mi?', html: `<b>${p.name}</b> izni kalıcı olarak silinecek.` })
	if (!ok) return
	permBusy.value = true
	router.delete(`/superadmin/permissions/${p.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'İzin Silindi', message: p.name })
		},
		onError: (errors) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errors)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => {
			permBusy.value = false
		},
	})
}

/* ── Sistem metrikleri (canlı) ── */
const liveSystem = ref({ ...props.settings.system })
const systemFetchedAt = ref(null)
const systemFetchError = ref(false)
let systemTimer = null
const SYSTEM_POLL_MS = 5000

const ramPct = computed(() => {
	const total = Number(liveSystem.value.memoryTotalMB) || 0
	const used  = Number(liveSystem.value.memoryUsageMB) || 0
	if (total <= 0) return 0
	return Math.round((used / total) * 1000) / 10
})
const diskPct = computed(() => {
	const total = Number(liveSystem.value.diskTotalGB) || 0
	const used  = Number(liveSystem.value.diskUsageGB) || 0
	if (total <= 0) return 0
	return Math.round((used / total) * 1000) / 10
})
const cpuPct = computed(() => Number(liveSystem.value.cpuUsagePct) || 0)

const reverbInfo = computed(() => ({
	running: !!liveSystem.value.reverb?.running,
	host:    liveSystem.value.reverb?.host ?? '127.0.0.1',
	port:    liveSystem.value.reverb?.port ?? 8080,
	error:   liveSystem.value.reverb?.error ?? null,
}))
function metricColor(pct) {
	if (pct < 60) return 'success'
	if (pct < 85) return 'warning'
	return 'danger'
}

async function fetchSystemInfo() {
	try {
		const { data } = await window.axios.get('/superadmin/system-info')
		if (data?.system) {
			liveSystem.value = data.system
			systemFetchedAt.value = data.fetchedAt
			systemFetchError.value = false
		}
	} catch (e) {
		// Son bilinen değerler ekranda kalır — ama artık bu bir hata olarak işaretlenir,
		// sessizce "sanki her şey yolunda" gösterilmez.
		systemFetchError.value = true
	}
}

function startSystemPolling() {
	if (systemTimer) return
	fetchSystemInfo()
	systemTimer = setInterval(fetchSystemInfo, SYSTEM_POLL_MS)
}

function stopSystemPolling() {
	if (!systemTimer) return
	clearInterval(systemTimer)
	systemTimer = null
}

watch(activeSection, (s) => {
	if (s === 'system') startSystemPolling()
	else stopSystemPolling()
}, { immediate: true })

onBeforeUnmount(stopSystemPolling)
</script>

<style scoped>
/* ── Layout ── */
.sa-layout {
	position: relative;
	display: grid;
	grid-template-columns: 260px 1fr;
	gap: 16px;
	align-items: flex-start;
}

@media (max-width: 1000px) { .sa-layout { grid-template-columns: 1fr; } }

/* ── Mobil (telefon) nav hamburger: masaüstünde gizli, sadece dar ekranda görünür ── */
.sa-mobile-nav-toggle { display: none; }
.sa-mobile-nav-backdrop { display: none; }

/* ── Sol Nav ── */
.sa-nav {
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 14px;
	padding: 8px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	position: sticky;
	top: 12px;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.sa-nav-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 9px 11px;
	background: none;
	border: none;
	border-radius: 9px;
	cursor: pointer;
	color: rgb(var(--color-muted));
	font-family: inherit;
	text-align: left;
	transition: background .12s, color .12s;
	width: 100%;
}
.sa-nav-item:hover { background: rgb(var(--color-bg) / .5); color: rgb(var(--color-ink)); }
.sa-nav-item.active {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-ink));
}
.sa-nav-item.active .sa-nav-icon {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
}

.sa-nav-icon {
	width: 28px; height: 28px;
	border-radius: 7px;
	background: rgb(var(--color-bg) / .5);
	color: rgb(var(--color-muted));
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
	transition: background .12s, color .12s;
}
.sa-nav-icon :deep(svg) { display: block; }

.sa-nav-text { flex: 1; min-width: 0; }
.sa-nav-label {
	font-size: 12.5px;
	font-weight: 600;
	color: inherit;
	line-height: 1.3;
}
.sa-nav-sub {
	font-size: 10.5px;
	color: rgb(var(--color-muted));
	margin-top: 1px;
}

.sa-nav-dirty {
	flex-shrink: 0;
	margin-left: 4px;
}

.sa-nav-footer {
	margin-top: 8px;
	padding-top: 8px;
	border-top: 1px solid rgb(var(--color-border));
}

.sa-version {
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 6px 11px;
	font-size: 10.5px;
	color: rgb(var(--color-muted));
}

/* ── Sağ İçerik ── */
.sa-content { min-width: 0; }

.sa-section {
	padding: 20px 22px 24px;
}

.section-sub {
	font-size: 12.5px;
	color: rgb(var(--color-muted));
	margin: 0 0 18px;
}

.sa-subhead {
	font-size: 11px;
	font-weight: 700;
	color: rgb(var(--color-muted));
	text-transform: uppercase;
	letter-spacing: 0.06em;
	margin: 0 0 10px;
}

.sa-divider {
	height: 1px;
	background: rgb(var(--color-border));
	margin: 18px 0;
}

/* ── Form fields ── */
.field-row {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 12px;
	margin-bottom: 12px;
}

.field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.field:last-child { margin-bottom: 0; }

.field label {
	font-size: 11.5px;
	font-weight: 600;
	color: rgb(var(--color-muted));
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.field-help {
	font-size: 11px;
	color: rgb(var(--color-muted));
	margin-top: 2px;
}

.flex-2 { flex: 2; grid-column: span 2; }

.mono-input {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 12px !important;
	letter-spacing: 0.02em;
}

/* Password input */
.password-input {
	position: relative;
	display: flex;
	align-items: center;
}
.password-input .form-input { padding-right: 36px; width: 100%; }
.pw-toggle {
	position: absolute;
	right: 8px;
	width: 26px; height: 26px;
	background: none;
	border: none;
	border-radius: 6px;
	color: rgb(var(--color-muted));
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: color .12s, background .12s;
}
.pw-toggle:hover { background: rgb(var(--color-bg)); color: rgb(var(--color-ink)); }

/* Copy input */
.copy-input {
	display: flex;
	gap: 6px;
}
.copy-input .form-input { flex: 1; }
.copy-btn {
	height: 36px;
	padding: 0 12px;
	background: rgb(var(--color-bg) / .5);
	border: 1.5px solid rgb(var(--color-border));
	border-radius: 9px;
	color: rgb(var(--color-muted));
	font-family: inherit;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	transition: all .12s;
}
.copy-btn:hover { background: rgb(var(--color-bg)); border-color: rgb(var(--color-muted)); color: rgb(var(--color-ink)); }

/* ── Toggle (switch) ── */
.toggle-card {
	display: flex;
	align-items: center;
	gap: 14px;
	padding: 12px 16px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 11px;
	margin-bottom: 8px;
	transition: background .15s, border-color .15s;
}
.toggle-card.toggle-warning {
	background: rgb(var(--color-warning) / .1);
	border-color: rgb(var(--color-warning) / .35);
}
.toggle-card.toggle-danger {
	background: rgb(var(--color-danger) / .08);
	border-color: rgb(var(--color-danger) / .25);
}

.toggle-info { flex: 1; min-width: 0; }

.toggle-label {
	font-size: 13px;
	font-weight: 700;
	color: rgb(var(--color-ink));
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.toggle-sub {
	font-size: 11.5px;
	color: rgb(var(--color-muted));
	margin-top: 3px;
	line-height: 1.5;
}

.sa-hint {
	font-size: 12px;
	color: rgb(var(--color-muted));
	margin-top: 10px;
	line-height: 1.6;
}
.sa-hint-error {
	color: rgb(var(--color-danger));
}

.danger-pill {
	display: inline-block;
	padding: 1px 6px;
	background: rgb(var(--color-danger));
	color: #fff;
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 0.06em;
	border-radius: 4px;
}

.warning-pill {
	display: inline-block;
	padding: 1px 6px;
	background: rgb(var(--color-warning));
	color: #fff;
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 0.06em;
	border-radius: 4px;
}

.switch {
	position: relative;
	display: inline-block;
	width: 38px;
	height: 22px;
	flex-shrink: 0;
}
.switch input {
	opacity: 0;
	width: 0;
	height: 0;
}
.slider {
	position: absolute;
	cursor: pointer;
	inset: 0;
	background: rgb(var(--color-border));
	border-radius: 999px;
	transition: background .15s;
}
.slider::before {
	content: '';
	position: absolute;
	height: 16px;
	width: 16px;
	left: 3px;
	top: 3px;
	background: #fff;
	border-radius: 50%;
	transition: transform .15s;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.switch input:checked + .slider {
	background: rgb(var(--color-success));
}
.switch input:checked + .slider::before {
	transform: translateX(16px);
}

/* ── Check group ── */
.check-group {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 8px;
	margin-bottom: 12px;
}

.check-card {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	padding: 10px 12px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 10px;
	cursor: pointer;
	transition: all .12s;
}
.check-card:hover { border-color: rgb(var(--color-muted)); }
.check-card:has(input:checked) {
	background: rgb(var(--color-primary-soft));
	border-color: #c4b5fd;
}
.check-card input {
	margin-top: 2px;
	accent-color: rgb(var(--color-primary));
	width: 14px;
	height: 14px;
}
.check-text { display: flex; flex-direction: column; gap: 2px; }
.check-text strong {
	font-size: 12.5px;
	color: rgb(var(--color-ink));
	font-weight: 700;
}
.check-sub {
	font-size: 11px;
	color: rgb(var(--color-muted));
}

/* ── Radio cards ── */
.radio-card-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 8px;
}

.radio-card {
	display: flex;
	gap: 10px;
	padding: 12px 14px;
	background: rgb(var(--color-surface));
	border: 1.5px solid rgb(var(--color-border));
	border-radius: 10px;
	cursor: pointer;
	transition: all .15s;
}
.radio-card input { position: absolute; opacity: 0; pointer-events: none; }
.radio-card:hover { border-color: rgb(var(--color-muted)); }
.radio-card.active {
	border-color: rgb(var(--color-ink));
	background: rgb(var(--color-bg) / .5);
	box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.05);
}

.rc-content { display: flex; flex-direction: column; gap: 2px; }
.rc-label {
	font-size: 12.5px;
	font-weight: 700;
	color: rgb(var(--color-ink));
}
.rc-sub {
	font-size: 11px;
	color: rgb(var(--color-muted));
	line-height: 1.5;
}

/* ── Test block (mail) ── */
.test-block {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 10px;
	margin-top: 12px;
}

.test-status {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 12.5px;
	font-weight: 700;
}
.test-success { color: rgb(var(--color-success)); }
.test-failed  { color: rgb(var(--color-danger)); }

.test-time {
	font-size: 11px;
	color: rgb(var(--color-muted));
	margin-top: 2px;
}

/* ── Integration card ── */
.integration-card {
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 12px;
	padding: 14px 16px;
	margin-bottom: 10px;
}

.ic-head {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 12px;
}

.ic-logo {
	width: 36px; height: 36px;
	border-radius: 9px;
	color: #fff;
	font-size: 10px;
	font-weight: 800;
	letter-spacing: 0.04em;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.ic-info { display: flex; flex-direction: column; gap: 2px; }

.ic-info h4 {
	font-size: 13.5px;
	font-weight: 700;
	color: rgb(var(--color-ink));
	margin: 0;
}

.ic-status {
	font-size: 11px;
	font-weight: 600;
}
.status-connected { color: rgb(var(--color-success)); }

/* ── Info row (backup, etc) ── */
.info-row {
	display: flex;
	align-items: center;
	gap: 20px;
	padding: 12px 16px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 10px;
	margin-top: 12px;
	flex-wrap: wrap;
}

.ir-block { display: flex; flex-direction: column; gap: 2px; }
.ir-label {
	font-size: 10.5px;
	font-weight: 700;
	color: rgb(var(--color-muted));
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.ir-value {
	font-size: 12.5px;
	font-weight: 700;
	color: rgb(var(--color-ink));
}

/* ── Cache actions ── */
.cache-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

/* ── Metrics ── */
.metric-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 10px;
}

.metric-card {
	padding: 12px 14px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 11px;
}

.metric-head {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	margin-bottom: 8px;
}

.metric-label {
	font-size: 11px;
	font-weight: 700;
	color: rgb(var(--color-muted));
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.metric-value {
	font-size: 18px;
	font-weight: 800;
	color: rgb(var(--color-ink));
}

.metric-sub {
	font-size: 11px;
	color: rgb(var(--color-muted));
	margin-top: 6px;
}

/* ── Versions ── */
.versions-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 6px;
}

.version-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 8px;
}

.vr-label {
	font-size: 12px;
	font-weight: 600;
	color: rgb(var(--color-muted));
}
.vr-value {
	font-size: 11.5px;
	color: rgb(var(--color-ink));
	font-weight: 700;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
}

/* ── Services ── */
.service-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
	gap: 12px;
}

.service-card {
	padding: 14px 16px;
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 12px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.service-head {
	display: flex;
	align-items: center;
	gap: 10px;
}

.service-icon {
	width: 34px; height: 34px;
	border-radius: 9px;
	background: rgb(var(--color-bg));
	color: rgb(var(--color-primary));
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.service-meta { flex: 1; min-width: 0; }
.service-title { font-size: 13.5px; font-weight: 700; color: rgb(var(--color-ink)); }
.service-sub   { font-size: 11.5px; color: rgb(var(--color-muted)); margin-top: 2px; }

.service-body { margin-top: 10px; }
.service-note {
	font-size: 12px;
	color: rgb(var(--color-muted));
	line-height: 1.55;
}
.service-note code {
	display: inline-block;
	padding: 2px 7px;
	background: rgb(var(--color-bg));
	border: 1px solid rgb(var(--color-border));
	border-radius: 5px;
	font-size: 11.5px;
	color: rgb(var(--color-ink));
	margin: 0 2px;
}
.service-note-warn { color: rgb(var(--color-warning)); }
.service-note-warn code { background: rgb(var(--color-warning) / .12); border-color: rgb(var(--color-warning) / .3); color: rgb(var(--color-warning)); }
.service-error { color: rgb(var(--color-danger)); font-size: 11.5px; margin-left: 4px; }

/* ── Counters ── */
.counter-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
	gap: 10px;
}

.counter-link {
	text-decoration: none;
	color: inherit;
	display: block;
}

/* ──────────────────────────────────────── */
/* ──     ROLLER & İZİNLER BÖLÜMÜ      ── */
/* ──────────────────────────────────────── */

.role-view-toggle {
	display: inline-flex;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 9px;
	padding: 3px;
	gap: 2px;
}
.rv-btn {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 6px 11px;
	background: none;
	border: none;
	border-radius: 7px;
	color: rgb(var(--color-muted));
	font-family: inherit;
	font-size: 11.5px;
	font-weight: 600;
	cursor: pointer;
	transition: background .12s, color .12s;
}
.rv-btn:hover { color: rgb(var(--color-ink)); }
.rv-btn.active {
	background: rgb(var(--color-surface));
	color: rgb(var(--color-ink));
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

/* — Liste başlığı — */
.role-list-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
	margin-bottom: 14px;
	flex-wrap: wrap;
}

.role-search {
	position: relative;
	display: flex;
	align-items: center;
	flex: 1;
	min-width: 220px;
	max-width: 380px;
}
.role-search svg {
	position: absolute;
	left: 11px;
	color: rgb(var(--color-muted));
	pointer-events: none;
}
.role-search-input {
	width: 100%;
	height: 34px;
	padding: 0 12px 0 32px;
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 9px;
	color: rgb(var(--color-ink));
	font-family: inherit;
	font-size: 12.5px;
	transition: border-color .15s, background .15s;
}
.role-search-input:focus {
	outline: none;
	border-color: #c4b5fd;
	background: rgb(var(--color-surface));
}

/* — Rol kartları — */
.role-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
	gap: 10px;
}

.role-card {
	display: flex;
	gap: 12px;
	padding: 14px;
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 12px;
	transition: border-color .15s, box-shadow .15s, transform .15s;
}
.role-card:hover {
	border-color: rgb(var(--color-muted));
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.role-icon-bg {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	color: #fff;
	font-size: 16px;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
	letter-spacing: -0.02em;
}

.role-card-body {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.role-card-title {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.role-card-title h4 {
	font-size: 13.5px;
	font-weight: 700;
	color: rgb(var(--color-ink));
	margin: 0;
	line-height: 1.3;
}

.system-pill {
	display: inline-block;
	padding: 1px 6px;
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 0.06em;
	border-radius: 4px;
}

.role-card-desc {
	font-size: 11.5px;
	color: rgb(var(--color-muted));
	line-height: 1.45;
	margin: 0;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.role-card-meta {
	display: flex;
	gap: 12px;
	margin-top: 4px;
}
.rc-meta-item {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 11px;
	color: rgb(var(--color-muted));
	font-weight: 600;
}
.rc-meta-item svg { color: rgb(var(--color-muted)); }

.role-card-actions {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex-shrink: 0;
}
.rc-action {
	width: 28px;
	height: 28px;
	background: none;
	border: 1px solid rgb(var(--color-border));
	border-radius: 7px;
	color: rgb(var(--color-muted));
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all .12s;
}
.rc-action:hover:not(:disabled) {
	background: rgb(var(--color-bg) / .5);
	border-color: rgb(var(--color-muted));
	color: rgb(var(--color-ink));
}
.rc-action:disabled {
	opacity: .35;
	cursor: not-allowed;
}
.rc-action.rc-danger:hover:not(:disabled) {
	background: rgb(var(--color-danger) / .08);
	border-color: rgb(var(--color-danger) / .25);
	color: rgb(var(--color-danger));
}

.role-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 40px 20px;
	color: rgb(var(--color-muted));
	background: rgb(var(--color-bg) / .5);
	border: 1px dashed rgb(var(--color-border));
	border-radius: 12px;
}
.role-empty svg { color: rgb(var(--color-muted)); margin-bottom: 8px; }
.role-empty p { margin: 0; font-size: 12.5px; }

/* ── İzin Matrisi ── */
.matrix-wrap { margin-top: 4px; }

.matrix-legend {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 9px 12px;
	margin-bottom: 10px;
	background: rgb(var(--color-primary-soft));
	border: 1px solid #e9d5ff;
	border-radius: 9px;
}
.ml-text {
	font-size: 11.5px;
	color: rgb(var(--color-primary-hover));
	font-weight: 500;
}

.matrix-scroll {
	overflow-x: auto;
	border: 1px solid rgb(var(--color-border));
	border-radius: 11px;
	background: rgb(var(--color-surface));
}

.perm-matrix {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 12px;
}

.perm-matrix thead th {
	position: sticky;
	top: 0;
	background: rgb(var(--color-bg) / .5);
	border-bottom: 1px solid rgb(var(--color-border));
	padding: 10px 12px;
	font-size: 11px;
	font-weight: 700;
	text-align: left;
	color: rgb(var(--color-muted));
	text-transform: uppercase;
	letter-spacing: 0.04em;
	white-space: nowrap;
}
.m-perm-col {
	min-width: 220px;
	position: sticky;
	left: 0;
	z-index: 2;
}
.m-role-col {
	text-align: center !important;
	min-width: 100px;
}

.m-role-head {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
}
.m-role-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
}
.m-role-name {
	color: rgb(var(--color-ink));
	font-weight: 700;
	text-transform: none;
	letter-spacing: 0;
}

.m-module-row td {
	background: rgb(var(--color-primary-soft));
	padding: 8px 14px;
	border-bottom: 1px solid rgb(var(--color-primary-soft));
	border-top: 1px solid rgb(var(--color-primary-soft));
}
.m-module-name {
	font-size: 11px;
	font-weight: 700;
	color: rgb(var(--color-primary-hover));
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
.m-module-count {
	margin-left: 8px;
	font-size: 10.5px;
	color: rgb(var(--color-primary) / .5);
	font-weight: 600;
}

.m-perm-row td { border-bottom: 1px solid rgb(var(--color-border)); }
.m-perm-row:hover td { background: rgb(var(--color-bg) / .5); }

.m-perm-cell {
	padding: 9px 12px;
	position: sticky;
	left: 0;
	background: rgb(var(--color-surface));
	z-index: 1;
}
.m-perm-row:hover .m-perm-cell { background: rgb(var(--color-bg) / .5); }

.m-perm-name {
	font-size: 12.5px;
	font-weight: 600;
	color: rgb(var(--color-ink));
	line-height: 1.3;
}
.m-perm-key {
	font-size: 10.5px;
	color: rgb(var(--color-muted));
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	margin-top: 1px;
}

.m-cell {
	text-align: center;
	padding: 8px;
	cursor: pointer;
}

/* Özel checkbox (matris) */
.m-check {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	position: relative;
}
.m-check input {
	position: absolute;
	opacity: 0;
	width: 100%;
	height: 100%;
	cursor: pointer;
}
.m-check input:disabled { cursor: not-allowed; }
.m-checkbox {
	width: 18px;
	height: 18px;
	border: 1.5px solid rgb(var(--color-border));
	border-radius: 5px;
	background: rgb(var(--color-surface));
	display: flex;
	align-items: center;
	justify-content: center;
	color: transparent;
	transition: all .12s;
}
.m-check:hover .m-checkbox { border-color: rgb(var(--color-primary)); }
.m-check input:checked + .m-checkbox {
	background: rgb(var(--color-primary));
	border-color: rgb(var(--color-primary));
	color: #fff;
}
.m-check input:disabled + .m-checkbox {
	background: rgb(var(--color-primary-soft));
	border-color: #c4b5fd;
	color: #fff;
	opacity: .7;
}

/* ── Rol Modal ── */
.role-modal-body { display: flex; flex-direction: column; gap: 12px; }

.color-picker {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}
.color-swatch {
	width: 26px;
	height: 26px;
	border-radius: 8px;
	border: 2px solid transparent;
	cursor: pointer;
	transition: transform .12s, border-color .12s, box-shadow .12s;
	padding: 0;
}
.color-swatch:hover { transform: scale(1.08); }
.color-swatch.active {
	border-color: rgb(var(--color-ink));
	box-shadow: 0 0 0 2px #fff inset;
}

.perm-modal-head {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	margin-bottom: 8px;
}
.perm-stats {
	font-size: 12px;
	color: rgb(var(--color-muted));
}
.perm-stats strong {
	color: rgb(var(--color-ink));
	font-weight: 700;
}

.perm-modules {
	display: flex;
	flex-direction: column;
	gap: 10px;
	max-height: 380px;
	overflow-y: auto;
	padding-right: 4px;
}
.perm-modules::-webkit-scrollbar { width: 6px; }
.perm-modules::-webkit-scrollbar-thumb { background: rgb(var(--color-border)); border-radius: 3px; }

.perm-module {
	background: rgb(var(--color-bg) / .5);
	border: 1px solid rgb(var(--color-border));
	border-radius: 10px;
	padding: 10px 12px;
}

.perm-module-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}
.pmh-info {
	display: flex;
	align-items: baseline;
	gap: 8px;
}
.pmh-info strong {
	font-size: 12.5px;
	color: rgb(var(--color-ink));
	font-weight: 700;
}
.pmh-count {
	font-size: 11px;
	color: rgb(var(--color-muted));
	font-weight: 600;
}

.perm-module-toggle {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	cursor: pointer;
	font-size: 11px;
	font-weight: 600;
	color: rgb(var(--color-primary));
}
.perm-module-toggle input {
	width: 13px;
	height: 13px;
	accent-color: rgb(var(--color-primary));
}

.perm-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 6px;
}

.perm-check {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 8px 10px;
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 8px;
	cursor: pointer;
	transition: border-color .12s, background .12s;
}
.perm-check:hover { border-color: #c4b5fd; }
.perm-check:has(input:checked) {
	background: rgb(var(--color-primary-soft));
	border-color: #c4b5fd;
}
.perm-check input {
	margin-top: 1px;
	width: 13px;
	height: 13px;
	accent-color: rgb(var(--color-primary));
	flex-shrink: 0;
}
.perm-check-body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.perm-check-name {
	font-size: 12px;
	font-weight: 600;
	color: rgb(var(--color-ink));
	line-height: 1.3;
}
.perm-check-desc {
	font-size: 10.5px;
	color: rgb(var(--color-muted));
	line-height: 1.4;
}

/* ── Silme Modalı ── */
.delete-msg {
	font-size: 13px;
	color: rgb(var(--color-muted));
	line-height: 1.55;
	margin: 0 0 12px;
}
.delete-msg strong { color: rgb(var(--color-ink)); font-weight: 700; }

.delete-detail {
	background: rgb(var(--color-danger) / .08);
	border: 1px solid rgb(var(--color-danger) / .25);
	border-radius: 9px;
	padding: 10px 12px;
}
.dd-row {
	display: flex;
	justify-content: space-between;
	padding: 4px 0;
}
.dd-label { font-size: 12px; color: rgb(var(--color-danger)); }
.dd-value { font-size: 12px; color: rgb(var(--color-ink)); font-weight: 700; }

/* ── İzinler Listesi (CRUD) ── */
.perm-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.perm-list-module {
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 10px;
	overflow: hidden;
}
.perm-list-module-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 14px;
	background: rgb(var(--color-bg) / .5);
	border-bottom: 1px solid rgb(var(--color-border));
}
.perm-list-module-head strong {
	font-size: 13px;
	color: rgb(var(--color-ink));
	font-weight: 700;
}
.perm-list-rows {
	display: flex;
	flex-direction: column;
}
.perm-list-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 9px 14px;
	border-bottom: 1px solid rgb(var(--color-border));
	transition: background .12s;
}
.perm-list-row:last-child { border-bottom: none; }
.perm-list-row:hover { background: rgb(var(--color-bg) / .5); }
.perm-list-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.perm-list-name { font-size: 13px; color: rgb(var(--color-ink)); font-weight: 600; }
.perm-list-key { font-size: 11px; color: rgb(var(--color-muted)); font-family: 'SF Mono', Monaco, monospace; }
.perm-list-actions { display: flex; gap: 4px; }
.field code {
	font-family: 'SF Mono', Monaco, monospace;
	font-size: 11.5px;
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	padding: 1px 5px;
	border-radius: 4px;
}

@media (max-width: 640px) {
	.header-actions .btn { flex: 1; justify-content: center; }

	.sa-section { padding: 16px 14px 18px; }

	/* İki kolonlu span'lar (SMTP Host, Fatura Öneki vb.) dar ekranda tek kolona insin */
	.field-row { grid-template-columns: 1fr; }
	.flex-2 { grid-column: auto; }

	.role-view-toggle { flex-wrap: wrap; width: 100%; }
	.rv-btn { flex: 1 1 auto; justify-content: center; }

	.test-block { flex-wrap: wrap; gap: 10px; }
	.test-block .btn { width: 100%; justify-content: center; }

	.copy-input { flex-wrap: wrap; }
	.copy-input .form-input { width: 100%; }

	/* Sol nav → hamburger: nav varsayılan gizli, toggle butonu görünür, açılınca dropdown gibi belirir */
	.sa-mobile-nav-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		padding: 9px 12px;
		background: rgb(var(--color-surface));
		border: 1px solid rgb(var(--color-border));
		border-radius: 12px;
		cursor: pointer;
		font-family: inherit;
		font-size: 12.5px;
		font-weight: 600;
		color: rgb(var(--color-ink));
		margin-bottom: 8px;
	}
	.sa-mobile-nav-toggle svg { flex-shrink: 0; color: rgb(var(--color-muted)); transition: transform .15s; }
	.sa-mobile-nav-toggle.open svg { transform: rotate(90deg); }
	.sa-mobile-nav-current { display: flex; align-items: center; gap: 8px; }
	.sa-mobile-nav-current .sa-nav-icon { width: 24px; height: 24px; }

	.sa-nav { display: none; }
	.sa-nav.sa-nav-mobile-open {
		display: flex;
		position: absolute;
		z-index: 40;
		left: 16px;
		right: 16px;
		top: auto;
		margin-top: 42px;
		box-shadow: 0 10px 32px rgba(0, 0, 0, 0.14);
	}
	.sa-mobile-nav-backdrop {
		display: block;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.25);
		z-index: 30;
	}
}
</style>
