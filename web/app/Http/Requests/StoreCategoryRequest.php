<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return Gate::allows('create', [Category::class, $project]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'type' => 'nullable|string|in:category,theme',
            'parent_id' => 'nullable|exists:categories,id',
            'code_ids' => 'nullable|array',
            'code_ids.*' => 'exists:codes,id',
        ];
    }
}
