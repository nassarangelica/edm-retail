<?php
/**
 * EDM Retail - configuration
 * Change these values to match your XAMPP setup.
 */
define('APP_NAME', 'EDM Retail');
define('BASE_URL', '/edm-retail');          // folder name inside C:\xampp\htdocs\
define('CURRENCY', '₱');

// Database (XAMPP defaults: user root, empty password)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'edm_retail');
define('DB_USER', 'root');
define('DB_PASS', '');

// Shared secret used by the external Logistics Provider when calling api/shipment.php
define('LOGISTICS_API_KEY', 'edm-logistics-demo-key-2026');

date_default_timezone_set('Asia/Manila');
