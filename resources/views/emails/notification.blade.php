@php
    $accent = $brand['accent'] ?? '#4f46e5';
    $rows = $rows ?? [];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px -20px rgba(15,23,42,.25)">
  <tr><td style="background:{{ $accent }};background-image:linear-gradient(135deg,{{ $accent }} 0%,#7c3aed 140%);padding:28px 32px;color:#fff">
    <div style="font-size:12px;letter-spacing:.2em;text-transform:uppercase;opacity:.85">{{ $brand['name'] }}</div>
    <div style="font-size:24px;font-weight:800;margin-top:6px;line-height:1.2">{{ $title }}</div>
  </td></tr>
  <tr><td style="padding:28px 32px 8px">
    @if(!empty($intro))<p style="margin:0 0 18px;font-size:16px;line-height:1.55;color:#334155">{!! nl2br(e($intro)) !!}</p>@endif
    @if(count($rows))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden">
      @foreach($rows as [$label, $value])
      <tr>
        <td style="padding:11px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;width:40%">{{ $label }}</td>
        <td style="padding:11px 14px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #f1f5f9">{{ $value }}</td>
      </tr>
      @endforeach
    </table>
    @endif
    @if(!empty($cta))
    <div style="text-align:center;margin:26px 0 6px">
      <a href="{{ $cta['url'] }}" style="display:inline-block;background:{{ $accent }};color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:12px">{{ $cta['label'] }}</a>
    </div>
    @endif
    @if(!empty($cta2))
    <div style="text-align:center;margin:4px 0 6px">
      <a href="{{ $cta2['url'] }}" style="display:inline-block;color:{{ $accent }};text-decoration:none;font-weight:600;font-size:14px;padding:10px 20px">{{ $cta2['label'] }}</a>
    </div>
    @endif
    @if(!empty($note))<p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#64748b">{!! nl2br(e($note)) !!}</p>@endif
  </td></tr>
  <tr><td style="padding:20px 32px 28px;border-top:1px solid #f1f5f9;font-size:13px;color:#64748b;line-height:1.6">
    <strong style="color:#0f172a">{{ $brand['name'] }}</strong>
    @if(!empty($brand['address']))<br>{{ $brand['address'] }}@endif
    @if(!empty($brand['phone']))<br>{{ $brand['phone'] }}@endif
    @if(!empty($brand['url']))<br><a href="{{ $brand['url'] }}" style="color:{{ $accent }};text-decoration:none">{{ str_replace('https://', '', $brand['url']) }}</a>@endif
  </td></tr>
</table>
<p style="font-size:11px;color:#94a3b8;margin:18px 0 0;line-height:1.6">
  <a href="https://joow.fr" style="color:#94a3b8;text-decoration:none"><img src="https://app.joow.fr/brand/joow-logo-email.png" width="64" height="28" alt="Joow" style="display:inline-block;vertical-align:middle;width:64px;height:auto;border:0;margin-right:6px">Envoyé via Joow</a>
</p>
</td></tr>
</table>
</body>
</html>
