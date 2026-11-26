<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminInviteController extends Controller
{
    const EXPIRY_HOURS = 48;

    // List pending invites (admin only)
    public function index()
    {
        $invites = AdminInvite::with('inviter:id,firstname,surname')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json(['invites' => $invites], 200);
    }

    // Send an invite (admin only)
    public function store(Request $request)
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

        $email = strtolower($request->input('email'));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'A user with this email already exists.',
            ], 409);
        }

        DB::beginTransaction();
        try {
            // Only one live invite per email
            AdminInvite::where('email', $email)->whereNull('accepted_at')->delete();

            $token = Str::random(64);

            $invite = AdminInvite::create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            ]);

            $url = config('app.frontend_url') . '/admin/accept-invite?token=' . $token;

            Mail::send('emails.admin-invite', [
                'inviter' => $request->user(),
                'url' => $url,
                'hours' => self::EXPIRY_HOURS,
            ], function ($message) use ($email) {
                $message->to($email)->subject('You have been invited as an admin');
            });

            DB::commit();
            return response()->json([
                'message' => 'Invite sent successfully.',
                'invite' => $invite,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while sending the invite.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Revoke a pending invite (admin only)
    public function destroy($id)
    {
        $invite = AdminInvite::whereNull('accepted_at')->find($id);

        if (!$invite) {
            return response()->json(['message' => 'Invite not found.'], 404);
        }

        $invite->delete();

        return response()->json(['message' => 'Invite revoked.'], 200);
    }

    // Check a token and return the invited email (public)
    public function show($token)
    {
        $invite = AdminInvite::findValid($token);

        if (!$invite) {
            return response()->json([
                'message' => 'This invite is invalid or has expired.',
            ], 410);
        }

        return response()->json(['email' => $invite->email], 200);
    }

    // Accept an invite and create the admin account (public)
    public function accept(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'firstname' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string|regex:/^0[789][01]\d{8}$/|unique:users,phone',
            'password' => 'required|string|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Lock the row so one invite can't be used twice at the same time
            $invite = AdminInvite::where('token_hash', hash('sha256', $request->input('token')))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This invite is invalid or has expired.',
                ], 410);
            }

            if (User::where('email', $invite->email)->exists()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'A user with this email already exists.',
                ], 409);
            }

            $user = new User();
            $user->firstname = $request->input('firstname');
            $user->surname = $request->input('surname');
            $user->email = $invite->email;
            $user->phone = $request->input('phone');
            $user->password = $request->input('password');
            $user->role = 'admin';
            // The invite email proves ownership of the address
            $user->email_verified_at = now();
            $user->save();

            $invite->accepted_at = now();
            $invite->save();

            $token = $user->createToken('user_token')->plainTextToken;

            DB::commit();
            return response()->json([
                'message' => 'Admin account created successfully.',
                'token' => $token,
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating the admin account.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}