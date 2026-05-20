<?php

declare(strict_types=1);

namespace App\DTO\Request\Example;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ItemCreateRequest')]
readonly class ItemCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'name', type: 'string')]
    public string $name;
    #[OA\Property(description: 'description', type: 'string')]
    public string $description;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max_length[255]',
            'description' => 'permit_empty|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
