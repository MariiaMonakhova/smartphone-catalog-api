<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for a partial (PATCH) update.
     *
     * We reuse the create rules but make every field optional: only fields
     * actually present in the request body are validated (and then updated),
     * which is exactly the PATCH semantic.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = (new StoreProductRequest)->rules();

        foreach ($rules as $field => $constraints) {
            $constraints = array_values(array_diff($constraints, ['required']));
            array_unshift($constraints, 'sometimes');
            $rules[$field] = $constraints;
        }

        return $rules;
    }
}
