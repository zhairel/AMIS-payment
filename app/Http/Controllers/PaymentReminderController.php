<?php

namespace App\Http\Controllers;

use App\Models\ReminderCampaign;
use App\Models\ReminderRecipient;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    // ── Index: list all campaigns ─────────────────────────────────────────────

    public function index()
    {
        $campaigns = ReminderCampaign::with('sentBy')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.payment_reminder.index', compact('campaigns'));
    }

    // ── Prepare: create a new DRAFT campaign ──────────────────────────────────

    public function prepare(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'school_year' => ['required', 'string', 'max:20'],
        ]);

        // Guard: refuse if an identical campaign name already exists and is not DRAFT
        $existing = ReminderCampaign::where('name', $data['name'])->first();
        if ($existing) {
            return back()->with('error',
                'A campaign named "' . $data['name'] . '" already exists. '
                . 'Please use a different name or continue with the existing campaign.'
            );
        }

        try {
            $campaign = $this->reminderService->prepareRecipients(
                $data['name'],
                $data['school_year'],
                Auth::id()
            );
        } catch (\Throwable $e) {
            Log::error('[PaymentReminder] prepareRecipients failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to prepare recipients. Please try again.');
        }

        return redirect()
            ->route('admin.reminder.preview', $campaign)
            ->with('success', "Campaign prepared. {$campaign->total_unique} unique parent email(s) found.");
    }

    // ── Preview: show campaign stats + email preview ───────────────────────────

    public function preview(ReminderCampaign $campaign)
    {
        $campaign->load('sentBy');
        $campaign->syncStats(); // Refresh counters

        // Check image availability
        $images = [
            'poster_payment_reminder' => public_path('images/reminder/poster_payment_reminder.png'),
            'poster_payment_info'     => public_path('images/reminder/poster_payment_info.png'),
            'banner_already_paid'     => public_path('images/reminder/banner_already_paid.jpg'),
        ];
        $missingImages = array_filter($images, fn($p) => !file_exists($p));

        return view('admin.payment_reminder.preview', compact('campaign', 'missingImages'));
    }

    // ── Send Test Email (no DB changes) ───────────────────────────────────────

    public function sendTest(Request $request, ReminderCampaign $campaign)
    {
        $data = $request->validate([
            'test_email' => ['required', 'email:rfc,dns'],
        ]);

        try {
            $this->reminderService->sendTestEmail(
                testEmail: $data['test_email'],
                billingMonth: $campaign->billing_month ?? now()->format('Y-m')
            );
            return back()->with('success',
                "✓ Test email sent to {$data['test_email']}. Recipient list and campaign stats were NOT affected."
            );
        } catch (\Throwable $e) {
            Log::error('[PaymentReminder] sendTest failed: ' . $e->getMessage());
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }
    }

    // ── Start: confirm and begin queue delivery ────────────────────────────────

    public function start(ReminderCampaign $campaign)
    {
        if (!$campaign->canStart()) {
            return back()->with('error',
                'This payment reminder campaign has already been started. '
                . 'Existing recipients will not be duplicated.'
            );
        }

        if ($campaign->total_unique === 0) {
            return back()->with('error',
                'No recipients found. Cannot start campaign with zero recipients.'
            );
        }

        try {
            $this->reminderService->startCampaign($campaign);
        } catch (\Throwable $e) {
            Log::error('[PaymentReminder] startCampaign failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to start campaign: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.reminder.preview', $campaign)
            ->with('success',
                "✓ Campaign started! {$campaign->total_pending} email(s) queued for delivery. "
                . "Successfully sent recipients will never receive a duplicate."
            );
    }

    // ── Pause ──────────────────────────────────────────────────────────────────

    public function pause(Request $request, ReminderCampaign $campaign)
    {
        $this->reminderService->pauseCampaign(
            $campaign,
            $request->input('reason', 'Manually paused by staff')
        );

        return back()->with('info',
            'Campaign paused. Already-sent emails will NOT be resent. '
            . 'Click "Resume Pending Emails" to continue.'
        );
    }

    // ── Resume: re-dispatch only PENDING + RETRY ───────────────────────────────

    public function resume(ReminderCampaign $campaign)
    {
        if (!$campaign->canResume()) {
            return back()->with('error',
                'This campaign cannot be resumed in its current state (' . $campaign->status_label . ').'
            );
        }

        try {
            $this->reminderService->resumeCampaign($campaign);
        } catch (\Throwable $e) {
            Log::error('[PaymentReminder] resumeCampaign failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to resume campaign: ' . $e->getMessage());
        }

        $campaign->refresh();
        $queued = $campaign->total_pending + $campaign->total_retry;

        return redirect()
            ->route('admin.reminder.preview', $campaign)
            ->with('success',
                "✓ Resumed! {$queued} unsent email(s) re-queued. Already-SENT recipients are untouched."
            );
    }

    // ── Delivery Logs ──────────────────────────────────────────────────────────

    public function logs(Request $request, ReminderCampaign $campaign)
    {
        $query = $campaign->recipients()->orderByDesc('updated_at');

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        // Filter by email
        if ($search = $request->query('search')) {
            $query->where('normalized_email', 'like', '%' . $search . '%');
        }

        $recipients = $query->paginate(50)->withQueryString();

        return view('admin.payment_reminder.logs', compact('campaign', 'recipients'));
    }
}
