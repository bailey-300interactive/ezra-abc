<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

$cfgFile = APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Ezra ABC is not set up yet: copy config.sample.php to config.php and run php dev/install.php');
}
$CONFIG = require $cfgFile;

if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $c = $CONFIG['db'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Default settings. Anything saved in the settings table overrides these. */
function default_settings(): array
{
    return [
        'title'          => 'Ezra ABC',
        'default_case'   => 'upper',   // upper | lower
        'default_mode'   => 'trace',   // trace | draw
        'allow_case'     => '1',       // show the ABC / abc switch to the kid
        'voice_on'       => '1',
        'sounds_on'      => '1',
        'speech_rate'    => '0.85',
        'difficulty'     => 'easy',    // easy | medium
        'show_fridge'    => '1',       // kid can open the fridge of saved drawings
        'auto_save'      => '1',       // save a finished tracing to the fridge automatically
    ];
}

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = default_settings();
        foreach (db()->query('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache;
}

function save_setting(string $k, string $v): void
{
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute([$k, $v]);
}

/** Default word + emoji for each letter. "E" is a good one to change to Ezra with his photo. */
function default_letters(): array
{
    return [
        'A' => ['Apple', '🍎'],    'B' => ['Ball', '⚽'],      'C' => ['Cat', '🐱'],
        'D' => ['Dog', '🐶'],      'E' => ['Elephant', '🐘'], 'F' => ['Fish', '🐟'],
        'G' => ['Giraffe', '🦒'],  'H' => ['Hat', '🎩'],      'I' => ['Ice cream', '🍦'],
        'J' => ['Juice', '🧃'],    'K' => ['Kite', '🪁'],     'L' => ['Lion', '🦁'],
        'M' => ['Moon', '🌙'],     'N' => ['Nest', '🪺'],     'O' => ['Octopus', '🐙'],
        'P' => ['Pig', '🐷'],      'Q' => ['Queen', '👑'],    'R' => ['Rainbow', '🌈'],
        'S' => ['Sun', '☀️'],      'T' => ['Train', '🚂'],    'U' => ['Umbrella', '☂️'],
        'V' => ['Van', '🚐'],      'W' => ['Whale', '🐳'],    'X' => ['Xylophone', '🎶'],
        'Y' => ['Yo-yo', '🪀'],    'Z' => ['Zebra', '🦓'],
    ];
}

function letters(bool $onlyEnabled = false): array
{
    $rows = db()->query('SELECT letter, word, emoji, image, voice, enabled, UNIX_TIMESTAMP(updated_at) AS ver FROM letters ORDER BY letter')->fetchAll();
    if ($onlyEnabled) {
        $rows = array_values(array_filter($rows, fn($r) => (int)$r['enabled'] === 1));
    }
    return $rows;
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Path for an uploaded file relative to the app root, with a cache-busting version. */
function upload_url(?string $rel, $ver = null): ?string
{
    if (!$rel) return null;
    return $rel . ($ver ? '?v=' . $ver : '');
}

/** Base URL path of the app ('' when it lives at the web root, '/abc' in a sub-folder). */
function base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $root   = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
    $file   = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    if ($file && str_starts_with($file, $root)) {
        $relFromRoot = substr($file, strlen($root));          // e.g. /admin/index.php
        if (str_ends_with($script, $relFromRoot)) {
            return rtrim(substr($script, 0, -strlen($relFromRoot)), '/');
        }
    }
    return '';
}
