<?php

define('ABSPATH', __DIR__ . '/');

function number_format_i18n($number, $decimals = 0) {
    return number_format((float) $number, (int) $decimals, ',', '.');
}

require_once dirname(__DIR__) . '/includes/Admin/Finance_Analytics_Admin.php';

use wg\membership\Admin\Finance_Analytics_Admin;

function formatting_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$method = new ReflectionMethod(Finance_Analytics_Admin::class, 'money_map');
$exact = $method->invoke(null, ['EUR' => '72239.89']);
$rounded = $method->invoke(null, ['EUR' => '72239.89'], 0);
$other_currency = $method->invoke(null, ['USD' => '1234.50']);

formatting_assert($exact === '72.239,89 €', 'exact finance values use the WordPress locale and euro symbol');
formatting_assert($rounded === '72.240 €', 'summary finance values are rounded to whole euros');
formatting_assert($other_currency === '1.234,50 USD', 'non-euro currencies retain their ISO code');

echo "finance analytics formatting harness: OK\n";
