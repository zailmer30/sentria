<?php

namespace App\Http\Controllers;

use App\Http\Requests\Legislation\StoreSignedCopyRequest;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Services\Legislation\LegislativeSignedCopyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegislativeSignedCopyController extends Controller
{
    public function __construct(private readonly LegislativeSignedCopyService $signedCopies) {}

    public function storeOrdinance(StoreSignedCopyRequest $request, Ordinance $ordinance): RedirectResponse
    {
        return $this->store($request, $ordinance);
    }

    public function destroyOrdinance(Request $request, Ordinance $ordinance): RedirectResponse
    {
        return $this->destroy($request, $ordinance);
    }

    public function previewOrdinance(Request $request, Ordinance $ordinance): StreamedResponse
    {
        return $this->preview($request, $ordinance);
    }

    public function downloadOrdinance(Request $request, Ordinance $ordinance): StreamedResponse
    {
        return $this->download($request, $ordinance);
    }

    public function storeResolution(StoreSignedCopyRequest $request, Resolution $resolution): RedirectResponse
    {
        return $this->store($request, $resolution);
    }

    public function destroyResolution(Request $request, Resolution $resolution): RedirectResponse
    {
        return $this->destroy($request, $resolution);
    }

    public function previewResolution(Request $request, Resolution $resolution): StreamedResponse
    {
        return $this->preview($request, $resolution);
    }

    public function downloadResolution(Request $request, Resolution $resolution): StreamedResponse
    {
        return $this->download($request, $resolution);
    }

    private function store(StoreSignedCopyRequest $request, Ordinance|Resolution $record): RedirectResponse
    {
        $replacing = $record->signedCopyIsAttached();

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $this->signedCopies->store($record, $this->requireUser($request), $file);

        return redirect()
            ->back()
            ->with('success', $replacing
                ? 'legislation.signed_copy_replaced'
                : 'legislation.signed_copy_uploaded');
    }

    private function destroy(Request $request, Ordinance|Resolution $record): RedirectResponse
    {
        $this->authorize('update', $record);

        $this->signedCopies->destroy($record, $this->requireUser($request));

        return redirect()
            ->back()
            ->with('success', 'legislation.signed_copy_removed');
    }

    private function preview(Request $request, Ordinance|Resolution $record): StreamedResponse
    {
        $this->authorize('view', $record);

        return $this->signedCopies->stream($record, false, $this->requireUser($request));
    }

    private function download(Request $request, Ordinance|Resolution $record): StreamedResponse
    {
        $this->authorize('view', $record);

        return $this->signedCopies->stream($record, true, $this->requireUser($request));
    }
}
