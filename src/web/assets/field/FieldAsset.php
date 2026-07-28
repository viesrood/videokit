<?php

declare(strict_types=1);

namespace viesrood\videokit\web\assets\field;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

/**
 * The control panel side of the video field: URL entry, preview, and the
 * search/browse modal.
 */
class FieldAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'videokit.js',
    ];

    public $css = [
        'videokit.css',
    ];

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('videokit', [
                'Browse',
                'Cancel',
                'Choose a video',
                'Clear',
                'Could not reach the video service.',
                'Enter at least three characters.',
                'Load more',
                'Loading...',
                'No videos found.',
                'Nothing to show yet.',
                'Refresh',
                'Remove video',
                'Search',
                'Search videos',
                'Select',
                'That is not a YouTube or Vimeo video URL.',
                'This video has no stored details yet.',
            ]);
        }
    }
}
