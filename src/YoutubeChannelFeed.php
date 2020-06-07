<?php
declare(strict_types=1);

namespace RZ\MixedFeed;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Psr7\Request;
use RZ\MixedFeed\AbstractFeedProvider\AbstractYoutubeVideoFeed;
use RZ\MixedFeed\Canonical\FeedItem;
use RZ\MixedFeed\Canonical\Image;

class YoutubeChannelFeed extends AbstractYoutubeVideoFeed
{
	/**
	 * @var string
	 */
	protected $channelId;

	/**
	 * YoutubeChannelFeed constructor.
	 *
	 * @param string             $channelId
	 * @param string             $apiKey
	 * @param CacheProvider|null $cacheProvider
	 *
	 * @throws Exception\CredentialsException
	 */
    public function __construct(string $channelId, string $apiKey, CacheProvider $cacheProvider = null)
    {
	    parent::__construct($apiKey, $cacheProvider);

        $this->channelId = $channelId;
    }

	protected function getCacheKey(): string
	{
		return $this->getFeedPlatform() . serialize($this->channelId);
	}

	/**
	 * @param int $count
	 *
	 * @return \Generator
	 */
	public function getRequests($count = 5): \Generator
	{
		$value = http_build_query([
			'order' => 'date',
			'part' => 'snippet',
			'key' => $this->apiKey,
			'channelId' => $this->channelId,
			'maxResults' => $count,
			'type' => 'video',
		]);
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
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'youtube_channel_video';
    }

	/**
	 * @inheritDoc
	 */
	protected function createFeedItemFromObject($item): FeedItem
	{
		$feedItem = AbstractFeedProvider::createFeedItemFromObject($item);
		$feedItem->setId($item->id->videoId);
		$feedItem->setLink('https://www.youtube.com/watch?v=' . $item->id->videoId);
		$feedItem->setTitle($item->snippet->title);
		$feedItem->setMessage($item->snippet->description);
		$feedItem->setAuthor($item->snippet->channelTitle);

		if (
			isset($item->snippet->thumbnails)
			&& is_object($item->snippet->thumbnails)
			&& count((array) $item->snippet->thumbnails) > 0
		) {
			end($item->snippet->thumbnails);
			$maxThumbnailKey = key($item->snippet->thumbnails);

			$feedItemImage = new Image();
			$feedItemImage->setUrl($item->snippet->thumbnails->{$maxThumbnailKey}->url);
			$feedItemImage->setWidth($item->snippet->thumbnails->{$maxThumbnailKey}->width);
			$feedItemImage->setHeight($item->snippet->thumbnails->{$maxThumbnailKey}->height);
			$feedItem->setImages([$feedItemImage]);
		}

		return $feedItem;
	}
}
