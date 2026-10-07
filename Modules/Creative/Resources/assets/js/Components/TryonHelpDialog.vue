<script setup>
import { ref } from 'vue'
import { OzModal } from '@/Components/Oz'

// Ürün Giydirme sayfasının "Nasıl çalışır?" penceresi: mokap akışı, iyi sonuç için gereken ürün
// verisi, ret → yeniden üretim ve maliyet. Akış değişirse (MockupPipelineService, MockupRerunService)
// buradaki metin de güncellenir.
const open = defineModel('open', { type: Boolean, default: false })

const SECTIONS = [
	{ key: 'flow', label: 'Akış' },
	{ key: 'data', label: 'İyi sonuç için' },
	{ key: 'review', label: 'Ret ve yeniden üretim' },
	{ key: 'cost', label: 'Maliyet ve süre' },
]
const tab = ref('flow')
</script>

<template>
	<OzModal v-model:open="open" title="Ürün Giydirme nasıl çalışır?" subtitle="Mokap akışının özeti" icon="info" tone="info" size="lg">
		<nav class="help-tabs">
			<button v-for="s in SECTIONS" :key="s.key" type="button" class="help-tab" :class="{ active: tab === s.key }" @click="tab = s.key">{{ s.label }}</button>
		</nav>

		<div v-if="tab === 'flow'" class="help-body">
			<p>İki yol var. <strong>Mokap</strong> (önerilen) tamamen otomatiktir: ürünü seç, <em>Mokap üret</em>'e bas, sonuçlar onaya düşer.
				<strong>Klasik giydirme</strong> sihirbazla (görsel → manken → poz) tek tek elle yapılır.</p>
			<ol class="help-steps">
				<li><strong>Ürün türü ve boy.</strong> Bölge (alt, üst, takım, tek parça, dış giyim) ürünün kategorisinden gelir
					(Creative Ayarları › Kategoriler). Boy önce ürünün <em>Boy</em> özelliğinden, yoksa kategoriden okunur;
					ikisi de yoksa yapay zekâ tahmin eder ve kartta uyarı çıkar.</li>
				<li><strong>Beden grupları.</strong> Varyantlardaki yaş bedenleri en yakın yaştaki mankenlere bölünür. Her grup kendi
					mankeniyle üretilir, kombin bütün gruplarda aynıdır. Mağazada beden seçilince o grubun görselleri gösterilir.</li>
				<li><strong>Stilist.</strong> Ürün fotoğraflarına bakıp hazır listeden tamamlayıcı parçaları (ayakkabı, çorap, aksesuar,
					gerekirse alt/üst) ve pozları seçer. Kural motoru bu seçimi yaşa, cinsiyete ve ürüne göre denetler.</li>
				<li><strong>Baz görseller.</strong> Manken, seçilen kombinle ve gri yer tutucu kıyafetle 3 pozda çekilir.</li>
				<li><strong>Giydirme.</strong> Ürünün her rengi, o rengin yalın fotoğraflarıyla bazların üzerine giydirilir. Ardından
					renk kilidi uygulanır ve otomatik kontrol yapılır (kartta ✓ geçti, ⚠ uyarı, ✕ kaldı).</li>
				<li><strong>Onay.</strong> Onaylanan görsel ürün galerisine beden ve renk bilgisiyle eklenir; Bagisto'ya da öyle gider.
					Onaylı tam boy görselden yakın planlar üretilebilir.</li>
			</ol>
		</div>

		<div v-else-if="tab === 'data'" class="help-body">
			<p>Hataların çoğu ürün verisinden gelir. Üretmeden önce şunları kontrol et:</p>
			<ul class="help-list">
				<li><strong>Yalın fotoğraf:</strong> Ürünün mankensiz, düz zeminde fotoğrafı olmalı.</li>
				<li><strong>Renk etiketi:</strong> Her fotoğrafa doğru rengi ver (ürün formu › Görseller). Fotoğrafı olmayan renk için
					mokap üretilmez; o renk genel setle gösterilir.</li>
				<li><strong>Ayrı çekilmiş takım:</strong> Üst ve alt ayrı fotoğraflardaysa <em>ikisine de aynı rengi</em> ver. İkisi
					birlikte gider; farklı renk verilirse sistem onları iki ayrı renk sanar ve eksik parçayı uydurur.</li>
				<li><strong>Boy:</strong> Ürün formu › Özellikler › <em>Boy</em> (ör. Uzun). Girilmezse yapay zekâ tahmin eder; yanlış
					tahmin şort/kapri gibi hatalara yol açar.</li>
				<li><strong>Kategori eşleşmesi:</strong> Ürünün kategorisi Creative Ayarları › Kategoriler'de bir bölgeye bağlı olmalı.</li>
				<li><strong>Mankenler:</strong> Her yaş grubu için onaylı manken olmalı; yoksa en yakın yaştaki kullanılır.</li>
			</ul>
		</div>

		<div v-else-if="tab === 'review'" class="help-body">
			<p>Reddederken seçtiğin ret maddeleri, yeniden üretimin <strong>hangi aşamadan</strong> başlayacağını belirler.
				Birden çok aşama seçildiyse akışta en baştaki geçerli olur. Yazdığın açıklama düzeltme talimatı olarak verilir.</p>
			<table class="help-table">
				<thead><tr><th>Aşama</th><th>Ne yeniden üretilir</th><th>Örnek ret maddeleri</th></tr></thead>
				<tbody>
					<tr><td>Kombin (stilist)</td><td>Ürünün bütün beden gruplarında kombin, bazlar ve giydirmeler</td><td>Kombin ürüne uymuyor, ürün tipi / boy yanlış</td></tr>
					<tr><td>Manken / baz</td><td>Yalnız o beden grubunun bazları ve giydirmeleri</td><td>Saç, göz, ten, duruş / poz, ışık, arka plan</td></tr>
					<tr><td>Giydirme / birleştirme</td><td>Yalnız o görsel</td><td>Renk uyumu, giysi uyumu, kol boyu, el veya ayakkabı bozuk, birleştirme izi</td></tr>
				</tbody>
			</table>
			<p class="help-tip"><strong>Sıra önemli:</strong> Hata veriden geliyorsa (renk etiketi, boy) önce veriyi düzelt, sonra
				karttaki yeniden üret düğmesine bas. Stilist aşamasında tek bir kartta basman yeterli; ürünün bütün setleri yenilenir.</p>
		</div>

		<div v-else class="help-body">
			<ul class="help-list">
				<li><strong>Ücretli:</strong> Bir ürünün mokapı yaklaşık 0,22 $; tek bir giydirmeyi yeniden üretmek yaklaşık 0,04 $.
					Tahmin, başlatmadan önce onay penceresinde gösterilir.</li>
				<li><strong>Süre:</strong> Görseller Batch API ile yarı fiyatına üretilir, sonuçlar dakikalar içinde, bazen daha geç gelir.
					Bu sırada kartlar <em>Sırada / Üretiliyor</em> görünür.</li>
				<li><strong>Günlük limit:</strong> Toplu başlatma Creative Ayarları'ndaki günlük limiti aşmaz.</li>
				<li><strong>Durdurma:</strong> <em>Üretimleri durdur</em> sıradaki ve süren işleri durdurur. Gönderilmemiş istekler ücretlenmez;
					o an işlenmekte olan bir istek yine ücretlenebilir.</li>
				<li><strong>Yakın planlar:</strong> Onaylı tam boy görselden kırpılıp büyütülür; detay kadrajları dışında ek yapay zekâ maliyeti yoktur.</li>
			</ul>
		</div>
	</OzModal>
</template>

<style scoped>
.help-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.help-tab { border: 1.5px solid var(--color-outline-variant); background: var(--color-surface); color: var(--color-muted); font-family: inherit; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 20px; cursor: pointer; }
.help-tab.active { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
.help-body { font-size: 13px; line-height: 1.6; color: var(--color-ink); }
.help-body p { margin: 0 0 10px; }
.help-steps, .help-list { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; }
.help-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px; }
.help-table th, .help-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-outline-variant); vertical-align: top; }
.help-table th { font-size: 11px; color: var(--color-muted); font-weight: 700; }
.help-tip { padding: 8px 10px; border-radius: 8px; background: var(--color-surface-container-low); }
</style>
