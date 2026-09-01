<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => [
                'required',
                Rule::enum(OpportunityStage::class),
                function ($attribute, $value, $fail) {
                    $opportunity = $this->route('opportunity');

                    if (! $opportunity) {
                        return;
                    }

                    $currentStage = $opportunity->stage;
                    $targetStage = OpportunityStage::from($value);

                    if ($targetStage === OpportunityStage::WON) {
                        $fail('Cannot transition to WON through this endpoint. Use the win workflow instead.');
                    }

                    if (! in_array($targetStage, $currentStage->validTransitions(), true)) {
                        $fail('Invalid stage transition.');
                    }
                },
            ],

            'reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'stage.required' => 'A target stage is required.',
            'reason.max' => 'The reason cannot exceed 5000 characters.',
        ];
    }
}
