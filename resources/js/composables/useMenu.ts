import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export interface MenuItem {
    id: number | string;
    name: string;
    icon?: string;
    route_name?: string | null;
    url?: string;
    subItems?: MenuItem[];
    children?: MenuItem[];
}

/**
 * HandleInertiaRequests::share() üzerinden gelen menüler için
 * paylaşılan aktiflik tespiti + ilk geçerli URL çözümleme.
 *
 * Hem Sidebar (üst seviye menüler) hem Header (aktif menünün subItem'ları)
 * tarafından kullanılır.
 */
export function useMenu() {
    const page = usePage();

    const menus = computed<MenuItem[]>(
        () => ((page.props as any).menus as MenuItem[]) || [],
    );

    const currentRoute = computed<string>(
        () => ((page.props as any).route_name as string) || "",
    );

    const currentUrl = computed<string>(
        () => page.url.split("?")[0].replace(/\/$/, "") || "/",
    );

    const isMenuActive = (item: MenuItem): boolean => {
        if (item.id === "dashboard") {
            return currentUrl.value === "/" || currentUrl.value === "/dashboard";
        }
        if (item.id === "profile") {
            return currentUrl.value.startsWith("/profile");
        }
        if (item.route_name && currentRoute.value) {
            const itemPrefix = item.route_name.split(".")[0];
            const currentPrefix = currentRoute.value.split(".")[0];
            if (itemPrefix === currentPrefix) return true;
        }
        const itemUrl = item.url?.split("?")[0].replace(/\/$/, "");
        if (itemUrl && itemUrl !== "" && itemUrl !== "#") {
            if (
                currentUrl.value === itemUrl ||
                currentUrl.value.startsWith(itemUrl + "/")
            )
                return true;
        }
        const children = item.subItems || item.children || [];
        if (children.length > 0) {
            return children.some((child) => isMenuActive(child));
        }
        return false;
    };

    const resolveTargetUrl = (item: MenuItem): string | null => {
        if (item.url && item.url !== "#") return item.url;
        const children = item.subItems || item.children || [];
        for (const c of children) {
            const u = resolveTargetUrl(c);
            if (u) return u;
        }
        return null;
    };

    /** Şu an aktif olan kök menü (subItem'lar header'da gösterilir). */
    const activeRootMenu = computed<MenuItem | null>(
        () => menus.value.find((m) => isMenuActive(m)) ?? null,
    );

    /** Aktif kök menünün altındaki menüler (Header için). */
    const activeSubItems = computed<MenuItem[]>(() => {
        const m = activeRootMenu.value;
        if (!m) return [];
        return (m.subItems || m.children || []) as MenuItem[];
    });

    return {
        menus,
        currentRoute,
        currentUrl,
        isMenuActive,
        resolveTargetUrl,
        activeRootMenu,
        activeSubItems,
    };
}
