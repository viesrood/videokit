<?php

declare(strict_types=1);

namespace viesrood\videokit\helpers;

use craft\helpers\Html;

/**
 * Renders the click-to-load poster that stands in for a video player.
 *
 * Nothing is requested from YouTube or Vimeo until the visitor clicks, which
 * is the actual privacy win: no third-party cookies, no player script, no
 * tracking pixel on a page nobody watched. The `noscript` fallback keeps the
 * video reachable when the loader script never runs.
 *
 * Labels are passed in already translated so this class stays free of Craft
 * runtime dependencies and remains unit-testable.
 */
final class Facade
{
    private const DEFAULT_RATIO = '16 / 9';

    private const PLAY_ICON = '<svg viewBox="0 0 68 48" width="68" height="48" aria-hidden="true" focusable="false">'
        . '<path class="vk-facade__play-shape" d="M66.5 7.7a8.6 8.6 0 0 0-6-6C55.2 0 34 0 34 0S12.8 0 7.5 1.7a8.6 8.6 0 0 0-6 6A89.6 89.6 0 0 0 0 24a89.6 89.6 0 0 0 1.5 16.3 8.6 8.6 0 0 0 6 6C12.8 48 34 48 34 48s21.2 0 26.5-1.7a8.6 8.6 0 0 0 6-6A89.6 89.6 0 0 0 68 24a89.6 89.6 0 0 0-1.5-16.3z"/>'
        . '<path class="vk-facade__play-arrow" d="M27 34V14l18 10z"/>'
        . '</svg>';

    /**
     * @param array{
     *     embedUrl: string,
     *     thumbnail?: string|null,
     *     thumbnailWidth?: int|null,
     *     thumbnailHeight?: int|null,
     *     title?: string|null,
     *     watchUrl?: string|null,
     *     ratio?: string|null,
     *     class?: string|null,
     *     playLabel?: string|null,
     *     fallbackLabel?: string|null,
     *     showTitle?: bool,
     * } $options
     */
    public static function render(array $options): string
    {
        $embedUrl = trim($options['embedUrl']);

        if ($embedUrl === '') {
            return '';
        }

        $title = self::text($options['title'] ?? null);
        $thumbnail = self::text($options['thumbnail'] ?? null);
        $watchUrl = self::text($options['watchUrl'] ?? null);
        $playLabel = self::text($options['playLabel'] ?? null) ?? 'Play video';
        $fallbackLabel = self::text($options['fallbackLabel'] ?? null) ?? 'Watch the video';

        $button = '';

        if ($thumbnail !== null) {
            $button .= Html::tag('img', '', [
                'class' => 'vk-facade__thumbnail',
                'src' => $thumbnail,
                'alt' => '',
                'loading' => 'lazy',
                'decoding' => 'async',
                'width' => self::dimension($options['thumbnailWidth'] ?? null),
                'height' => self::dimension($options['thumbnailHeight'] ?? null),
            ]);
        }

        $button .= Html::tag('span', self::PLAY_ICON, [
            'class' => 'vk-facade__play',
            'aria-hidden' => 'true',
        ]);

        if (($options['showTitle'] ?? false) && $title !== null) {
            $button .= Html::tag('span', Html::encode($title), ['class' => 'vk-facade__title']);
        }

        $inner = Html::tag('button', $button, [
            'type' => 'button',
            'class' => 'vk-facade__button',
            'aria-label' => $playLabel,
        ]);

        if ($watchUrl !== null) {
            $inner .= Html::tag('noscript', Html::tag('a', Html::encode($fallbackLabel), [
                'class' => 'vk-facade__fallback',
                'href' => $watchUrl,
                'rel' => 'noopener noreferrer',
                'target' => '_blank',
            ]));
        }

        return Html::tag('div', $inner, [
            'class' => array_values(array_filter(['vk-facade', self::text($options['class'] ?? null)])),
            'data-videokit-facade' => true,
            'data-embed-url' => $embedUrl,
            'data-title' => $title,
            'style' => sprintf('--vk-ratio: %s;', self::ratio($options['ratio'] ?? null)),
        ]);
    }

    /**
     * Only a plain `w / h` (or a bare number) is accepted, so the value cannot
     * smuggle extra declarations into the inline style attribute.
     */
    private static function ratio(mixed $value): string
    {
        if (!is_string($value)) {
            return self::DEFAULT_RATIO;
        }

        $ratio = trim($value);

        if (preg_match('#^\d{1,5}(\.\d{1,3})?(\s*/\s*\d{1,5}(\.\d{1,3})?)?$#', $ratio) !== 1) {
            return self::DEFAULT_RATIO;
        }

        return $ratio;
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function dimension(mixed $value): ?int
    {
        if (!is_int($value) || $value <= 0) {
            return null;
        }

        return $value;
    }
}
