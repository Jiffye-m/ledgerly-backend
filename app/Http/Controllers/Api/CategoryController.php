<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')
            ->where('business_id', $request->business()->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'business_id' => $request->business()->id,
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name, $request->business()->id),
            'description' => $request->description,
        ]);

        return response()->json([
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorizeBusiness($category);

        return response()->json([
            'category' => new CategoryResource($category->loadCount('products')),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorizeBusiness($category);

        $data = $request->validated();
        if (isset($data['name'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->business_id, $category->id);
        }

        $category->update($data);

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorizeBusiness($category);

        // Products keep existing (category_id -> null) via nullOnDelete on the migration
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function uniqueSlug(string $name, int $businessId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        $query = fn ($s) => Category::where('business_id', $businessId)
            ->where('slug', $s)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));

        while ($query($slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
