<template>
	<Head title="Ürün Giydirme" />
	<div class="page-tryon">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Ürün Giydirme' },
			]"
		/>

		<CreativeNav current="tryon" />

		<PageHeader title="Ürün Giydirme" subtitle="Ürünü bir mankenin pozlarına giydir, ürün görseli olarak ekle">
			<template #actions>
				<Button variant="ghost" with-icon @click="refresh">
					<template #leading><RefreshCw :size="14" /></template>
					Yenile
				</Button>
			</template>
		</PageHeader>

		<!-- Adım göstergesi: sırayla kilitli/açık/tamam adımlar — bir adım tamamlanmadan sonrakine geçilemez. -->
		<div v-if="can('creative.asset.manage')" class="wizard-steps">
			<template v-for="(s, i) in wizardSteps" :key="s.n">
				<button
					type="button"
					class="wizard-step"
					:class="{ done: s.complete, current: currentStep === s.n, locked: s.n > maxUnlockedStep }"
					:disabled="s.n > maxUnlockedStep"
					@click="goToStep(s.n)"
				>
					<span class="wizard-step-num">
						<Check v-if="s.complete && currentStep !== s.n" :size="12" />
						<template v-else>{{ s.n }}</template>
					</span>
					<span class="wizard-step-label">{{ s.label }}</span>
				</button>
				<span v-if="i < wizardSteps.length - 1" class="wizard-step-line" :class="{ done: s.complete }"></span>
			</template>
		</div>

		<!-- Adım 1: Ürün -->
		<Card v-if="can('creative.asset.manage') && currentStep === 1" title="1. Ürün Seç">
			<template #actions>
				<div class="product-search">
					<Search :size="13" class="search-icon" />
					<input v-model="productQuery" type="text" placeholder="Ürün ara…" />
					<Loader2 v-if="productsLoading" :size="13" class="search-spin" />
				</div>
			</template>

			<div class="filter-row">
				<button
					type="button"
					class="filter-chip"
					:class="{ active: withoutGarmentOnly }"
					@click="withoutGarmentOnly = !withoutGarmentOnly"
				>
					Sadece fotoğrafsız ürünler
				</button>
				<span v-if="!productQuery && productsTotal > productPool.length" class="hint">
					İlk {{ productPool.length }} / {{ productsTotal }} ürün gösteriliyor — daralmak için arayın
				</span>
			</div>

			<EmptyState
				v-if="filteredProducts.length === 0"
				:icon="PackageSearch"
				title="Ürün bulunamadı."
				hint="Arama terimini değiştirmeyi ya da filtreyi kapatmayı deneyin."
			/>
			<div v-else class="product-grid">
				<button
					v-for="p in filteredProducts"
					:key="p.id"
					type="button"
					class="product-card"
					:class="{ selected: selectedProduct === p.id }"
					:title="p.has_garment ? p.name : `${p.name} — giydirilecek fotoğrafı yok`"
					@click="selectProduct(p)"
				>
					<div class="product-thumb">
						<img v-if="p.cover" :src="p.cover" :alt="p.name" loading="lazy" decoding="async" />
						<span v-else class="no-preview">{{ p.name.charAt(0) }}</span>
					</div>
					<span class="product-name">{{ p.name }}</span>
					<span v-if="!p.has_garment" class="no-garment-badge">Fotoğraf yok</span>
				</button>
			</div>

			<div class="step-nav">
				<span class="step-nav-spacer"></span>
				<Button variant="primary" with-icon :disabled="!step1Complete" @click="nextStep">
					Devam Et
					<template #trailing><ChevronRight :size="14" /></template>
				</Button>
			</div>
		</Card>

		<!-- Adım 2: Görseller -->
		<Card v-if="can('creative.asset.manage') && currentStep === 2" title="2. Görselleri Ekle">
			<template #actions>
				<span class="hint">{{ selectedProductObj?.name }}</span>
			</template>

			<div class="garment-upload">
				<div class="garment-upload-head">
					<strong>Ürün Görseli <span class="required-mark" title="Zorunlu">*</span></strong>
					<span v-if="!selectedProductHasGarment" class="hint hint-warning">Bu ürünün fotoğrafı yok — devam etmek için bir görsel yükleyin.</span>
					<span v-else class="hint">Ürünün mevcut fotoğrafı kullanılacak — farklısını kullanmak için yeni bir görsel yükleyin.</span>
				</div>
				<div class="garment-upload-body">
					<!-- Kart üründe zaten fotoğraf olsa bile hep boş başlar — yalnız burada elle
					     yüklenen görsel kartın içinde gösterilir, ürünün mevcut fotoğrafı önizlenmez. -->
					<label class="garment-select-card" :class="{ 'required-empty': !selectedProductHasGarment }" v-if="!garmentPreview">
						<input type="file" accept="image/*" @change="onGarmentFileChange" hidden />
						<ImagePlus :size="24" />
						<span>Görsel Seç</span>
					</label>
					<div v-else class="garment-select-card has-image">
						<img :src="garmentPreview" alt="Yüklenen ürün görseli" />
						<button type="button" class="garment-remove-badge" title="Kaldır" @click="clearGarmentFile"><X :size="13" /></button>
					</div>
					<span class="upload-hint">JPG, PNG veya WEBP · en fazla 8MB</span>
				</div>
			</div>

			<div class="garment-details">
				<div class="garment-upload-head">
					<strong>Detay görselleri (opsiyonel)</strong>
					<span class="hint">Arkadan, yandan, yaka/dikiş, kumaş detayı vb. — AI giydirirken hepsini dikkate alır. JPG/PNG/WEBP, en fazla 8MB.</span>
				</div>

				<!-- Sabit kategoriler her zaman görünür kartlardır — açmak için tıklamaya gerek yok, doldurmak opsiyoneldir. -->
				<div class="detail-fixed-grid">
					<div v-for="d in fixedDetailSlots" :key="d.id" class="detail-fixed-item">
						<label class="garment-select-card sm" v-if="!d.preview">
							<input type="file" accept="image/*" @change="onDetailFileChange(d.id, $event)" hidden />
							<ImagePlus :size="18" />
						</label>
						<div v-else class="garment-select-card sm has-image">
							<img :src="d.preview" :alt="d.label" />
							<button type="button" class="garment-remove-badge" title="Kaldır" @click="clearFixedDetailSlot(d)"><X :size="12" /></button>
						</div>
						<span class="detail-fixed-label">{{ d.label }}</span>
					</div>
				</div>

				<!-- Sabit kategorilere girmeyen ek görseller (opsiyonel, isteğe bağlı eklenir). -->
				<div v-if="customDetailSlots.length" class="detail-list">
					<div v-for="d in customDetailSlots" :key="d.id" class="detail-item">
						<label class="detail-thumb" v-if="!d.preview">
							<input type="file" accept="image/*" @change="onDetailFileChange(d.id, $event)" hidden />
							<span>Görsel seç…</span>
						</label>
						<div v-else class="detail-thumb has-image">
							<img :src="d.preview" alt="Detay görseli" />
						</div>
						<div class="detail-label-wrap">
							<input
								v-model="d.label"
								type="text"
								class="detail-label-input"
								:class="{ 'is-suggested': d.suggested }"
								placeholder="Etiket (ör. Kol Ucu)"
								maxlength="60"
								@input="d.suggested = false"
							/>
							<span v-if="d.classifying" class="detail-label-hint">tahmin ediliyor…</span>
							<span v-else-if="d.suggested" class="detail-label-hint">öneri, %{{ Math.round(d.suggestionScore * 100) }}</span>
						</div>
						<Button variant="ghost" size="sm" @click="removeCustomDetailSlot(d.id)">Kaldır</Button>
					</div>
				</div>

				<button type="button" class="preset-chip" @click="addCustomDetailSlot">+ Serbest başlıklı</button>
			</div>

			<div class="step-nav">
				<Button variant="ghost" with-icon @click="prevStep">
					<template #leading><ChevronLeft :size="14" /></template>
					Geri
				</Button>
				<Button variant="primary" with-icon :disabled="!step2Complete" @click="nextStep">
					Devam Et
					<template #trailing><ChevronRight :size="14" /></template>
				</Button>
			</div>
		</Card>

		<!-- Adım 3: Manken -->
		<Card v-if="can('creative.asset.manage') && currentStep === 3" title="3. Manken Seç">
			<template #actions>
				<span class="hint">{{ mannequins.length }} uygun manken</span>
			</template>

			<EmptyState
				v-if="mannequins.length === 0"
				:icon="Users"
				title="Giydirmeye uygun manken yok."
				hint="Önce bir manken üretin (durum: hazır)."
			/>
			<div v-else class="mannequin-row">
				<button
					v-for="m in mannequins"
					:key="m.id"
					type="button"
					class="mannequin-pill"
					:class="{ selected: selectedMannequin === m.id }"
					@click="selectedMannequin = m.id"
				>
					<div class="pill-thumb"><img v-if="m.reference_url" :src="m.reference_url" :alt="m.name" loading="lazy" decoding="async" /></div>
					<span>{{ m.name }}</span>
				</button>
			</div>

			<div class="step-nav">
				<Button variant="ghost" with-icon @click="prevStep">
					<template #leading><ChevronLeft :size="14" /></template>
					Geri
				</Button>
				<Button variant="primary" with-icon :disabled="!step3Complete" @click="nextStep">
					Devam Et
					<template #trailing><ChevronRight :size="14" /></template>
				</Button>
			</div>
		</Card>

		<!-- Adım 4: Pozlar (bağımsız kütüphane) -->
		<Card v-if="can('creative.asset.manage') && currentStep === 4" title="4. Pozlar Seç">
			<template #actions>
				<Button v-if="poses.length" variant="ghost" size="sm" @click="toggleAllPoses">
					{{ allPosesSelected ? 'Seçimi kaldır' : 'Tümünü seç' }}
				</Button>
				<span class="hint">{{ selectedPoses.size }}/{{ poses.length }} seçili</span>
			</template>

			<EmptyState v-if="poses.length === 0" :icon="Footprints" title="Hazır poz yok.">
				<template #default>
					Önce <Link href="/creative/poses" class="inline-link">Pozlar</Link> sayfasından üretin.
				</template>
			</EmptyState>
			<div v-else class="pose-grid">
				<button
					v-for="pose in poses"
					:key="pose.id"
					type="button"
					class="pose-pick-card"
					:class="{ selected: selectedPoses.has(pose.id) }"
					@click="togglePose(pose.id)"
				>
					<img v-if="pose.preview_url" :src="pose.preview_url" :alt="pose.label" loading="lazy" decoding="async" />
					<span
						v-if="approvedPoseIdsForSelectedProduct.has(pose.id)"
						class="pose-approved-flag"
						title="Bu poz için seçili üründe onaylı bir görsel zaten var — yeniden üretmek onayı sıfırlar"
					><ShieldCheck :size="12" /></span>
					<span class="pose-pick-label">{{ pose.label }}</span>
					<span class="pose-check" :class="{ on: selectedPoses.has(pose.id) }"></span>
				</button>
			</div>

			<div class="step-nav">
				<Button variant="ghost" with-icon @click="prevStep">
					<template #leading><ChevronLeft :size="14" /></template>
					Geri
				</Button>
				<Button variant="primary" with-icon :disabled="!step4Complete" @click="nextStep">
					Devam Et
					<template #trailing><ChevronRight :size="14" /></template>
				</Button>
			</div>
		</Card>

		<!-- Adım 5: Üret -->
		<Card v-if="can('creative.asset.manage') && currentStep === 5" title="5. Giydir ve Üret">
			<div class="review-summary">
				<div class="review-summary-item">
					<span class="review-summary-label">Ürün</span>
					<span class="review-summary-value">{{ selectedProductObj?.name }}</span>
				</div>
				<div class="review-summary-item">
					<span class="review-summary-label">Manken</span>
					<span class="review-summary-value">{{ selectedMannequinObj?.name }}</span>
				</div>
				<div class="review-summary-item">
					<span class="review-summary-label">Pozlar</span>
					<span class="review-summary-value">{{ selectedPoses.size }} seçili</span>
				</div>
			</div>

			<Alert
				v-if="overlapCount > 0"
				variant="warning"
				title="Onaylı görsel yeniden üretilecek"
				:message="`Seçtiğiniz pozlardan ${overlapCount} tanesi için zaten onaylı bir giydirme görseli var. Yeniden üretirseniz bu görsellerin onay durumu sıfırlanır (ürün kapağı değişmez, ama tekrar onay bekler).`"
			/>

			<div class="action-bar">
				<div class="selection-summary">
					<strong>{{ selectedPoses.size }}</strong> poz ×
					<strong>{{ selectedProduct ? 1 : 0 }}</strong> ürün =
					<strong>{{ selectedProduct ? selectedPoses.size : 0 }}</strong> görsel
					<p v-if="!canGenerate && missingRequirements.length" class="missing-hint">
						<Info :size="12" /> Eksik: {{ missingRequirements.join(', ') }}
					</p>
				</div>
				<Button variant="primary" with-icon :disabled="!canGenerate" :loading="busy" @click="generate">
					<template #leading><Play :size="14" /></template>
					{{ busy ? 'Kuyruğa alınıyor…' : 'Giydir ve Ürün Görseli Yap' }}
				</Button>
			</div>

			<div class="step-nav">
				<Button variant="ghost" with-icon @click="prevStep">
					<template #leading><ChevronLeft :size="14" /></template>
					Geri
				</Button>
				<span class="step-nav-spacer"></span>
			</div>
		</Card>

		<!-- Sonuçlar -->
		<Card :title="resultsProductObj ? `${resultsProductObj.name} — Giydirmeler` : 'Son Giydirmeler (tüm ürünler)'">
			<template #actions>
				<button v-if="resultsProductObj !== null" type="button" class="link-btn" @click="clearProductFilter">
					Tüm ürünleri göster
				</button>
				<Loader2 v-if="productResultsLoading" :size="13" class="search-spin" />
				<span class="hint">{{ filteredResults.length }}/{{ displayResults.length }} kayıt</span>
			</template>

			<div v-if="displayResults.length" class="filter-row">
				<button
					v-for="f in STATUS_FILTERS"
					:key="f.key"
					type="button"
					class="filter-chip"
					:class="{ active: statusFilter === f.key }"
					@click="statusFilter = f.key"
				>{{ f.label }}</button>
				<span class="filter-sep"></span>
				<button
					v-for="f in REVIEW_FILTERS"
					:key="f.key"
					type="button"
					class="filter-chip"
					:class="{ active: reviewFilter === f.key }"
					@click="reviewFilter = f.key"
				>{{ f.label }}</button>
			</div>

			<EmptyState
				v-if="displayResults.length === 0"
				:icon="ImageOff"
				:title="resultsProductObj ? `${resultsProductObj.name} için henüz giydirme yok.` : 'Henüz giydirme yok.'"
			/>
			<EmptyState
				v-else-if="filteredResults.length === 0"
				:icon="ImageOff"
				title="Filtreyle eşleşen kayıt yok."
				hint="Farklı bir durum/onay filtresi deneyin."
			/>
			<div v-else class="result-grid">
				<div v-for="r in filteredResults" :key="r.id" class="result-card">
					<div class="result-thumb">
						<img v-if="r.image_url" class="clickable" :src="r.image_url" :alt="r.product_name" loading="lazy" decoding="async" @click="openPreview(r)" />
						<span v-else class="no-preview">{{ statusLabel(r.status) }}</span>
						<button v-if="r.image_url" class="zoom-badge" title="Büyük önizleme" @click="openPreview(r)"><Maximize2 :size="13" /></button>
						<Badge class="status-badge" :color="statusColor(r.status)" :label="statusLabel(r.status)" variant="filled" />
						<Badge v-if="r.is_cover" class="cover-badge" :icon="Star" label="Kapak" color="neutral" variant="filled" />
						<Badge
							v-if="r.review_status"
							class="review-badge"
							:color="reviewColor(r.review_status)"
							:label="reviewLabel(r.review_status)"
							variant="filled"
						/>
					</div>
					<div class="result-meta">
						<span class="result-product">{{ r.product_name }}</span>
						<span class="result-sub">{{ r.mannequin_name }} · {{ r.pose_label }}</span>
						<span v-if="r.creator_name" class="result-creator">Üreten: {{ r.creator_name }}{{ r.is_own ? ' (siz)' : '' }}</span>
						<span v-if="r.tryon_model" class="result-model" :title="r.tryon_model">Model: {{ r.tryon_model }}</span>
						<div v-if="r.review_tags && r.review_tags.length" class="review-tags">
							<Tag v-for="t in r.review_tags" :key="t" :label="t" color="danger" />
						</div>
						<p v-if="r.error" class="result-error" :title="r.error"><AlertTriangle :size="11" /> {{ r.error }}</p>
						<div v-if="r.can_review" class="review-actions">
							<Button variant="success" size="sm" :disabled="busyReview === r.id" @click="approve(r)"><template #leading><Check :size="11" /></template>Onayla</Button>
							<Button variant="danger" size="sm" :disabled="busyReview === r.id" @click="reject(r)"><template #leading><X :size="11" /></template>Reddet</Button>
						</div>
						<div v-if="(r.status === 'done' || r.status === 'failed') && can('creative.asset.manage')" class="result-actions">
							<template v-if="r.status === 'done' && r.review_status === 'approved'">
								<button v-if="!r.is_cover" type="button" class="link-btn" @click="setCover(r)">Kapak yap</button>
								<span v-else class="is-cover-note">Kapak</span>
							</template>
							<button type="button" class="link-btn danger" @click="destroyResult(r)">Sil</button>
						</div>
					</div>
					<Link :href="`/creative/tryon/${r.id}`" class="chat-link">
						<ClipboardList :size="13" /> Üretim Detayı
					</Link>
					<Link v-if="r.can_chat" :href="`/creative/tryon/${r.id}/review-chat`" class="chat-link">
						<MessageCircle :size="13" /> AI ile Konuş <span v-if="r.review_chats?.length">({{ r.review_chats.length }} mesaj)</span>
					</Link>
				</div>
			</div>
		</Card>

		<!-- Büyük önizleme (lightbox) -->
		<Teleport to="body">
			<div v-if="preview" class="lightbox" @click.self="closePreview">
				<div class="lb-box">
					<button class="lb-close" @click="closePreview"><X :size="16" /></button>
					<div
						class="lb-img-wrap"
						:class="{ zoomed: zoom.scale > 1, dragging: zoom.dragging }"
						@wheel.prevent="onWheel"
						@pointerdown="onPointerDown"
						@pointermove="onPointerMove"
						@pointerup="onPointerUp"
						@pointercancel="onPointerUp"
						@dblclick="onDblClick"
						@touchstart.passive="onTouchStart"
						@touchmove.prevent="onTouchMove"
					>
						<img
							:src="preview.image_url"
							:alt="preview.product_name"
							draggable="false"
							:style="{ transform: `translate(${zoom.x}px, ${zoom.y}px) scale(${zoom.scale})` }"
						/>
					</div>
					<div class="lb-side">
						<h3 class="lb-title">{{ preview.product_name }}</h3>
						<div class="lb-tags-meta">
							<Tag :label="preview.mannequin_name" color="primary" />
							<Tag :label="preview.pose_label" color="neutral" />
							<Tag v-if="preview.review_status" :label="reviewLabel(preview.review_status)" :color="reviewColor(preview.review_status)" />
						</div>
						<span class="lb-hint">Yakınlaştırmak için fare tekerleği veya pinch, gezinmek için sürükleyin.</span>
						<a :href="preview.image_url" target="_blank" :download="`tryon-${preview.id}.${extOf(preview.image_url)}`" class="btn btn-primary lb-download">
							<Download :size="14" />
							İndir
						</a>
					</div>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script setup>
import { ref, reactive, computed, watch, inject, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
	RefreshCw, Play, Maximize2, Star, AlertTriangle, Check, X, ClipboardList, MessageCircle, Download,
	Search, Loader2, PackageSearch, Users, Footprints, ImageOff, ShieldCheck, Info, ChevronRight, ChevronLeft, ImagePlus,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import Tag from '@/Components/Tag.vue'
import Alert from '@/Components/Alert.vue'
import EmptyState from '@/Components/EmptyState.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { openRejectDialog } from '../support/rejectDialog'
import { extOf } from '../support/mediaExt'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	products: { type: Array, default: () => [] },
	productsTotal: { type: Number, default: 0 },
	mannequins: { type: Array, default: () => [] },
	poses: { type: Array, default: () => [] },
	results: { type: Array, default: () => [] },
	rejectionReasons: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')

// ── Ürün arama/seçim ──
// `serverProducts` null iken ilk sayfa yükünde gelen `props.products` (ilk N
// ürün) gösterilir; kullanıcı arama kutusuna yazınca sunucudan (tüm katalogda
// arayan) taze bir liste gelir — bkz. TryonController::products().
const productQuery = ref('')
const serverProducts = ref(null)
const productsLoading = ref(false)
const withoutGarmentOnly = ref(false)
let searchTimer = null

function scheduleProductSearch() {
	clearTimeout(searchTimer)
	const q = productQuery.value.trim()
	if (!q) {
		serverProducts.value = null
		productsLoading.value = false
		return
	}
	productsLoading.value = true
	searchTimer = setTimeout(async () => {
		try {
			const { data } = await window.axios.get('/creative/tryon/products', { params: { q } })
			serverProducts.value = data?.data || []
		} catch {
			serverProducts.value = []
		} finally {
			productsLoading.value = false
		}
	}, 300)
}
watch(productQuery, scheduleProductSearch)

const productPool = computed(() => serverProducts.value ?? props.products)
const filteredProducts = computed(() => {
	if (!withoutGarmentOnly.value) return productPool.value
	return productPool.value.filter(p => !p.has_garment)
})

const busy = ref(false)
const busyReview = ref(null)
const selectedProduct = ref(null)
// Seçili ürünün nesnesi ayrıca saklanır — arama sonucu değişip ürün artık
// görünür listede olmasa bile (ör. arama temizlendi) seçim özetinde adı
// kaybolmasın diye.
const selectedProductObj = ref(null)
const selectedMannequin = ref(null)
const selectedPoses = reactive(new Set())
const garmentFile = ref(null)
const garmentPreview = ref(null)

function selectProduct(p) {
	selectedProduct.value = p.id
	selectedProductObj.value = p
	resultsProductObj.value = p
	fetchProductResults(p.id)
}

const selectedMannequinObj = computed(() => props.mannequins.find(m => m.id === selectedMannequin.value) ?? null)

// Detay görselleri (opsiyonel) — arkadan/yandan/yaka-dikiş/kumaş vb. Her giriş
// { id, label, file, preview }; sadece 'file' seçili olanlar gönderime dahil edilir.
// Bilinen kategoriler (fixedDetailSlots) sabit, her zaman görünen kartlardır —
// ana ürün görseli kartıyla aynı görünümde, tıklamayla "açılmaları" gerekmez.
// Bunların dışında kalan serbest başlıklı ek görseller customDetailSlots'ta.
const DETAIL_PRESETS = ['Arkadan', 'Yandan', 'Yaka / Dikiş Detayı', 'Kumaş Detayı', 'Yakın Çekim']
let detailSeq = 0

function makeDetailSlot(label) {
	return {
		id: ++detailSeq, label, file: null, preview: null,
		suggested: false, suggestionScore: null, classifying: false, detectedLabels: [],
	}
}

const fixedDetailSlots = reactive(DETAIL_PRESETS.map(makeDetailSlot))
const customDetailSlots = reactive([])

function addCustomDetailSlot() {
	if (customDetailSlots.length >= 6) return
	customDetailSlots.push(makeDetailSlot(''))
}

function onDetailFileChange(id, e) {
	const file = e.target.files?.[0] || null
	const slot = fixedDetailSlots.find(d => d.id === id) || customDetailSlots.find(d => d.id === id)
	if (!slot) return
	if (slot.preview) URL.revokeObjectURL(slot.preview)
	slot.file = file
	slot.preview = file ? URL.createObjectURL(file) : null
	slot.suggested = false
	slot.suggestionScore = null
	slot.detectedLabels = []
	if (file) classifyDetailSuggestion(slot)
}

// Detay görseli seçilir seçilmez yerel ML modeliyle (Gemini/bulut çağrısı
// YOK) ne gösterdiğini tahmin edip etiketi öneri olarak doldurur — kullanıcı
// zaten bir şey yazdıysa/preset seçtiyse asla ezmez. Sınıflandırma kapalıysa
// (config('creative.detail_classification.enabled')=false) sunucu boş liste
// döner, bu durumda hiçbir şey değişmez.
async function classifyDetailSuggestion(slot) {
	if (!slot.file) return
	slot.classifying = true
	try {
		const fd = new FormData()
		fd.append('image', slot.file)
		const { data } = await window.axios.post('/creative/tryon/classify-detail', fd)
		slot.detectedLabels = data?.labels || []
		const top = slot.detectedLabels[0]
		if (top && !slot.label?.trim()) {
			slot.label = top.display
			slot.suggested = true
			slot.suggestionScore = top.score
		}
	} catch {
		// Öneri opsiyoneldir — sessizce yut, kullanıcı elle etiketlemeye devam edebilir.
	} finally {
		slot.classifying = false
	}
}

// Sabit kategori kartı kaldırılamaz (her zaman görünür kalır) — yalnız
// içindeki dosya/önizleme temizlenip kart boş duruma döner.
function clearFixedDetailSlot(slot) {
	if (slot.preview) URL.revokeObjectURL(slot.preview)
	slot.file = null
	slot.preview = null
	slot.suggested = false
	slot.suggestionScore = null
	slot.detectedLabels = []
}

function removeCustomDetailSlot(id) {
	const idx = customDetailSlots.findIndex(d => d.id === id)
	if (idx === -1) return
	if (customDetailSlots[idx].preview) URL.revokeObjectURL(customDetailSlots[idx].preview)
	customDetailSlots.splice(idx, 1)
}

function clearGarmentDetails() {
	fixedDetailSlots.forEach(clearFixedDetailSlot)
	customDetailSlots.forEach(d => { if (d.preview) URL.revokeObjectURL(d.preview) })
	customDetailSlots.splice(0, customDetailSlots.length)
}

const selectedProductHasGarment = computed(() => !!selectedProductObj.value?.has_garment)

const allPosesSelected = computed(() =>
	props.poses.length > 0 && props.poses.every(p => selectedPoses.has(p.id)),
)
const canGenerate = computed(() =>
	selectedProduct.value !== null
	&& selectedMannequin.value !== null
	&& selectedPoses.size > 0
	&& (selectedProductHasGarment.value || garmentFile.value !== null),
)

const missingRequirements = computed(() => {
	const missing = []
	if (selectedProduct.value === null) missing.push('ürün')
	if (selectedMannequin.value === null) missing.push('manken')
	if (selectedPoses.size === 0) missing.push('en az bir poz')
	if (selectedProduct.value !== null && !selectedProductHasGarment.value && !garmentFile.value) missing.push('giysi görseli')
	return missing
})

// ── Adımlı akış (Progress with Steps) ──
// Bir adım tamamlanmadan sonrakine geçilemez: step-N-complete koşulu sağlanana
// kadar hem ilgili "Devam Et" butonu disabled kalır hem de üstteki adım
// göstergesinde o adımın sonrası kilitli (tıklanamaz) görünür.
const currentStep = ref(1)
const step1Complete = computed(() => selectedProduct.value !== null)
const step2Complete = computed(() => selectedProductHasGarment.value || garmentFile.value !== null)
const step3Complete = computed(() => selectedMannequin.value !== null)
const step4Complete = computed(() => selectedPoses.size > 0)

const wizardSteps = computed(() => [
	{ n: 1, label: 'Ürün', complete: step1Complete.value },
	{ n: 2, label: 'Görseller', complete: step2Complete.value },
	{ n: 3, label: 'Manken', complete: step3Complete.value },
	{ n: 4, label: 'Pozlar', complete: step4Complete.value },
	{ n: 5, label: 'Üret', complete: false },
])

// En ileri erişilebilir adım — ilk tamamlanmamış adımın bir sonrasıdır.
const maxUnlockedStep = computed(() => {
	if (!step1Complete.value) return 1
	if (!step2Complete.value) return 2
	if (!step3Complete.value) return 3
	if (!step4Complete.value) return 4
	return 5
})

function goToStep(n) {
	if (n <= maxUnlockedStep.value) currentStep.value = n
}
function nextStep() {
	if (currentStep.value < 5) goToStep(currentStep.value + 1)
}
function prevStep() {
	if (currentStep.value > 1) currentStep.value -= 1
}

// Bir önceki adımdaki seçim geçersiz hale gelirse (ör. ürün değiştirilip
// giysi görseli kalktı) mevcut adım artık kilitli kalan bir adımın ötesinde
// olabilir — bu durumda son açık adıma geri çeker.
watch(maxUnlockedStep, (max) => { if (currentStep.value > max) currentStep.value = max })

// ── Ürüne özel giydirme geçmişi ──
// index()'in gönderdiği `results` global akıştır (tüm ürünlerin son 60 kaydı) —
// yalnız 'creative.approve' yetkili, ürün seçme adımını görmeyen onaylayıcılar
// için bu genel akış gösterilir. Bir ürün seçildiğinde galeri, o ürünün TÜM
// geçmişini döndüren /creative/tryon/results uç noktasına geçer.
// Wizard'daki `selectedProduct`'tan KASITLI olarak ayrı tutulur: "Tüm ürünleri
// göster" yalnız bu galeriyi etkilesin, üretim akışındaki (1-5. adım) seçimi
// sıfırlamasın.
const productResults = ref(null)
const productResultsLoading = ref(false)
const resultsProductObj = ref(null)

async function fetchProductResults(productId) {
	productResultsLoading.value = true
	try {
		const { data } = await window.axios.get('/creative/tryon/results', { params: { product_id: productId } })
		productResults.value = data?.data || []
	} catch {
		productResults.value = []
	} finally {
		productResultsLoading.value = false
	}
}

function clearProductFilter() {
	resultsProductObj.value = null
	productResults.value = null
}

function refetchProductResultsIfActive() {
	if (resultsProductObj.value !== null) fetchProductResults(resultsProductObj.value.id)
}

const displayResults = computed(() => productResults.value ?? props.results)

// Zaten onaylanmış (ve ürün kapağı olarak yayınlanmış) bir poz yeniden
// kuyruğa alınırsa ProductOnModelService::queue() onay durumunu sessizce
// sıfırlıyor — burada bunu seçim aşamasında görünür kılıyoruz.
const approvedPoseIdsForSelectedProduct = computed(() => {
	const set = new Set()
	if (selectedProduct.value === null) return set
	for (const r of displayResults.value) {
		if (r.product_id === selectedProduct.value && r.review_status === 'approved' && r.pose_id) set.add(r.pose_id)
	}
	return set
})
const overlapCount = computed(() =>
	Array.from(selectedPoses).filter(id => approvedPoseIdsForSelectedProduct.value.has(id)).length,
)

const BATCH_WARN_THRESHOLD = 8

function onGarmentFileChange(e) {
	const file = e.target.files?.[0] || null
	garmentFile.value = file
	if (garmentPreview.value) URL.revokeObjectURL(garmentPreview.value)
	garmentPreview.value = file ? URL.createObjectURL(file) : null
}

function clearGarmentFile() {
	if (garmentPreview.value) URL.revokeObjectURL(garmentPreview.value)
	garmentFile.value = null
	garmentPreview.value = null
}

// Ürün değişince önceki yüklenen görsel(ler) başka bir ürüne taşınmasın.
watch(selectedProduct, () => { clearGarmentFile(); clearGarmentDetails() })
const hasPending = computed(() => displayResults.value.some(r => r.status === 'queued' || r.status === 'generating'))

const STATUS_LABELS = { queued: 'Sırada', generating: 'Üretiliyor', done: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Onay Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }
function statusLabel(s) { return STATUS_LABELS[s] || s }
function reviewLabel(r) { return REVIEW_LABELS[r] || r }

const STATUS_COLORS = { queued: 'neutral', generating: 'warning', done: 'success', failed: 'danger' }
const REVIEW_COLORS = { pending: 'warning', approved: 'success', rejected: 'danger' }
function statusColor(s) { return STATUS_COLORS[s] ?? 'neutral' }
function reviewColor(r) { return REVIEW_COLORS[r] ?? 'neutral' }

// ── Sonuç galerisi filtreleri (istemci taraflı — results zaten 60 kayıtla sınırlı) ──
const STATUS_FILTERS = [
	{ key: 'all', label: 'Tümü' },
	{ key: 'queued', label: 'Sırada' },
	{ key: 'generating', label: 'Üretiliyor' },
	{ key: 'done', label: 'Hazır' },
	{ key: 'failed', label: 'Başarısız' },
]
const REVIEW_FILTERS = [
	{ key: 'all', label: 'Tümü' },
	{ key: 'pending', label: 'Onay Bekliyor' },
	{ key: 'approved', label: 'Onaylı' },
	{ key: 'rejected', label: 'Reddedildi' },
]
const statusFilter = ref('all')
const reviewFilter = ref('all')
const filteredResults = computed(() => displayResults.value.filter(r =>
	(statusFilter.value === 'all' || r.status === statusFilter.value)
	&& (reviewFilter.value === 'all' || r.review_status === reviewFilter.value),
))

function approve(r) {
	if (busyReview.value) return
	busyReview.value = r.id
	router.post(`/creative/tryon/${r.id}/approve`, {}, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Onaylanamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null; refetchProductResultsIfActive() },
	})
}

async function reject(r) {
	const result = await openRejectDialog($swal, props.rejectionReasons)
	if (!result) return

	busyReview.value = r.id
	router.post(`/creative/tryon/${r.id}/reject`, { reason: result.reason, tags: result.tags }, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Reddedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null; refetchProductResultsIfActive() },
	})
}

function togglePose(id) {
	if (selectedPoses.has(id)) selectedPoses.delete(id)
	else selectedPoses.add(id)
}

function toggleAllPoses() {
	if (allPosesSelected.value) props.poses.forEach(p => selectedPoses.delete(p.id))
	else props.poses.forEach(p => selectedPoses.add(p.id))
}

async function generate() {
	if (!canGenerate.value || busy.value) return

	if (overlapCount.value > 0) {
		const ok = await $swal.confirm({
			title: 'Onaylı Görsel Yeniden Üretilecek',
			html: `Seçili pozlardan <strong>${overlapCount.value}</strong> tanesi için zaten onaylı bir giydirme görseli var. Yeniden üretirseniz bu görsellerin onay durumu sıfırlanır (ürün kapağı hemen değişmez, ama tekrar onay bekleyecek).`,
			confirmText: 'Yine de Üret',
			cancelText: 'Vazgeç',
		})
		if (!ok) return
	}

	if (selectedPoses.size > BATCH_WARN_THRESHOLD) {
		const ok = await $swal.confirm({
			title: 'Toplu Üretim',
			html: `<strong>${selectedPoses.size}</strong> poz seçili — her biri birkaç dakika sürebilir, toplamda uzun bir kuyruk oluşacak. Devam edilsin mi?`,
			confirmText: 'Kuyruğa Al',
			cancelText: 'Vazgeç',
		})
		if (!ok) return
	}

	busy.value = true
	router.post('/creative/tryon', {
		product_id: selectedProduct.value,
		mannequin_id: selectedMannequin.value,
		pose_ids: Array.from(selectedPoses),
		garment_image: garmentFile.value,
		garment_details: [...fixedDetailSlots, ...customDetailSlots]
			.filter(d => d.file)
			.map(d => ({ image: d.file, label: d.label?.trim() || null, detected_labels: d.detectedLabels || [] })),
	}, {
		preserveScroll: true,
		onSuccess: () => {
			selectedPoses.clear()
			clearGarmentFile()
			clearGarmentDetails()
			refetchProductResultsIfActive()
			currentStep.value = 4
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Başlatılamadı', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function setCover(r) {
	router.post(`/creative/tryon/${r.id}/cover`, {}, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'Yapılamadı', message: Object.values(errs)[0] || 'Hata.' }),
		onFinish: () => refetchProductResultsIfActive(),
	})
}

async function destroyResult(r) {
	const ok = await $swal.dangerConfirm({
		title: 'Sonucu Sil',
		html: `<strong>${r.product_name}</strong> için üretilen ürün görseli silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/creative/tryon/${r.id}`, {
		preserveScroll: true,
		onFinish: () => refetchProductResultsIfActive(),
	})
}

function refresh() {
	router.reload({ only: ['results'] })
	refetchProductResultsIfActive()
}

// Büyük önizleme (lightbox) + zoom/pan
const preview = ref(null)
const zoom = reactive({ scale: 1, x: 0, y: 0, dragging: false })
const MIN_SCALE = 1
const MAX_SCALE = 5

function resetZoom() {
	zoom.scale = 1
	zoom.x = 0
	zoom.y = 0
}

function openPreview(r) {
	if (!r.image_url) return
	preview.value = r
	resetZoom()
}

function closePreview() { preview.value = null }

// img, flex ile wrapper'ın merkezine ortalandığı için CSS transform-origin (img'in kendi
// merkezi) de wrapper merkeziyle çakışır — imleç noktasını sabit tutmak için scale
// değişimini bu merkeze göre telafi etmemiz gerekiyor, yoksa zoom her zaman görselin
// ortasına doğru kayar.
function zoomAt(cx, cy, rect, targetScale) {
	const centerX = rect.width / 2
	const centerY = rect.height / 2
	const prev = zoom.scale
	const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, targetScale))
	const k = next / prev
	zoom.x = (1 - k) * (cx - centerX) + k * zoom.x
	zoom.y = (1 - k) * (cy - centerY) + k * zoom.y
	zoom.scale = next
	if (next === MIN_SCALE) { zoom.x = 0; zoom.y = 0 }
}

function onWheel(e) {
	const rect = e.currentTarget.getBoundingClientRect()
	const cx = e.clientX - rect.left
	const cy = e.clientY - rect.top
	zoomAt(cx, cy, rect, zoom.scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15))
}

let dragStart = null
function onPointerDown(e) {
	if (zoom.scale <= 1) return
	e.preventDefault()
	zoom.dragging = true
	dragStart = { x: e.clientX - zoom.x, y: e.clientY - zoom.y }
	e.currentTarget.setPointerCapture(e.pointerId)
}
function onPointerMove(e) {
	if (!zoom.dragging || !dragStart) return
	zoom.x = e.clientX - dragStart.x
	zoom.y = e.clientY - dragStart.y
}
function onPointerUp(e) {
	zoom.dragging = false
	dragStart = null
	if (e.currentTarget?.hasPointerCapture?.(e.pointerId)) e.currentTarget.releasePointerCapture(e.pointerId)
}

function onDblClick(e) {
	if (zoom.scale > 1) {
		resetZoom()
		return
	}
	const rect = e.currentTarget.getBoundingClientRect()
	zoomAt(e.clientX - rect.left, e.clientY - rect.top, rect, 2)
}

// Mobil pinch-zoom / tek parmak sürükleme
let touchStartDist = null
let touchStartScale = 1
let touchStartPos = null
function touchDist(touches) {
	const [a, b] = touches
	return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY)
}
function onTouchStart(e) {
	if (e.touches.length === 2) {
		touchStartDist = touchDist(e.touches)
		touchStartScale = zoom.scale
	} else if (e.touches.length === 1 && zoom.scale > 1) {
		touchStartPos = { x: e.touches[0].clientX - zoom.x, y: e.touches[0].clientY - zoom.y }
	}
}
function onTouchMove(e) {
	if (e.touches.length === 2 && touchStartDist) {
		const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, touchStartScale * (touchDist(e.touches) / touchStartDist)))
		zoom.scale = next
		if (next === MIN_SCALE) { zoom.x = 0; zoom.y = 0 }
	} else if (e.touches.length === 1 && touchStartPos) {
		zoom.x = e.touches[0].clientX - touchStartPos.x
		zoom.y = e.touches[0].clientY - touchStartPos.y
	}
}

function onKey(e) { if (e.key === 'Escape') closePreview() }

let timer = null
onMounted(() => {
	timer = setInterval(() => { if (hasPending.value) refresh() }, 5000)
	window.addEventListener('keydown', onKey)
})
onUnmounted(() => {
	if (timer) clearInterval(timer)
	clearTimeout(searchTimer)
	window.removeEventListener('keydown', onKey)
})
</script>

<style scoped>
.empty-block { text-align: center; color: var(--color-muted); padding: 28px 0; font-style: italic; font-size: 13px; }
.hint { font-size: 12px; color: var(--color-muted); }
.inline-link { color: var(--color-primary); font-weight: 600; }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; }

/* Seçim bağlamı çubuğu */
/* Adım göstergesi (Progress with Steps) */
.wizard-steps { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; background: var(--color-surface); border: 1px solid var(--color-outline-variant); border-radius: 12px; padding: 10px 16px; margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.wizard-step { display: flex; align-items: center; gap: 7px; border: none; background: none; font-family: inherit; padding: 4px 8px; border-radius: 8px; cursor: pointer; }
.wizard-step:not(:disabled):hover { background: var(--color-surface-container-low); }
.wizard-step:disabled { cursor: not-allowed; opacity: .45; }
.wizard-step-num { display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid var(--color-outline-variant); font-size: 11px; font-weight: 700; color: var(--color-muted); flex-shrink: 0; }
.wizard-step.current .wizard-step-num { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
.wizard-step.done .wizard-step-num { border-color: var(--color-success); background: var(--color-success); color: #fff; }
.wizard-step-label { font-size: 12.5px; font-weight: 600; color: var(--color-muted); white-space: nowrap; }
.wizard-step.current .wizard-step-label { color: var(--color-ink); }
.wizard-step-line { flex: 1; min-width: 12px; height: 2px; background: var(--color-outline-variant); border-radius: 2px; }
.wizard-step-line.done { background: var(--color-success); }

/* Adım içi ileri/geri navigasyonu */
.step-nav { display: flex; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--color-outline-variant); }
.step-nav-spacer { flex: 1; }

/* Adım 5 seçim özeti */
.review-summary { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--color-outline-variant); }
.review-summary-item { display: flex; flex-direction: column; gap: 2px; }
.review-summary-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--color-muted); }
.review-summary-value { font-size: 14px; font-weight: 600; color: var(--color-ink); }

/* Ürün arama */
.product-search { display: flex; align-items: center; gap: 6px; background: var(--color-surface-container-low); border: 1px solid var(--color-outline-variant); border-radius: 8px; padding: 5px 10px; min-width: 220px; }
.product-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; width: 100%; color: var(--color-ink); }
.search-icon { color: var(--color-muted); flex-shrink: 0; }
.search-spin { color: var(--color-primary); flex-shrink: 0; animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }

/* Filtre çipleri (ürün-fotoğrafsız / sonuç durumu-onay) */
.filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
.filter-sep { width: 1px; align-self: stretch; background: var(--color-outline-variant); margin: 0 2px; }
.filter-chip { border: 1.5px solid var(--color-outline-variant); background: var(--color-surface); color: var(--color-muted); font-family: inherit; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 20px; cursor: pointer; }
.filter-chip:hover { border-color: var(--color-primary); color: var(--color-ink); }
.filter-chip.active { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }

/* Ürünler */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
.product-card { position: relative; text-align: center; padding: 12px 10px; border: 2px solid var(--color-outline-variant); border-radius: 12px; background: var(--color-surface); cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 8px; font-family: inherit; content-visibility: auto; contain-intrinsic-size: 0 100px; }
.product-card.selected { border-color: var(--color-primary); background: var(--color-primary-soft); }
.no-garment-badge { position: absolute; top: 6px; right: 6px; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 20px; background: var(--color-danger); color: #fff; }
.product-thumb { width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, var(--color-primary-soft), var(--color-surface-container-high)); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.product-thumb img { width: 100%; height: 100%; object-fit: cover; }
.product-thumb .no-preview { color: var(--color-primary); font-size: 20px; }
.product-name { font-size: 12px; font-weight: 600; color: var(--color-ink); line-height: 1.3; }

/* Ürün görseli yükleme */
.garment-upload { }
.garment-upload-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; margin-bottom: 10px; font-size: 13px; color: var(--color-ink); }
.required-mark { color: var(--color-warning); font-weight: 700; margin-left: 2px; }
.garment-upload-head .hint-warning { color: var(--color-warning); }
.garment-upload-body { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.garment-select-card { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; width: 160px; height: 160px; padding: 0; border: 2px dashed var(--color-outline-variant); border-radius: 14px; background: var(--color-surface); color: var(--color-muted); font-family: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; overflow: hidden; }
.garment-select-card:hover { border-color: var(--color-primary); color: var(--color-primary); }
.garment-select-card.has-image { border-style: solid; border-color: var(--color-primary); cursor: default; }
.garment-select-card.has-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
.garment-select-card.sm { width: 108px; height: 108px; border-radius: 12px; }
.garment-select-card.required-empty { border-color: var(--color-warning); }
.garment-select-card.required-empty:hover { border-color: var(--color-warning); color: var(--color-warning); }
.garment-remove-badge { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border: none; border-radius: 8px; background: rgba(26,26,46,.6); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.garment-remove-badge:hover { background: rgba(26,26,46,.85); }
.upload-hint { font-size: 11px; color: var(--color-muted); }

/* Detay görselleri */
.garment-details { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-outline-variant); }
.detail-fixed-grid { display: flex; flex-wrap: wrap; gap: 16px; margin: 12px 0 16px; }
.detail-fixed-item { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.detail-fixed-label { font-size: 11.5px; font-weight: 600; color: var(--color-muted); text-align: center; max-width: 108px; }
.preset-chip { border: 1.5px dashed var(--color-outline-variant); background: var(--color-surface); color: var(--color-muted); font-family: inherit; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 20px; cursor: pointer; }
.preset-chip:hover { border-color: var(--color-primary); color: var(--color-primary); }
.detail-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
.detail-item { display: flex; align-items: center; gap: 10px; }
.detail-thumb { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; border: 2px dashed var(--color-outline-variant); border-radius: 10px; color: var(--color-muted); font-size: 10px; font-weight: 600; cursor: pointer; text-align: center; flex-shrink: 0; overflow: hidden; }
.detail-thumb:hover { border-color: var(--color-primary); color: var(--color-primary); }
.detail-thumb.has-image { border-style: solid; cursor: default; padding: 0; }
.detail-thumb.has-image img { width: 100%; height: 100%; object-fit: cover; }
.detail-label-wrap { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.detail-label-input { width: 100%; height: 38px; padding: 0 12px; border: 1.5px solid var(--color-outline-variant); border-radius: 8px; font-family: inherit; font-size: 13px; color: var(--color-ink); background: var(--color-surface-container-low); outline: none; }
.detail-label-input:focus { border-color: var(--color-primary); background: var(--color-surface); }
.detail-label-input.is-suggested { font-style: italic; color: var(--color-muted); border-color: color-mix(in srgb, var(--color-primary) 40%, transparent); }
.detail-label-hint { font-size: 11px; color: var(--color-muted); padding-left: 2px; }

/* Mankenler */
.mannequin-row { display: flex; flex-wrap: wrap; gap: 10px; }
.mannequin-pill { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; border: 2px solid var(--color-outline-variant); border-radius: 30px; background: var(--color-surface); cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; color: var(--color-ink); }
.mannequin-pill.selected { border-color: var(--color-primary); background: var(--color-primary-soft); }
.mannequin-pill .pill-thumb { width: 30px; height: 30px; border-radius: 50%; overflow: hidden; background: var(--color-surface-container-low); flex-shrink: 0; }
.mannequin-pill .pill-thumb img { width: 100%; height: 100%; object-fit: cover; }

.pose-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
.pose-pick-card { position: relative; border: 2px solid var(--color-outline-variant); border-radius: 10px; overflow: hidden; background: var(--color-surface); cursor: pointer; padding: 0; font-family: inherit; content-visibility: auto; contain-intrinsic-size: 0 165px; }
.pose-pick-card.selected { border-color: var(--color-primary); }
.pose-pick-card img { width: 100%; aspect-ratio: 3/4; object-fit: cover; display: block; }
.pose-pick-label { display: block; font-size: 11px; font-weight: 600; color: var(--color-ink); padding: 5px 6px; }
.pose-check { position: absolute; top: 6px; right: 6px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, transparent); background: rgba(255,255,255,.7); }
.pose-check.on { background: var(--color-primary); box-shadow: 0 0 0 1px var(--color-primary); }
.pose-approved-flag { position: absolute; top: 6px; left: 6px; width: 20px; height: 20px; border-radius: 50%; background: var(--color-success); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.2); }

/* Aksiyon */
.action-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 4px; }
.selection-summary { font-size: 13px; color: var(--color-muted); }
.selection-summary strong { color: var(--color-ink); }
.missing-hint { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: var(--color-warning); margin-top: 4px; }

/* Sonuçlar */
.result-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
.result-card { border: 1px solid var(--color-outline-variant); border-radius: 12px; overflow: hidden; background: var(--color-surface); content-visibility: auto; contain-intrinsic-size: 0 260px; }
.result-thumb { position: relative; aspect-ratio: 3/4; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; }
.result-thumb img { width: 100%; height: 100%; object-fit: cover; }
.result-thumb img.clickable { cursor: zoom-in; }
.zoom-badge { position: absolute; bottom: 6px; right: 6px; width: 26px; height: 26px; border: none; border-radius: 8px; background: rgba(26,26,46,.6); color: #fff; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; z-index: 1; }
.result-card:hover .zoom-badge { opacity: 1; }
.zoom-badge:hover { background: rgba(26,26,46,.85); }
.no-preview { font-size: 11px; color: var(--color-muted); font-weight: 700; }
.status-badge { position: absolute; top: 6px; left: 6px; }
.cover-badge { position: absolute; top: 6px; right: 6px; }
.review-badge { position: absolute; bottom: 6px; left: 6px; }
.result-meta { padding: 8px 10px; }
.result-product { font-size: 12px; font-weight: 700; color: var(--color-ink); display: block; }
.result-sub { font-size: 11px; color: var(--color-muted); }
.result-creator { font-size: 10px; color: var(--color-muted); display: block; }
.result-model { font-size: 10px; color: var(--color-muted); display: block; font-family: monospace; }
.review-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.result-error { display: flex; align-items: center; gap: 4px; font-size: 10px; color: var(--color-danger); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.review-actions { display: flex; gap: 6px; margin-top: 6px; }
.result-actions { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.link-btn.danger { color: var(--color-danger); margin-left: auto; }
.is-cover-note { font-size: 11px; color: var(--color-success); font-weight: 600; }
.chat-link { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 12px; margin: 0 12px 12px; background: var(--color-primary-soft); color: var(--color-primary-hover); border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: color-mix(in srgb, var(--color-primary-soft) 60%, var(--color-primary) 10%); }

/* Lightbox */
.lightbox { position: fixed; inset: 0; z-index: 9999; background: rgba(15,15,25,.82); display: flex; align-items: center; justify-content: center; padding: 32px; backdrop-filter: blur(3px); }
.lb-box { display: flex; gap: 0; max-width: 1100px; max-height: 90vh; background: var(--color-surface); border-radius: 16px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,.4); position: relative; }
.lb-close { position: absolute; top: 12px; right: 12px; z-index: 2; width: 34px; height: 34px; border: none; border-radius: 50%; background: rgba(255,255,255,.9); color: var(--color-ink); font-size: 16px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.lb-close:hover { background: #fff; }
.lb-img-wrap { background: #11111b repeating-conic-gradient(#1a1a26 0% 25%, #15151f 0% 50%) 0 / 24px 24px; display: flex; align-items: center; justify-content: center; min-width: 0; overflow: hidden; cursor: zoom-in; touch-action: none; }
.lb-img-wrap.zoomed { cursor: grab; }
.lb-img-wrap.dragging { cursor: grabbing; }
.lb-img-wrap img { max-width: 62vw; max-height: 90vh; object-fit: contain; display: block; transition: transform .08s; will-change: transform; -webkit-user-drag: none; user-select: none; }
.lb-img-wrap.dragging img { transition: none; }
.lb-side { width: 300px; flex-shrink: 0; padding: 22px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.lb-title { font-size: 17px; font-weight: 700; color: var(--color-ink); }
.lb-tags-meta { display: flex; flex-wrap: wrap; gap: 6px; }
.lb-hint { font-size: 11px; color: var(--color-muted); line-height: 1.4; }
.lb-download { margin-top: auto; justify-content: center; }
@media (max-width: 820px) { .lb-box { flex-direction: column; } .lb-img-wrap img { max-width: 86vw; max-height: 50vh; } .lb-side { width: auto; } }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.wizard-steps { position: static; }
	.wizard-step-label { display: none; }

	.product-search { min-width: 0; width: 100%; }

	.product-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
	.pose-grid { grid-template-columns: repeat(auto-fill, minmax(95px, 1fr)); gap: 8px; }
	.result-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }

	.action-bar { flex-wrap: wrap; }
	.selection-summary { width: 100%; }
	.action-bar .btn { width: 100%; justify-content: center; }
}
</style>
