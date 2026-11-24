<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Feature + permission gating is handled inside the controller.
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
        $sessionYearId = $this->input('session_year_id');

        return [
            'session_year_id' => 'required|exists:session_years,id',
            'income_category_id' => 'required|exists:income_categories,id',
            'title' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required',
            'reference_no' => [
                'nullable',
                Rule::unique('incomes', 'reference_no')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)->where('session_year_id', $sessionYearId)),
            ],
            'description' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'reference_no.unique' => trans('Reference number already exists for the selected session year.'),
            'amount.min' => trans('Amount must be greater than 0.'),
        ];
    }
}
