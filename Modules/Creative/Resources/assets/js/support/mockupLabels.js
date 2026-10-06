// Mokap ayarlarının Türkçe etiketleri (backend anahtarları: MockupOutfit::REGIONS/LENGTHS,
// StylistOption::KINDS, MockupPromptBuilder::PRODUCT_FEATURES, poz view).
export const REGIONS = { bottom: 'Alt', top: 'Üst', one_piece: 'Tek parça', set: 'Takım' }
export const LENGTHS = { mini: 'Mini', knee: 'Diz', midi: 'Midi', maxi: 'Maksi' }
export const KINDS = { top: 'Üst', bottom: 'Alt', footwear: 'Ayakkabı', socks: 'Çorap', accessory: 'Aksesuar', color: 'Renk' }
export const GENDERS = { girl: 'Kız', boy: 'Erkek' }
export const STYLES = { casual: 'Günlük', sporty: 'Spor', dressy: 'Şık', romantic: 'Romantik' }
export const SEASONS = { warm: 'Yaz', cool: 'Kış' }
export const PLACEMENTS = { hair: 'Saç', wrist: 'Bilek', chest: 'Göğüs' }
export const VIEWS = { front: 'Önden', side: 'Yandan', back: 'Arkadan', three_quarter: 'Üç-çeyrek' }
export const FEATURES = { pockets: 'Cep', collar: 'Yaka', buttons: 'Düğme', zipper: 'Fermuar', belt_loops: 'Kemer köprüsü', ruffles: 'Fırfır', print: 'Baskı' }

/** { key: label } → OzSelect/OzCombobox seçenekleri */
export const toOptions = (map) => Object.entries(map).map(([value, label]) => ({ value, label }))
