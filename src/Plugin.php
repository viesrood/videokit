<?php

declare(strict_types=1);

namespace viesrood\videokit;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use CraftCms\Cms\Twig\Twig;
use viesrood\videokit\fields\VideoField;
use viesrood\videokit\models\Settings;
use viesrood\videokit\services\EmbedService;
use viesrood\videokit\services\ProvidersService;
use viesrood\videokit\services\VideosService;
use viesrood\videokit\utilities\VideosUtility;
use viesrood\videokit\variables\VideoKitVariable;
use viesrood\videokit\web\twig\VideoKitExtension;
use yii\base\Event;

/**
 * VideoKit plugin.
 *
 * A video field for YouTube and Vimeo:
 * - paste a URL or browse the configured accounts from the control panel;
 * - metadata is cached in the database, so rendering never calls an API;
 * - Twig helpers for the player URL, a plain iframe, and a click-to-load
 *   facade that contacts nothing until the visitor clicks.
 *
 * @property-read EmbedService $embed
 * @property-read ProvidersService $providers
 * @property-read VideosService $videos
 */
class Plugin extends BasePlugin
{
    /** Cache tag for every response VideoKit stores in Craft's cache. */
    public const CACHE_TAG = 'videokit';

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    /**
     * Register the services as components so Plugin::getInstance()->videos works.
     *
     * @return array{components: array<string, mixed>}
     */
    public static function config(): array
    {
        return [
            'components' => [
                'embed' => EmbedService::class,
                'providers' => ProvidersService::class,
                'videos' => VideosService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerFieldType();
        $this->registerTwigExtension();
        $this->registerVariable();
        $this->registerUtility();
        $this->registerCacheOptions();

        Craft::info('VideoKit plugin loaded', __METHOD__);
    }

    /**
     * Shortcut to the front-end embed helpers.
     */
    public function getEmbed(): EmbedService
    {
        /** @var EmbedService $service */
        $service = $this->get('embed');

        return $service;
    }

    /**
     * Shortcut to the provider registry.
     */
    public function getProviders(): ProvidersService
    {
        /** @var ProvidersService $service */
        $service = $this->get('providers');

        return $service;
    }

    /**
     * Shortcut to the metadata service.
     */
    public function getVideos(): VideosService
    {
        /** @var VideosService $service */
        $service = $this->get('videos');

        return $service;
    }

    public function getSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = parent::getSettings();

        return $settings;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();

        return $view->renderTemplate('videokit/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'providers' => $this->getProviders()->getAllProviders(),
        ], View::TEMPLATE_MODE_CP);
    }

    private function registerFieldType(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types[] = VideoField::class;
            }
        );
    }

    private function registerTwigExtension(): void
    {
        // Deferred, because the extension resolves settings that are not ready
        // while plugins are still booting.
        //
        // Craft 6 binds Twig as a scoped service. A queue worker flushes scoped
        // instances after every job, and the next Twig instance only has the
        // extensions registered on that instance. So register on the current
        // instance, and again on every instance that is resolved later.
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;

        $app->onInit(static function (): void {
            $register = static function (Twig $twig): void {
                $twig->registerExtension(new VideoKitExtension());
            };

            $register(app(Twig::class));
            app()->afterResolving(Twig::class, $register);
        });
    }

    private function registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('videokit', VideoKitVariable::class);
            }
        );
    }

    private function registerUtility(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types[] = VideosUtility::class;
            }
        );
    }

    private function registerCacheOptions(): void
    {
        // Makes the API response cache clearable from Craft's own Caches utility.
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_TAG_OPTIONS,
            static function (RegisterCacheOptionsEvent $event): void {
                $event->options[] = [
                    'tag' => self::CACHE_TAG,
                    'label' => Craft::t('videokit', 'VideoKit API responses'),
                ];
            }
        );
    }
}
