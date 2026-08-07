<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\CashRegisterSession;

class CashDiscrepancyNotification extends Notification
{
    use Queueable;

    protected $session;

    /**
     * Create a new notification instance.
     */
    public function __construct(CashRegisterSession $session)
    {
        $this->session = $session;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Cash Discrepancy Detected')
                    ->line('A discrepancy has been detected in the cash register session #' . $this->session->id)
                    ->line('Difference amount: ' . $this->session->difference_amount)
                    ->action('View Session', url('/sessions/' . $this->session->id))
                    ->line('Please review the session for details.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'session_id' => $this->session->id,
            'difference_amount' => $this->session->difference_amount,
        ];
    }
}
