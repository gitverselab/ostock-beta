<?php

declare(strict_types=1);

namespace App\Models;

class Item
{
    public ?int $id = null;
    public string $name = '';
    public string $item_code = '';
    public string $uom = '';
    public int $category_id = 0;
    public float $cost = 0.0;
    public int $is_calendar_item = 0;
    public string $primary_uom_label = '';
    public string $secondary_uom_label = '';
    public ?string $category_name = null;

    public static function fromArray(array $data): self
    {
        $item = new self();

        $item->id = isset($data['id']) ? (int) $data['id'] : null;
        $item->name = (string) ($data['name'] ?? '');
        $item->item_code = (string) ($data['item_code'] ?? '');
        $item->uom = (string) ($data['uom'] ?? '');
        $item->category_id = (int) ($data['category_id'] ?? 0);
        $item->cost = (float) ($data['cost'] ?? 0);
        $item->is_calendar_item = (int) ($data['is_calendar_item'] ?? 0);
        $item->primary_uom_label = (string) ($data['primary_uom_label'] ?? '');
        $item->secondary_uom_label = (string) ($data['secondary_uom_label'] ?? '');
        $item->category_name = isset($data['category_name']) ? (string) $data['category_name'] : null;

        return $item;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'item_code' => $this->item_code,
            'uom' => $this->uom,
            'category_id' => $this->category_id,
            'cost' => $this->cost,
            'is_calendar_item' => $this->is_calendar_item,
            'primary_uom_label' => $this->primary_uom_label,
            'secondary_uom_label' => $this->secondary_uom_label,
            'category_name' => $this->category_name,
        ];
    }
}