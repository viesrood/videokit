<?php

declare(strict_types=1);

namespace viesrood\videokit\models;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use DateTime;
use Twig\Markup;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\helpers\Duration;
use viesrood\videokit\helpers\Facade;
use viesrood\videokit\helpers\Thumbnails;
use viesrood\videokit\Plugin;

/**
 * A single video: the provider reference that is stored on the element, plus
 * the metadata that is cached alongside it.
 *
 * Only `provider`, `videoId`, `url` and `hash` live in the element content;
 * everything else comes from the metadata table and may legitimately be empty,
 * so nothing here may assume metadata is present.
 */
class Video extends Model
{
    public ?string $provider = null;

    public ?string $videoId = null;

    public ?string $url = null;

    /** Vimeo privacy hash for unlisted videos; null everywhere else. */
    public ?string $hash = null;

    public ?string $title = null;

    public ?string $description = null;

    public ?string $authorName = null;

    public ?string $authorUrl = null;

    public ?DateTime $datePublished = null;

    /** Length in seconds. Null for live streams and premieres. */
    public ?int $duration = null;

    public ?int $plays = null;

    public ?int $width = null;

    public ?int $height = null;

    public bool $private = false;

    /**
     * @var array<int, array{url: string, width: int, height: int}>
     */
    public array $thumbnails = [];

    /**
     * The untouched provider payload, for templates that need a field VideoKit
     * does not map.
     *
     * @var array<string, mixed>
     */
    public array $raw = [];

    /**
     * Build a video from stored or posted data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $video = new self();

        foreach (['provider', 'videoId', 'url', 'hash', 'title', 'description', 'authorName', 'authorUrl'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $video->{$key} = trim($value);
            }
        }

        foreach (['duration', 'plays', 'width', 'height'] as $key) {
            $value = $data[$key] ?? null;

            if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
                $video->{$key} = (int)$value;
            }
        }

        if (isset($data['datePublished'])) {
            $date = DateTimeHelper::toDateTime($data['datePublished']);
            $video->datePublished = $date === false ? null : $date;
        }

        $video->private = (bool)($data['private'] ?? false);

        if (isset($data['thumbnails']) && is_array($data['thumbnails'])) {
            $video->thumbnails = Thumbnails::normalize($data['thumbnails']);
        }

        if (isset($data['raw']) && is_array($data['raw'])) {
            /** @var array<string, mixed> $raw */
            $raw = $data['raw'];
            $video->raw = $raw;
        }

        return $video;
    }

    /**
     * The four keys that are stored on the element itself.
     *
     * @return array{url: string, provider: string, videoId: string, hash: string|null}
     */
    public function forContentColumn(): array
    {
        return [
            'url' => (string)$this->url,
            'provider' => (string)$this->provider,
            'videoId' => (string)$this->videoId,
            'hash' => $this->hash,
        ];
    }

    /**
     * The metadata that is cached in the videokit_videos table.
     *
     * @return array<string, mixed>
     */
    public function toStorage(): array
    {
        return [
            'provider' => $this->provider,
            'videoId' => $this->videoId,
            'url' => $this->url,
            'hash' => $this->hash,
            'title' => $this->title,
            'description' => $this->description,
            'authorName' => $this->authorName,
            'authorUrl' => $this->authorUrl,
            'datePublished' => $this->datePublished?->format(DateTime::ATOM),
            'duration' => $this->duration,
            'plays' => $this->plays,
            'width' => $this->width,
            'height' => $this->height,
            'private' => $this->private,
            'thumbnails' => $this->thumbnails,
            'raw' => $this->raw,
        ];
    }

    public function serializeData(): string
    {
        return Json::encode($this->toStorage());
    }

    /**
     * Whether this video carries any metadata beyond the bare provider
     * reference. False means the picker never managed to look it up.
     */
    public function hasMetadata(): bool
    {
        return $this->title !== null || $this->thumbnails !== [];
    }

    public function getThumbnail(int $width = 600): ?string
    {
        return Thumbnails::closest($this->thumbnails, $width);
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    public function getThumbnailEntry(int $width = 600): ?array
    {
        return Thumbnails::closestEntry($this->thumbnails, $width);
    }

    /** Clock-style duration, or an empty string when there is none. */
    public function getFormattedDuration(): string
    {
        return Duration::format($this->duration);
    }

    /** ISO 8601 duration for schema.org markup, or null when there is none. */
    public function getDuration8601(): ?string
    {
        return Duration::toIso8601($this->duration);
    }

    /** Aspect ratio as a CSS `width / height` value. */
    public function getAspectRatio(): string
    {
        if ($this->width !== null && $this->height !== null && $this->width > 0 && $this->height > 0) {
            return sprintf('%d / %d', $this->width, $this->height);
        }

        return Plugin::getInstance()?->getSettings()->facadeAspectRatio ?? '16 / 9';
    }

    /** The public page for this video, for a fallback link. */
    public function getWatchUrl(): ?string
    {
        if ($this->provider !== null && $this->videoId !== null) {
            $canonical = $this->getProvider()?->getCanonicalUrl($this->videoId, $this->hash);

            if ($canonical !== null) {
                return $canonical;
            }
        }

        return $this->url;
    }

    public function getProviderName(): string
    {
        $provider = $this->getProvider();

        if ($provider !== null) {
            return $provider::displayName();
        }

        return (string)$this->provider;
    }

    /**
     * The player URL. Works without an API key and without a database read,
     * because the provider and id are stored on the element itself.
     *
     * @param array<string, mixed> $options
     */
    public function getEmbedUrl(array $options = []): ?string
    {
        if ($this->videoId === null) {
            return null;
        }

        return $this->getProvider()?->getEmbedUrl($this->videoId, $this->hash, $options);
    }

    /**
     * A player iframe. Prefer getFacadeHtml() on public pages: this loads the
     * third-party player immediately.
     *
     * @param array<string, mixed> $options
     */
    public function getEmbedHtml(array $options = []): Markup
    {
        $embedUrl = $this->getEmbedUrl($options);

        if ($embedUrl === null) {
            return Template::raw('');
        }

        $attributes = [
            'src' => $embedUrl,
            'title' => $this->title ?? Craft::t('videokit', 'Video'),
            'loading' => 'lazy',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'frameborder' => '0',
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'allowfullscreen' => true,
        ];

        if (isset($options['class']) && is_string($options['class'])) {
            $attributes['class'] = $options['class'];
        }

        return Template::raw(Html::tag('iframe', '', $attributes));
    }

    /**
     * A click-to-load poster. Nothing is requested from the provider until the
     * visitor clicks.
     *
     * @param array<string, mixed> $options
     */
    public function getFacadeHtml(array $options = []): Markup
    {
        $settings = Plugin::getInstance()?->getSettings();
        $thumbnailWidth = $settings->facadeThumbnailWidth ?? 1280;

        $embedOptions = $options['player'] ?? [];
        $embedUrl = $this->getEmbedUrl(is_array($embedOptions) ? $embedOptions + ['autoplay' => true] : ['autoplay' => true]);

        if ($embedUrl === null) {
            return Template::raw('');
        }

        $thumbnail = $options['thumbnail'] ?? null;
        $entry = $this->getThumbnailEntry($thumbnailWidth);

        return Template::raw(Facade::render([
            'embedUrl' => $embedUrl,
            'thumbnail' => is_string($thumbnail) ? $thumbnail : ($entry['url'] ?? null),
            'thumbnailWidth' => is_string($thumbnail) ? null : ($entry['width'] ?? null),
            'thumbnailHeight' => is_string($thumbnail) ? null : ($entry['height'] ?? null),
            'title' => $this->title,
            'watchUrl' => $this->getWatchUrl(),
            'ratio' => is_string($options['ratio'] ?? null) ? $options['ratio'] : $this->getAspectRatio(),
            'class' => is_string($options['class'] ?? null) ? $options['class'] : null,
            'showTitle' => (bool)($options['showTitle'] ?? false),
            'playLabel' => $this->title !== null
                ? Craft::t('videokit', 'Play video: {title}', ['title' => $this->title])
                : Craft::t('videokit', 'Play video'),
            'fallbackLabel' => Craft::t('videokit', 'Watch the video on {provider}', [
                'provider' => $this->getProviderName(),
            ]),
        ]));
    }

    /**
     * The payload the control panel JS works with. Deliberately carries an
     * embed URL rather than ready-made iframe HTML.
     *
     * @return array<string, mixed>
     */
    public function forJs(): array
    {
        $entry = $this->getThumbnailEntry(480);

        return [
            'provider' => $this->provider,
            'providerName' => $this->getProviderName(),
            'videoId' => $this->videoId,
            'url' => $this->url,
            'hash' => $this->hash,
            'title' => $this->title,
            'authorName' => $this->authorName,
            'duration' => $this->getFormattedDuration(),
            'plays' => $this->plays,
            'private' => $this->private,
            'thumbnail' => $entry['url'] ?? null,
            'thumbnailWidth' => $entry['width'] ?? null,
            'thumbnailHeight' => $entry['height'] ?? null,
            'embedUrl' => $this->getEmbedUrl(['autoplay' => true]),
            'watchUrl' => $this->getWatchUrl(),
            'hasMetadata' => $this->hasMetadata(),
        ];
    }

    private function getProvider(): ?ProviderInterface
    {
        if ($this->provider === null) {
            return null;
        }

        return Plugin::getInstance()?->getProviders()->getProviderByHandle($this->provider);
    }
}
