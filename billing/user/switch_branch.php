<?php
/** Header branch selector: makes a permitted branch the working branch and reloads the shell. */
require_once "../../addon/conf/connection.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    perm_set_branch((int) ($_POST['branch_id'] ?? 0));
}
header('Location: ../topmain.php');
