<?php

namespace App\Rules;

use App\Support\ServiceArea;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Put on the city / municipality field of an address form: the address must be inside the
 * service area (Laguna by default). Reads the sibling province / code fields of the same form.
 */
class InServiceArea implements ValidationRule, DataAwareRule
{
    private array $data = [];

    public function __construct(
        private string $what = 'address',
        private string $province = 'province',
        private string $codeField = 'municipality_code',
        private string $psgcField = 'municipality_psgc',
        private string $provinceCodeField = 'province_code',
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = $this->data[$this->codeField] ?? null ?: ($this->data[$this->psgcField] ?? null);
        if (! ServiceArea::covers($code, $this->data[$this->province] ?? null, is_string($value) ? $value : null, $this->data[$this->provinceCodeField] ?? null)) {
            $fail(ServiceArea::message($this->what));
        }
    }
}
