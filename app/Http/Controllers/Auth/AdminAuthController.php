<?php

namespace App\Http\Controllers\Auth;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        $auth = auth('admin')->user();
        if($auth->role != 'owner'){
            return response()->json(['error' => "Unauthorized access to page.\nOnly system owners are allowed to add other admins"], 401);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', Rule::in(['admin', 'super_admin'])]
        ]);

        // Owner can add super admins and admins,
        // Owner can manage both super and normal admins
        // Super admin can manage only normal admins

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

//        $token = $admin->createToken('admin_token')->plainTextToken;

//        return response()->json(['token' => $token, 'admin' => $admin]);
        event(new AdminActionPerformed($auth->name,$auth->email,"Registering new $admin->role",$auth->role));
        return response()->json(['success' => $admin]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
        }

        if ($admin->status != 1) {
            throw ValidationException::withMessages(['email' => ['Admin account is not active.']]);
        }
        $token = $admin->createToken('admin_token')->plainTextToken;

        event(new AdminActionPerformed($admin->name,$admin->email,"Login attempt",$admin->role));

        return response()->json(['token' => $token, 'admin' => $admin]);
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Admin logged out successfully']);
    }
}
