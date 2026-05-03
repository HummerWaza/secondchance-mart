<?php
// ============================================================
// SecondChance Mart - Database Connection (PDO)
// ============================================================

// ── For InfinityFree: replace these with values from cPanel → MySQL Databases
define('DB_HOST', 'localhost');           // InfinityFree: sql###.infinityfree.com
define('DB_NAME', 'secondchance_mart');   // InfinityFree: epiz_XXXXXXX_dbname
define('DB_USER', 'root');                // InfinityFree: epiz_XXXXXXX
define('DB_PASS', '');                    // InfinityFree: the password you set
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection.
 * Uses PDO for secure parameterized queries (prevents SQL injection).
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                   // use native prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Show friendly error instead of exposing DB details
            die('<div style="text-align:center;padding:50px;font-family:sans-serif;">
                    <h2>&#9888; Database Connection Failed</h2>
                    <p>Please check your database settings in <code>config/database.php</code></p>
                    <small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>
                 </div>');
        }
    }
    return $pdo;
}
