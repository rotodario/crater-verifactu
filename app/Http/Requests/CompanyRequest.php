<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                Rule::unique('companies')->ignore($this->header('company'), 'id'),
            ],
            'tax_number' => [
                'nullable',
                'string',
                'max:20',
            ],
            'slug' => [
                'nullable'
            ],
            'address.country_id' => [
                'required',
            ],
        ];
    }

    public function getCompanyPayload()
    {
        return collect($this->validated())
            ->only([
                'name',
                'tax_number',
                'slug'
            ])
            ->toArray();
    }
}
