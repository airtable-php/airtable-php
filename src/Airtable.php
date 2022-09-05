<?php

namespace AirtablePHP;

use AirtablePHP\Query\Connection;

class Airtable
{
    protected static ?string $apiKey = null;

    protected static ?string $baseId = null;

    protected static array $tables = [];

    public static function init(string $apiKey, ?string $baseId = null, array $tables = []): void
    {
        static::$apiKey = $apiKey;
        static::$baseId = $baseId;

        foreach ($tables as $modelClass => $tableId) {
            static::initTable($modelClass, $tableId);
        }
    }

    public static function initTable(string $modelClass, string|Connection $tableId): void
    {
        static::$tables[$modelClass] = $tableId;
    }

    public static function getModelConnection(string $modelClass): Connection
    {
        if (static::$tables[$modelClass] instanceof Connection) {
            return static::$tables[$modelClass];
        }

        return new Connection(static::$apiKey, static::$baseId, static::$tables[$modelClass]);
    }
}
