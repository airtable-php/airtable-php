<?php

namespace AirtablePHP\Query;

use AirtablePHP\Airtable;
use AirtablePHP\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Builder
{
    protected Connection $connection;

    protected ?string $filterByFormula = null;

    protected array $selects = [];

    protected array $wheres = [];

    protected array $orders = [];

    protected ?int $limit = null;

    public function __construct(protected string $modelClass)
    {
        $this->connection = Airtable::getModelConnection($this->modelClass);
    }

    public function select(array|string $fields): static
    {
        $this->selects = is_array($fields) ? $fields : func_get_args();

        return $this;
    }

    public function filterByFormula(string $filterByFormula): static
    {
        $this->filterByFormula = $filterByFormula;
        $this->wheres = [];

        return $this;
    }

    public function where(string $attribute, string $value): static
    {
        $this->filterByFormula = null;
        $this->wheres[$attribute] = $value;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'field' => $field,
            'direction' => $direction,
        ];

        return $this;
    }

    public function orderByDesc(string $field): static
    {
        return $this->orderBy($field, 'desc');
    }

    public function first(): ?Model
    {
        $query = [
            'filterByFormula' => $this->getFilterByFormula(),
            'sort' => $this->getSort(),
            'maxRecords' => 1,
            'pageSize' => 1,
        ];

        $record = $this->http()->get('', $query)->throw()->json('records.0');

        return $this->createModelFromAirtableRecord($record);
    }

    public function find(string $id): ?Model
    {
        $record = $this->http()->get($id)->throw()->json();

        return $this->createModelFromAirtableRecord($record);
    }

    public function get(): Collection
    {
        $query = [
            'fields' => $this->selects ?: null,
            'filterByFormula' => $this->getFilterByFormula(),
            'sort' => $this->getSort(),
            'maxRecords' => $this->limit,
            'offset' => null,
        ];

        $records = collect();

        while (true) {
            $response = $this->http()->get('', $query)->throw()->json();

            $records = $records->concat(
                collect($response['records'])->mapInto($this->modelClass)
            );

            if (! isset($response['offset'])) {
                break;
            }

            $query['offset'] = $response['offset'];
        }

        return $records;
    }

    public function getFilterByFormula(): ?string
    {
        if (! $this->filterByFormula && ! $this->wheres) {
            return null;
        }

        if ($this->filterByFormula) {
            return $this->filterByFormula;
        }

        $filterByFormula = collect($this->wheres)
            ->map(fn (string $value, string $attribute) => "{{$attribute}} = '{$value}'")
            ->implode(', ');

        $filterByFormula = "AND({$filterByFormula})";

        return $filterByFormula;
    }

    public function getSort(): ?array
    {
        return $this->orders ? $this->orders : [];
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function limit(int $value): static
    {
        $this->limit = $value;

        return $this;
    }

    public function update(string $id, array $attributes, string $method = 'patch'): Model
    {
        $data = [
            'fields' => $attributes,
        ];

        $record = $this->http()->{$method}($id, $data)->throw()->json();

        return $this->createModelFromAirtableRecord($record);
    }

    protected function http(): PendingRequest
    {
        $baseUrl = "https://api.airtable.com/v0/{$this->connection->baseId}/{$this->connection->tableId}";

        return Http::withToken($this->connection->apiKey)->baseUrl($baseUrl);
    }

    protected function createModelFromAirtableRecord(?array $record): ?Model
    {
        if (! $record) {
            return null;
        }

        return new $this->modelClass($record);
    }

    public static function make(string $modelClass): static
    {
        return new static($modelClass);
    }

    public function firstOrCreate(array $attributes, array $values = []): ?Model
    {
        foreach ($attributes as $key => $value) {
            $this->where($key, $value);
        }

        if ($record = $this->first()) {
            return $record;
        }

        $recordData = [
            'fields' => array_merge($attributes, $values),
        ];

        $record = $this->http()->post('', $recordData)->throw()->json();

        return $this->createModelFromAirtableRecord($record);
    }

    public function updateOrCreate(array $attributes, array $values = []): ?Model
    {
        $mergedData = array_merge($attributes, $values);

        foreach ($attributes as $key => $value) {
            $this->where($key, $value);
        }

        if ($record = $this->first()) {
            foreach ($values as $key => $value) {
                if ($record->getAttribute($key) !== $value) {
                    $updatedRecord = $this->http()
                        ->patch($record->getId(), ['fields' => $mergedData])
                        ->throw()
                        ->json();

                    return $this->createModelFromAirtableRecord($updatedRecord);
                }
            }

            return $record;
        }

        $recordData = [
            'fields' => $mergedData,
        ];

        $record = $this->http()->post('', $recordData)->throw()->json();

        return $this->createModelFromAirtableRecord($record);
    }
}
