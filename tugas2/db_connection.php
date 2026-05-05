<?php
/**
 * db_connection.php
 * Koneksi ke database MySQL menggunakan PDO
 */

/**
 * Membuat dan mengembalikan koneksi PDO ke database SIMRS.
 *
 * @return PDO Objek koneksi PDO yang sudah dikonfigurasi
 * @throws PDOException Jika koneksi ke database gagal
 *
 * @example
 *   $pdo = getConnection();
 *   $stmt = $pdo->query("SELECT * FROM dokter");
 */
function getConnection(): PDO
{
    $host   = '127.0.0.1';
    $dbname = 'simrs';
    $user   = 'root';
    $pass   = '';
    $port   = '3306';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}