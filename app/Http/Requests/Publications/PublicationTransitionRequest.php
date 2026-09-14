<?php

namespace App\Http\Requests\Publications;

use App\States\Publication\InternalDocument;
use App\States\Publication\MarkPublic;
use App\States\Publication\PublicationReview;
use App\States\Publication\PublicationWorkflowStatus;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicationTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publications.review') === true
            || $this->user()?->can('publications.publish') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', Rule::in(array_keys(self::stateMap()))],
        ];
    }

    /**
     * @return class-string<PublicationWorkflowStatus>
     */
    public function targetStateClass(): string
    {
        $map = self::stateMap();
        $key = $this->validated('to');

        /** @var class-string<PublicationWorkflowStatus> $class */
        $class = $map[$key];

        return $class;
    }

    /**
     * @return array<string, class-string<PublicationWorkflowStatus>>
     */
    public static function stateMap(): array
    {
        return [
            InternalDocument::$name => InternalDocument::class,
            SecretariatReview::$name => SecretariatReview::class,
            PublicationReview::$name => PublicationReview::class,
            MarkPublic::$name => MarkPublic::class,
            Published::$name => Published::class,
        ];
    }
}
