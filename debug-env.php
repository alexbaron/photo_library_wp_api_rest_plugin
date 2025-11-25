<?php

/**
 * Debug script pour vérifier la lecture du fichier .env
 * Usage: Accéder via navigateur ou WP-CLI
 */

// Chemin vers le fichier .env
$env_file = __DIR__ . '/.env';

echo "=== DEBUG .env FILE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Environment: " . (defined('WP_DEBUG') && WP_DEBUG ? 'DEBUG' : 'PRODUCTION') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// 1. Vérifier l'existence du fichier
echo "1. Fichier .env\n";
echo "   Path: $env_file\n";
echo "   Exists: " . (file_exists($env_file) ? 'YES' : 'NO') . "\n";

if (file_exists($env_file)) {
    echo "   Readable: " . (is_readable($env_file) ? 'YES' : 'NO') . "\n";
    echo "   Size: " . filesize($env_file) . " bytes\n";
    echo "   Permissions: " . substr(sprintf('%o', fileperms($env_file)), -4) . "\n";
    echo "   Owner: " . (function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($env_file))['name'] ?? 'unknown') : 'unknown') . "\n";
} else {
    echo "   ERROR: File does not exist!\n";
}

echo "\n2. Current Working Directory\n";
echo "   CWD: " . getcwd() . "\n";
echo "   __DIR__: " . __DIR__ . "\n";
echo "   __FILE__: " . __FILE__ . "\n";

// 3. Tester différentes méthodes de lecture
echo "\n3. Test lecture fichier .env\n";

if (file_exists($env_file)) {
    try {
        $content = file_get_contents($env_file);
        echo "   file_get_contents(): " . (strlen($content)) . " caractères lus\n";

        // Afficher les premières lignes (sans valeurs sensibles)
        $lines = explode("\n", $content);
        echo "   Premières lignes:\n";
        foreach (array_slice($lines, 0, 5) as $i => $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                echo "     Line " . ($i + 1) . ": $key=***\n";
            } else {
                echo "     Line " . ($i + 1) . ": $line\n";
            }
        }

    } catch (Exception $e) {
        echo "   ERROR reading file: " . $e->getMessage() . "\n";
    }
}

// 4. Vérifier les variables d'environnement
echo "\n4. Variables d'environnement\n";
$env_vars = ['PINECONE_API_KEY', 'NODE_ENV', 'WP_ENV'];

foreach ($env_vars as $var) {
    $getenv_val = getenv($var);
    $server_val = $_SERVER[$var] ?? null;
    $env_val = $_ENV[$var] ?? null;

    echo "   $var:\n";
    echo "     getenv(): " . ($getenv_val ? 'SET (' . strlen($getenv_val) . ' chars)' : 'NOT SET') . "\n";
    echo "     \$_SERVER: " . ($server_val ? 'SET (' . strlen($server_val) . ' chars)' : 'NOT SET') . "\n";
    echo "     \$_ENV: " . ($env_val ? 'SET (' . strlen($env_val) . ' chars)' : 'NOT SET') . "\n";
}

// 5. Test de parsing manuel
echo "\n5. Test parsing manuel\n";
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $parsed_vars = [];

    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $parsed_vars[$key] = $value;
        }
    }

    echo "   Variables parsées: " . count($parsed_vars) . "\n";
    foreach ($parsed_vars as $key => $value) {
        echo "     $key: " . (strlen($value)) . " caractères\n";
    }
}

// 6. Informations système
echo "\n6. Informations système\n";
echo "   User: " . get_current_user() . "\n";
echo "   SAPI: " . php_sapi_name() . "\n";
echo "   OS: " . PHP_OS . "\n";

if (function_exists('apache_get_modules')) {
    echo "   Apache modules: " . implode(', ', apache_get_modules()) . "\n";
}

echo "\n=== END DEBUG ===\n";
