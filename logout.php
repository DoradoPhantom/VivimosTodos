<?php
declare(strict_types=1);

// Cierra sesion y vuelve al login
require_once __DIR__ . '/includes/auth.php';
logout_user();
redirect('login.php');
