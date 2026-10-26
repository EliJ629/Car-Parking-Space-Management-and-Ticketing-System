<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'Dashboard') ?> - Bayline Parking Manager</title>
<script>(function(){var t=null;try{t=localStorage.getItem('bayline-theme')}catch(e){}if(t!=='dark'&&t!=='light'){var m=document.cookie.match(/(?:^|; )bayline-theme=(dark|light)/);t=m?m[1]:null}if(t)document.documentElement.setAttribute('data-theme',t)})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e('/css/bayline.css?v='.filemtime(public_path('css/bayline.css'))) ?>">
<link rel="stylesheet" href="<?= e('/css/manager.css?v='.filemtime(public_path('css/manager.css'))) ?>">
<link rel="stylesheet" href="<?= e('/css/pager.css?v='.filemtime(public_path('css/pager.css'))) ?>">
</head>
<body class="manager-body">
<header class="manager-header">
  <div class="w manager-header-inner">
    <a class="brand" href="<?= e(route('manager.dashboard')) ?>">
      <span class="logo"><?= view('partials.logo')->render() ?></span>
      <span><b>Bayline</b><small>Parking manager console</small></span>
    </a>
    <span class="manager-header-spacer"></span>
    <time class="manager-clock" datetime="<?= e(now()->setTimezone('Asia/Manila')->toIso8601String()) ?>">
    <?= e(now()->setTimezone('Asia/Manila')->format('D, M j, Y g:i A')) ?>
</time>

    <a class="manager-user" href="<?= e(route('manager.my-account')) ?>"><b><?= e(auth()->user()->name) ?></b><span>Parking manager</span></a>
    <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
    <form method="POST" action="<?= e(route('staff.logout')) ?>" class="manager-logout">
      <?= csrf_field() ?>
      <button class="btn ghost sm" type="submit">Log out</button>
    </form>
  </div>
</header>

<?php $activePage = $activePage ?? ''; ?>
<nav class="manager-nav w" aria-label="Manager workspace">
  <?php
  $managerLinks = [
      'dashboard' => ['Dashboard', 'manager.dashboard'],
      'map' => ['Live map', 'manager.map'],
      'slots' => ['Slots', 'manager.slots.index'],
      'rates' => ['Rates and policies', 'manager.rates.index'],
      'accounts' => ['Accounts', 'manager.accounts.index'],
      'records' => ['Records', 'manager.records.index'],
      'sales' => ['Sales', 'manager.sales.index'],
      'reports' => ['Reports', 'manager.reports.index'],
      'activity' => ['Activity log', 'manager.activity.index'],
      'incidents' => ['Incidents', 'manager.incidents.index'],
      'bans' => ['Banned vehicles', 'manager.bans.index'],
      'landing' => ['Landing page', 'manager.landing.edit'],
      'settings' => ['Settings', 'manager.settings.index'],
  ];
foreach ($managerLinks as $page => [$label, $routeName]) {
    $active = $activePage === $page;
    ?>
    <a class="<?= $active ? 'active' : '' ?>" href="<?= e(route($routeName)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php } ?>
</nav>

<main class="manager-main w">
  <?php if (session('status')) { ?>
    <div class="manager-feedback manager-feedback-success" role="status"><?= e(session('status')) ?></div>
  <?php } ?>
  <?php if (session('temporaryPassword')) { ?>
    <div class="manager-feedback" role="status">
      <strong>Temporary password (shown once)</strong>
      <code class="manager-temporary-password"><?= e(session('temporaryPassword')) ?></code>
      <span>Copy it now and share it securely with the staff member.</span>
    </div>
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
<script src="<?= e(asset('js/plate.js').'?v='.filemtime(public_path('js/plate.js'))) ?>"></script>
<script src="<?= e(asset('js/table-pager.js').'?v='.filemtime(public_path('js/table-pager.js'))) ?>"></script>
</body>
</html>
