<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
redirect('index.php');
