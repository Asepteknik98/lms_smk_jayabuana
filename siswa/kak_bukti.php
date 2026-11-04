<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/auth.php';
check_access([3]);
require __DIR__.'/../includes/kak_bukti_view.php';
