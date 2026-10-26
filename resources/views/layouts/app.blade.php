<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Bayline')</title>
<script>try{var t=localStorage.getItem('bayline-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/css/bayline.css?v={{ filemtime(public_path('css/bayline.css')) }}">
@stack('head')
</head>
<body>
@yield('body')
<script src="{{ asset('js/bayline.js') }}?v={{ filemtime(public_path('js/bayline.js')) }}"></script>
@stack('scripts')
</body>
</html>
