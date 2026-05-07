<?php

declare(strict_types=1);

namespace App\DTO\Response\Common;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Generic payload response DTO
 */
#[OA\Schema(
    schema: 'PayloadResponse',
    title: 'Payload Response',
    description: 'Generic payload wrapper'
)]
readonly class PayloadResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(
            description: 'Payload data',
            type: 'object',
            additionalProperties: true
        )]
        public array $payload
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(payload: $payload);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
