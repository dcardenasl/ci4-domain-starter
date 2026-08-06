<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Scaffolding;

/**
 * The hub rejects any permission whose code doesn't start with
 * "{app.code}.". `permissionCodePrefix` must therefore always match this
 * app's own `hub.appCode`, not a value hardcoded separately — see SCAFF-008.
 */
class ScaffoldingTest extends CIUnitTestCase
{
    private function withAppCodeEnv(string $value, callable $fn): void
    {
        // .env already loads `hub.appCode` into $_ENV, which env() checks
        // before falling back to getenv() — putenv() alone would not
        // override it, so $_ENV must be set directly here.
        $hadEnv = array_key_exists('hub.appCode', $_ENV);
        $previousEnv = $_ENV['hub.appCode'] ?? null;
        $previousGetenv = getenv('hub.appCode');

        $_ENV['hub.appCode'] = $value;
        putenv('hub.appCode=' . $value);

        try {
            $fn();
        } finally {
            if ($hadEnv) {
                $_ENV['hub.appCode'] = $previousEnv;
            } else {
                unset($_ENV['hub.appCode']);
            }
            if ($previousGetenv === false) {
                putenv('hub.appCode');
            } else {
                putenv('hub.appCode=' . $previousGetenv);
            }
        }
    }

    public function testPermissionCodePrefixMatchesHubAppCode(): void
    {
        $this->withAppCodeEnv('e2e-loc008-domain', function (): void {
            $config = (new Scaffolding())->build();

            $this->assertSame('e2e-loc008-domain', $config->permissionCodePrefix);
        });
    }

    public function testPermissionCodePrefixTracksAppCodeAcrossProjects(): void
    {
        $this->withAppCodeEnv('another-domain', function (): void {
            $config = (new Scaffolding())->build();

            $this->assertSame('another-domain', $config->permissionCodePrefix);
        });
    }
}
