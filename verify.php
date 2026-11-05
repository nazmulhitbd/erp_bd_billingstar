<?php
/** Second step of the main sign-in: the code sent by SMS or email (addon/inc/two_step.php). addon/inc/user.php sends people here. */
ob_start();
require_once __DIR__ . '/addon/conf/dbconnect.php';
require_once __DIR__ . '/addon/inc/two_step.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Cache-Control: no-store');

$error = (string) ($_SESSION['tfa_error'] ?? '');
$notice = '';
unset($_SESSION['tfa_error']);
$t = tfa_pending();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $t) {
    if (!hash_equals($t['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Your form expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'resend') {
        $error = tfa_resend();
        $notice = $error === '' ? 'A new code is on its way.' : '';
    } else {
        $result = tfa_verify($_POST['code'] ?? '');
        if (is_array($result)) {
            $u = perm_q('SELECT * FROM admin_user WHERE user_id=?', 'i', [(int) $result['user_id']])[0] ?? null;
            $error = !$u ? 'This user no longer exists.' : perm_login_block($u);
            if ($error === '') {
                perm_start_session($u);
                bs_limit_clear(bs_login_keys($u['user_name'])[0]);
                header('Location: ' . $result['next'], true, 303);
                exit;
            }
        } else {
            $error = $result;
        }
    }
    $t = tfa_pending();
}

$b = brand_info();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $h(($b['company'] !== '' ? $b['company'] : $b['brand']) . ' · Verify sign-in'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="addon/login/login.css?v=<?php echo (int) @filemtime(__DIR__ . '/addon/login/login.css'); ?>">
<style>:root { --brand: <?php echo $h($b['color']); ?>; --brand-ink: <?php echo $h($b['color_ink']); ?>; --brand-deep: <?php echo $b['color_ink'] === '#ffffff' ? 'color-mix(in srgb, var(--brand) 58%, #000)' : 'var(--brand)'; ?>; }
.lg-ok { margin-bottom: 14px; padding: 9px 12px; border-radius: 10px; background: #ecfdf3; color: #067647; font-weight: 500; }
.lg-again { width: 100%; margin-top: 10px; height: 44px; border: 2px solid #475569; border-radius: 10px; background: none; color: var(--ink); font: inherit; font-weight: 500; cursor: pointer; }
.lg-back { display: block; margin-top: 14px; color: var(--muted); font-size: 13px; text-align: center; }
#tfa_code { letter-spacing: .3em; font-size: 18px; }</style>
</head>
<body>
<main class="lg">
  <section class="lg-brand">
    <div class="lg-mark">
      <?php if ($b['logo'] !== ''): ?><img src="<?php echo $h($b['logo']); ?>" alt="<?php echo $h($b['brand']); ?>"><?php else: ?><span class="lg-icon"><i class="bi bi-shield-lock"></i></span><?php endif; ?>
      <div><strong><?php echo $h($b['brand']); ?></strong><?php if ($b['slogan'] !== ''): ?><span><?php echo $h($b['slogan']); ?></span><?php endif; ?></div>
    </div>
    <div class="lg-intro">
      <h1>Verify</h1>
      <p class="lg-headline">Two-step sign-in</p>
      <p>Your password was right. Enter the code we sent to finish signing in.</p>
    </div>
  </section>
  <section class="lg-form">
    <div class="lg-card">
      <h2>Enter code</h2>
      <?php if ($t): ?>
      <p class="lg-sub"><?php echo $h(tfa_sent_text($t)); ?></p>
      <?php if ($notice !== ''): ?><div class="lg-ok" role="status"><?php echo $h($notice); ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="lg-error" role="alert"><?php echo $h($error); ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo $h($t['csrf']); ?>">
        <div class="lg-field">
          <label class="lg-sr" for="tfa_code">Sign-in code</label>
          <div class="lg-input"><i class="bi bi-shield-lock"></i><input id="tfa_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus placeholder="6-digit code"></div>
        </div>
        <button type="submit" class="lg-submit" name="action" value="verify">Verify and sign in</button>
        <button type="submit" class="lg-again" name="action" value="resend" formnovalidate>Send a new code</button>
      </form>
      <?php else: ?>
      <p class="lg-sub">The sign-in could not be finished.</p>
      <div class="lg-error" role="alert"><?php echo $h($error !== '' ? $error : 'This sign-in has expired. Sign in again.'); ?></div>
      <?php endif; ?>
      <a class="lg-back" href="index.php">Back to sign in</a>
    </div>
  </section>
</main>
</body>
</html>
