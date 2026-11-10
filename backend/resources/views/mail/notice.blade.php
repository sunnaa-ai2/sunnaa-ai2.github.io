<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>{{ $heading }}</title></head>
<body style="margin:0;background:#08080b;font-family:Tahoma,Arial,sans-serif;color:#f3f2ee">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#111116;border:1px solid #26262e;border-radius:18px;padding:28px">
        <tr><td style="font-size:22px;font-weight:bold;padding-bottom:10px">صُنّاع <span style="background:#ff2e3b;color:#fff;border-radius:5px;padding:1px 6px;font-size:15px">AI</span></td></tr>
        <tr><td style="font-size:18px;font-weight:bold;padding-bottom:10px">{{ $heading }}</td></tr>
        @foreach ($lines as $line)
        <tr><td style="color:#c9c8d0;font-size:15px;line-height:1.8;padding-bottom:8px">{{ $line }}</td></tr>
        @endforeach
        @if ($buttonUrl)
        <tr><td align="center" style="padding:14px 0 22px"><a href="{{ $buttonUrl }}" style="display:inline-block;background:#ff2e3b;color:#fff;text-decoration:none;font-weight:bold;padding:13px 30px;border-radius:999px">{{ $buttonText }}</a></td></tr>
        @endif
        <tr><td style="color:#8e8d98;font-size:12px;line-height:1.7">@if ($note){{ $note }}<br>@endif سبارك للابتكار والإبداع · مبادرة صُنّاع</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
