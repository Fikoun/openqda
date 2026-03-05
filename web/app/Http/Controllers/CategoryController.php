<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories for a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $categories = Category::where('project_id', $project->id)
            ->with(['codes', 'children'])
            ->get();

        return response()->json(['categories' => $categories]);
    }

    /**
     * Create a new category and optionally attach codes.
     */
    public function store(StoreCategoryRequest $request, Project $project): JsonResponse
    {
        try {
            $color = $request->color ?? $this->generateRandomColor();

            $category = new Category([
                'name' => $request->name,
                'description' => $request->description,
                'color' => $color,
                'type' => $request->type ?? 'category',
                'project_id' => $project->id,
                'creating_user_id' => $request->user()->id,
                'parent_id' => $request->parent_id,
            ]);

            $category->save();

            // Attach codes if provided
            if ($request->has('code_ids') && is_array($request->code_ids)) {
                $category->codes()->attach($request->code_ids);
            }

            $category->load(['codes', 'children']);

            return response()->json([
                'message' => 'Category created successfully',
                'category' => $category,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'An error occurred while creating the category: '.$th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing category.
     */
    public function update(UpdateCategoryRequest $request, Project $project, Category $category): JsonResponse
    {
        try {
            $category->update($request->only([
                'name', 'description', 'color', 'type', 'parent_id',
            ]));

            $category->load(['codes', 'children']);

            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'An error occurred while updating the category: '.$th->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy(DestroyCategoryRequest $request, Project $project, Category $category): JsonResponse
    {
        try {
            $category->codes()->detach();
            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'An error occurred while deleting the category: '.$th->getMessage(),
            ], 500);
        }
    }

    /**
     * Attach codes to a category.
     */
    public function attachCodes(Request $request, Project $project, Category $category): JsonResponse
    {
        $request->validate([
            'code_ids' => 'required|array|min:1',
            'code_ids.*' => 'exists:codes,id',
        ]);

        try {
            $category->codes()->syncWithoutDetaching($request->code_ids);

            $category->load(['codes', 'children']);

            return response()->json([
                'message' => 'Codes attached successfully',
                'category' => $category,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'An error occurred while attaching codes: '.$th->getMessage(),
            ], 500);
        }
    }

    /**
     * Detach codes from a category.
     */
    public function detachCodes(Request $request, Project $project, Category $category): JsonResponse
    {
        $request->validate([
            'code_ids' => 'required|array|min:1',
            'code_ids.*' => 'exists:codes,id',
        ]);

        try {
            $category->codes()->detach($request->code_ids);
            $category->load(['codes', 'children']);

            return response()->json([
                'message' => 'Codes detached successfully',
                'category' => $category,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'An error occurred while detaching codes: '.$th->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a random hex color for a category.
     */
    private function generateRandomColor(): string
    {
        $r = rand(40, 159);
        $g = rand(40, 159);
        $b = rand(40, 159);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
