<?php

declare(strict_types=1);

namespace App\DTO\Request\Example;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ItemUpdateRequest')]
readonly class ItemUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'name', type: 'string', nullable: true)]
    public ?string $name;
    #[OA\Property(description: 'description', type: 'string', nullable: true)]
    public ?string $description;

    public function rules(): array
    {
        return [
            'name' => 'permit_empty|string|max_length[255]',
            'description' => 'permit_empty|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
        ], fn ($v) => $v !== null);
    }
}
