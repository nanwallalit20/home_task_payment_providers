<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $products = Product::where('user_id', Auth::id())->get();

        return $this->successResponse(['products' => $products]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'name' => $request->validated('name'),
            'quantity' => $request->validated('quantity'),
            'user_id' => Auth::id(),
        ]);

        return $this->successResponse(['product' => $product], 'Product created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return $this->forbiddenResponse('Unauthorized access to product');
        }

        return $this->successResponse(['product' => $product]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return $this->forbiddenResponse('Unauthorized access to product');
        }

        $product->update([
            'name' => $request->validated('name'),
            'quantity' => $request->validated('quantity'),
        ]);

        return $this->successResponse(['product' => $product->fresh()], 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->user_id !== Auth::id()) {
            return $this->forbiddenResponse('Unauthorized access to product');
        }

        $product->delete();

        return $this->successResponse([], 'Product deleted successfully');
    }
}
