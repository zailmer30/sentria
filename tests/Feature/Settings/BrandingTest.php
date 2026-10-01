<?php

use App\Enums\PlatePattern;
use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Branding\AccentPalette;
use App\Services\Branding\BrandingService;
use App\Services\Branding\PlatePalette;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function brandingActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-branding@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function brandingSealUpload(string $name = 'seal.jpg'): UploadedFile
{
    $image = imagecreatetruecolor(80, 80);
    $paper = imagecolorallocate($image, 255, 255, 255);
    $ink = imagecolorallocate($image, 16, 72, 40);
    imagefilledrectangle($image, 0, 0, 79, 79, $paper);
    imagefilledellipse($image, 40, 40, 36, 36, $ink);
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $path = sys_get_temp_dir().'/sentria-'.$name.'-'.uniqid('', true);
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/jpeg', \UPLOAD_ERR_OK, true);
}

it('redirects guests away from branding settings', function (): void {
    $this->get(route('settings.branding.edit'))->assertRedirect(route('login'));
});

it('forbids the secretariat from viewing or updating branding', function (): void {
    $secretariat = brandingActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->get(route('settings.branding.edit'))
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Bayan',
            'short_name' => 'SB',
            'locality' => 'Demo City',
            'accent' => '#123456',
        ])
        ->assertForbidden();
});

it('lets a system administrator open branding from the settings hub', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->has('areas')
            ->where('areas.0.key', 'branding')
            ->where('areas.0.available', true));

    $this->actingAs($admin)
        ->get(route('settings.branding.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Branding')
            ->where('can.update', true)
            ->where('branding.accent', AccentPalette::DEFAULT));
});

it('saves organization identity and a custom accent', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Bayan ng Demo',
            'short_name' => 'SB',
            'locality' => 'Municipality of Demo',
            'accent' => '#c08a1e',
        ])
        ->assertRedirect();

    $snapshot = app(BrandingService::class)->snapshot();

    expect($snapshot->name)->toBe('Sangguniang Bayan ng Demo')
        ->and($snapshot->shortName)->toBe('SB')
        ->and($snapshot->locality)->toBe('Municipality of Demo')
        ->and($snapshot->accent)->toBe('#C08A1E');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('--color-accent: #C08A1E', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.name', 'Sangguniang Bayan ng Demo')
            ->where('organization.short_name', 'SB')
            ->where('branding.accent', '#C08A1E'));
});

it('saves a custom plate colour and prints it for every plate surface', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Bayan ng Demo',
            'short_name' => 'SB',
            'locality' => 'Municipality of Demo',
            'accent' => '#b0103a',
            'plate' => '7a0f24',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(BrandingService::class)->snapshot()->plate)->toBe('#7A0F24');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('--color-accent: #B0103A', false)
        ->assertSee('--color-floor-plate: #7A0F24', false)
        ->assertSee('--login-navy: #7A0F24', false)
        ->assertInertia(fn (Assert $page) => $page->where('branding.plate', '#7A0F24'));
});

it('keeps the saved plate when a request omits it', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);
    $payload = [
        'name' => 'Sangguniang Bayan ng Demo',
        'short_name' => 'SB',
        'locality' => 'Municipality of Demo',
        'accent' => AccentPalette::DEFAULT,
    ];

    $this->actingAs($admin)->put(route('settings.branding.update'), [...$payload, 'plate' => '#14532D']);
    $this->actingAs($admin)->put(route('settings.branding.update'), $payload)->assertSessionHasNoErrors();

    expect(app(BrandingService::class)->snapshot()->plate)->toBe('#14532D');
});

it('rejects a plate too light for white text', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->from(route('settings.branding.edit'))
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'plate' => '#F5D76E',
        ])
        ->assertRedirect(route('settings.branding.edit'))
        ->assertSessionHasErrors('plate');

    expect(app(BrandingService::class)->snapshot()->plate)->toBe(PlatePalette::DEFAULT);
});

it('prints no brand CSS while both colours are the defaults', function (): void {
    expect(app(BrandingService::class)->css())->toBe('');
});

it('keeps each plate\'s authored texture until a pattern is chosen', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-plate-pattern', false);

    expect(app(BrandingService::class)->snapshot()->platePattern)->toBe(PlatePattern::Authored);
});

it('saves a plate pattern and stamps it on the root element', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Bayan ng Demo',
            'short_name' => 'SB',
            'locality' => 'Municipality of Demo',
            'accent' => AccentPalette::DEFAULT,
            'plate_pattern' => 'dots',
        ])
        ->assertSessionHasNoErrors();

    expect(app(BrandingService::class)->snapshot()->platePattern)->toBe(PlatePattern::Dots);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-plate-pattern="dots"', false)
        ->assertInertia(fn (Assert $page) => $page->where('branding.plate_pattern', 'dots'));

    $this->actingAs($admin)
        ->get(route('settings.branding.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('branding.plate_pattern', 'dots'));
});

it('rejects an unknown plate pattern', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->from(route('settings.branding.edit'))
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'plate_pattern' => 'url(evil)',
        ])
        ->assertSessionHasErrors('plate_pattern');
});

it('rejects an invalid accent hex', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->from(route('settings.branding.edit'))
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => 'blue',
        ])
        ->assertRedirect(route('settings.branding.edit'))
        ->assertSessionHasErrors('accent');
});

it('uploads, replaces, and removes a seal', function (): void {
    Storage::fake('public');

    $admin = brandingActor(UserRole::SystemAdministrator);
    $original = brandingSealUpload('seal.jpg');

    $this->actingAs($admin)
        ->post(route('settings.branding.update'), [
            '_method' => 'PUT',
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'logo' => $original,
        ])
        ->assertRedirect();

    $first = app(BrandingService::class)->snapshot();
    expect($first->logoPath)->toBe('branding/logo.png')
        ->and($first->logoUrl)->toContain('/storage/branding/logo.png');
    Storage::disk('public')->assertExists($first->logoPath);

    $replacement = brandingSealUpload('seal.png');

    $this->actingAs($admin)
        ->post(route('settings.branding.update'), [
            '_method' => 'PUT',
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'logo' => $replacement,
        ])
        ->assertRedirect();

    $second = app(BrandingService::class)->snapshot();
    expect($second->logoPath)->toBe('branding/logo.png');
    Storage::disk('public')->assertExists($second->logoPath);

    $this->actingAs($admin)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'remove_logo' => true,
        ])
        ->assertRedirect();

    $cleared = app(BrandingService::class)->snapshot();
    expect($cleared->logoPath)->toBeNull()
        ->and($cleared->logoUrl)->toBeNull();
    Storage::disk('public')->assertMissing($second->logoPath);
});

it('rejects an svg seal', function (): void {
    Storage::fake('public');
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->from(route('settings.branding.edit'))
        ->post(route('settings.branding.update'), [
            '_method' => 'PUT',
            'name' => 'Sangguniang Panlalawigan',
            'short_name' => 'SP',
            'locality' => 'Province of Demo',
            'accent' => AccentPalette::DEFAULT,
            'logo' => UploadedFile::fake()->create('seal.svg', 40, 'image/svg+xml'),
        ])
        ->assertRedirect(route('settings.branding.edit'))
        ->assertSessionHasErrors('logo');
});

it('shares saved organization identity with the login page', function (): void {
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->put(route('settings.branding.update'), [
            'name' => 'Sangguniang Bayan ng Demo',
            'short_name' => 'SB',
            'locality' => 'Municipality of Demo',
            'accent' => AccentPalette::DEFAULT,
        ])
        ->assertRedirect();

    $this->post('/logout');
    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('organization.name', 'Sangguniang Bayan ng Demo')
            ->where('organization.locality', 'Municipality of Demo')
            ->where('branding.logo_url', null));
});

it('resets branding to installation defaults', function (): void {
    Storage::fake('public');
    $admin = brandingActor(UserRole::SystemAdministrator);

    $this->actingAs($admin)
        ->post(route('settings.branding.update'), [
            '_method' => 'PUT',
            'name' => 'Custom Body',
            'short_name' => 'CB',
            'locality' => 'Somewhere',
            'accent' => '#111111',
            'plate_pattern' => 'grid',
            'logo' => brandingSealUpload('seal.png'),
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('settings.branding.reset'))
        ->assertRedirect();

    $snapshot = app(BrandingService::class)->snapshot();
    $defaults = app(BrandingService::class)->defaults();

    expect($snapshot->name)->toBe($defaults['name'])
        ->and($snapshot->shortName)->toBe($defaults['short_name'])
        ->and($snapshot->locality)->toBe($defaults['locality'])
        ->and($snapshot->accent)->toBe($defaults['accent'])
        ->and($snapshot->plate)->toBe(PlatePalette::DEFAULT)
        ->and($snapshot->platePattern)->toBe(PlatePattern::Authored)
        ->and($snapshot->logoPath)->toBeNull()
        ->and(SystemSetting::query()->where('key', 'branding.accent')->value('value'))->not->toBeNull();
});
