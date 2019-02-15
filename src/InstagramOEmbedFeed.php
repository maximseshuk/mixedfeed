<?php

namespace RZ\MixedFeed;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use RZ\MixedFeed\Canonical\FeedItem;
use RZ\MixedFeed\Canonical\Image;

class InstagramOEmbedFeed extends AbstractFeedProvider
{
    /**
     * @var array
     */
    protected $embedUrls;
    /**
     * InstagramOEmbedFeed constructor.
     *
     * @param                    $embedUrls
     * @param CacheProvider|null $cacheProvider
     */
    public function __construct($embedUrls, CacheProvider $cacheProvider = null)
    {
        parent::__construct($cacheProvider);
        $this->embedUrls = $embedUrls;
    }

    protected function getCacheKey(): string
    {
        return $this->getFeedPlatform() . serialize($this->embedUrls);
    }

    /**
     * @param int $count
     *
     * @return \Generator
     */
    public function getRequests($count = 5): \Generator
    {
        foreach ($this->embedUrls as $embedUrl) {
            $value = http_build_query([
                'url' => $embedUrl,
            ], null, '&', PHP_QUERY_RFC3986);
            yield new Request(
                'GET',
                'https://api.instagram.com/oembed?'.$value
            );
        }
    }

    /**
     * @param int $count
     *
     * @return array
     */
    protected function getRawFeed($count = 5)
    {
        try {
            $countKey = $this->getCacheKey() . $count;
            if (null !== $this->cacheProvider &&
                $this->cacheProvider->contains($countKey)) {
                return $this->cacheProvider->fetch($countKey);
            }
            $body = [];
            $promises = [];
            $client = new \GuzzleHttp\Client();
            foreach ($this->getRequests($count) as $request) {
                // Initiate each request but do not block
                $promises[] = $client->sendAsync($request);
            }
            $responses = \GuzzleHttp\Promise\settle($promises)->wait();

            /** @var array $response */
            foreach ($responses as $response) {
                //var_dump($response);
                if ($response['state'] !== 'rejected') {
                    array_push($body, json_decode($response['value']->getBody()->getContents()));
                } else {
                    throw $response['reason'];
                }
            }

            if (null !== $this->cacheProvider) {
                $this->cacheProvider->save(
                    $countKey,
                    $body,
                    $this->ttl
                );
            }

            return $body;
        } catch (ClientException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'instagram_oembed';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTime($item)
    {
        if (false !== preg_match("#datetime=\\\"([^\"]+)\\\"#", $item->html, $matches)) {
            return new \DateTime($matches[1]);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        if (isset($item->title)) {
            return $item->title;
        }

        return "";
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($feed)
    {
        return null !== $feed && is_array($feed) && !isset($feed['error']);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($feed)
    {
        return $feed['error'];
    }

    /**
     * @inheritDoc
     */
    protected function createFeedItemFromObject($item): FeedItem
    {
        $feedItem = parent::createFeedItemFromObject($item);
        $feedItem->setId($item->media_id);
        $feedItem->setAuthor($item->author_name);
        $feedItem->setLink($item->author_url);
        $feedItemImage = new Image();
        $feedItemImage->setUrl($item->thumbnail_url);
        $feedItemImage->setWidth($item->thumbnail_width);
        $feedItemImage->setHeight($item->thumbnail_height);
        $feedItem->addImage($feedItemImage);
        return $feedItem;
    }
}
