<?php

namespace App\Modules\Addons\Marketing\Services\Publishing;

use App\Models\Marketing\PublicationChannel;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\AbstractPublishDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\EmailBlastDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\FacebookPageDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\InstagramFeedDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\InstagramReelsDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\InstagramStoriesDriver;
use App\Modules\Addons\Marketing\Services\Publishing\Drivers\WhatsAppStatusDriver;
use RuntimeException;

class ChannelManager
{
    private static array $driverMap = [
        'facebook:page_feed'    => FacebookPageDriver::class,
        'instagram:reels'       => InstagramReelsDriver::class,
        'instagram:feed_square' => InstagramFeedDriver::class,
        'instagram:stories'     => InstagramStoriesDriver::class,
        'whatsapp:status'       => WhatsAppStatusDriver::class,
        'email:blast'           => EmailBlastDriver::class,
    ];

    public function getDriver(PublicationChannel $channel, int $companyId = 1): AbstractPublishDriver
    {
        $key = "{$channel->platform}:{$channel->channel_type}";

        if (!isset(self::$driverMap[$key])) {
            throw new RuntimeException("No hay driver para canal: {$key}");
        }

        return new (self::$driverMap[$key])($channel, $companyId);
    }

    public function validateAllChannels(int $companyId = 1): array
    {
        $channels = PublicationChannel::forCompany($companyId)->active()->get();
        $results  = [];

        foreach ($channels as $channel) {
            try {
                $driver = $this->getDriver($channel, $companyId);
                $result = $driver->validateCredentials();

                $channel->update([
                    'credentials_ready'          => $result['valid'],
                    'credentials_status_message' => $result['message'],
                    'credentials_validated_at'   => now(),
                ]);

                $results[] = [
                    'channel_id'   => $channel->id,
                    'channel_name' => $channel->name,
                    'valid'        => $result['valid'],
                    'message'      => $result['message'],
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'channel_id'   => $channel->id,
                    'channel_name' => $channel->name,
                    'valid'        => false,
                    'message'      => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getAvailablePlatforms(): array
    {
        return array_keys(self::$driverMap);
    }
}
