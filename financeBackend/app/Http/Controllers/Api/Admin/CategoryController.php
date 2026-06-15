<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AdminUser;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        return response()->json(
            Category::query()->orderBy('type')->orderBy('id')->get(),
        );
    }

    public function store(Request $request)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        $category = Category::query()->create($this->validatedData($request));

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        $category->update($this->validatedData($request));

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category)
    {
        if (! $this->admin($request)) {
            return $this->unauthorized();
        }

        if ($category->transactions()->exists()) {
            return response()->json(['message' => '该分类已有流水，不能删除'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'deleted']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'color' => ['required', 'string', 'max:20'],
            'is_system' => ['boolean'],
        ]);
    }

    private function admin(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }

    private function unauthorized()
    {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
