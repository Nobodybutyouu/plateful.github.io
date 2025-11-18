<?php

require_once __DIR__ . '/../config/database.php';

function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        $database = new Database();
        $connection = $database->getConnection();
    }

    return $connection;
}
