<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentGrantRequest;
use App\Models\Committee;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\User;
use App\Notifications\DocumentAccessGranted;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\InAppNotifier;
use Illuminate\Http\RedirectResponse;

class DocumentGrantController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InAppNotifier $notifier,
    ) {}

    public function store(StoreDocumentGrantRequest $request, Document $document): RedirectResponse
    {
        $validated = $request->validated();

        $grant = DocumentGrant::query()->create([
            'document_id' => $document->getKey(),
            'user_id' => $validated['user_id'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'committee_id' => $validated['committee_id'] ?? null,
            'ability' => $validated['ability'] ?? 'view',
            'granted_by' => $request->user()?->getKey(),
            'granted_at' => now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->audit->record(
            event: 'document.grant',
            category: 'documents',
            auditable: $document,
            actor: $request->user(),
            new: ['grant_id' => $grant->getKey()],
            message: 'Document access grant created.',
        );

        $ability = $grant->ability ?? 'view';
        $actor = $request->user();

        if ($grant->user_id !== null) {
            $grantee = User::query()->find($grant->user_id);

            if ($grantee !== null) {
                $this->notifier->send(
                    $grantee,
                    new DocumentAccessGranted($document, $ability),
                    $actor,
                );
            }
        } elseif ($grant->committee_id !== null) {
            $committee = Committee::query()->find($grant->committee_id);

            if ($committee !== null) {
                $this->notifier->send(
                    $committee->activeMembers()->get(),
                    new DocumentAccessGranted($document, $ability),
                    $actor,
                );
            }
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.grant_created');
    }

    public function destroy(Document $document, DocumentGrant $grant): RedirectResponse
    {
        $this->authorize('grantAccess', $document);
        abort_unless($grant->document_id === $document->getKey(), 404);

        $grant->delete();

        $this->audit->record(
            event: 'document.grant_revoked',
            category: 'documents',
            auditable: $document,
            actor: request()->user(),
            old: ['grant_id' => $grant->getKey()],
            message: 'Document access grant revoked.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.grant_revoked');
    }
}
