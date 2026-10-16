<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'Dashboard') ?> - Bayline Parking Manager</title>
<script>try{var t=localStorage.getItem('bayline-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/bayline.css').'?v='.filemtime(public_path('css/bayline.css'))) ?>">
<link rel="stylesheet" href="<?= e(asset('css/manager.css').'?v='.filemtime(public_path('css/manager.css'))) ?>">
</head>
<body class="manager-body">
<header class="manager-header">
  <div class="w manager-header-inner">
    <a class="brand" href="<?= e(route('manager.dashboard')) ?>">
      <span class="logo"><?= view('partials.logo')->render() ?></span>
      <span><b>Bayline</b><small>Parking manager console</small></span>
    </a>
    <span class="manager-header-spacer"></span>
    <time class="manager-clock" datetime="<?= e(now()->toIso8601String()) ?>"><?= e(now()->format('D, M j, Y g:i A')) ?></time>
    <span class="manager-user"><b><?= e(auth()->user()->name) ?></b><span>Parking manager</span></span>
    <button class="tt" id="theme" type="button">Dark mode</button>
    <form method="POST" action="<?= e(route('staff.logout')) ?>" class="manager-logout">
      <?= csrf_field() ?>
      <button class="btn ghost sm" type="submit">Log out</button>
    </form>
  </div>
</header>

<?php $activePage = $activePage ?? ''; ?>
<nav class="manager-nav w" aria-label="Manager workspace">
  <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= e(route('manager.dashboard')) ?>"<?= $activePage === 'dashboard' ? ' aria-current="page"' : '' ?>>Dashboard</a>
  <a class="<?= $activePage === 'slots' ? 'active' : '' ?>" href="<?= e(route('manager.slots.index')) ?>"<?= $activePage === 'slots' ? ' aria-current="page"' : '' ?>>Slots</a>
  <?php foreach (['Rates and policies', 'Accounts', 'Records', 'Sales', 'Reports', 'Activity log', 'Incidents', 'Settings'] as $plannedPage) { ?>
    <span class="manager-nav-planned" aria-disabled="true" title="This manager workspace is being built next"><?= e($plannedPage) ?></span>
  <?php } ?>
</nav>

<main class="manager-main w">
  <?php if (session('status')) { ?>
    <div class="manager-feedback manager-feedback-success" role="status"><?= e(session('status')) ?></div>
  <?php } ?>
  <?php if (isset($errors) && $errors->any()) { ?>
    <div class="manager-feedback manager-feedback-error" role="alert">
      <strong>We could not save that change.</strong>
      <ul>
        <?php foreach ($errors->all() as $error) { ?>
          <li><?= e($error) ?></li>
        <?php } ?>
      </ul>
    </div>
  <?php } ?>
  <?= $content ?>
</main>

<footer class="manager-footer">
  <div class="w">
    <span><b>Bayline</b> &middot; Every bay, every ticket.</span>
    <a href="<?= e(route('staff.home')) ?>">Staff workspace</a>
  </div>
</footer>
<script src="<?= e(asset('js/bayline.js').'?v='.filemtime(public_path('js/bayline.js'))) ?>"></script>
</body>
</html>
