<?php
require_once __DIR__ . '/includes/auth.php';
logout_user();
session_start();
flash('info', 'You have been logged out.');
redirect('login.php');
