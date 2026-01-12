<?php

// Router para servidor PHP built-in
// Redireciona todas as requisições para index.php

$requestUri = $_SERVER['REQUEST_URI'];
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// Remove query string da URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Se for um arquivo estático que existe, serve diretamente
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false; // Serve o arquivo
}

// Caso contrário, redireciona para index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';

