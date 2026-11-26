<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    // Public: list products (search, filter, sort, paginate)
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'min_price' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'on_sale' => 'nullable|boolean',
            'sort' => 'nullable|in:newest,price_asc,price_desc',
            'per_page' => 'nullable|integer|min:1|max:48',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid query.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // whereHas('shop') hides products whose shop was deleted
        $query = Product::with('shop')->whereHas('shop');

        if ($q = $request->query('q')) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        // Price filters and sorting use the price after any active discount
        if ($request->filled('min_price')) {
            $query->whereEffectivePrice('>=', (int) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->whereEffectivePrice('<=', (int) $request->query('max_price'));
        }

        if ($request->boolean('on_sale')) {
            $query->onSale();
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderByEffectivePrice('asc')->orderByDesc('id'),
            'price_desc' => $query->orderByEffectivePrice('desc')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        $products = $query->paginate((int) $request->query('per_page', 12))->withQueryString();

        return ProductResource::collection($products);
    }

    // Public: distinct categories for filters
    public function categories()
    {
        $categories = Product::whereHas('shop')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json(['categories' => $categories], 200);
    }

    // Public: single product
    public function show($id)
    {
        $product = Product::with('shop')->whereHas('shop')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return new ProductResource($product);
    }

    // Vendor: own products (admin sees all)
    public function mine(Request $request)
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 48);

        $query = Product::with('shop')->orderByDesc('id');

        if ($user->role !== 'admin') {
            $shopIds = Shop::where('vendor_id', $user->id)->pluck('id');
            $query->whereIn('shop_id', $shopIds);
        }

        return ProductResource::collection($query->paginate($perPage));
    }

    // Vendor/admin: create a product
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'initial_price' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shop = $this->resolveShop($request);

        if (!$shop) {
            return response()->json([
                'message' => 'No shop found for this product. Create a shop first, or pass a valid shop_id.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $image = null;

            if ($request->hasFile('image')) {
                $image = $request->file('image')->store('product_images', 'public');
            }

            $product = new Product();
            $product->shop_id = $shop->id;
            $product->name = $request->input('name');
            $product->brand = $request->input('brand');
            $product->category = $request->input('category');
            $product->description = $request->input('description');
            $product->initial_price = $request->input('initial_price');
            $product->stock = $request->input('stock');
            $product->image = $image;
            $product->save();

            DB::commit();
            return (new ProductResource($product->load('shop')))
                ->additional(['message' => 'Product created successfully.'])
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating the product.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Vendor/admin: update a product (owner or admin)
    // Multipart forms can't use PUT in PHP, so send POST with _method=PUT
    public function update(Request $request, $id)
    {
        $product = Product::with('shop')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        if (!$product->canBeManagedBy($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'brand' => 'sometimes|required|string|max:100',
            'category' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
            'initial_price' => 'sometimes|required|integer|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // A fixed discount must stay below the price
        $validator->after(function ($validator) use ($request, $product) {
            $initial = (int) $request->input('initial_price', $product->initial_price);

            if ($product->discount_type === 'fixed' && $initial <= $product->discount_value) {
                $validator->errors()->add('initial_price', 'The price must be higher than the current discount (₦' . number_format($product->discount_value) . '). Change or remove the discount first.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldImage = null;

            $product->fill($request->only([
                'name', 'brand', 'category', 'description',
                'initial_price', 'stock',
            ]));

            if ($request->hasFile('image')) {
                $oldImage = $product->image;
                $product->image = $request->file('image')->store('product_images', 'public');
            }

            $product->save();
            DB::commit();

            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            return (new ProductResource($product->load('shop')))
                ->additional(['message' => 'Product updated successfully.'])
                ->response();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while updating the product.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Vendor/admin: delete a product (soft delete, owner or admin)
    public function destroy(Request $request, $id)
    {
        $product = Product::with('shop')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        if (!$product->canBeManagedBy($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.'], 200);
    }

    // Vendors: their shop_id (or their only shop). Admins must send shop_id.
    private function resolveShop(Request $request): ?Shop
    {
        $user = $request->user();
        $shopId = $request->input('shop_id');

        if ($user->role === 'admin') {
            return $shopId ? Shop::find($shopId) : null;
        }

        $shops = Shop::where('vendor_id', $user->id)->get();

        if ($shopId) {
            return $shops->firstWhere('id', (int) $shopId);
        }

        return $shops->count() === 1 ? $shops->first() : null;
    }
}