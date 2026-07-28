<?php

declare(strict_types=1);

namespace viesrood\videokit\controllers;

use Craft;
use craft\web\Controller;
use Throwable;
use viesrood\videokit\base\ProviderException;
use viesrood\videokit\base\ProviderInterface;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Collection;
use viesrood\videokit\Plugin;
use yii\web\Response;

/**
 * Backend for the video field: look up a pasted URL, and search or browse a
 * provider from the picker modal.
 *
 * Credentials never leave the server. The client only ever sends a provider
 * handle, a video URL or an opaque paging cursor.
 */
class VideosController extends Controller
{
    /** Requests per minute per user, so a stuck modal cannot drain an API quota. */
    private const RATE_LIMIT = 60;

    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireLogin();
        $this->requirePermission('accessCp');

        if (!$this->underRateLimit()) {
            $this->response->setStatusCode(429);
            $this->response->data = [
                'success' => false,
                'error' => Craft::t('videokit', 'Too many requests. Wait a moment and try again.'),
            ];
            $this->response->format = Response::FORMAT_JSON;

            return false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Resolve a pasted video URL into a video.
     */
    public function actionGetVideo(): Response
    {
        $url = trim((string)$this->request->getBodyParam('url', ''));
        $refresh = (bool)$this->request->getBodyParam('refresh', false);

        $parsed = VideoUrl::parse($url);

        if ($parsed === null) {
            return $this->failure(Craft::t('videokit', 'That is not a YouTube or Vimeo video URL.'));
        }

        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return $this->failure(Craft::t('videokit', 'VideoKit is not available.'), 500);
        }

        try {
            // Falls back to the bare provider reference when there is no
            // metadata, so the field still gets a usable value.
            $video = $plugin->getVideos()->getVideoByUrl($url, $refresh);
        } catch (Throwable $e) {
            return $this->upstreamFailure($e, 'get-video');
        }

        if ($video === null) {
            return $this->failure(Craft::t('videokit', 'That is not a YouTube or Vimeo video URL.'));
        }

        return $this->asJson(['success' => true, 'video' => $video->forJs()]);
    }

    /**
     * Keyword search within one provider.
     */
    public function actionSearch(): Response
    {
        $provider = $this->resolveProvider();

        if ($provider instanceof Response) {
            return $provider;
        }

        $query = trim((string)$this->request->getBodyParam('q', ''));

        if (mb_strlen($query) < 3) {
            return $this->failure(Craft::t('videokit', 'Enter at least three characters.'));
        }

        if (!$provider->supportsSearch()) {
            return $this->failure(Craft::t('videokit', 'This provider does not support search.'));
        }

        try {
            $results = $provider->search(mb_substr($query, 0, 200), $this->page());
        } catch (Throwable $e) {
            return $this->upstreamFailure($e, 'search');
        }

        return $this->asJson(['success' => true] + $results->forJs());
    }

    /**
     * The collections shown in the picker sidebar.
     */
    public function actionGetCollections(): Response
    {
        $provider = $this->resolveProvider();

        if ($provider instanceof Response) {
            return $provider;
        }

        try {
            $collections = $provider->getCollections();
        } catch (Throwable $e) {
            return $this->upstreamFailure($e, 'get-collections');
        }

        return $this->asJson([
            'success' => true,
            'collections' => array_map(
                static fn(Collection $collection): array => $collection->forJs(),
                $collections,
            ),
        ]);
    }

    /**
     * One page of videos from a collection.
     */
    public function actionGetCollectionVideos(): Response
    {
        $provider = $this->resolveProvider();

        if ($provider instanceof Response) {
            return $provider;
        }

        $type = (string)$this->request->getBodyParam('collectionType', '');

        if (!Collection::isValidType($type)) {
            return $this->failure(Craft::t('videokit', 'Unknown collection.'));
        }

        $collection = new Collection();
        $collection->type = $type;
        $collection->id = (string)$this->request->getBodyParam('collectionId', '');

        try {
            $results = $provider->getCollectionVideos($collection, $this->page());
        } catch (Throwable $e) {
            return $this->upstreamFailure($e, 'get-collection-videos');
        }

        return $this->asJson(['success' => true] + $results->forJs());
    }

    /**
     * @return ProviderInterface|Response The provider, or a failure response.
     */
    private function resolveProvider(): ProviderInterface|Response
    {
        $handle = (string)$this->request->getBodyParam('provider', '');
        $provider = Plugin::getInstance()?->getProviders()->getEnabledProviderByHandle($handle);

        if ($provider === null) {
            return $this->failure(Craft::t('videokit', 'That video provider is not available.'));
        }

        return $provider;
    }

    /**
     * The paging cursor is opaque: a YouTube page token or a Vimeo page number.
     * It is length-capped and otherwise handed back to the provider unchanged.
     */
    private function page(): ?string
    {
        $page = $this->request->getBodyParam('page');

        if (!is_string($page) || $page === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_=-]{1,128}$/', $page) === 1 ? $page : null;
    }

    private function underRateLimit(): bool
    {
        $userId = Craft::$app->getUser()->getId();

        if ($userId === null) {
            return false;
        }

        $cache = Craft::$app->getCache();
        $key = sprintf('%s:ratelimit:%d:%d', Plugin::CACHE_TAG, $userId, (int)floor(time() / 60));
        $count = (int)$cache->get($key);

        if ($count >= self::RATE_LIMIT) {
            return false;
        }

        $cache->set($key, $count + 1, 120);

        return true;
    }

    private function upstreamFailure(Throwable $e, string $context): Response
    {
        if ($e instanceof ProviderException) {
            Craft::warning(sprintf('VideoKit (%s): %s', $context, $e->getMessage()), __METHOD__);

            return $this->failure(Craft::t('videokit', 'The video service did not respond. Try again later.'), 502);
        }

        // Never leak an arbitrary exception message to the browser.
        Craft::error(sprintf('VideoKit (%s) failed: %s', $context, $e->getMessage()), __METHOD__);

        return $this->failure(Craft::t('videokit', 'The request failed. Check the Craft logs for details.'), 500);
    }

    private function failure(string $message, int $statusCode = 400): Response
    {
        $this->response->setStatusCode($statusCode);

        return $this->asJson(['success' => false, 'error' => $message]);
    }
}
