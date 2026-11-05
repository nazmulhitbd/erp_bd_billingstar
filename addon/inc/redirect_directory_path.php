<?php
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$script_name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$script_directory = rtrim(dirname($script_name), '/');

if (PHP_SAPI === 'cli-server' && $script_directory !== '' && $request_path === $script_directory) {
    header('Location: ' . $script_directory . '/', true, 308);
    exit;
}