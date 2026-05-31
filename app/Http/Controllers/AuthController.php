<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register  (public)
     * Body: { name, email, password, password_confirmation, phone? }
     * Creates a 'customer' account and issues a Sanctum token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password'              => 'required|string|min:6|confirmed',
            'phone'                 => 'nullable|string|max:30',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'phone'     => $data['phone'] ?? null,
            'role'      => 'customer',
            'is_active' => true,
        ]);

        $token = $user->createToken('customer-site')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->presentUser($user),
        ], 201);
    }

    /**
     * POST /api/auth/login  (public)
     * Body: { email, password }
     * Returns: { token, user }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact support.'],
            ]);
        }

        $tokenName = $user->isAdmin() ? 'admin-panel' : 'customer-site';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->presentUser($user),
        ]);
    }

    /**
     * POST /api/auth/logout  (auth)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/me  (auth)
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->presentUser($request->user()));
    }

    private function presentUser(User $u): array
    {
        return [
            'id'        => $u->id,
            'name'      => $u->name,
            'email'     => $u->email,
            'role'      => $u->role,
            'phone'     => $u->phone,
            'is_active' => (bool) $u->is_active,
            'created_at'=> $u->created_at,
        ];
    }
}
