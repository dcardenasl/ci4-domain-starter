<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Example;

use App\Controllers\ApiController;
use App\DTO\Request\Example\ItemCreateRequestDTO;
use App\DTO\Request\Example\ItemIndexRequestDTO;
use App\DTO\Request\Example\ItemUpdateRequestDTO;
use App\Interfaces\Example\ItemServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ItemController extends ApiController
{
    protected ItemServiceInterface $itemService;

    protected function resolveDefaultService(): object
    {
        $this->itemService = Services::itemService();

        return $this->itemService;
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ItemIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', ItemCreateRequestDTO::class);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->itemService->update($id, $dto, $context),
            ItemUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->itemService->show($id, $context));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->itemService->destroy($id, $context));
    }
}
