<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AccountVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Register a new user
    public function store(Request $request)
    {

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|regex:/^0[789][01]\d{8}$/|unique:users,phone',
            'password' => 'required|string|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            'role' => 'nullable|string|in:admin,user,vendor',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = new User();
            $user->firstname = $request->input('firstname');
            $user->surname = $request->input('surname');
            $user->email = $request->input('email');
            $user->phone = $request->input('phone');
            $user->password = $request->input('password');
            $user->role = $request->input('role', 'user');
            $user->save();

            // Remove existing verification tokens for the user
            DB::table('account_verifications')->where('email', $user->email)->delete();

            // Generate a new verification token
            $token = rand(100000, 999999);

            // Store the token in the database with an expiration time (e.g., 30 minutes)
            AccountVerification::create([
                'email' => $user->email,
                'token' => $token,
                'expires_at' => now()->addMinutes(30),
            ]);

            $url = config('app.frontend_url') . "/verify-email?token={$token}&email={$user->email}";
            // Send the verification email
            Mail::send('emails.user-verification', [
                'user' => $user,
                'url' => $url,
                'token' => $token
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verify Your Email Address');
            });

            DB::commit();
            return response()->json([
                'message' => 'User created successfully.',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating the user.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Verify a user's email
    public function verifyEmail(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $verification = AccountVerification::where('email', $request->input('email'))
            ->where('token', $request->input('token'))
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Invalid verification token.',
            ], 400);
        }

        if ($verification->expires_at < now()) {
            return response()->json([
                'message' => 'Verification token has expired.',
            ], 400);
        }

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $user->email_verified_at = now();
        $user->save();

        // Delete the verification record after successful verification
        $verification->delete();

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user,
        ], 200);
    }

    // resend verification email
    public function resendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        DB::beginTransaction();
        try {
            $user = User::where('email', $request->input('email'))->first();
            if (!$user) {
                return response()->json([
                    'message' => 'User not found.',
                ], 404);
            }

            // Remove existing verification tokens for the user
            DB::table('account_verifications')->where('email', $user->email)->delete();

            // Generate a new verification token
            $token = rand(100000, 999999);

            // Store the token in the database with an expiration time (e.g., 30 minutes)
            AccountVerification::create([
                'email' => $user->email,
                'token' => $token,
                'expires_at' => now()->addMinutes(30),
            ]);

            $url = config('app.frontend_url') . "/verify-email?token={$token}&email={$user->email}";
            // Send the verification email
            Mail::send('emails.user-verification', [
                'user' => $user,
                'url' => $url,
                'token' => $token
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verify Your Email Address');
            });

            DB::commit();
            return response()->json([
                'message' => 'Verification email resent successfully.',
                'user' => $user,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while resending the verification email.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Login a user
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // create unique throttle key based on email and IP address
            $throttleKey = strtolower($request->input('email')) . '|' . $request->ip();

            $seconds = RateLimiter::availableIn($throttleKey);

            //Check if the user has exceeded the maximum number of login attempts
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return response()->json([
                    'message' => "Too many login attempts. Please try again later in {$seconds} seconds.",
                ], 429);
            }

            $user = User::where("email", $request->input('email'))->first();
            if (!$user || !Hash::check($request->input('password'), $user->password)) {
                RateLimiter::hit($throttleKey, 60); // Increment the login attempts for this key, with a decay time of 60 seconds
                return response()->json([
                    'message' => 'Invalid email or password.',
                    'errors' => [
                        'email' => [' '],
                        'password' => ['Incorrect email address or password.'],
                    ],
                ], 401);
            }

            // Clear the login attempts on successful login
            RateLimiter::clear($throttleKey);

            // delete existing tokens for the user
            $user->tokens()->delete();

            // create a new token for the user
            $token = $user->createToken('user_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while logging in.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
