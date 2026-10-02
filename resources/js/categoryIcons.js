import { lucideIcon, hasLucide, iconPickerOptions } from '@/lucideIcons.js'

// Ürün kategorisi ikonları (product_categories.icon) ortak ikon kaydındaki adla saklanır
// ('shirt', 'lab:dress', 'oz:cocuk-etek' — bkz. lucideIcons.js). Seçici önce giyim/aksesuar odaklı
// aşağıdaki listeyi, ardından bizim ikonları ve tüm seti gösterir (aranabilir).
// Kategoriler sayfası (tablo) ve CategoryFormDrawer (seçici) bu dosyayı kullanır.
export const DEFAULT_CATEGORY_ICON = 'package'

// ad → Türkçe etiket (seçicide tooltip + arama: "ayakkabı", "çanta" …)
const FEATURED = {
	// genel
	package: 'Genel / Ürün',
	layers: 'Koleksiyon / Takım',
	tag: 'Etiket',
	// giyim
	shirt: 'Tişört',
	'lab:shirt-long-sleeve': 'Uzun kollu / Sweatshirt',
	'lab:shirt-folded-buttons': 'Gömlek',
	'lab:shirt-t-v-neck': 'V yaka tişört',
	'lab:sweater': 'Kazak / Triko',
	'lab:vest': 'Yelek',
	'lab:jacket': 'Ceket / Mont / Dış giyim',
	'lab:trousers': 'Pantolon / Alt giyim',
	'oz:kot': 'Kot pantolon',
	'oz:esofman-alti': 'Eşofman altı',
	'oz:tayt': 'Tayt',
	'lab:shorts': 'Şort',
	'oz:cocuk-pantolon': 'Çocuk pantolon / Çocuk alt giyim',
	'oz:cocuk-sort': 'Çocuk şort',
	'oz:salopet': 'Salopet',
	'lab:skirt': 'Etek',
	'oz:etek': 'Etek (fırfırlı)',
	'oz:cocuk-etek': 'Çocuk etek',
	'lab:dress': 'Elbise',
	'lab:lingerie': 'İç giyim',
	'lab:bra-sports': 'Spor sütyeni',
	'moon': 'Pijama / Ev giyim',
	dumbbell: 'Spor giyim',
	// kitle
	venus: 'Kadın',
	mars: 'Erkek',
	'venus-and-mars': 'Unisex',
	baby: 'Bebek / Çocuk',
	'lab:stroller': 'Bebek ürünleri',
	// ayakkabı & aksesuar
	footprints: 'Ayakkabı',
	'lab:sneaker': 'Spor ayakkabı',
	'lab:socks': 'Çorap',
	'lab:tie': 'Kravat',
	'lab:tie-bow': 'Papyon',
	'lab:scarf': 'Atkı / Şal',
	'lab:belt': 'Kemer',
	'lab:hat-beanie': 'Bere',
	'lab:hat-baseball': 'Şapka',
	'shopping-bag': 'Çanta',
	handbag: 'El çantası',
	backpack: 'Sırt çantası',
	glasses: 'Gözlük',
	watch: 'Saat',
	gem: 'Takı / Mücevher',
	'lab:gem-ring': 'Yüzük',
	// sezon
	sun: 'Yaz sezonu',
	snowflake: 'Kış sezonu',
	leaf: 'Sonbahar sezonu',
	flower: 'İlkbahar sezonu',
	umbrella: 'Yağmurluk',
	'mountain-snow': 'Outdoor / Kayak',
	// kampanya & üretim
	sparkles: 'Yeni gelenler',
	star: 'Öne çıkan',
	percent: 'İndirim / Outlet',
	gift: 'Hediye',
	scissors: 'Kumaş / Terzilik',
	'lab:reel-thread': 'İplik / Tuhafiye',
	'lab:coat-hanger': 'Askı',
	ruler: 'Ölçü / Beden',
	palette: 'Renk / Desen',
	'bed-double': 'Ev tekstili',
}

export const categoryIconOptions = iconPickerOptions(Object.keys(FEATURED), FEATURED)

export const categoryIconLabel = (name) => categoryIconOptions.find((o) => o.value === name)?.label ?? name

// Kayıtlı değer kayıtta yoksa (eski emoji kayıtları, silinmiş özel ikon) varsayılan ikona düşer
export const categoryIconName = (value) => (value && hasLucide(value) ? value : DEFAULT_CATEGORY_ICON)

// Yüklenirken boş çizilir (yanlış ikon yanıp sönmesin)
const Blank = () => null

// OzIcon :name / OzIconPicker :resolve için bileşen
export function ozIconForCategory(value) {
	return lucideIcon(categoryIconName(value)) ?? Blank
}
