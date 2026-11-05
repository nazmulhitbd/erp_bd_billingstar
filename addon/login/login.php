<?php
/** Sign-in form. addon/inc/user.php handles the post and calls display_message() with any error. */
$login_branches = perm_ready() ? perm_q('SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name') : [];
$login_portals = brand_info()['show_portals'];
?>
<script>if (self.parent.frames.length != 0) self.parent.location = document.location;</script>
<h2>Sign in</h2>
<p class="lg-sub">Use the login your office gave you.</p>
<?php if ($finalversion == 0): ?><p class="lg-demo">Demo login: demo / demo</p><?php endif; ?>
<div class="lg-error" id="cdr_login_message" role="alert" hidden></div>
<form name="ctl01" method="post" action="index.php" id="ctl01">
  <?php if (count($login_branches) > 1): ?>
  <div class="lg-field">
    <label class="lg-sr" for="branch_id">Branch</label>
    <div class="lg-input"><i class="bi bi-diagram-3"></i><select name="branch_id" id="branch_id">
      <option value="0">My default branch</option>
      <?php foreach ($login_branches as $lb): ?><option value="<?php echo (int) $lb['branch_id']; ?>"<?php echo (int) ($_POST['branch_id'] ?? 0) === (int) $lb['branch_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($lb['branch_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
    </select></div>
  </div>
  <?php endif; ?>
  <div class="lg-field">
    <label class="lg-sr" for="InputLogin">User name</label>
    <div class="lg-input"><i class="bi bi-person"></i><input type="text" name="user_name" id="InputLogin" placeholder="User name" required autofocus autocomplete="username" value="<?php echo htmlspecialchars((string) ($_POST['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
  </div>
  <div class="lg-field">
    <label class="lg-sr" for="InputPassword">Password</label>
    <div class="lg-input"><i class="bi bi-lock"></i><input type="password" name="password" id="InputPassword" placeholder="Password" required autocomplete="current-password"><button type="button" class="lg-eye" id="lg-eye" aria-label="Show password" aria-pressed="false">Show</button></div>
  </div>
  <label class="lg-check"><input type="checkbox" id="lg-remember"> Remember my user name</label>
  <button type="submit" class="lg-submit" name="okButton" id="okButton" value="Login">Sign in</button>
</form>
<?php if ($login_portals): ?>
<div class="lg-or">Or</div>
<nav class="lg-links" aria-label="Other sign-in pages"><a href="customer_portal/login.php"><i class="bi bi-people"></i> Customer portal</a><a href="sr_portal/login.php"><i class="bi bi-person-badge"></i> Sales staff portal</a></nav>
<?php endif; ?>
<script>
function display_message(message) {
  var box = document.getElementById('cdr_login_message');
  box.textContent = message;
  box.hidden = !message;
  return false;
}
document.getElementById('lg-eye').addEventListener('click', function () {
  var input = document.getElementById('InputPassword');
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  this.setAttribute('aria-pressed', show ? 'true' : 'false');
  this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  this.textContent = show ? 'Hide' : 'Show';
});
(function () {
  var key = 'billing_star_login_name';
  var login = document.getElementById('InputLogin');
  var remember = document.getElementById('lg-remember');
  try {
    var saved = localStorage.getItem(key);
    if (saved) {
      remember.checked = true;
      if (!login.value) login.value = saved;
      document.getElementById('InputPassword').focus();
    }
    document.getElementById('ctl01').addEventListener('submit', function () {
      try {
        if (remember.checked) localStorage.setItem(key, login.value);
        else localStorage.removeItem(key);
      } catch (e) {}
    });
  } catch (e) {
    remember.parentNode.hidden = true;
  }
})();
</script>
