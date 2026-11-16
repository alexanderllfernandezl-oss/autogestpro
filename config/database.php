
<?php

class Database
{
    private string $host = 'sql108.infinityfree.com';
    private string $database = 'if0_43456838_autogest_pro';
    private string $username = 'if0_43456838';
    private string $password = 'YjVO9psJLfY';

    public function connect(): PDO
    {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";

        return new PDO(
            $dsn,
            $this->username,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
}
