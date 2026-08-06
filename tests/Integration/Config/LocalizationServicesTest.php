<?php

declare(strict_types=1);

namespace Tests\Integration\Config;

use App\Models\PublicSlugModel;
use App\Models\TranslationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Localization;
use Config\Services;
use dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore;
use dcardenasl\Ci4ApiCore\Localization\PublicSlugStore;
use dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver;
use dcardenasl\Ci4ApiCore\Localization\SlugGenerator;

/**
 * Verifies the LOC-006 wiring end to end: the three factories resolve the
 * expected core types, and the translation/slug stores round-trip through
 * the real `translations` / `public_slugs` tables via the app's own
 * TranslationModel/PublicSlugModel. No resource is registered in
 * `Config\Localization` yet (infrastructure only), so the round-trip test
 * builds its own store instances against an ad-hoc resource type rather
 * than the shared, still-empty registry.
 */
final class LocalizationServicesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testFactoriesResolveExpectedCoreTypes(): void
    {
        $this->assertInstanceOf(RequestLocaleResolver::class, Services::requestLocaleResolver(false));
        $this->assertInstanceOf(LocalizedTranslationStore::class, Services::localizedTranslationStore(false));
        $this->assertInstanceOf(PublicSlugStore::class, Services::publicSlugStore(false));
    }

    public function testRequestLocaleResolverIsSharedAcrossCalls(): void
    {
        $this->assertSame(Services::requestLocaleResolver(), Services::requestLocaleResolver());
    }

    public function testTranslationStoreRoundTripsThroughTheDatabase(): void
    {
        $config = new Localization();
        $config->translatableFields = ['smoke_article' => ['title', 'summary']];

        $store = new LocalizedTranslationStore(
            model(TranslationModel::class),
            new RequestLocaleResolver(),
            $config
        );

        $store->sync('smoke_article', 1, [
            ['locale' => 'en', 'title' => 'Hello', 'summary' => 'Hello summary'],
            ['locale' => 'es', 'title' => 'Hola', 'summary' => 'Resumen hola'],
        ]);

        $rows = $store->forResource('smoke_article', 1);
        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[$row['locale']] = $row['fields'];
        }

        $this->assertSame('Hello', $byLocale['en']['title'] ?? null);
        $this->assertSame('Resumen hola', $byLocale['es']['summary'] ?? null);
        $this->seeInDatabase('translations', [
            'translatable_type' => 'smoke_article',
            'translatable_id'   => 1,
            'locale'            => 'es',
            'field'             => 'title',
            'value'             => 'Hola',
        ]);
    }

    public function testPublicSlugStoreRoundTripsThroughTheDatabaseAndRejectsCaseOnlyCollisions(): void
    {
        $store = new PublicSlugStore(
            model(PublicSlugModel::class),
            new SlugGenerator(),
            new RequestLocaleResolver(),
            new Localization()
        );

        $store->syncForResource('smoke_article', 1, ['en' => 'Hello World', 'es' => 'Hola Mundo']);

        $resolvedId = $store->resolveResourceId('smoke_article', 'hola-mundo');
        $this->assertSame(1, $resolvedId);

        $this->seeInDatabase('public_slugs', [
            'resource_type' => 'smoke_article',
            'resource_id'   => 1,
            'locale'        => 'en',
            'slug'          => 'hello-world',
        ]);

        // A second resource claiming a slug that differs only by case from the
        // one syncForResource() just wrote for resource_id 1 must collide —
        // this is the collation guarantee the LOC-006 migrations depend on.
        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        model(PublicSlugModel::class)->insert([
            'resource_type' => 'smoke_article',
            'resource_id'   => 2,
            'locale'        => 'en',
            'slug'          => 'Hello-World',
        ]);
    }
}
