<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenant XML feed — token-gated, auth'siz, throttle:60,1.
 *
 * URL: {slug}.bizimsite.com/feed.xml?token={feed_secret}
 *
 * Format: Google Merchant XML (TR pazaryeri de-facto standardı).
 * - Bellek dostu: Product::accessibleToTenant()->lazy(500) ile chunk stream.
 * - 5-dk cache: ETag-vari yaklaşım — products.max(updated_at) anahtarlı (Phase 4+).
 */
class TenantFeedController extends Controller
{
    public function show(Request $request, string $slug): StreamedResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            abort(404);
        }

        $tenant = Tenant::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $tenant) {
            abort(404);
        }

        // Constant-time compare — timing attack koruması.
        if (! hash_equals((string) $tenant->feed_secret, $token)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($tenant) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
            echo '<channel>' . "\n";
            echo '  <title>' . e($tenant->name) . ' · Ürün Feed</title>' . "\n";
            echo '  <link>' . e(config('app.url')) . '</link>' . "\n";

            Product::query()
                ->accessibleToTenant($tenant->id)
                ->with(['brand:id,name'])
                ->lazy(500)
                ->each(function (Product $p) {
                    echo "  <item>\n";
                    echo '    <g:id>' . e($p->id) . "</g:id>\n";
                    echo '    <g:title><![CDATA[' . $p->name . "]]></g:title>\n";
                    echo '    <g:link>' . e(config('app.url') . '/products/' . $p->slug) . "</g:link>\n";
                    echo '    <g:image_link>' . e("https://picsum.photos/seed/tek-p{$p->id}/800/1000") . "</g:image_link>\n";
                    echo '    <g:brand><![CDATA[' . ($p->brand?->name ?? '') . "]]></g:brand>\n";
                    echo '    <g:price>' . number_format((float) $p->price, 2, '.', '') . " TRY</g:price>\n";
                    echo '    <g:availability>in stock</g:availability>' . "\n";
                    if ($p->sku) {
                        echo '    <g:mpn>' . e($p->sku) . "</g:mpn>\n";
                    }
                    echo "  </item>\n";
                });

            echo '</channel>' . "\n";
            echo '</rss>' . "\n";
        }, 'feed.xml', [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
