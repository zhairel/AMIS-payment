<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>AMIS Payment Reminder – Monthly School Fees</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f4f0; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; display: block; max-width: 100%; height: auto; }
        a { color: #1a6b2f; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-wrapper { width: 100% !important; }
            .email-body { padding: 20px 16px !important; }
            .poster-img { width: 100% !important; max-width: 100% !important; }
            .header-logo { height: 40px !important; }
        }
    </style>
</head>
<body style="background-color:#f0f4f0; padding:24px 0; margin:0;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td align="center" style="padding:0 12px;">

      <!-- Outer container -->
      <table class="email-wrapper" role="presentation" width="650" cellpadding="0" cellspacing="0" border="0"
             style="max-width:650px; width:100%; background:#ffffff; border-radius:10px; overflow:hidden;
                    box-shadow:0 2px 12px rgba(0,0,0,0.10);">

        <!-- ── HEADER ───────────────────────────────────────────────────────── -->
        <tr>
          <td style="background: linear-gradient(135deg, #1a6b2f 0%, #2d8a47 100%); padding:24px 28px; text-align:center;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center">
                  <!-- School logo embedded as CID (email-safe, no broken image) -->
                  <img
                       src="{{ $message->embed(public_path('images/AMIS_Logo.png')) }}"
                       alt="Al Munawwara Islamic School"
                       height="72"
                       style="height:72px; width:auto; display:inline-block; border-radius:50%; background:#ffffff; padding:4px;">
                </td>
              </tr>
              <tr>
                <td align="center" style="padding-top:12px;">
                  <p style="color:#d4edda; font-size:11px; letter-spacing:2px; font-weight:700; margin:0;">
                    المدرسة المنورة الإسلامية
                  </p>
                  <p style="color:#ffffff; font-size:17px; font-weight:800; letter-spacing:1.5px; margin:5px 0 0 0; text-transform:uppercase;">
                    Al Munawwara Islamic School
                  </p>
                  <p style="color:#a7d7b3; font-size:11px; letter-spacing:1px; margin:4px 0 0 0;">
                    Automated Payment Reminder System
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── BODY ─────────────────────────────────────────────── -->
        <tr>
          <td class="email-body" style="padding:32px 36px; background:#ffffff;">

            <!-- Greeting -->
            <p style="font-size:20px; font-weight:800; color:#1a6b2f; margin:0 0 6px 0;">
              Assalamu Alaikum{{ !empty($recipientName) && $recipientName !== 'Valued Family' ? ', ' . $recipientName : '' }}!
            </p>

            @if(!empty($billingMonth))
              <p style="margin:0 0 14px 0; color:#15803d; font-size:13px; font-weight:700;">
                Billing Cycle: {{ \Carbon\Carbon::parse(strlen($billingMonth) === 7 ? $billingMonth . '-01' : $billingMonth)->format('F Y') }}
              </p>
            @endif

            <!-- Message -->
            <p style="font-size:15px; color:#333333; line-height:1.7; margin:0 0 14px 0;">
              This is a friendly reminder regarding any <strong>pending monthly school payment</strong>.
            </p>
            <p style="font-size:15px; color:#333333; line-height:1.7; margin:0 0 18px 0;">
              If you still have an outstanding balance, kindly settle your payment as soon as possible.
            </p>

            <!-- Anti-trimming invisible unique token to prevent Gmail Show Quoted Text truncation -->
            <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;">
              Reminder UUID: {{ (string) \Illuminate\Support\Str::uuid() }} • Time: {{ microtime(true) }}
            </div>

            <!-- Already Paid Notice -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0;">
              <tr>
                <td style="background:#fff8e1; border-left:4px solid #f59e0b; border-radius:0 6px 6px 0;
                            padding:14px 18px;">
                  <p style="font-size:13px; font-weight:800; color:#92400e; margin:0; text-transform:uppercase; letter-spacing:0.5px;">
                    ⚠ NOTE: IF YOU HAVE ALREADY PAID, PLEASE DISREGARD THIS MESSAGE. NO REPLY IS NECESSARY.
                  </p>
                </td>
              </tr>
            </table>

            <!-- ── IMAGE 1 – PAYMENT REMINDER POSTER ───────────── -->
            <img class="poster-img"
                 src="{{ $message->embed($image1Path) }}"
                 alt="AMIS Monthly Payment Reminder Poster"
                 width="578"
                 style="width:100%; max-width:578px; height:auto; display:block; margin:0 auto 20px auto;
                        border-radius:8px;">

            <!-- ── IMAGE 2 – PAYMENT INFORMATION POSTER ─────────── -->
            <img class="poster-img"
                 src="{{ $message->embed($image2Path) }}"
                 alt="AMIS Payment Information – BDO, GCash, Maya"
                 width="578"
                 style="width:100%; max-width:578px; height:auto; display:block; margin:0 auto 20px auto;
                        border-radius:8px;">

            <!-- ── IMAGE 3 – ALREADY PAID / DO NOT REPLY BANNER ── -->
            <img class="poster-img"
                 src="{{ $message->embed($image3Path) }}"
                 alt="NOTE: If you already paid, ignore this and do not reply back"
                 width="578"
                 style="width:100%; max-width:578px; height:auto; display:block; margin:0 auto 24px auto;
                        border-radius:8px;">

            <!-- Receipt instruction -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
              <tr>
                <td style="background:#e8f5e9; border:2px solid #4caf50; border-radius:10px; padding:18px 20px;">
                  <p style="font-size:16px; font-weight:900; color:#1a5c28; margin:0 0 6px 0; letter-spacing:0.2px;">
                    📧 After successful fund transfer:
                  </p>
                  <p style="font-size:16px; font-weight:800; color:#1a6b2f; margin:0; line-height:1.6;">
                    Please do not forget to send the payment receipt to<br>
                    <a href="mailto:amisfinance2324@gmail.com"
                       style="color:#1a5c28; font-weight:900; font-size:17px; text-decoration:underline;">amisfinance2324@gmail.com</a>
                  </p>
                </td>
              </tr>
            </table>

            <!-- Closing -->
            <p style="font-size:15px; color:#333333; line-height:1.7; margin:0 0 6px 0;">
              Thank you for your continued cooperation and support.
            </p>
            <p style="font-size:15px; color:#1a6b2f; font-weight:700; margin:0 0 24px 0;">
              Jazakumullahu Khairan.
            </p>

            <!-- Signature -->
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                   style="border-top:2px solid #e8f5e9; padding-top:16px; margin-top:8px; width:100%;">
              <tr>
                <td>
                  <p style="font-size:14px; font-weight:800; color:#1a6b2f; margin:0; letter-spacing:0.5px; text-transform:uppercase;">
                    SUPPORT STAFF
                  </p>
                  <p style="font-size:13px; color:#555555; margin:4px 0 0 0;">
                    Al Munawwara Islamic School
                  </p>
                  <p style="font-size:12px; color:#777777; margin:2px 0 0 0;">
                    <a href="mailto:amisfinance2324@gmail.com" style="color:#1a6b2f;">amisfinance2324@gmail.com</a>
                  </p>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- ── FOOTER ────────────────────────────────────────────── -->
        <tr>
          <td style="background:#1a6b2f; padding:18px 28px; text-align:center;">
            <p style="font-size:12px; color:#a7d7b3; margin:0; line-height:1.6;">
              This is an automated payment reminder sent by the AMIS Support Staff.<br>
              <strong style="color:#ffffff;">Please do not reply to this email.</strong>
              If you have questions, contact us at
              <a href="mailto:amisfinance2324@gmail.com" style="color:#86efac;">amisfinance2324@gmail.com</a>.
            </p>
            <p style="font-size:11px; color:#6dba7f; margin:10px 0 0 0;">
              © {{ date('Y') }} Al Munawwara Islamic School — All rights reserved.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
