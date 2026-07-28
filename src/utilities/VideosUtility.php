<?php

declare(strict_types=1);

namespace viesrood\videokit\utilities;

use Craft;
use craft\base\Utility;
use craft\web\View;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\Plugin;
use viesrood\videokit\web\assets\utility\UtilityAsset;

/**
 * Control panel utility "VideoKit": inspect the metadata cache, and clear or
 * refresh it per provider.
 */
class VideosUtility extends Utility
{
    public static function id(): string
    {
        return 'videokit';
    }

    public static function displayName(): string
    {
        return 'VideoKit';
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@viesrood/videokit/icon.svg') ?: null;
    }

    public static function contentHtml(): string
    {
        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();
        $view->registerAssetBundle(UtilityAsset::class);

        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return '';
        }

        $counts = $plugin->getVideos()->getCounts();

        $providers = array_map(
            static fn(ProviderInterface $provider): array => [
                'handle' => $provider::handle(),
                'name' => $provider::displayName(),
                'configured' => $provider->isConfigured(),
                'count' => $counts[$provider::handle()] ?? 0,
            ],
            array_values($plugin->getProviders()->getAllProviders()),
        );

        return $view->renderTemplate('videokit/utility', [
            'providers' => $providers,
            'total' => array_sum($counts),
        ], View::TEMPLATE_MODE_CP);
    }
}
