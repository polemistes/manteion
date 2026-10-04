<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\User;

/**
 * Someone else replied to the recipient's leaflet. Withdrawn again when
 * the reply is deleted (see LeafletResponseController).
 */
class NewLeafletResponse extends ActivityNotification
{
    public function __construct(
        public LeafletResponse $response,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::LeafletResponses;
    }

    public function actor(): User
    {
        return $this->response->user;
    }

    public function subject(): Leaflet
    {
        return $this->response->leaflet;
    }

    public function excerpt(): string
    {
        return self::snippet($this->response->body);
    }

    /**
     * @return array<string, int>
     */
    protected function references(): array
    {
        return ['response_id' => $this->response->id];
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.new_leaflet_response.title');
    }

    protected function pushBody(): string
    {
        return __('notifications.push.new_leaflet_response.body', [
            'name' => $this->response->user->name,
            'excerpt' => $this->excerpt(),
        ]);
    }
}
