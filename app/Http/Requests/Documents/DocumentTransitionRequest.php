<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\ResolvesCommitteeReferralInput;
use App\States\Document\AgendaInclusion;
use App\States\Document\Amendments;
use App\States\Document\Approved;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral;
use App\States\Document\CommitteeReport;
use App\States\Document\CommitteeReview;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\FinalDocument;
use App\States\Document\PublicPublication;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Registered;
use App\States\Document\Rejected;
use App\States\Document\ReturnedForRevision;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use App\States\Document\Transmittal;
use App\States\Document\Voting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTransitionRequest extends FormRequest
{
    use ResolvesCommitteeReferralInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCommitteeReferralInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', Rule::in(array_keys(self::stateMap()))],
            'return_reason' => [
                Rule::requiredIf($this->input('to') === ReturnedForRevision::$name),
                'nullable',
                'string',
                'min:3',
                'max:2000',
            ],
            ...$this->committeeReferralDetailRules($this->input('to') === CommitteeReferral::$name),
        ];
    }

    /**
     * @return class-string<DocumentWorkflowStatus>
     */
    public function targetStateClass(): string
    {
        $map = self::stateMap();
        $key = $this->validated('to');

        /** @var class-string<DocumentWorkflowStatus> $class */
        $class = $map[$key];

        return $class;
    }

    /**
     * @return array<string, class-string<DocumentWorkflowStatus>>
     */
    public static function stateMap(): array
    {
        return [
            Submitted::$name => Submitted::class,
            SecretariatReview::$name => SecretariatReview::class,
            ReturnedForRevision::$name => ReturnedForRevision::class,
            Registered::$name => Registered::class,
            CommitteeReferral::$name => CommitteeReferral::class,
            CommitteeReview::$name => CommitteeReview::class,
            CommitteeReport::$name => CommitteeReport::class,
            AgendaInclusion::$name => AgendaInclusion::class,
            ReadingDeliberation::$name => ReadingDeliberation::class,
            Amendments::$name => Amendments::class,
            Voting::$name => Voting::class,
            Approved::$name => Approved::class,
            Rejected::$name => Rejected::class,
            FinalDocument::$name => FinalDocument::class,
            Transmittal::$name => Transmittal::class,
            Archive::$name => Archive::class,
            PublicPublication::$name => PublicPublication::class,
        ];
    }
}
