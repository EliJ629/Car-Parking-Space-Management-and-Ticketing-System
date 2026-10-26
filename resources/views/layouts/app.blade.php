<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Bayline')</title>
<script>(function(){var t=null;try{t=localStorage.getItem('bayline-theme')}catch(e){}if(t!=='dark'&&t!=='light'){var m=document.cookie.match(/(?:^|; )bayline-theme=(dark|light)/);t=m?m[1]:null}if(t)document.documentElement.setAttribute('data-theme',t)})();</script>
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
