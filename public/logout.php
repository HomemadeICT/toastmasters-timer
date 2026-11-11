<?php
require_once __DIR__ . '/../src/bootstrap.php';

require_method('POST');
require_csrf();
logout_user();
redirect('login.php');
