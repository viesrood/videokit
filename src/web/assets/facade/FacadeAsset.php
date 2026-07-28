<?php

declare(strict_types=1);

namespace viesrood\videokit\web\assets\facade;

use craft\web\AssetBundle;

/**
 * The front-end click-to-load loader: a small script plus the poster styling.
 *
 * Sites that compile their own bundle can set `registerFacadeAssets` to false
 * and copy `dist/videokit-facade.js` into it; the markup carries everything
 * the script needs in data attributes.
 */
class FacadeAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $js = [
        'videokit-facade.js',
    ];

    public $css = [
        'videokit-facade.css',
    ];

    public $jsOptions = [
        'defer' => true,
    ];
}
