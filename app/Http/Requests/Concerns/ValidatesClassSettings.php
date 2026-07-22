<?php

namespace App\Http\Requests\Concerns;

use App\Services\InstitutionContext;
use App\Services\Time\IanaTimeZone;
use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ValidatesClassSettings
{
    /** @return list<mixed> */
    protected function classKeyRules(): array
    {
        $institution = app(InstitutionContext::class)->current($this, $this->user());

        return [
            'required',
            'string',
            'max:100',
            'alpha_dash:ascii',
            Rule::unique('course_offerings', 'key')->where('institution_id', $institution->getKey()),
        ];
    }

    /** @return list<mixed> */
    protected function timezoneRules(): array
    {
        return [
            'nullable',
            'string',
            'max:64',
            static function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    IanaTimeZone::nullable($value);
                } catch (InvalidArgumentException) {
                    $fail(__('classes.errors.timezone'));
                }
            },
        ];
    }
}
