<?php
// ============================================================
// SecondChance Mart - Database Connection (PDO)
// Auto-switches between local and live database
// ============================================================

$isLocal = (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', 'localhost:8000', '127.0.0.1']))
        || (php_sapi_name() === 'cli-server');

if ($isLocal) {
    // ── Local (XAMPP or any local MySQL) ─────────────────────
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'scmart');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
} else {
    // ── Live (InfinityFree) ───────────────────────────────────
    define('DB_HOST',    'sql103.infinityfree.com');
    define('DB_NAME',    'if0_41830658_scmart');
    define('DB_USER',    'if0_41830658');
    define('DB_PASS',    'lFTNasGwFHe2');
}

define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="text-align:center;padding:50px;font-family:sans-serif;">
                    <h2>&#9888; Database Connection Failed</h2>
                    <p>Please check your database settings in <code>config/database.php</code></p>
                    <small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>
                 </div>');
        }
    }
    return $pdo;
}
