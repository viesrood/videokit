<?php

declare(strict_types=1);

namespace viesrood\videokit\services;

use Craft;
use craft\base\Component;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\Plugin;
use viesrood\videokit\providers\Vimeo;
use viesrood\videokit\providers\YouTube;
use yii\caching\TagDependency;

/**
 * The registry of video providers.
 *
 * Providers are code-defined, so this is a fixed list rather than a database
 * table. "Known" means the class exists, "configured" means its credential is
 * present, and "enabled" additionally means the settings allow it.
 */
class ProvidersService extends Component
{
    /** @var array<string, ProviderInterface>|null */
    private ?array $_providers = null;

    /**
     * Every known provider, whether or not it is configured. Embedding needs
     * no credential, so this is what the front end resolves against.
     *
     * @return array<string, ProviderInterface>
     */
    public function getAllProviders(): array
    {
        if ($this->_providers === null) {
            $this->_providers = [];

            foreach ([YouTube::class, Vimeo::class] as $class) {
                /** @var ProviderInterface $provider */
                $provider = Craft::createObject($class);
                $this->_providers[$provider::handle()] = $provider;
            }
        }

        return $this->_providers;
    }

    /**
     * Providers that are configured and allowed by the settings.
     *
     * @return array<string, ProviderInterface>
     */
    public function getEnabledProviders(): array
    {
        $plugin = Plugin::getInstance();
        $allowed = $plugin !== null ? $plugin->getSettings()->getEnabledProviders() : [];

        return array_filter(
            $this->getAllProviders(),
            static fn(ProviderInterface $provider): bool => $provider->isConfigured()
                && ($allowed === [] || in_array($provider::handle(), $allowed, true)),
        );
    }

    /**
     * A provider by handle, configured or not.
     */
    public function getProviderByHandle(string $handle): ?ProviderInterface
    {
        return $this->getAllProviders()[$handle] ?? null;
    }

    /**
     * A provider by handle, but only when it is enabled. Use this for anything
     * that talks to a provider API.
     */
    public function getEnabledProviderByHandle(string $handle): ?ProviderInterface
    {
        return $this->getEnabledProviders()[$handle] ?? null;
    }

    /**
     * The provider that recognises the given URL, whether or not it is
     * configured.
     */
    public function getProviderForUrl(string $url): ?ProviderInterface
    {
        $parsed = VideoUrl::parse($url);

        if ($parsed === null) {
            return null;
        }

        return $this->getProviderByHandle($parsed['provider']);
    }

    /**
     * Whether any provider (or the given one) is ready to use.
     */
    public function isConfigured(?string $handle = null): bool
    {
        if ($handle !== null) {
            return $this->getEnabledProviderByHandle($handle) !== null;
        }

        return $this->getEnabledProviders() !== [];
    }

    /**
     * Discard cached API responses for one provider, or for all of them.
     */
    public function clearCaches(?string $handle = null): void
    {
        if ($handle !== null) {
            $this->getProviderByHandle($handle)?->clearCache();

            return;
        }

        TagDependency::invalidate(Craft::$app->getCache(), Plugin::CACHE_TAG);
    }
}
