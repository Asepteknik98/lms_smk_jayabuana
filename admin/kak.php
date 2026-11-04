<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/auth.php';
check_access([1]);
require __DIR__.'/../includes/kak_staff_page.php';
