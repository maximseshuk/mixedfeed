<?php
namespace RZ\MixedFeed;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Psr7\Request;
use RZ\MixedFeed\Canonical\FeedItem;
use RZ\MixedFeed\Canonical\Image;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\Exception\FeedProviderErrorException;

/**
 * Get an Youtube channel feed.
 */
class YoutubeFeed extends AbstractFeedProvider
{
    protected $channelId;
    protected $accessToken;
    protected static $timeKey = 'publishedAt';

	/**
	 *
	 * @param string $channelId
	 * @param string $accessToken Your App Token
	 * @param CacheProvider|null $cacheProvider
	 * @throws CredentialsException
	 */
    public function __construct($channelId, $accessToken, CacheProvider $cacheProvider = null)
    {
	    parent::__construct($cacheProvider);
        $this->channelId = $channelId;
        $this->accessToken = $accessToken;

        if (null === $this->accessToken ||
            false === $this->accessToken ||
            empty($this->accessToken)) {
            throw new CredentialsException("YoutubeFeed needs a valid access token.", 1);
        }
    }

	protected function getCacheKey(): string
	{
		return $this->getFeedPlatform() . $this->channelId;
	}

	/**
	 * @inheritDoc
	 */
	public function getRequests($count = 5): \Generator
	{
		$value = http_build_query([
			'order' => 'date',
			'part' => 'snippet',
			'channelId' => $this->channelId,
			'maxResults' => $count,
			'key' => $this->accessToken,
		], null, '&', PHP_QUERY_RFC3986);
		yield new Request(
			'GET',
			'https://www.googleapis.com/youtube/v3/search?'.$value
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isValid($feed)
	{
		if (count($this->errors) > 0) {
			throw new FeedProviderErrorException($this->getFeedPlatform(), implode(', ', $this->errors));
		}
		return isset($feed->items) && is_iterable($feed->items);
	}

	/**
	 * @param int $count
	 * @return mixed
	 */
	protected function getFeed($count = 5)
	{
		$rawFeed = $this->getRawFeed($count);
		if ($this->isValid($rawFeed)) {
			return $rawFeed->items;
		}
		return [];
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
	protected function createFeedItemFromObject($item): FeedItem
    {
        $feedItem = parent::createFeedItemFromObject($item);
        $feedItem->setId( $item->id->videoId);
        if (isset( $item->snippet)) {
            $feedItem->setAuthor($item->snippet->channelTitle);
        }
        $feedItem->setLink('https://www.youtube.com/watch?v=' . $item->id->videoId);

        if (isset( $item->snippet->thumbnails)) {
            $feedItemImage = new Image();
            $feedItemImage->setUrl($item->snippet->thumbnails->high->url);
            $feedItemImage->setWidth($item->snippet->thumbnails->high->width);
            $feedItemImage->setHeight($item->snippet->thumbnails->high->height);
            $feedItem->addImage($feedItemImage);
        }

        return $feedItem;
    }
}
