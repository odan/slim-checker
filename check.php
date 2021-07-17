<?php

//
// Slim 4 - Server Setup Tester
//

if (!isset($_SESSION)) {
    session_name('phptest');
    session_start();
}

echo "<!DOCTYPE html>\n<html><head>\n";
echo "<style>html * { font-size: 1em !important; color: #000; font-family:'Courier New'; }</style>";
echo "</head><body>";

ob_start();
phpinfo();
$phpinfo = ob_get_clean();

if (strpos($phpinfo, 'Windows NT') !== false) {
    write_info('System', 'Windows');
} else {
    write_info('System', 'Linux');
}

write_info('Webserver', $_SERVER["SERVER_SOFTWARE"] ?? '?');

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.2.0') >= 0) {
    write_ok('PHP-Version', $phpVersion);
} else {
    write_error('PHP-Version', $phpVersion);
}

$phpSapi = PHP_SAPI;
write_info('PHP-SAPI', $phpSapi);

// Detect apache mode_rewrite
ob_start();
phpinfo(INFO_MODULES);
$contents = ob_get_contents();
ob_end_clean();
if (strpos($contents, 'mod_rewrite') !== false) {
    write_ok('Apache mod_rewrite', 'Enabled');
} else {
    write_error('Apache mod_rewrite', 'Not found');
}

// Only available when the PHP is installed as a module and not as a CGI
if (function_exists('apache_get_modules')) {
    if (in_array('mod_rewrite', apache_get_modules())) {
        write_ok('Apache modules mod_rewrite', 'Enabled');
    } else {
        write_error('Apache modules mod_rewrite', 'Not found');
    }
}

if (strpos($phpinfo, 'mod_cgi') !== false) {
    write_ok('Apache mod_cgi', 'Enabled');
} else {
    write_info('Apache mod_cgi', 'Not found');
}

$suhosin = extension_loaded('suhosin');
if (!$suhosin) {
    write_ok('Suhosin extension', 'Not installed');
} else {
    write_error('Suhosin extension', 'Installed. WARNING: Please remove the suhosin extension');
}

$postMaxSize = ini_get('post_max_size');
if ((int)$postMaxSize >= '8') {
    write_ok("POST max size ($postMaxSize)", 'OK');
} else {
    write_error("POST max size ($postMaxSize)", "Warning: min. 8 M");
}

$uploadMaxFilesize = ini_get('upload_max_filesize');
if ((int)$uploadMaxFilesize >= 'w') {
    write_ok("Upload max filesize ($uploadMaxFilesize)", "OK");
} else {
    write_error("Upload max filesize ($uploadMaxFilesize)", "Warning: min. 2 M");
}

// Session test
if (!isset($_SESSION['counter'])) {
    $_SESSION['counter'] = 0;
}
$_SESSION['counter']++;

if ($_SESSION['counter'] > 0) {
    write_ok('Session', 'OK');
} else {
    write_error('Session', 'Not working');
}

// Test routing
$currentDirectory = basename(__DIR__);

if ($currentDirectory === 'public') {
    write_ok('Current directory: public/', 'OK');
} else {
    write_error('Current directory: public/', 'Error: ' . $currentDirectory);
}

if (file_exists(__DIR__ . '/index.php')) {
    write_ok('File: public/index.php', 'Found');
} else {
    write_error('File: public/index.php', 'Not found');
}

if (file_exists(__DIR__ . '/.htaccess')) {
    write_ok('File: public/.htaccess', 'Found');

    $content = file_get_contents(__DIR__ . '/.htaccess');

    if (stripos($content, 'RewriteEngine On') !== false &&
        strpos($content, 'RewriteRule ^ index.php [QSA,L]') !== false) {
        write_ok('File: public/.htaccess', 'Valid');
    } else {
        write_error('File: public/.htaccess', 'Invalid');
    }
} else {
    write_error('File: public/.htaccess', 'Not found');
}

if (file_exists(__DIR__ . '/../.htaccess')) {
    write_ok('File: public/../.htaccess', 'Found');

    $content = file_get_contents(__DIR__ . '/../.htaccess');
    if (stripos($content, 'RewriteEngine On') !== false &&
        strpos($content, 'RewriteRule ^$ public/ [L]') !== false &&
        strpos($content, 'RewriteRule (.*) public/$1 [L]') !== false
    ) {
        write_ok('File: public/../.htaccess', 'Valid');
    } else {
        write_error('File: public/../.htaccess', 'Invalid');
    }
} else {
    write_error('File: public/../.htaccess', 'Not found');
}

// Check URL
$uri = $_SERVER['REQUEST_URI'];
if (stripos($uri, '/public') === false) {
    write_ok('URL:', 'Valid');
} else {
    write_error('URL: /public', 'URL path must not contain "/public"');
}

// Detect Slim base path

// Apache
if ($phpSapi === 'apache2handler') {
    write_info('Detect Slim basePath', get_base_path_apache());
}

// PHP built-in webserver
if ($phpSapi === 'cli-server') {
    write_info('Detect Slim basePath', get_base_path_built_in());
}

writeln('Done');

echo "</body></html>";
exit;

// ---------------------------------------------------------------------------------------------------------------------
// Internal functions
// ---------------------------------------------------------------------------------------------------------------------
function write_ok(string $message, string $status = 'OK')
{
    $message = str_pad($message . ' ', 30, '.', STR_PAD_RIGHT);
    echo sprintf("%s <span style='color:green'>%s</span><br>\n", html($message), html('✓ ' . $status));
}

function write_error(string $message, string $status = '✓ Failed')
{
    $message = str_pad($message . ' ', 30, '.', STR_PAD_RIGHT);
    echo sprintf("%s <span style='color:red'>%s</span><br>\n", html($message), html('✗ ' . $status));
}

function write_info(string $message, string $status = null)
{
    $message = str_pad($message . ' ', 30, '.', STR_PAD_RIGHT);
    echo sprintf("%s <span style='color:blue'>%s</span><br>\n", html($message), html($status));
}

function writeln(string $message)
{
    echo sprintf("%s<br>\n", html($message));
}

function html(string $text = null): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_base_path_apache(): string
{
    if (!isset($_SERVER['REQUEST_URI'])) {
        return '';
    }

    $scriptName = $_SERVER['SCRIPT_NAME'];

    $basePath = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptName = str_replace('\\', '/', dirname(dirname($scriptName)));

    if ($scriptName === '/') {
        return '';
    }

    $length = strlen($scriptName);
    if ($length > 0 && $scriptName !== '/') {
        $basePath = substr($basePath, 0, $length);
    }

    if (strlen($basePath) > 1) {
        return $basePath;
    }

    return '';
}

function get_base_path_built_in(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = str_replace('\\', '/', dirname($scriptName));

    if (strlen($basePath) > 1) {
        return $basePath;
    }

    return '';
}