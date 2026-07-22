import { usePage } from '@inertiajs/vue3'

export function useCan() {
  const page = usePage()

  function can(permission) {
    return page.props.auth?.permissions?.includes(permission) ?? false
  }

  function canAny(permissions) {
    return permissions.some((p) => can(p))
  }

  function canAll(permissions) {
    return permissions.every((p) => can(p))
  }

  return { can, canAny, canAll }
}
