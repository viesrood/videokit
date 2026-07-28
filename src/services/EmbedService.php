<?php

declare(strict_types=1);

namespace viesrood\videokit\services;

use Craft;
use craft\base\Component;
use craft\helpers\Template;
use Twig\Markup;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;
use viesrood\videokit\web\assets\facade\FacadeAsset;

/**
 * The front-end entry point behind the Twig helpers and craft.videokit.
 *
 * Accepts either a Video (a field value) or a plain URL string, so a template
 * can embed a hard-coded video without a field.
 */
class EmbedService extends Component
{
    /**
     * Turn a field value or a URL into a Video, without contacting a provider.
     */
    public function resolve(Video|string|null $video): ?Video
    {
        if ($video instanceof Video) {
            return $video;
        }

        if (!is_string($video) || trim($video) === '') {
            return null;
        }

        return Plugin::getInstance()?->getVideos()->getVideoByUrl(trim($video));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function embedUrl(Video|string|null $video, array $options = []): ?string
    {
        return $this->resolve($video)?->getEmbedUrl($options);
    }

    public function thumbnail(Video|string|null $video, int $width = 1280): ?string
    {
        return $this->resolve($video)?->getThumbnail($width);
    }

    /**
     * A player iframe that loads immediately.
     *
     * @param array<string, mixed> $options
     */
    public function embed(Video|string|null $video, array $options = []): Markup
    {
        return $this->resolve($video)?->getEmbedHtml($options) ?? Template::raw('');
    }

    /**
     * A click-to-load poster. Registers the loader assets on first use, unless
     * the site brings its own.
     *
     * @param array<string, mixed> $options
     */
    public function facade(Video|string|null $video, array $options = []): Markup
    {
        $resolved = $this->resolve($video);

        if ($resolved === null) {
            return Template::raw('');
        }

        $this->registerFacadeAssets();

        return $resolved->getFacadeHtml($options);
    }

    public function isConfigured(?string $provider = null): bool
    {
        return Plugin::getInstance()?->getProviders()->isConfigured($provider) ?? false;
    }

    private function registerFacadeAssets(): void
    {
        $plugin = Plugin::getInstance();

        if ($plugin === null || !$plugin->getSettings()->registerFacadeAssets) {
            return;
        }

        $view = Craft::$app->getView();

        if ($view instanceof \craft\web\View) {
            $view->registerAssetBundle(FacadeAsset::class);
        }
    }
}
