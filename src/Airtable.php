<?php

namespace AirtablePHP;

use AirtablePHP\Query\Connection;

class Airtable
{
    protected static array $tables = [];

    public static function init(string $apiKey, array $bases = []): void
    {
        foreach ($bases as $baseId => $tables) {
            foreach ($tables as $modelClass => $tableId) {
                static::$tables[$modelClass] = new Connection($apiKey, $baseId, $tableId);
            }
        }
    }

    public static function getModelConnection(string $modelClass): Connection
    {
        return static::$tables[$modelClass] ?? throw new MissingTableConfigException($modelClass);
    }
}
