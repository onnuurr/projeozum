import { ozFold } from './ozText'

// OzTable filtre mantığı (bileşenden bağımsız, sunucu tarafı eşlemesi için de okunabilir).
// Sütun filtre tipleri:
//   { type: 'select', multiple?, options?: [{ value, label }] | ['A'], placeholder?, label? }
//       options verilmezse satırlardaki benzersiz değerlerden üretilir
//   { type: 'text', placeholder? }                         içerir (TR karakter duyarsız)
//   { type: 'range', suffix?, format?: (n) => string }     en az / en çok
//   { type: 'date' }                                       ISO tarih (yyyy-mm-dd) aralığı
// Sütunun filterValue(row)'u varsa karşılaştırma ona göre yapılır, yoksa row[key].

export function filterDefault(filter) {
  switch (filter.type) {
    case 'select': return filter.multiple ? [] : ''
    case 'range': return { min: '', max: '' }
    case 'date': return { from: '', to: '' }
    default: return ''
  }
}

export function isFilterActive(filter, v) {
  if (v == null) return false
  switch (filter.type) {
    case 'select': return Array.isArray(v) ? v.length > 0 : v !== ''
    case 'range': return v.min !== '' || v.max !== ''
    case 'date': return !!(v.from || v.to)
    default: return String(v).trim() !== ''
  }
}

const toTime = (x) => (x instanceof Date ? x.getTime() : new Date(x).getTime())

export function matchesFilter(filter, v, cell) {
  switch (filter.type) {
    case 'select': return Array.isArray(v) ? v.includes(cell) : cell === v
    case 'text': return ozFold(cell).includes(ozFold(String(v).trim()))
    case 'range': {
      const n = Number(cell)
      return (v.min === '' || n >= Number(v.min)) && (v.max === '' || n <= Number(v.max))
    }
    case 'date': {
      if (cell == null || cell === '') return false
      const t = toTime(cell)
      // bitiş günü dahil
      return (!v.from || t >= toTime(v.from)) && (!v.to || t < toTime(v.to) + 86400000)
    }
    default: return true
  }
}

export function normalizeOptions(filter, values) {
  if (filter.options) return filter.options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) }))
  return [...new Set(values.filter((x) => x != null && x !== ''))]
    .sort((a, b) => String(a).localeCompare(String(b), 'tr', { numeric: true }))
    .map((x) => ({ value: x, label: filter.optionLabel ? filter.optionLabel(x) : String(x) }))
}
