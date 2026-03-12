<?php

declare(strict_types=1);

namespace App\Models;

class Warehouse
{
    public ?int $id = null;
    public string $name = '';
    public string $address = '';

    public static function fromArray(array $data): self
    {
        $warehouse = new self();

        $warehouse->id = isset($data['id']) ? (int) $data['id'] : null;
        $warehouse->name = (string) ($data['name'] ?? '');
        $warehouse->address = (string) ($data['address'] ?? '');

        return $warehouse;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
        ];
    }
}