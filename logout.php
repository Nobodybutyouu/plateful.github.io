<?php
require_once __DIR__ . '/init.php';

if (is_authenticated()) {
    logout();
}

redirect('/login.php');
