<?php

declare(strict_types=1);

namespace viesrood\videokit\variables;

use craft\helpers\Template;
use Twig\Markup;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;

/**
 * `craft.videokit` - the same helpers as the Twig functions, for templates
 * that prefer the variable style.
 */
class VideoKitVariable
{
    public function video(Video|string|null $video): ?Video
    {
        return Plugin::getInstance()?->getEmbed()->resolve($video);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function embedUrl(Video|string|null $video, array $options = []): ?string
    {
        return Plugin::getInstance()?->getEmbed()->embedUrl($video, $options);
    }

    public function thumbnail(Video|string|null $video, int $width = 1280): ?string
    {
        return Plugin::getInstance()?->getEmbed()->thumbnail($video, $width);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function embed(Video|string|null $video, array $options = []): Markup
    {
        return Plugin::getInstance()?->getEmbed()->embed($video, $options) ?? Template::raw('');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function facade(Video|string|null $video, array $options = []): Markup
    {
        return Plugin::getInstance()?->getEmbed()->facade($video, $options) ?? Template::raw('');
    }

    public function isConfigured(?string $provider = null): bool
    {
        return Plugin::getInstance()?->getEmbed()->isConfigured($provider) ?? false;
    }

    /**
     * Handles and names of every provider that is ready to use.
     *
     * @return array<string, string>
     */
    public function providers(): array
    {
        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return [];
        }

        $providers = [];

        foreach ($plugin->getProviders()->getEnabledProviders() as $handle => $provider) {
            /** @var ProviderInterface $provider */
            $providers[$handle] = $provider::displayName();
        }

        return $providers;
    }
}
