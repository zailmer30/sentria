<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\UpdateDocumentReferralRequest;
use App\Models\Document;
use App\Services\Documents\CommitteeReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DocumentReferralController extends Controller
{
    public function __construct(private readonly CommitteeReferralService $referrals) {}

    public function update(UpdateDocumentReferralRequest $request, Document $document): RedirectResponse
    {
        try {
            $this->referrals->sync(
                $document,
                $request->committeeIds(),
                $this->requireUser($request),
                $request->meetingOn(),
                $request->remarks(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'committee_ids' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.referral_updated');
    }
}
