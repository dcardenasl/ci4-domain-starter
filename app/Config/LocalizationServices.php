<?php

declare(strict_types=1);

namespace Config;

/**
 * Localization Services
 *
 * Content-localization infrastructure: the shared locale resolver plus the
 * translation and public-slug stores. Consumer models (`App\Models\TranslationModel`,
 * `App\Models\PublicSlugModel`) are injected so core never assumes a model
 * namespace or table prefix. See
 * vendor/dcardenasl/ci4-api-core/docs/EXTENDING_LOCALIZATION.md.
 */
trait LocalizationServices
{
    public static function requestLocaleResolver(bool $getShared = true): \dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver
    {
        if ($getShared) {
            return static::getSharedInstance('requestLocaleResolver');
        }

        return new \dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver(service('request'));
    }

    public static function localizedTranslationStore(bool $getShared = true): \dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore
    {
        if ($getShared) {
            return static::getSharedInstance('localizedTranslationStore');
        }

        return new \dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore(
            model(\App\Models\TranslationModel::class),
            static::requestLocaleResolver(),
            config('Localization')
        );
    }

    public static function publicSlugStore(bool $getShared = true): \dcardenasl\Ci4ApiCore\Localization\PublicSlugStore
    {
        if ($getShared) {
            return static::getSharedInstance('publicSlugStore');
        }

        return new \dcardenasl\Ci4ApiCore\Localization\PublicSlugStore(
            model(\App\Models\PublicSlugModel::class),
            new \dcardenasl\Ci4ApiCore\Localization\SlugGenerator(),
            static::requestLocaleResolver(),
            config('Localization')
        );
    }
}
