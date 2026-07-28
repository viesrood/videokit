<?php

declare(strict_types=1);

namespace viesrood\videokit\controllers;

use Craft;
use craft\web\Controller;
use Throwable;
use viesrood\videokit\Plugin;
use viesrood\videokit\utilities\VideosUtility;
use yii\web\Response;

/**
 * Backend for the VideoKit utility: clear or refresh the metadata cache.
 */
class CacheController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireLogin();
        $this->requirePermission('utility:' . VideosUtility::id());

        return parent::beforeAction($action);
    }

    /**
     * Drop stored metadata and the cached API responses.
     */
    public function actionClear(): Response
    {
        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return $this->failure();
        }

        $provider = $this->provider();
        $deleted = $plugin->getVideos()->delete($provider);
        $plugin->getProviders()->clearCaches($provider);

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
            'message' => Craft::t('videokit', '{count, plural, =1{1 video} other{# videos}} cleared.', [
                'count' => $deleted,
            ]),
        ]);
    }

    /**
     * Fetch stored videos again, oldest first.
     */
    public function actionRefresh(): Response
    {
        $plugin = Plugin::getInstance();

        if ($plugin === null) {
            return $this->failure();
        }

        $provider = $this->provider();
        $plugin->getProviders()->clearCaches($provider);

        try {
            $result = $plugin->getVideos()->refresh($provider);
        } catch (Throwable $e) {
            Craft::error('VideoKit: refreshing metadata failed: ' . $e->getMessage(), __METHOD__);

            return $this->failure();
        }

        return $this->asJson([
            'success' => true,
            'refreshed' => $result['refreshed'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'message' => Craft::t('videokit', 'Refreshed {refreshed}, failed {failed}, skipped {skipped}.', $result),
        ]);
    }

    private function provider(): ?string
    {
        $provider = $this->request->getBodyParam('provider');

        if (!is_string($provider) || $provider === '') {
            return null;
        }

        return Plugin::getInstance()?->getProviders()->getProviderByHandle($provider) !== null
            ? $provider
            : null;
    }

    private function failure(): Response
    {
        $this->response->setStatusCode(500);

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('videokit', 'The request failed. Check the Craft logs for details.'),
        ]);
    }
}
