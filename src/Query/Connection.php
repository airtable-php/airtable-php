<?php

namespace AirtablePHP\Query;

class Connection
{
    public function __construct(
        public string $apiKey,
        public string $baseId,
        public string $tableId,
    ) {}
}
