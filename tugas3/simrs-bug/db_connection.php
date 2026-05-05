<?php


function getConnection(): PDO
{
    $host   = '127.0.0.1';
    $dbname = 'simrs';
    $user   = 'root';
    $pass   = '';

    $dsn = "mysql:host=$host;dbname=$dbname";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, $user, $pass, $options);
}
