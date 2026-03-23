<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    //
    public function store(Request $request) {
        
        // $validated = $request->validate([
        //     'firstname' => 'required|string|max:255',
        //     'surname' => 'required|string|max:255',
        //     'email' => 'required|string|email|max:255|unique:users',
        //     'phone' => 'required|string|max:20',
        //     'password' => 'required|string|min:8|confirmed',
        //     'role' => 'required|string|in:admin,user',
        // ]);

        // Validate the incoming request data
        $validator = Validator::make($request->all(),[
            'firstname' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|regex:/^0[789][01]\d{8}$/|unique:users,phone',
            'password' => 'required|string|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            'role' => 'nullable|string|in:admin,user,vendor',
        ]);

        // Check if validation fails
        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // DB::table('users')->insert([
            //     'firstname' => $request->input('firstname'),
            //     'surname' => $request->input('surname'),
            //     'email' => $request->input('email'),
            //     'phone' => $request->input('phone'),
            //     'password' => bcrypt($request->input('password')),
            //     'role' => $request->input('role', 'user'), // Default role is 'user'
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);

            $user = new User();
            $user->firstname = $request->input('firstname');
            $user->surname = $request->input('surname');
            $user->email = $request->input('email');
            $user->phone = $request->input('phone');
            $user->password = $request->input('password');
            $user->role = $request->input('role', 'user');
            $user->save();

            return response()->json([
                'message' => 'User created successfully.',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the user.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
