<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

check_access([2]);

header('Location: materi.php');
exit;
