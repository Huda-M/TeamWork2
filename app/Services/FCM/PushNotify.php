<?php

namespace App\Services\FCM;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotify
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(storage_path('app/bridgex.json'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendPushNotification($deviceToken, $title, $message, $notificationData = [])
    {
        if (! $deviceToken) {
            return ['success' => false, 'message' => 'Device token is missing'];
        }
        $notification = Notification::create($title, $message);
        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification($notification)
            ->withData($notificationData);

        $this->messaging->send($message);

        return ['success' => true, 'message' => 'Notification sent successfully'];
    }

    /**
     * Send notification to multiple devices (Multicast)
     */
    public function sendBulkNotification(array $tokens, string $title, string $body, array $data = [])
    {
        if (empty($tokens)) {
            return null;
        }

        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        return $this->messaging->sendMulticast($message, $tokens);
    }
}
