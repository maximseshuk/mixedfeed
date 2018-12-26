<?php
namespace RZ\MixedFeed;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Exception\ClientException;
use RZ\MixedFeed\Exception\CredentialsException;

/**
 * Get an Youtube channel feed.
 */
class YoutubeFeed extends AbstractFeedProvider
{
    protected $channelId;
    protected $accessToken;
    protected $cacheProvider;
    protected $cacheKey;
    protected static $timeKey = 'publishedAt';

    public function __construct($channelId, $accessToken, CacheProvider $cacheProvider = null)
    {
        $this->channelId = $channelId;
        $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;
        $this->cacheKey = $this->getFeedPlatform() . $this->channelId;

        if (null === $this->accessToken ||
            false === $this->accessToken ||
            empty($this->accessToken)) {
            throw new CredentialsException("YoutubeFeed needs a valid access token.", 1);
        }
    }

    protected function getFeed($count = 5)
    {
        try {
            $countKey = $this->cacheKey . $count;

            if (null !== $this->cacheProvider &&
                $this->cacheProvider->contains($countKey)) {
                return $this->cacheProvider->fetch($countKey);
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://www.googleapis.com/youtube/v3/search', [
                'query' => [
	                'order' => 'date',
	                'part' => 'snippet',
	                'channelId' => $this->channelId,
                    'maxResults' => $count,
	                'key' => $this->accessToken,
                ],
            ]);
            $body = json_decode($response->getBody());

            if (null !== $this->cacheProvider) {
                $this->cacheProvider->save(
                    $countKey,
                    $body->items,
                    $this->ttl
                );
            }

            return $body->items;
        } catch (ClientException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTime($item)
    {
	    $date = new \DateTime();

	    if (null !== $item->snippet) {
		    $date->setTimestamp(strtotime($item->snippet->publishedAt));
	    }

        return $date;
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        if (isset($item->snippet)) {
            if(!empty($item->snippet->description)) {
	            return $item->snippet->description;
            }
            else {
	            return $item->snippet->title;
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'youtube';
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
}
