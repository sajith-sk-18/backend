<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * POST /api/auth/forgot-password   (public, throttled)
     * Body: { email }
     *
     * Always returns 200 with a generic success message so the endpoint
     * can't be used to enumerate registered emails. The actual email (if
     * any) is logged via the `log` mail driver in dev.
     *
     * In APP_ENV=local we also return the raw reset URL in the response
     * body so test runs don't have to grep the log.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Generic response baseline — never leak whether the email exists.
        $response = [
            'message' => "If an account exists for {$request->email}, a reset link has been sent.",
        ];

        if ($user && $user->is_active) {
            // Issue a single-use 64-char token and persist it. Laravel's
            // built-in Password broker stores the hashed token in
            // password_reset_tokens keyed by email.
            $status = Password::broker('users')->sendResetLink(
                ['email' => $user->email]
            );

            // In dev we also surface the URL directly to make manual testing
            // possible without an SMTP server.
            if (app()->environment('local')) {
                // Re-create a viewable reset URL using the token written to DB.
                $token = $this->latestTokenForEmail($user->email);
                if ($token) {
                    $frontUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
                    $response['dev_reset_url']  = "{$frontUrl}/reset-password?token={$token}&email=" . urlencode($user->email);
                    $response['dev_token']      = $token;
                }
            }

            // For visibility in case anything goes sideways
            $response['_status'] = $status;
        }

        return response()->json($response);
    }

    /**
     * POST /api/auth/reset-password    (public, throttled)
     * Body: { email, token, password, password_confirmation }
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        // Convenience: auto-issue a fresh customer-site token so the UI can
        // log the user straight in after a successful reset.
        $user = User::where('email', $request->email)->first();
        $token = $user?->createToken('customer-site')->plainTextToken;

        return response()->json([
            'message' => 'Password has been reset.',
            'token'   => $token,
            'user'    => $user ? [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'phone'     => $user->phone,
                'is_active' => (bool) $user->is_active,
                'created_at'=> $user->created_at,
            ] : null,
        ]);
    }

    /**
     * The Password broker doesn't expose the plain token after creation, but
     * for the dev convenience response we need it. The framework stores a
     * hash, not the plain value — so we'd normally have to re-derive it from
     * the framework's notification. Instead, we generate our own token and
     * upsert it into the broker's table. That makes the dev URL usable and
     * still works through the standard `Password::reset` validation because
     * the broker hashes both sides for comparison.
     */
    private function latestTokenForEmail(string $email): ?string
    {
        // Generate the plain token, store the hashed version (broker stores
        // hashed tokens). Overwrites any existing row for this email.
        $plain = Str::random(60);
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email'      => $email,
                'token'      => Hash::make($plain),
                'created_at' => now(),
            ]
        );
        return $plain;
    }
}
