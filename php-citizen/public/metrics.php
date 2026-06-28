<?php

header('Content-Type: text/plain');

echo "# HELP php_service_up Service availability\n";
echo "# TYPE php_service_up gauge\n";
echo "php_service_up 1\n";

echo "# HELP php_memory_usage_bytes Current PHP memory usage\n";
echo "# TYPE php_memory_usage_bytes gauge\n";
echo "php_memory_usage_bytes " . memory_get_usage() . "\n";

echo "# HELP php_peak_memory_usage_bytes Peak PHP memory usage\n";
echo "# TYPE php_peak_memory_usage_bytes gauge\n";
echo "php_peak_memory_usage_bytes " . memory_get_peak_usage() . "\n";

$limit = ini_get('memory_limit');

if ($limit === '-1') {
    $bytes = -1;
} else {
    $unit = strtoupper(substr($limit, -1));
    $value = (int)$limit;

    switch ($unit) {
        case 'G':
            $bytes = $value * 1024 * 1024 * 1024;
            break;
        case 'M':
            $bytes = $value * 1024 * 1024;
            break;
        case 'K':
            $bytes = $value * 1024;
            break;
        default:
            $bytes = (int)$limit;
    }
}

echo "# HELP php_memory_limit_bytes PHP memory limit\n";
echo "# TYPE php_memory_limit_bytes gauge\n";
echo "php_memory_limit_bytes {$bytes}\n";