<?php

declare(strict_types=1);

namespace viesrood\videokit\web\twig;

use craft\helpers\Template;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;

/**
 * Twig helpers.
 *
 *   {{ videokit_facade(entry.video) }}           click-to-load poster (recommended)
 *   {{ videokit_embed(entry.video) }}            plain iframe, loads immediately
 *   {{ videokit_embed_url(entry.video) }}        player URL
 *   {{ videokit_thumbnail(entry.video, 1280) }}  poster URL
 *   {{ videokit_video('https://youtu.be/...') }} look a URL up as a Video
 *   {% if videokit_configured() %}               is any provider set up?
 *
 * Only the two functions that build markup are marked HTML-safe. The URL
 * helpers return plain strings on purpose, so Twig keeps escaping them when
 * they land in an attribute.
 */
class VideoKitExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'VideoKit';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('videokit_video', [$this, 'video']),
            new TwigFunction('videokit_embed_url', [$this, 'embedUrl']),
            new TwigFunction('videokit_thumbnail', [$this, 'thumbnail']),
            new TwigFunction('videokit_embed', [$this, 'embed'], ['is_safe' => ['html']]),
            new TwigFunction('videokit_facade', [$this, 'facade'], ['is_safe' => ['html']]),
            new TwigFunction('videokit_configured', [$this, 'isConfigured']),
        ];
    }

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
}
