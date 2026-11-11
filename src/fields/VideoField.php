<?php

declare(strict_types=1);

namespace viesrood\videokit\fields;

use Craft;
use craft\base\ElementInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface as Craft6ElementInterface;
use CraftCms\Cms\Image\Enums\ImageTransformMode;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\ThumbableFieldInterface;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;
use viesrood\videokit\web\assets\field\FieldAsset;
use yii\db\ExpressionInterface;
use yii\db\Schema;

/**
 * A single YouTube or Vimeo video.
 *
 * The element stores the provider reference (url, provider, videoId and, for
 * unlisted Vimeo videos, the privacy hash). Everything else lives in the
 * metadata cache, which means a stored value keeps rendering even when the
 * cache is empty or the API key has been removed.
 */
class VideoField extends Field implements PreviewableFieldInterface, ThumbableFieldInterface
{
    /**
     * Provider handles this field accepts. Empty means every enabled provider.
     *
     * @var string[]
     */
    public array $allowedProviders = [];

    /** Offer the search and browse modal, not just URL entry. */
    public bool $showBrowser = true;

    /** Show a thumbnail preview of the selected video. */
    public bool $showPreview = true;

    public static function displayName(): string
    {
        return Craft::t('videokit', 'Video');
    }

    public static function icon(): string
    {
        return 'film';
    }

    public static function phpType(): string
    {
        return sprintf('\\%s|null', Video::class);
    }

    /**
     * Four separate keys rather than one blob, so a query can filter on the
     * URL and a template can build a player URL without any lookup at all.
     *
     * @return array<string, string>
     */
    public static function dbType(): array
    {
        return [
            'url' => Schema::TYPE_STRING,
            'provider' => Schema::TYPE_STRING,
            'videoId' => Schema::TYPE_STRING,
            'hash' => Schema::TYPE_STRING,
        ];
    }

    /**
     * @param array<int, static> $instances
     * @param array<string, mixed> $params
     * @return array<mixed>|string|ExpressionInterface|false|null
     */
    public static function queryCondition(
        array $instances,
        mixed $value,
        array &$params,
    ): array|string|ExpressionInterface|false|null {
        $valueSql = static::valueSql($instances, 'url');

        if ($valueSql === null) {
            return false;
        }

        return Db::parseParam($valueSql, $value, caseInsensitive: true);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $video = $this->toVideo($value);

        if ($video === null || $video->provider === null || $video->videoId === null) {
            return null;
        }

        // Metadata comes from the local cache only. Looking it up over HTTP
        // here would mean an API call per row on an element index.
        $stored = Plugin::getInstance()?->getVideos()->getStoredVideo($video->provider, $video->videoId);

        if ($stored === null) {
            return $video;
        }

        $stored->url = $video->url ?? $stored->url;
        $stored->hash = $video->hash ?? $stored->hash;

        return $stored;
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!$value instanceof Video || $value->provider === null || $value->videoId === null) {
            return null;
        }

        return $value->forContentColumn();
    }

    /**
     * @return array<int, mixed>
     */
    public function getElementValidationRules(): array
    {
        return [
            [
                function (ElementInterface $element): void {
                    $this->validateVideo($element);
                },
            ],
        ];
    }

    public function getSearchKeywords(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof Video) {
            return '';
        }

        $keywords = array_filter([
            $value->title,
            $value->authorName,
            $value->url,
            $value->getProviderName(),
        ]);

        return StringHelper::encodeMb4(implode(' ', $keywords));
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof Video) {
            return '';
        }

        return Html::encode($value->title ?? (string)$value->url);
    }

    public function getThumbHtml(
        mixed $value,
        Craft6ElementInterface $element,
        int $size,
        ImageTransformMode $mode = ImageTransformMode::Fit,
    ): ?string
    {
        if (!$value instanceof Video) {
            return null;
        }

        $thumbnail = $value->getThumbnail($size);

        if ($thumbnail === null) {
            return null;
        }

        return Html::tag('div', '', [
            'class' => 'thumb',
            'style' => [
                'background-image' => sprintf('url(%s)', $thumbnail),
                'background-size' => 'cover',
                'background-position' => 'center',
            ],
        ]);
    }

    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        $value = $element->getFieldValue($this->handle);

        // Normally the picker already stored the metadata; this only catches
        // programmatic saves.
        if ($value instanceof Video) {
            Plugin::getInstance()?->getVideos()->ensureVideo($value);
        }

        parent::afterElementSave($element, $isNew);
    }

    public function getSettingsHtml(): ?string
    {
        $plugin = Plugin::getInstance();
        $providers = $plugin !== null ? $plugin->getProviders()->getAllProviders() : [];

        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();

        return $view->renderTemplate('videokit/_field/settings', [
            'field' => $this,
            'providerOptions' => array_map(
                static fn(ProviderInterface $provider): array => [
                    'label' => $provider::displayName(),
                    'value' => $provider::handle(),
                ],
                array_values($providers),
            ),
        ]);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();
        $view->registerAssetBundle(FieldAsset::class);

        $id = $this->getInputId();
        $providers = $this->getUsableProviders();

        $settings = [
            'name' => $view->namespaceInputName($this->handle),
            'value' => $value instanceof Video ? $value->forJs() : null,
            'showBrowser' => $this->showBrowser && $providers !== [],
            'showPreview' => $this->showPreview,
            'providers' => array_values(array_map(
                static fn(ProviderInterface $provider): array => [
                    'handle' => $provider::handle(),
                    'name' => $provider::displayName(),
                    'supportsSearch' => $provider->supportsSearch(),
                    'supportsCollections' => $provider->supportsCollections(),
                ],
                $providers,
            )),
            'perPage' => Plugin::getInstance()?->getSettings()->videosPerPage ?? 24,
        ];

        // registerJsWithVars JSON-encodes each var before calling the factory,
        // so both arguments arrive here as ready-to-inline strings.
        $view->registerJsWithVars(
            static fn(string $inputId, string $componentSettings): string
                => "new Craft.VideoKit.Input($inputId, $componentSettings);",
            [$view->namespaceInputId($id), $settings],
        );

        return $view->renderTemplate('videokit/_field/input', [
            'id' => $id,
            'field' => $this,
            'video' => $value instanceof Video ? $value : null,
            'settings' => $settings,
            'warning' => $this->getConfigurationWarning(),
        ]);
    }

    /**
     * The providers this field may use: enabled globally, and allowed here.
     *
     * @return array<string, ProviderInterface>
     */
    private function getUsableProviders(): array
    {
        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return [];
        }

        $providers = $plugin->getProviders()->getEnabledProviders();

        if ($this->allowedProviders === []) {
            return $providers;
        }

        return array_filter(
            $providers,
            fn(ProviderInterface $provider): bool => in_array($provider::handle(), $this->allowedProviders, true),
        );
    }

    private function getConfigurationWarning(): ?string
    {
        if ($this->getUsableProviders() !== []) {
            return null;
        }

        return Craft::t(
            'videokit',
            'No video provider is configured yet. You can still paste a video URL, but titles and thumbnails will stay empty.',
        );
    }

    private function validateVideo(ElementInterface $element): void
    {
        $value = $element->getFieldValue($this->handle);

        if (!$value instanceof Video) {
            return;
        }

        if ($value->provider === null || $value->videoId === null) {
            $element->addError(
                $this->handle,
                Craft::t('videokit', 'Enter a YouTube or Vimeo video URL.'),
            );

            return;
        }

        if ($this->allowedProviders !== [] && !in_array($value->provider, $this->allowedProviders, true)) {
            $element->addError($this->handle, Craft::t('videokit', 'Videos from {provider} are not allowed here.', [
                'provider' => $value->getProviderName(),
            ]));
        }
    }

    /**
     * Accepts a Video, the stored four-key array, a posted array, or a bare
     * URL string (which is what a programmatic save or an import will hand us).
     */
    private function toVideo(mixed $value): ?Video
    {
        if ($value instanceof Video) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            // Stored content arrives as JSON when it is read back raw.
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $this->fromArray($decoded);
            }

            return $this->fromUrl($value);
        }

        if (is_array($value)) {
            return $this->fromArray($value);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function fromArray(array $value): ?Video
    {
        $url = $value['url'] ?? null;
        $provider = $value['provider'] ?? null;
        $videoId = $value['videoId'] ?? null;

        // Trust the stored reference when it is complete and plausible;
        // otherwise fall back to parsing the URL again.
        if (
            is_string($provider) && is_string($videoId)
            && VideoUrl::isValidId($provider, $videoId)
        ) {
            $video = new Video();
            $video->provider = $provider;
            $video->videoId = $videoId;
            $video->hash = is_string($value['hash'] ?? null) ? $value['hash'] : null;
            $video->url = is_string($url) && $url !== ''
                ? $url
                : Plugin::getInstance()?->getProviders()->getProviderByHandle($provider)
                    ?->getCanonicalUrl($videoId, $video->hash);

            return $video;
        }

        return is_string($url) ? $this->fromUrl($url) : null;
    }

    private function fromUrl(string $url): Video
    {
        $parsed = VideoUrl::parse($url);

        if ($parsed === null) {
            // Keep the raw value so validation can explain what went wrong
            // instead of silently emptying the field.
            $video = new Video();
            $video->url = $url;

            return $video;
        }

        $video = new Video();
        $video->provider = $parsed['provider'];
        $video->videoId = $parsed['videoId'];
        $video->hash = $parsed['hash'];
        $video->url = $url;

        return $video;
    }
}
