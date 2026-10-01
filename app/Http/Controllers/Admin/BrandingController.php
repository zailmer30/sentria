<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatePattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Services\Branding\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function edit(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('settings.viewAny'), 403);

        $snapshot = $this->branding->snapshot();

        return Inertia::render('Admin/Settings/Branding', [
            'branding' => [
                'name' => $snapshot->name,
                'short_name' => $snapshot->shortName,
                'locality' => $snapshot->locality,
                'accent' => $snapshot->accent,
                'plate' => $snapshot->plate,
                'plate_pattern' => $snapshot->platePattern->value,
                'logo_url' => $snapshot->logoUrl,
            ],
            'defaults' => $this->branding->defaults(),
            'brand_css' => $this->branding->css(),
            'can' => [
                'update' => $user->can('settings.update'),
            ],
        ]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->branding->save(
            name: $validated['name'],
            shortName: $validated['short_name'],
            locality: $validated['locality'],
            accent: $validated['accent'],
            logo: $request->file('logo'),
            removeLogo: $request->boolean('remove_logo'),
            actor: $this->requireUser($request),
            plate: $validated['plate'] ?? null,
            platePattern: isset($validated['plate_pattern']) ? PlatePattern::from($validated['plate_pattern']) : null,
        );

        return back()->with('success', 'settings.branding.saved');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('settings.update'), 403);

        $this->branding->resetToDefaults($user);

        return back()->with('success', 'settings.branding.reset_done');
    }
}
