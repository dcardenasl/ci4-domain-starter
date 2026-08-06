<?php

declare(strict_types=1);

namespace Config;

/**
 * Content-localization registry.
 *
 * Register a translatable resource by adding its resource type and field
 * list below, e.g. `'article' => ['title', 'summary']`. See
 * vendor/dcardenasl/ci4-api-core/docs/EXTENDING_LOCALIZATION.md for the full
 * wiring guide (models, migrations, service factories, DTO normalization).
 */
class Localization extends \dcardenasl\Ci4ApiCore\Config\Localization
{
    /** @var array<string, list<string>> */
    public array $translatableFields = [
        // 'article' => ['title', 'summary'],
    ];
}
