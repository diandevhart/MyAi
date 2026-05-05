<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
        public readonly array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => $this->type,
            'title'        => $this->title,
            'message'      => $this->message,
            'action_url'   => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'meta'         => $this->meta,
        ];
    }
}
