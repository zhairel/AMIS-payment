<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use App\Models\MagicLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function showRegister()
    {
        return redirect()->route('login');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Rate limit registration attempts per email address to 2 per 60 seconds
        $limiterKey = 'register-email:' . $email;
        if (RateLimiter::tooManyAttempts($limiterKey, 2)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "Too many verification requests. Please wait {$seconds} seconds."]);
        }
        
        RateLimiter::hit($limiterKey, 60);

        $user = User::where('email', $email)->first();

        if ($user) {
            if (in_array($user->account_status, ['blocked', 'suspended'], true)) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'This account is not available. Please contact AMIS support.']);
            }

            if (! $this->sendVerificationLink($user, $request)) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'We could not send the verification link right now. Please contact AMIS support or try again later.']);
            }

            $request->session()->put('verify_email', $email);
            $request->session()->put('verify_timer_start', time());

            return redirect()->route('verify.email.notice')
                ->with('email', $email)
                ->with('success', 'We sent a secure sign-in link to your email.');
        }

        $user = User::create([
            'name' => Str::before($email, '@'),
            'username' => User::makeUniqueUsername($email),
            'email' => $email,
            'password' => Hash::make(Str::random(48)),
            'role' => 'applicant',
            'account_status' => 'pending',
        ]);

        if (! $this->sendVerificationLink($user, $request)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the verification link right now. Please contact AMIS support or try again later.']);
        }

        $request->session()->put('verify_email', $email);
        $request->session()->put('verify_timer_start', time());

        return redirect()->route('verify.email.notice')
            ->with('email', $email)
            ->with('success', 'We sent a verification link to your email.');
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Check if email is currently in 5-minute lockout
        $lockoutKey = 'otp_lockout:' . $email;
        if (Cache::has($lockoutKey)) {
            $lockoutUntil = (int) Cache::get($lockoutKey);
            $remaining = max(1, $lockoutUntil - time());
            if ($remaining > 0) {
                return response()->json([
                    'status' => 'error',
                    'locked' => true,
                    'lockout_seconds' => $remaining,
                    'message' => 'Too many incorrect attempts. Verification is temporarily locked for 5 minutes.'
                ], 429);
            }
            // Lockout expired, clear it
            Cache::forget($lockoutKey);
        }

        // Rate limit sending OTP codes to 3 per 60 seconds per email
        $limiterKey = 'send-otp:' . $email;
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'status' => 'error',
                'message' => "Too many verification requests. Please wait {$seconds} seconds."
            ], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        // Find or create user
        $user = User::where('email', $email)->first();

        if ($user) {
            if (in_array($user->account_status, ['blocked', 'suspended'], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This account is blocked or suspended. Please contact AMIS support.'
                ], 403);
            }
        } else {
            // Register a new user
            $user = User::create([
                'name' => Str::before($email, '@'),
                'username' => User::makeUniqueUsername($email),
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'role' => 'applicant',
                'account_status' => 'pending',
            ]);
        }

        // Generate 4-digit numeric code
        $code = sprintf("%04d", rand(1000, 9999));

        // Save verification code in DB (expires in 5 minutes, invalidating any previous code)
        VerificationCode::updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(5),
                'used' => false,
            ]
        );

        // Reset failed attempts counter upon generating a new OTP
        Cache::forget('otp_attempts:' . $email);
        RateLimiter::clear('verify-otp:' . $email);

        // Send Notification containing the code
        try {
            $user->notify(new \App\Notifications\SendOtpCode($code));
        } catch (\Throwable $exception) {
            Log::error('Failed to send OTP verification code.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send the verification code. Please check your SMTP mail configuration.'
            ], 500);
        }

        // Log OTP Generation
        try {
            DB::table('admin_audit_logs')->insert([
                'user_id' => $user->id,
                'event' => 'otp_generated',
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'successful' => true,
                'message' => '4-digit OTP code generated and sent (5 min validity)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log OTP generation in audit logs', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'A 4-digit verification code has been sent to your email.'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:4'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $code = trim($validated['code']);

        // 1. Check if email is currently in 5-minute lockout
        $lockoutKey = 'otp_lockout:' . $email;
        if (Cache::has($lockoutKey)) {
            $lockoutUntil = (int) Cache::get($lockoutKey);
            $remaining = max(1, $lockoutUntil - time());
            if ($remaining > 0) {
                return response()->json([
                    'status' => 'error',
                    'locked' => true,
                    'lockout_seconds' => $remaining,
                    'message' => 'Too many incorrect attempts. Verification is temporarily locked for 5 minutes.'
                ], 429);
            }
            // Lockout expired
            Cache::forget($lockoutKey);
        }

        // 2. Retrieve active verification code
        $verifyCode = VerificationCode::where('email', $email)
            ->where('used', false)
            ->first();

        if (! $verifyCode) {
            return response()->json([
                'status' => 'error',
                'expired' => true,
                'message' => 'This verification code is no longer valid. Please request a new code.'
            ], 422);
        }

        // 3. Check if the code is expired (5 minute validity)
        if ($verifyCode->expires_at <= now()) {
            return response()->json([
                'status' => 'error',
                'expired' => true,
                'message' => 'This verification code has expired. Please request a new code.'
            ], 422);
        }

        // 4. Check if the code matches
        if ($verifyCode->code !== $code) {
            $attemptsKey = 'otp_attempts:' . $email;
            $failedAttempts = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $failedAttempts, now()->addMinutes(10));

            // Log verification failure
            try {
                DB::table('admin_audit_logs')->insert([
                    'user_id' => null,
                    'event' => 'otp_verification_failed',
                    'email' => $email,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                    'successful' => false,
                    'message' => "Incorrect OTP code entered (Attempt {$failedAttempts}/5)",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {}

            // Check if 5 wrong attempts reached
            if ($failedAttempts >= 5) {
                // Invalidate current OTP
                $verifyCode->update(['used' => true]);
                Cache::forget($attemptsKey);

                // Set 5-minute lockout (300 seconds)
                $lockoutDuration = 300;
                Cache::put($lockoutKey, time() + $lockoutDuration, now()->addSeconds($lockoutDuration));

                return response()->json([
                    'status' => 'error',
                    'locked' => true,
                    'lockout_seconds' => $lockoutDuration,
                    'message' => 'Too many incorrect attempts. Verification is temporarily locked for 5 minutes.'
                ], 422);
            }

            $attemptsRemaining = 5 - $failedAttempts;
            $attemptWord = $attemptsRemaining === 1 ? 'attempt' : 'attempts';

            return response()->json([
                'status' => 'error',
                'attempts_remaining' => $attemptsRemaining,
                'message' => "Incorrect verification code. {$attemptsRemaining} {$attemptWord} remaining."
            ], 422);
        }

        // Mark code as used
        $verifyCode->update(['used' => true]);
        Cache::forget('otp_attempts:' . $email);
        Cache::forget($lockoutKey);

        // Find the user
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found. Please try requesting a new code.'
            ], 404);
        }

        // Mark email as verified and account status as verified
        if (!$user->hasVerifiedEmail()) {
            $user->forceFill([
                'email_verified_at' => now(),
                'account_status' => 'verified',
            ])->save();

            event(new Verified($user));
        } elseif ($user->account_status !== 'verified') {
            $user->update(['account_status' => 'verified']);
        }

        $user->forceFill(['last_active_at' => now()])->save();

        // Authenticate the user
        Auth::login($user);
        $request->session()->regenerate();

        // Log OTP Verification Success
        try {
            DB::table('admin_audit_logs')->insert([
                'user_id' => $user->id,
                'event' => 'otp_verified',
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'successful' => true,
                'message' => 'OTP verification successful. User logged in.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'status' => 'success',
            'redirectUrl' => route('payment.dashboard')
        ]);
    }


    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('payment.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('payment.dashboard');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => Str::lower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->account_status !== 'verified' || !$user->hasVerifiedEmail()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Please verify your email first. Check your inbox or Spam/Junk folder for the verification link.',
                ])->withInput($request->only('email', 'auth_mode'));
            }

            $user->forceFill(['last_active_at' => now()])->save();

            $request->session()->regenerate();

            return redirect()
                ->route('payment.dashboard')
                ->with('show_beta_notice', true);
        }

        // Log failed attempt to admin_audit_logs and warn in log file
        $email = Str::lower(trim($request->input('email')));
        try {
            \Illuminate\Support\Facades\DB::table('admin_audit_logs')->insert([
                'user_id' => null,
                'event' => 'login_failed',
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'successful' => false,
                'message' => "Failed login attempt for account: {$email}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Ignore
        }

        Log::warning('Failed login attempt', [
            'ip' => $request->ip(),
            'email' => $email,
            'user_agent' => $request->userAgent(),
        ]);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email', 'auth_mode'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function checkVerificationStatus(Request $request)
    {
        $email = $request->session()->get('verify_email');
        if (!$email) {
            if (Auth::check() && Auth::user()->hasVerifiedEmail() && Auth::user()->account_status === 'verified') {
                return response()->json([
                    'verified' => true,
                    'redirectUrl' => route('payment.dashboard'),
                ]);
            }
            return response()->json([
                'verified' => false,
            ]);
        }

        $user = User::where('email', $email)->first();
        if ($user && $user->hasVerifiedEmail() && $user->account_status === 'verified') {
            Auth::login($user);
            $user->forceFill(['last_active_at' => now()])->save();
            $request->session()->regenerate();
            $request->session()->forget('verify_email');
            $request->session()->forget('verify_timer_start');

            return response()->json([
                'verified' => true,
                'redirectUrl' => route('payment.dashboard'),
            ]);
        }

        return response()->json([
            'verified' => false,
        ]);
    }

    public function showVerificationNotice(Request $request)
    {
        // If the user just requested a verification link, always show the
        // waiting page — even if they are still authenticated from a
        // previous session.  Log them out so the timer page works cleanly.
        if ($request->session()->has('verify_email')) {
            if (Auth::check()) {
                Auth::guard('web')->logout();
            }
            return response()
                ->view('auth.verify-email')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        // No verify_email in session — the user landed here without going
        // through the registration flow.
        if (Auth::check()) {
            $user = Auth::user();

            // Already verified → dashboard
            if ($user->hasVerifiedEmail() && $user->account_status === 'verified') {
                return redirect()->route('payment.dashboard');
            }

            // Authenticated but not verified (edge case)
            return response()
                ->view('auth.verify-email')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        // Guest with no verify_email session → back to login
        return redirect()->route('login');
    }

    public function showVerifyConfirm(Request $request, int $id, string $hash)
    {
        $token = $request->query('token');
        $ip = $request->ip();
        $userAgent = Str::limit((string) $request->userAgent(), 1000, '');
        $timestamp = now();

        // 1. Check if token exists in request
        if (!$token) {
            $this->logVerificationAttempt(null, 'invalid_link', 'No token provided in verification GET request', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        $tokenHash = hash('sha256', $token);
        $magicLink = MagicLink::where('token_hash', $tokenHash)->first();

        // 2. Check if token exists in DB
        if (!$magicLink) {
            $this->logVerificationAttempt(null, 'invalid_link', 'Magic link token not found on GET', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        // 3. Check if token is expired
        if ($magicLink->expires_at->isPast()) {
            $this->logVerificationAttempt($magicLink->user_id, 'magic_link_expired', "Token expired at {$magicLink->expires_at} on GET", $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Link Expired',
            ]);
        }

        // 4. Check if token is already used
        if ($magicLink->used_at !== null) {
            $this->logVerificationAttempt($magicLink->user_id, 'magic_link_reused_attempt', 'Attempted reuse of already used magic link on GET', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Link Already Used',
            ]);
        }

        // 5. Check if user matches token
        $user = User::find($id);
        if (!$user || $magicLink->user_id !== $user->id || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            $this->logVerificationAttempt($magicLink->user_id, 'invalid_link', 'User mismatch or invalid email hash on GET', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        // Token is valid! Render the landing page with confirmation form
        return view('auth.verify-confirm', [
            'id' => $id,
            'hash' => $hash,
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $token = $request->query('token') ?? $request->input('token');
        $ip = $request->ip();
        $userAgent = Str::limit((string) $request->userAgent(), 1000, '');
        $timestamp = now();

        // 1. Check if token exists
        if (!$token) {
            $this->logVerificationAttempt(null, 'invalid_link', 'No token provided in verification POST request', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        $tokenHash = hash('sha256', $token);
        $magicLink = MagicLink::where('token_hash', $tokenHash)->first();

        // 2. Check if token exists in DB
        if (!$magicLink) {
            $this->logVerificationAttempt(null, 'invalid_link', 'Magic link token not found on POST', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        // 3. Check if token is expired
        if ($magicLink->expires_at->isPast()) {
            $this->logVerificationAttempt($magicLink->user_id, 'magic_link_expired', "Token expired at {$magicLink->expires_at} on POST", $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Link Expired',
            ]);
        }

        // 4. Check if token is already used
        if ($magicLink->used_at !== null) {
            $this->logVerificationAttempt($magicLink->user_id, 'magic_link_reused_attempt', 'Attempted reuse of magic link on POST', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Link Already Used',
            ]);
        }

        // 5. Check if user matches token
        $user = User::find($id);
        if (!$user || $magicLink->user_id !== $user->id || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            $this->logVerificationAttempt($magicLink->user_id, 'invalid_link', 'User mismatch or invalid hash on POST', $ip, $userAgent, $timestamp);
            return view('auth.verify-result', [
                'status' => 'error',
                'message' => 'Invalid Link',
            ]);
        }

        // Verification successful! Mark as used and update user status
        $magicLink->update(['used_at' => $timestamp]);

        if (!$user->hasVerifiedEmail()) {
            $user->forceFill([
                'email_verified_at' => $timestamp,
                'account_status' => 'verified',
            ])->save();

            event(new Verified($user));
        } elseif ($user->account_status !== 'verified') {
            $user->update(['account_status' => 'verified']);
        }

        // Log Magic Link Verified event
        $this->logVerificationAttempt($user->id, 'magic_link_verified', 'Verification Successful', $ip, $userAgent, $timestamp);

        $user->forceFill(['last_active_at' => now()])->save();

        // Authenticate the user
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('verify_email');
        $request->session()->forget('verify_timer_start');

        return view('auth.verify-result', [
            'status' => 'success',
            'message' => 'Verification Successful',
            'redirectUrl' => route('payment.dashboard'),
        ]);
    }

    private function logVerificationAttempt(?int $userId, string $event, string $message, string $ip, string $userAgent, $timestamp): void
    {
        try {
            $email = null;
            if ($userId) {
                $user = User::find($userId);
                $email = $user?->email;
            } else {
                $email = request()->input('email') ?? request()->session()->get('verify_email');
            }

            DB::table('admin_audit_logs')->insert([
                'user_id' => $userId,
                'event' => $event,
                'email' => $email,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'successful' => ($event === 'magic_link_verified'),
                'message' => $message,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log verification attempt in audit logs', [
                'error' => $e->getMessage(),
                'event' => $event,
                'message' => $message,
            ]);
        }

        Log::info("Verification attempt: {$event} - {$message}", [
            'user_id' => $userId,
            'ip' => $ip,
        ]);
    }

    public function resendVerificationLink(Request $request)
    {
        if (Auth::check()) {
            if (Auth::user()->hasVerifiedEmail()) {
                return redirect()->route('payment.dashboard');
            }
            $sessionEmail = Auth::user()->email;
        } else {
            if (!$request->session()->has('verify_email')) {
                abort(403, 'Unauthorized verification resend request.');
            }
            $sessionEmail = $request->session()->get('verify_email');
        }

        $request->validate(['email' => 'required|email|exists:users,email']);

        $email = strtolower(trim($request->email));
        if ($email !== strtolower(trim($sessionEmail))) {
            abort(403, 'Unauthorized verification resend request.');
        }

        // Rate limit resending to 2 requests per 60 seconds per email address
        $limiterKey = 'resend-verification:' . $email;
        if (RateLimiter::tooManyAttempts($limiterKey, 2)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return back()->withErrors([
                'email' => "Please wait {$seconds} seconds before requesting another verification link."
            ]);
        }
        
        RateLimiter::hit($limiterKey, 60);

        $user = User::where('email', $request->email)->first();

        if ($user && !in_array($user->account_status, ['blocked', 'suspended'], true)) {
            if (! $this->sendVerificationLink($user, $request)) {
                // Clear rate limit attempt so they can try again immediately if it failed to send
                RateLimiter::clear($limiterKey);
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'We could not resend the verification link right now. Please try again later.']);
            }
        }

        $request->session()->put('verify_email', $request->email);
        $request->session()->put('verify_timer_start', time());

        return back()->with('success', 'Verification link resent! Please check your inbox or Spam/Junk folder.');
    }

    private function sendVerificationLink(User $user, Request $request): bool
    {
        try {
            $user->sendEmailVerificationNotification();
            return true;
        } catch (Throwable $exception) {
            Log::error('Failed to send enrollment verification link.', [
                'email' => $user->email,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function setOffline(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->forceFill(['last_active_at' => now()->subMinutes(10)])->save();
        }

        return response()->json(['status' => 'success']);
    }
}
