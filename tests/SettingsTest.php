<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Settings;

final class SettingsTest extends TestCase
{
    public function testCredentialsDefaultToEnvVarPlaceholders(): void
    {
        $settings = new Settings();

        self::assertSame('$YOUTUBE_API_KEY', $settings->youtubeApiKey);
        self::assertSame('$VIMEO_ACCESS_TOKEN', $settings->vimeoAccessToken);
        self::assertSame('$YOUTUBE_CHANNEL_ID', $settings->youtubeChannelId);
        self::assertSame('$VIMEO_USER_ID', $settings->vimeoUserId);
    }

    public function testMetadataRefreshIsOffByDefault(): void
    {
        // A front-end request must never be able to trigger an API call.
        self::assertSame(0, (new Settings())->metadataTtl);
    }

    public function testEnabledProvidersAcceptsAnArrayOrAString(): void
    {
        $settings = new Settings();

        $settings->setEnabledProviders([VideoUrl::YOUTUBE]);
        self::assertSame(['youtube'], $settings->getEnabledProviders());

        $settings->setEnabledProviders('youtube, vimeo');
        self::assertSame(['youtube', 'vimeo'], $settings->getEnabledProviders());

        $settings->setEnabledProviders(null);
        self::assertSame([], $settings->getEnabledProviders());
    }

    public function testEnabledProvidersSurvivesTheWayCraftSavesSettings(): void
    {
        $settings = new Settings();

        // Craft calls setAttributes($values, false), which only assigns names
        // that attributes() reports. A checkbox group also posts an empty
        // value, which must not be mistaken for "one provider enabled".
        $settings->setAttributes(['enabledProviders' => ['', 'vimeo']], false);

        self::assertContains('enabledProviders', $settings->attributes());
        self::assertSame(['vimeo'], $settings->getEnabledProviders());

        $settings->setAttributes(['enabledProviders' => ['']], false);

        self::assertSame([], $settings->getEnabledProviders());
    }

    public function testEnabledProvidersDropsUnknownAndDuplicateHandles(): void
    {
        $settings = new Settings();
        $settings->setEnabledProviders(['YouTube', 'youtube', 'dailymotion', '', 42]);

        self::assertSame(['youtube'], $settings->getEnabledProviders());
    }

    public function testValidationAcceptsSaneValues(): void
    {
        $settings = new Settings();
        $settings->videosPerPage = 24;
        $settings->cacheDuration = 3600;
        $settings->timeout = 10;
        $settings->facadeAspectRatio = '4 / 3';

        self::assertTrue($settings->validate(), print_r($settings->getErrors(), true));
    }

    public function testValidationRejectsOutOfRangeValues(): void
    {
        $settings = new Settings();
        $settings->videosPerPage = 0;
        $settings->cacheDuration = -1;
        $settings->timeout = 0;

        self::assertFalse($settings->validate());
        self::assertArrayHasKey('videosPerPage', $settings->getErrors());
        self::assertArrayHasKey('cacheDuration', $settings->getErrors());
        self::assertArrayHasKey('timeout', $settings->getErrors());
    }

    public function testValidationRejectsARatioThatCouldInjectStyles(): void
    {
        $settings = new Settings();
        $settings->facadeAspectRatio = '1; background: url(https://evil.example.com)';

        self::assertFalse($settings->validate());
        self::assertArrayHasKey('facadeAspectRatio', $settings->getErrors());
    }
}
