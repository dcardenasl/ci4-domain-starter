<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ItemEntity extends Entity
{
    protected $casts = [
'id' => 'integer',
        'name' => 'string',
        'description' => 'string',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}
