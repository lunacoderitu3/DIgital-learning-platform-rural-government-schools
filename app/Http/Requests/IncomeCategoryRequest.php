<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IncomeCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Feature + permission gating is handled inside the controller
     * (ResponseService::noFeatureThenSendJson / noPermissionThenSendJson).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // On update the resource id is the last route parameter; null on store.
        $id = last($this->route()->parameters());
        $schoolId = Auth::user()->school_id;

        return [
            'name' => [
                'required',
                Rule::unique('income_categories', 'name')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)->whereNull('deleted_at')),
            ],
            'description' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => trans('The name field is required.'),
            'name.unique' => trans('Category name already exists.'),
        ];
    }
}
