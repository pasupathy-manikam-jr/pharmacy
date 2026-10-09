<?php

namespace App\Mail;

use App\Models\Branch;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StockDigest extends Mailable
{
    /**
     * @param  list<array{product: string, batch_no: string, expiry_date: string, qty: int}>  $expiring
     * @param  list<array{name: string, reorder_level: int, on_hand: int}>  $low
     */
    public function __construct(public Branch $branch, public array $expiring, public array $low) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':branch: :expiring expiring, :low to reorder', ['branch' => $this->branch->name, 'expiring' => count($this->expiring), 'low' => count($this->low)]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.stock-digest');
    }
}
