<?php

declare(strict_types=1);

namespace viesrood\videokit\web\assets\utility;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

/**
 * Wires up the clear and refresh buttons in the VideoKit utility.
 */
class UtilityAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'videokit-utility.js',
    ];

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('videokit', [
                'The request failed. Check the Craft logs for details.',
            ]);
        }
    }
}
