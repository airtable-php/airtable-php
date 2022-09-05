<?php

namespace AirtablePHP;

use AirtablePHP\Query\Builder;
use AirtablePHP\Query\Connection;
use AirtablePHP\Query\HasMany;
use AirtablePHP\Query\HasOne;
use DateTimeImmutable;

class Model
{
    protected ?string $id = null;

    protected ?DateTimeImmutable $createdTime = null;

    protected array $attributes = [];

    public function __construct(?array $attributes)
    {
        if (! $attributes) {
            return;
        }

        if (isset($attributes['fields'])) {
            $this->id = $attributes['id'] ?? null;
            $this->createdTime = isset($attributes['createdTime']) ? new DateTimeImmutable($attributes['createdTime']) : null;
            $this->attributes = $attributes['fields'];
        } else {
            $this->attributes = $attributes;
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedTime(): ?DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $key): null|string|array
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function hasOne(string $modelClass, string $attribute): HasOne
    {
        $foreignId = $this->getAttribute($attribute)[0];

        return HasOne::make($modelClass)->filterByFormula("RECORD_ID() = '{$foreignId}'");
    }

    public function hasMany(string $modelClass, string $attribute): HasMany
    {
        $foreignIds = $this->getAttribute($attribute);

        $filterByFormula = collect($foreignIds)
            ->map(fn (string $foreignId) => "RECORD_ID() = '{$foreignId}'")
            ->implode(', ');

        $filterByFormula = "OR({$filterByFormula})";

        return HasMany::make($modelClass)->filterByFormula($filterByFormula);
    }

    public static function query(): Builder
    {
        return new Builder(static::class);
    }

    public static function init(string|Connection $tableId): void
    {
        Airtable::initTable(static::class, $tableId);
    }

    public function __get(string $name): mixed
    {
        if (! method_exists($this, $name)) {
            return $this->getAttribute($name);
        }

        $builder = $this->{$name}();

        return match ($builder::class) {
            HasOne::class => $builder->first(),
            HasMany::class => $builder->get(),
        };
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([new Builder(static::class), $name], $arguments);
    }
}
