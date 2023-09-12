<?php

namespace Chorume\Repository;

use Chorume\Database\Db;

class Repository
{
    public function __construct(protected Db $db)
    {
    }
}