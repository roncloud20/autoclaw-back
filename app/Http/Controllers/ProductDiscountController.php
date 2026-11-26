<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ProductResource;

class ProductDiscountController extends Controller
{
    // Set or change a product's discount (owner or admin)
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
            'type' => 'required|in:percent,fixed',
            'value' => 'required|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request, $product) {
            $errors = $validator->errors();

            if (!$errors->has('type') && !$errors->has('value')) {
                $value = (int) $request->input('value');

                if ($request->input('type') === 'percent' && $value > 99) {
                    $errors->add('value', 'A percentage discount must be between 1 and 99.');
                }

                if ($request->input('type') === 'fixed' && $value >= $product->initial_price) {
                    $errors->add('value', 'The discount must be less than the product price (₦' . number_format($product->initial_price) . ').');
                }
            }

            if (!$errors->has('starts_at') && !$errors->has('ends_at')) {
                $starts = $this->parseDate($request->input('starts_at'));
                $ends = $this->parseDate($request->input('ends_at'));

                if ($ends && $ends->lte(now())) {
                    $errors->add('ends_at', 'The end date must be in the future.');
                } elseif ($starts && $ends && $ends->lte($starts)) {
                    $errors->add('ends_at', 'The end date must be after the start date.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->discount_type = $request->input('type');
        $product->discount_value = (int) $request->input('value');
        $product->discount_starts_at = $this->parseDate($request->input('starts_at'));
        $product->discount_ends_at = $this->parseDate($request->input('ends_at'));
        $product->save();

        return (new ProductResource($product->load('shop')))
            ->additional(['message' => 'Discount saved.'])
            ->response();
    }

    // Remove a product's discount (owner or admin)
    public function destroy(Request $request, $id)
    {
        $product = Product::with('shop')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        if (!$product->canBeManagedBy($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $product->discount_type = null;
        $product->discount_value = null;
        $product->discount_starts_at = null;
        $product->discount_ends_at = null;
        $product->save();

        return (new ProductResource($product->load('shop')))
            ->additional(['message' => 'Discount removed.'])
            ->response();
    }

    // Clients send ISO dates with an offset; store them in the app's timezone
    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            return null;
        }
    }
}