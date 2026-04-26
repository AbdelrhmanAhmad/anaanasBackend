<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\HomeSlider;
use Illuminate\Http\Request;

/**
 * Public endpoint that powers the home page hero slider.
 * The full editing experience lives in the Filament back-office.
 */
class HomeSliderController extends Controller
{
    /**
     * GET /api/home/sliders
     *
     * Optional query params:
     *  - country_id: filter to slides for a specific country (null country = global, always returned)
     *  - locale:     'ar' | 'en' — when provided, the response also includes a flattened
     *                `image_desktop` / `image_mobile` pair for that locale so clients
     *                without locale logic can render directly.
     */
    public function index(Request $request)
    {
        $countryId = $request->filled('country_id')
            ? (int) $request->get('country_id')
            : null;

        $locale = strtolower((string) $request->get('locale', ''));
        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = '';
        }

        $now = now();

        $query = HomeSlider::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

        if ($countryId !== null) {
            $query->where(function ($q) use ($countryId) {
                $q->whereNull('country_id')->orWhere('country_id', $countryId);
            });
        }

        $sliders = $query
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $data = $sliders->map(function (HomeSlider $s) use ($locale) {
            $arr = $s->toArray();

            // Flatten for the requested locale so simple consumers can use a single field.
            if ($locale !== '') {
                $arr['image_desktop'] = $locale === 'ar'
                    ? $arr['image_desktop_ar_url'] ?? null
                    : $arr['image_desktop_en_url'] ?? null;
                $arr['image_mobile'] = $locale === 'ar'
                    ? $arr['image_mobile_ar_url'] ?? null
                    : $arr['image_mobile_en_url'] ?? null;
            }

            // Drop raw storage paths — clients should rely on the *_url fields.
            unset(
                $arr['image_desktop_ar'],
                $arr['image_desktop_en'],
                $arr['image_mobile_ar'],
                $arr['image_mobile_en'],
            );

            return $arr;
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
