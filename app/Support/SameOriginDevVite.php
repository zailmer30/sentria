<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

/**
 * In local+debug, emit root-relative Vite URLs so the browser loads HMR
 * assets from the same origin as Laravel (which may be a forwarded port
 * like :8001) instead of talking to :5173 directly.
 */
class SameOriginDevVite extends Vite
{
    /** @param  string  $asset */
    protected function hotAsset($asset): string
    {
        if (! app()->isProduction() && (bool) config('app.debug')) {
            return '/'.ltrim($asset, '/');
        }

        return parent::hotAsset($asset);
    }
}
