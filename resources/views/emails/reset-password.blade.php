<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f1f3f4;font-family:Arial,Helvetica,sans-serif;color:#1b2127">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f3f4;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border:1px solid #d3d8dc;border-radius:10px">
      <tr><td style="background:#1b2127;padding:18px 24px;border-radius:10px 10px 0 0">
        <span style="font-size:22px;font-weight:bold;color:#f2b705">Bayline</span>
        <span style="font-size:12px;color:#c5ccd1;margin-left:8px">Every bay, every ticket.</span>
      </td></tr>
      <tr><td style="padding:24px">
        <p style="margin:0 0 12px;font-size:16px">Hi {{ $name }},</p>
        <p style="margin:0 0 20px;font-size:15px;line-height:1.5">Someone asked to choose a new password for your Bayline account. Use the button to set it.</p>
        <p style="margin:0 0 20px;text-align:center">
          <a href="{{ $link }}" style="display:inline-block;background:#f2b705;color:#2b2200;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:6px">Choose a new password</a>
        </p>
        <p style="margin:0 0 20px;font-size:14px;color:#55616b;text-align:center">The link works for {{ $minutes }} minutes and can be used once.</p>
        <p style="margin:0 0 6px;font-size:13px;color:#55616b">Or paste this link into your browser:</p>
        <p style="margin:0 0 20px;font-size:12px;word-break:break-all;color:#55616b">{{ $link }}</p>
        <p style="margin:0;font-size:13px;color:#55616b">If you did not ask for this, you can ignore this email. Your password stays the same until the link is used.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
