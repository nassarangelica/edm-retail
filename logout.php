<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) audit('LOGOUT', 'users', user()['id']);
$_SESSION = [];
session_destroy();
session_start();
flash('info', 'You have been logged out.');
redirect('index.php');
