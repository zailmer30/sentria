<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * The granular permission matrix. Permissions are named `module.ability` and
 * every role receives an explicit list — there is no implicit inheritance, so
 * an unlisted ability is always denied.
 *
 * AI never gets its own permissions: AI surfaces run with the permissions of
 * the authenticated user who invoked them.
 */
class PermissionMatrixSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $module => $abilities) {
            foreach ($abilities as $ability => $description) {
                Permission::query()->updateOrCreate(
                    ['name' => "{$module}.{$ability}", 'guard_name' => 'web'],
                    ['module' => $module, 'description' => $description],
                );
            }
        }

        foreach ($this->matrix() as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            $role?->syncPermissions($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function permissions(): array
    {
        return [
            'dashboard' => [
                'view' => 'Open the dashboard.',
            ],
            'users' => [
                'viewAny' => 'List user accounts.',
                'view' => 'View a user account.',
                'create' => 'Create a user account.',
                'update' => 'Edit a user account.',
                'delete' => 'Deactivate or soft-delete a user account.',
            ],
            'roles' => [
                'viewAny' => 'List roles and their permissions.',
                'manage' => 'Create, edit, and delete roles.',
                'assign' => 'Assign roles to users.',
            ],
            'sessions' => [
                'viewAny' => 'List legislative sessions.',
                'view' => 'View a legislative session.',
                'create' => 'Create a legislative session.',
                'update' => 'Edit session details.',
                'delete' => 'Soft-delete a session.',
                'schedule' => 'Move a session to Scheduled.',
                'start' => 'Open a session on the floor.',
                'suspend' => 'Suspend or resume a session in progress.',
                'adjourn' => 'Adjourn a session.',
                'archive' => 'Archive a finalized session.',
                'declareQuorum' => 'Record the presiding officer\'s quorum declaration.',
            ],
            'agenda' => [
                'viewAny' => 'View session agendas.',
                'manage' => 'Add, edit, remove, and reorder agenda items.',
                'lock' => 'Lock the agenda ahead of a session.',
            ],
            'documents' => [
                'viewAny' => 'List documents permitted by role and confidentiality.',
                'view' => 'Open a document record.',
                'create' => 'Submit a new document.',
                'update' => 'Edit document details.',
                'delete' => 'Soft-delete a document.',
                'uploadVersion' => 'Upload a new version of a document.',
                'download' => 'Download the document file.',
                'review' => 'Perform secretariat review of a submission.',
                'register' => 'Register a document into the official record.',
                'refer' => 'Refer a document to a committee.',
                'grantAccess' => 'Grant another user, role, or committee access to a document.',
                'archive' => 'Archive a document.',
            ],
            'legislation' => [
                'viewAny' => 'List ordinances and resolutions.',
                'view' => 'View an ordinance or resolution.',
                'manage' => 'Record enactment, approval, veto, and effectivity details.',
            ],
            'committees' => [
                'viewAny' => 'List committees.',
                'view' => 'View a committee.',
                'manage' => 'Create and edit committees.',
                'manageMembers' => 'Add or remove committee members.',
            ],
            'referrals' => [
                'viewAny' => 'List committee referrals.',
                'view' => 'View a committee referral.',
                'manage' => 'Create, reassign, and close referrals.',
            ],
            'reports' => [
                'viewAny' => 'List committee reports.',
                'view' => 'View a committee report.',
                'create' => 'Draft a committee report.',
                'submitForReview' => 'Send a draft committee report for checking.',
                'submit' => 'File a committee report on the document page for Committee Hour.',
                'adopt' => 'Record the chair motion to adopt a committee report on the floor.',
            ],
            'attendance' => [
                'viewAny' => 'View session attendance.',
                'record' => 'Record or correct attendance.',
            ],
            'motions' => [
                'viewAny' => 'View motions raised in a session.',
                'create' => 'Move a motion.',
                'second' => 'Second a motion.',
                'withdraw' => 'Withdraw one\'s own motion.',
                'rule' => 'Rule on a motion as presiding officer.',
            ],
            'voting' => [
                'viewAny' => 'View voting records and tallies.',
                'open' => 'Open a voting round.',
                'close' => 'Close a voting round.',
                'cast' => 'Cast a ballot.',
                'recordManual' => 'Record ballots taken viva voce or by nominal voting.',
            ],
            'minutes' => [
                'viewAny' => 'List minutes.',
                'view' => 'Read minutes.',
                'generateDraft' => 'Request an AI draft of minutes.',
                'edit' => 'Edit draft minutes.',
                'review' => 'Mark minutes as reviewed.',
                'approve' => 'Approve minutes.',
                'finalize' => 'Finalize minutes as the official record.',
            ],
            'transcripts' => [
                'viewAny' => 'List transcripts.',
                'view' => 'Read a transcript.',
                'manage' => 'Start, stop, upload, and correct transcripts.',
            ],
            'publications' => [
                'viewAny' => 'List publication records.',
                'review' => 'Review a document for public release.',
                'publish' => 'Publish a document to the public portal.',
                'unpublish' => 'Withdraw a document from the public portal.',
            ],
            'ai' => [
                'use' => 'Use the AI assistant.',
                'summarize' => 'Request document summaries.',
                'search' => 'Use semantic search over permitted documents.',
                'compare' => 'Compare document versions with AI assistance.',
                'checkConsistency' => 'Run AI consistency checks against existing legislation.',
                'transcribe' => 'Run speech-to-text on session audio.',
            ],
            'notifications' => [
                'viewAny' => 'View one\'s own notifications.',
            ],
            'session-chat' => [
                'use' => 'Use private in-session floor chat.',
            ],
            'audit' => [
                'viewAny' => 'Read the audit trail.',
                'verify' => 'Run audit chain verification.',
            ],
            'settings' => [
                'viewAny' => 'View system settings.',
                'update' => 'Change system settings.',
                'chamber' => 'Set up chamber microphones and capture devices.',
            ],
            'backup' => [
                'run' => 'Trigger a backup.',
                'restore' => 'Restore from a backup.',
            ],
            'portal' => [
                'view' => 'Browse the public portal.',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function matrix(): array
    {
        $everyone = ['dashboard.view', 'notifications.viewAny', 'portal.view'];

        $readsLegislation = [
            'documents.viewAny', 'documents.view', 'documents.download',
            'legislation.viewAny', 'legislation.view',
            'committees.viewAny', 'committees.view',
            'sessions.viewAny', 'sessions.view',
            'agenda.viewAny',
            'attendance.viewAny',
            'minutes.viewAny', 'minutes.view',
            'transcripts.viewAny', 'transcripts.view',
            'referrals.viewAny', 'referrals.view',
            'reports.viewAny', 'reports.view',
            'voting.viewAny',
            'motions.viewAny',
            'publications.viewAny',
        ];

        $usesAi = ['ai.use', 'ai.summarize', 'ai.search', 'ai.compare'];

        $seatedMember = [
            ...$everyone,
            ...$readsLegislation,
            ...$usesAi,
            'documents.create', 'documents.uploadVersion',
            'motions.create', 'motions.second', 'motions.withdraw',
            'voting.cast',
            'session-chat.use',
        ];

        return [
            UserRole::SystemAdministrator->value => $this->allPermissionNames(),

            UserRole::Secretariat->value => [
                ...$everyone,
                ...$readsLegislation,
                ...$usesAi,
                'ai.transcribe',
                'users.viewAny', 'users.view',
                'sessions.create', 'sessions.update', 'sessions.schedule', 'sessions.start', 'sessions.suspend', 'sessions.adjourn', 'sessions.archive',
                'agenda.manage', 'agenda.lock',
                'documents.create', 'documents.update', 'documents.uploadVersion',
                'documents.review', 'documents.register', 'documents.refer', 'documents.grantAccess', 'documents.archive',
                'legislation.manage',
                'committees.manage', 'committees.manageMembers',
                'referrals.manage',
                'reports.create', 'reports.submitForReview', 'reports.submit', 'reports.adopt',
                'attendance.record',
                'session-chat.use',
                'voting.open', 'voting.close', 'voting.recordManual',
                'minutes.generateDraft', 'minutes.edit', 'minutes.review',
                'transcripts.manage',
                'publications.review', 'publications.publish', 'publications.unpublish',
                'audit.viewAny',
                'settings.chamber',
            ],

            UserRole::PresidingOfficer->value => [
                ...$seatedMember,
                'sessions.suspend', 'sessions.adjourn', 'sessions.declareQuorum',
                'motions.rule',
                'voting.open', 'voting.close',
                'minutes.review', 'minutes.approve', 'minutes.finalize',
                'attendance.record',
            ],

            UserRole::BoardMember->value => $seatedMember,

            UserRole::CommitteeChair->value => [
                ...$seatedMember,
                'committees.manageMembers',
                'referrals.manage',
                'reports.create', 'reports.submitForReview', 'reports.adopt',
                'documents.refer',
            ],

            UserRole::CommitteeMember->value => [
                ...$seatedMember,
                'reports.create', 'reports.submitForReview',
            ],

            UserRole::LegalTechnicalReviewer->value => [
                ...$everyone,
                ...$readsLegislation,
                ...$usesAi,
                'ai.checkConsistency',
                'documents.review',
                'audit.viewAny',
            ],

            UserRole::PublicUser->value => [
                'portal.view',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function allPermissionNames(): array
    {
        $names = [];

        foreach ($this->permissions() as $module => $abilities) {
            foreach (array_keys($abilities) as $ability) {
                $names[] = "{$module}.{$ability}";
            }
        }

        return $names;
    }
}
