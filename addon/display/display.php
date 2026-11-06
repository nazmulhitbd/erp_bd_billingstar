<?php
/** Sign-in page: the company panel beside the sign-in form (addon/login/login.php). All text comes from Company & Login Settings. */
include_once(dirname(__FILE__) . '/../../addon/conf/VersionControl.php');
require_once dirname(__FILE__) . '/../inc/brand.php';
$b = brand_info();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$company = $b['company'] !== '' ? $b['company'] : $b['brand'];
$contacts = array_filter([
    ['geo-alt', $b['address'], ''],
    ['telephone', $b['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $b['phone'])],
    ['phone', $b['mobile'], 'tel:' . preg_replace('/[^0-9+]/', '', $b['mobile'])],
    ['envelope', $b['email'], 'mailto:' . $b['email']],
    ['globe2', preg_replace('#^https?://#i', '', $b['website']), $b['website']],
    ['clock', $b['hours'], ''],
], fn($c) => $c[1] !== '');
$notices = $b['show_notices'] ? brand_notices() : [];
/* Drawn twice: inside the brand panel on wide screens, under the form on phones (login.css shows one). */
$contactList = function () use ($contacts, $h) {
    if (!$contacts) {
        return;
    }
    ?>
    <ul class="lg-contact" aria-label="Contact">
      <?php foreach ($contacts as [$icon, $text, $href]): ?>
      <li><i class="bi bi-<?php echo $icon; ?>"></i><?php if ($href !== ''): ?><a href="<?php echo $h($href); ?>"<?php echo $icon === 'globe2' ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo $h($text); ?></a><?php else: ?><span><?php echo nl2br($h($text)); ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $h($company . ' · Sign in'); ?></title>
<link rel="icon" type="image/svg+xml" href="addon/images/petabill-favicon.svg"><meta name="copyright" content="&copy; Petacore Technology. PetaBill is a trademark of Petacore Technology.">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="addon/login/login.css?v=<?php echo (int) @filemtime(dirname(__FILE__) . '/../login/login.css'); ?>">
<style>:root { --brand: <?php echo $h($b['color']); ?>; --brand-ink: <?php echo $h($b['color_ink']); ?>; --brand-deep: <?php echo $b['color_ink'] === '#ffffff' ? 'color-mix(in srgb, var(--brand) 58%, #000)' : 'var(--brand)'; ?>; }</style>
</head>
<body>
<main class="lg">
  <section class="lg-brand">
    <div class="lg-mark">
      <?php if ($b['logo'] !== ''): ?><img src="<?php echo $h($b['logo']); ?>" alt="<?php echo $h($b['brand']); ?>"><?php else: ?><img src="addon/images/petabill-mark.svg" alt="PetaBill"><?php endif; ?>
      <div><strong><?php echo $h($b['brand']); ?></strong><?php if ($b['slogan'] !== ''): ?><span><?php echo $h($b['slogan']); ?></span><?php endif; ?></div>
    </div>
    <div class="lg-intro">
      <h1>Welcome</h1>
      <p class="lg-headline"><?php echo $h($b['title'] !== '' ? $b['title'] : $company); ?></p>
      <?php if ($b['welcome'] !== ''): ?><p><?php echo nl2br($h($b['welcome'])); ?></p><?php endif; ?>
    </div>
    <?php $contactList(); ?>
  </section>
  <section class="lg-form">
    <div class="lg-card">
      <?php include_once(dirname(__FILE__) . '/../login/login.php'); ?>
      <?php foreach ($notices as $n): ?>
      <aside class="lg-notice"><i class="bi bi-megaphone"></i><div><b><?php echo $h($n['title']); ?></b><p><?php echo nl2br($h($n['body'])); ?></p></div></aside>
      <?php endforeach; ?>
    </div>
    <?php $contactList(); ?>
    <footer class="lg-foot"><?php echo StatusBarMMSS(); ?></footer>
  </section>
</main>
</body>
</html>
