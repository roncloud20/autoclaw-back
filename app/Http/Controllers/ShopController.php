<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    // create shop
    public function store(Request $request){
        $vendor = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'address' => 'required|string',
            'country' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'zipcode' => 'required|string',
            'logo' => 'required|image|mimes:jpeg,png,svg,jpg'
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Credentials',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {

            if($request->hasFile('logo')) {
                $logo = $request->file('logo')->store('shop_logo', 'public');
            }

            $shop = new Shop();
            $shop->vendor_id = $vendor->id;
            $shop->name = $request->input('name');
            $shop->address = $request->input('address');
            $shop->country = $request->input('country');
            $shop->state = $request->input('state');
            $shop->city = $request->input('city');
            $shop->zipcode = $request->input('zipcode');
            $shop->logo = $logo;
            $shop->save();

            DB::commit();
            return response()->json([
                'message' => 'Shop Created Successfully',
                'shop' => $shop,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occured',
                'errors' => $e
            ], 500);
        }
    }


}
