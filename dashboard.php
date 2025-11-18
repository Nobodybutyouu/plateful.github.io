<?php
require_once __DIR__ . '/init.php';

if (!is_authenticated()) {
    redirect('/login.php');
}

redirect_after_login();
