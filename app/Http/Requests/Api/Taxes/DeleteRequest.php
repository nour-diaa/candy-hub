<?php

namespace GetCandy\Api\Http\Requests\Taxes;

use GetCandy\Http\Requests\Api\FormRequest;
use GetCandy\Api\Models\Tax;

class DeleteRequest extends FormRequest
{
    public function authorize()
    {
        // return $this->user()->can('delete', Tax::class);
        return true;
    }

    public function rules()
    {
        return [
        ];
    }
}