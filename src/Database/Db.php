<?php

namespace Chorume\Database;

class Db
{
    /**
     * @var string
     */
    private $server;
    /**
     * @var string
     */
    private $database;
    /**
     * @var string
     */
    private $user;
    /**
     * @var string
     */
    private $password;

    /**
     * @var PDO
     */
    private static $conn;

    public function __construct(string $server, string $database, string $user, string $password)
    {
        $this->server = $server;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;

        $this->getDb();
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public function getDb()
    {
        if (!self::$conn) {
            try {
                self::$conn = mysqli_connect($this->server, $this->user, $this->password, $this->database);
            } catch (\Throwable $th) {
                echo $th->getMessage();
                exit();
            }
        }

        return self::$conn;
    }

    private function setParams($stmt, $parametros = [])
    {
        $params = [];
        $params[] = array_reduce($parametros, function ($acc, $item) {
            return $acc .= $item['type'];
        }, '');

        foreach ($parametros as $valor) {
            $params[] = &$valor['value'];
        }

        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    public function query($sql, $params = [])
    {
        $server = self::$conn->prepare($sql);

        if (!empty($params)) {
            $this->setParams($server, $params);
        }

        $server->execute();

        return $server;
    }

    public function select($sql, $params = [])
    {
        $server = $this->query($sql, $params);

        return $server->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
