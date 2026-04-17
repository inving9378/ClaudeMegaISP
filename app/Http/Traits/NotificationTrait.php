<?php

namespace App\Http\Traits;

trait NotificationTrait
{

    protected function getNotificationAttributes($notification)
    {
        $data = $notification->data ?? null;
        if (is_null($data) || $data === '') {
            return null;
        }
        $attr = $data[0] ?? $data;
        return isset($attr['attributes']) ? $attr['attributes'] : $attr;
    }
}
