<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\TrustCloudflareHeaders::class);
        $middleware->append(\App\Http\Middleware\IdleTimeout::class);

        $middleware->alias([
            'applicant' => \App\Http\Middleware\ApplicantOnly::class,
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'finance' => \App\Http\Middleware\FinanceOnly::class,
        ]);

        // Trust all proxies (cPanel shared hosting / CloudFlare).
        // This ensures the request scheme is detected correctly for signed URL validation.
        $middleware->trustProxies(at: '*');

        // The public email-start endpoint is already throttled and only accepts
        // an email address. Excluding it avoids cPanel/browser session-cookie
        // mismatches causing Page Expired (419) before users can request a link.
        $middleware->validateCsrfTokens(except: [
            'register',
            'g-callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, \Illuminate\Http\Request $request) {
            if ($request->is('email/verify/*')) {
                return response()
                    ->view('auth.verify-result', [
                        'status' => 'error',
                        'message' => 'Invalid or Expired Link',
                    ], 403)
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma', 'no-cache');
            }

            return null;
        });

        $exceptions->reportable(function (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 429) {
                $request = request();
                $email = $request->input('email') ?? $request->session()->get('verify_email');
                $userId = auth()->id() ?? ($request->user()?->id);
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                
                try {
                    \Illuminate\Support\Facades\DB::table('admin_audit_logs')->insert([
                        'user_id' => $userId,
                        'event' => 'rate_limit_exceeded',
                        'email' => $email,
                        'ip_address' => $request->ip(),
                        'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 1000, ''),
                        'successful' => false,
                        'message' => "Rate limit exceeded at: " . $request->path() . " (Method: " . $request->method() . ")",
                        'metadata' => json_encode([
                            'retry_after' => $retryAfter,
                            'route' => $request->route()?->getName(),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $tblException) {
                    // Fail silently if DB connection is unavailable
                }

                \Illuminate\Support\Facades\Log::warning('Rate Limit Exceeded (429)', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'endpoint' => $request->fullUrl(),
                    'email' => $email,
                    'retry_after' => $retryAfter,
                ]);
            }
        });
    })
    ->withProviders([
        \SocialiteProviders\Manager\ServiceProvider::class,
    ])
    ->create();
