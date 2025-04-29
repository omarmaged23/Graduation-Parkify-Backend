<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user ,'userData'=>null]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        // Get ONLY what we need for auth
        $user = User::where('email', $request->email)
            ->select(['id', 'name', 'email', 'password', 'email_verified_at'])
            ->first();
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
        }
    
        // Check account status WITHOUT auto-loading userData
        $accountStatus = $user->userData()->exists() 
            ? $user->userData->is_active 
            : true;
    
        if (!$accountStatus) {
            throw ValidationException::withMessages(['status' => ['Account is not active']]);
        }
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        // Return minimal user data (optional: add ->makeHidden('password'))
        return response()->json([
            'token' => $token,
            'user' => $user->only('id', 'name', 'email', 'email_verified_at'),
            'userData' => $user->relationLoaded('userData') ? $user->userData : null
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
