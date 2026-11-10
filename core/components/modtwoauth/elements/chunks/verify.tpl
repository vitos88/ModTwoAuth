<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>[[+title]]</title>
<style>
html,body{margin:0;padding:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f4f6f8;color:#333}
.m2a-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box}
.m2a-card{width:min(520px,100%);background:#fff;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.16);padding:30px;box-sizing:border-box;border-top:4px solid #1976d2}
.m2a-title{font-size:23px;font-weight:600;margin-bottom:12px}
.m2a-hint{font-size:14px;line-height:1.5;color:#555;margin-bottom:20px}
.m2a-error{background:#ffebee;color:#c62828;padding:11px 13px;border-radius:6px;margin-bottom:16px;font-size:14px}
.m2a-input{width:100%;box-sizing:border-box;padding:14px;border:1px solid #ccc;border-radius:6px;text-align:center;font-size:22px;letter-spacing:7px;margin-bottom:12px}
.m2a-button{width:100%;padding:14px;border:0;border-radius:6px;background:#1976d2;color:#fff;font-weight:600;font-size:15px;cursor:pointer}
.m2a-button:disabled{opacity:.6;cursor:wait}
.m2a-back{display:block;text-align:center;margin-top:18px;color:#777;text-decoration:none;font-size:13px}
/* Стили можно полностью заменить в пользовательском чанке. */
[[+custom_css]]
</style>
</head>
<body>
<div class="m2a-wrap"><div class="m2a-card">
<div class="m2a-title">[[+heading]]</div>
[[+error_html]]
<div class="m2a-hint">[[+hint]]</div>
[[+content]]
<a class="m2a-back" href="[[+back_url]]">[[+back_text]]</a>
</div></div>
</body>
</html>
