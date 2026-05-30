<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::logout();
redirect(SITE_URL . '/index.php');
