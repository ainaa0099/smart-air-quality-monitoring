<?php
header('Content-Type: text/plain');

echo "# HELP php_service_up Service availability
";
echo "# TYPE php_service_up gauge
";
echo "php_service_up 1
";

echo "# HELP php_memory_usage_bytes Current PHP memory usage
";
echo "# TYPE php_memory_usage_bytes gauge
";
echo "php_memory_usage_bytes " . memory_get_usage() . "
";

echo "# HELP php_peak_memory_usage_bytes Peak PHP memory usage
";
echo "# TYPE php_peak_memory_usage_bytes gauge
";
echo "php_peak_memory_usage_bytes " . memory_get_peak_usage() . "
";

echo "# HELP php_memory_limit_bytes PHP memory limit
";
echo "# TYPE php_memory_limit_bytes gauge
";
echo "php_memory_limit_bytes " . memory_get_limit() . "
";