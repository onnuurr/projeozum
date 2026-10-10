import { computed, unref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Modülün tek ayar sayfası (page.props.moduleSettings, backend'de yetkiye göre süzülür):
// modülün kök ya da alt menü URL'lerinden biri kaydın menuRoots'unda geçiyorsa o kayıt.
// Masaüstünde OzModuleTabs, mobilde OzMobileNav çark olarak gösterir.
export function useModuleSettings(moduleRef) {
  const page = usePage()

  const settings = computed(() => {
    const m = unref(moduleRef)
    if (!m) return null
    const urls = [m.to, ...(m.children ?? []).map((c) => c.to)].filter(Boolean)
    return (page.props.moduleSettings ?? []).find((s) => s.menuRoots.some((r) => urls.includes(r))) ?? null
  })

  const settingsActive = computed(() => {
    const path = page.url.split('?')[0]
    return !!settings.value && (path === settings.value.url || path.startsWith(`${settings.value.url}/`))
  })

  return { settings, settingsActive }
}
