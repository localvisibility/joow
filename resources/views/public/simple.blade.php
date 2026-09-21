<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#08080c;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
.card{max-width:460px;width:92vw;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:40px 32px;text-align:center}
.icon{font-size:44px}
h1{font-size:22px;margin:14px 0 8px;color:#fff}
p{color:#94a3b8;line-height:1.55;margin:0}
a.btn{display:inline-block;margin-top:22px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px}
small{display:block;margin-top:26px;color:#475569;font-size:12px}
</style>
</head>
<body>
<div class="card">
  <div class="icon">{{ $icon ?? '✅' }}</div>
  <h1>{{ $title }}</h1>
  <p>{{ $message }}</p>
  @if(!empty($url))<a class="btn" href="{{ $url }}">{{ $urlLabel ?? 'Retour au site' }}</a>@endif
  <small>Joow</small>
</div>
</body>
</html>
