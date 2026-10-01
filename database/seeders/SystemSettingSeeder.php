<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->settings() as $setting) {
            SystemSetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settings(): array
    {
        return [
            [
                'key' => 'organization.name',
                'group' => 'general',
                'label' => 'Organization name',
                'description' => 'Name of the legislative body as it appears on official output.',
                'type' => 'string',
                'value' => config('sentria.organization.name'),
                'is_public' => true,
            ],
            [
                'key' => 'organization.short_name',
                'group' => 'general',
                'label' => 'Organization short name',
                'description' => 'Short label shown in the navigation rail and compact chrome.',
                'type' => 'string',
                'value' => config('sentria.organization.short_name'),
                'is_public' => true,
            ],
            [
                'key' => 'organization.locality',
                'group' => 'general',
                'label' => 'Locality',
                'description' => 'Province, city, or municipality served by this installation.',
                'type' => 'string',
                'value' => config('sentria.organization.locality'),
                'is_public' => true,
            ],
            [
                'key' => 'branding.accent',
                'group' => 'general',
                'label' => 'Accent color',
                'description' => 'Primary action colour. Live/red is not customizable.',
                'type' => 'string',
                'value' => config('sentria.branding.accent'),
                'is_public' => true,
            ],
            [
                'key' => 'branding.plate',
                'group' => 'general',
                'label' => 'Plate color',
                'description' => 'Deep colour behind hero cards, the chamber floor, the portal plate, and sign-in. Must carry white text.',
                'type' => 'string',
                'value' => config('sentria.branding.plate'),
                'is_public' => true,
            ],
            [
                'key' => 'branding.plate_pattern',
                'group' => 'general',
                'label' => 'Plate pattern',
                'description' => 'Texture over every plate, or authored to keep each surface\'s own.',
                'type' => 'string',
                'value' => config('sentria.branding.plate_pattern'),
                'is_public' => true,
            ],
            [
                'key' => 'branding.logo_path',
                'group' => 'general',
                'label' => 'Brand logo',
                'description' => 'Official seal shown in place of the lettermark.',
                'type' => 'string',
                'value' => null,
                'is_public' => true,
            ],
            [
                'key' => 'quorum.rule',
                'group' => 'session',
                'label' => 'Quorum rule',
                'description' => 'How the quorum threshold is computed. The system reports status only; the presiding officer decides whether to proceed.',
                'type' => 'select',
                'value' => config('sentria.quorum.rule'),
                'options' => ['majority_of_seated', 'two_thirds_of_seated', 'fixed'],
            ],
            [
                'key' => 'quorum.fixed_threshold',
                'group' => 'session',
                'label' => 'Fixed quorum threshold',
                'description' => 'Number of members required when the quorum rule is set to "fixed".',
                'type' => 'integer',
                'value' => null,
            ],
            [
                'key' => 'voting.electronic_is_binding',
                'group' => 'session',
                'label' => 'Electronic voting is legally binding',
                'description' => 'Whether electronic ballots carry legal effect under this body\'s rules of procedure. Off by default.',
                'type' => 'boolean',
                'value' => config('sentria.voting.electronic_is_binding'),
            ],
            [
                'key' => 'documents.max_upload_size_kb',
                'group' => 'documents',
                'label' => 'Maximum upload size (KB)',
                'description' => 'Largest file accepted during document submission.',
                'type' => 'integer',
                'value' => config('sentria.documents.max_upload_size_kb'),
            ],
            [
                'key' => 'documents.default_confidentiality',
                'group' => 'documents',
                'label' => 'Default confidentiality',
                'description' => 'Confidentiality level applied to newly submitted documents.',
                'type' => 'select',
                'value' => 'internal',
                'options' => config('sentria.documents.confidentiality_levels'),
            ],
            [
                'key' => 'security.malware_scanner',
                'group' => 'security',
                'label' => 'Malware scanner',
                'description' => 'Scanner adapter used for uploads. Production deployments must not use the no-op driver.',
                'type' => 'select',
                'value' => config('sentria.malware_scanning.driver'),
                'options' => ['null', 'clamav'],
                'is_locked' => true,
            ],
            [
                'key' => 'security.session_lifetime_minutes',
                'group' => 'security',
                'label' => 'Session timeout (minutes)',
                'description' => 'Idle time before an authenticated session expires.',
                'type' => 'integer',
                'value' => config('session.lifetime'),
            ],
            [
                'key' => 'retention.allow_hard_delete',
                'group' => 'security',
                'label' => 'Allow hard delete of official records',
                'description' => 'Off by default. Enabling requires dual-administrator confirmation and is audited.',
                'type' => 'boolean',
                'value' => config('sentria.retention.allow_hard_delete'),
                'is_locked' => true,
            ],
            [
                'key' => 'chamber.default_capture_mode',
                'group' => 'session',
                'label' => 'Default chamber capture mode',
                'description' => 'Mixer mix plus secretariat speaker assignment, or per-seat microphones.',
                'type' => 'select',
                'value' => config('sentria.chamber.default_capture_mode', 'mixer_mix'),
                'options' => ['mixer_mix', 'per_seat'],
            ],
            [
                'key' => 'chamber.device',
                'group' => 'session',
                'label' => 'Chamber recording device',
                'description' => 'Audio input on the recording computer. Empty uses that computer’s default input.',
                'type' => 'json',
                'value' => null,
            ],
            [
                'key' => 'ai.enabled',
                'group' => 'ai',
                'label' => 'AI assistance enabled',
                'description' => 'Turns AI features on. AI never votes, approves, finalizes minutes, determines quorum, or publishes.',
                'type' => 'boolean',
                'value' => config('sentria.ai.enabled'),
            ],
            [
                'key' => 'ai.chat_model',
                'group' => 'ai',
                'label' => 'Chat model',
                'description' => 'Model used by the AI assistant. Provider credentials live in configuration, never in the database.',
                'type' => 'string',
                'value' => config('sentria.ai.chat_model'),
            ],
            [
                'key' => 'publication.require_second_reviewer',
                'group' => 'documents',
                'label' => 'Require a second reviewer before publishing',
                'description' => 'Adds a second human sign-off to the publication workflow.',
                'type' => 'boolean',
                'value' => true,
            ],
        ];
    }
}
