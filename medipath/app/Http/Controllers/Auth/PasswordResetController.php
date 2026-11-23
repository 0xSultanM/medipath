<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Forgot / reset password flow (PLAN Sprint 1.3). */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $resets) {}

    public function show(Request $request, ?string $token = null): Response
    {
        $email = (string) $request->query('email', '');
        $token = $token ?? (string) $request->query('token', '');
        $tokenValid = $token !== '' && $email !== '' && $this->resets->validToken($email, $token);

        return Inertia::render('Guest/ForgotReset', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenValid,
            'ttlMinutes' => (int) config('medipath.security.reset_token_ttl_minutes'),
        ]);
    }

    public function send(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = Str::lower((string) $request->validated('email'));
        $key = 'forgot|'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Too many reset requests. Wait a minute before trying again.');
        }

        RateLimiter::hit($key, 60);

        $user = User::query()->where('email', $email)->first();
        $demo = null;

        if ($user) {
            $result = $this->resets->request($user, (string) $request->ip());
            $demo = app()->environment('local') ? $result['url'] : null;
        }

        $response = back()->with(
            'success',
            'If an account exists for that address, a one-time reset link was sent. The link expires in '
            .config('medipath.security.reset_token_ttl_minutes').' minutes.'
        );

        if ($demo) {
            $response->with('demo_reset_link', $demo);
        }

        return $response;
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $this->resets->reset(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->with('success', 'Password updated. All other sessions were signed out. Sign in with your new password.');
    }
}
