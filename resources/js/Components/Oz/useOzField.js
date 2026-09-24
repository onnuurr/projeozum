import { computed, inject } from 'vue'

// Oz form bileşenlerinin ortak bağlamı: OzField içindeyse id/aria/hata oradan gelir,
// değilse bileşenin kendi prop'ları kullanılır.
export function useOzField(props) {
  const field = inject('ozField', null)

  return {
    inputId: computed(() => props.id || field?.id),
    invalid: computed(() => props.invalid || !!field?.invalid.value),
    required: computed(() => props.required || !!field?.required.value),
    describedBy: computed(() => field?.describedBy.value),
  }
}
