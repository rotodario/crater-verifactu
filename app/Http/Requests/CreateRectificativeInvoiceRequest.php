<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRectificativeInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'rectification_type' => [
                'nullable',
                'string',
                Rule::in(['REPLACEMENT', 'DIFFERENCES']),
            ],
            'rectification_reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
