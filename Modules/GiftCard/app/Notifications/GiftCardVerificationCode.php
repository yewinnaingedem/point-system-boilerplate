<?php

namespace Modules\GiftCard\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Second step of a protected exchange: the 6-digit code, by email. */
class GiftCardVerificationCode extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $giftCardName,
        public readonly int $points,
        public readonly int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your gift card verification code'))
            ->line(__('Use this code to confirm exchanging :points points for ":card":', ['points' => number_format($this->points), 'card' => $this->giftCardName]))
            ->line("**{$this->code}**")
            ->line(__('It works for :minutes minutes. If you did not ask for this, ignore this email: no points are taken without the code.', ['minutes' => $this->minutes]));
    }
}
