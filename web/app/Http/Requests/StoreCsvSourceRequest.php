<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class StoreCsvSourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $projectId = $this->input('projectId');
        $project = Project::find($projectId);

        if (! $project) {
            return false;
        }

        return Gate::allows('view', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10 MB size limit
            'projectId' => 'required|exists:projects,id',
            'suffix' => 'nullable|string|max:100',
        ];
    }

    /**
     * Prepare the data for validation and handle rate limiting.
     */
    protected function prepareForValidation(): void
    {
        $rateLimitKey = 'upload-limit:'.(optional($this->user())->id ?: $this->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, $perMinute = 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            abort(response()->json([
                'message' => 'Rate limit exceeded. Try again in '.$seconds.' seconds.',
            ], 429));
        }
    }
}
