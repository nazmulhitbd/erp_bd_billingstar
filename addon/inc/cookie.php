<?php
/** The sign-in page clears what a previous visit left: rows per page and the old copy-protection cookies. */
function cookiefree()
{
    setcookie("limit", "",0,"/");
    setcookie("authenticatechk", "",0, "/");
    setcookie("authenmac", "",0, "/");
}
