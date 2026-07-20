<?php

namespace App\Http\Requests;

use App\Services\InstitutionJoinCodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInstitutionJoinCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'use_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'duration_days' => ['required', 'integer', 'min:0', 'max:30'],
            'duration_hours' => ['required', 'integer', 'min:0', 'max:23'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:59'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $seconds = $this->durationSecondsFromInput();
                if ($seconds < InstitutionJoinCodeService::MIN_TTL_SECONDS) {
                    $validator->errors()->add('duration', __('The code duration must be at least one second.'));
                } elseif ($seconds > InstitutionJoinCodeService::MAX_TTL_SECONDS) {
                    $validator->errors()->add('duration', __('The code duration cannot exceed 30 days.'));
                }
            },
        ];
    }

    public function durationSeconds(): int
    {
        return $this->durationSecondsFromInput();
    }

    private function durationSecondsFromInput(): int
    {
        return ((int) $this->input('duration_days') * 86_400)
            + ((int) $this->input('duration_hours') * 3_600)
            + ((int) $this->input('duration_minutes') * 60)
            + (int) $this->input('duration_seconds');
    }
}
