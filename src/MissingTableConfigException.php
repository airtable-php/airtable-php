<?php

namespace AirtablePHP;

use Exception;

class MissingTableConfigException extends Exception
{
    public function __construct(string $modelClass)
    {
        parent::__construct("Table [{$modelClass}] configuration is missing");
    }
}
