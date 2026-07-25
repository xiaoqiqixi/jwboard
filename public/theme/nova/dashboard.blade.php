<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#07111f">
  <meta name="color-scheme" content="dark">
  <title>{{ $title }}</title>
  <link rel="stylesheet" href="/theme/{{ $theme }}/assets/nova.css?v={{ $version }}">
  <link rel="stylesheet" href="/theme/{{ $theme }}/assets/nova-performance.css?v={{ $version }}">
</head>
<body data-accent="{{ $theme_config['accent_color'] ?? 'cyan' }}">
  <div id="app" aria-live="polite"></div>
  <script>
    window.NOVA_SETTINGS = {!! json_encode([
      'title' => $title,
      'description' => $description,
      'logo' => $logo,
      'announcement' => $theme_config['announcement'] ?? '',
      'turnstile' => [
        'enabled' => (bool) config('services.turnstile.enabled'),
        'siteKey' => config('services.turnstile.site_key'),
      ],
      'adminPath' => config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))),
      'version' => $version
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
  </script>
  @if (config('services.turnstile.enabled'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
  @endif
  <script src="/theme/{{ $theme }}/assets/nova.js?v={{ $version }}" defer></script>
  {!! $theme_config['custom_html'] ?? '' !!}
</body>
</html>
