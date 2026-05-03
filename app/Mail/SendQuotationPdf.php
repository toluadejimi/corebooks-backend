<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendQuotationPdf extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public Quotation $quotation,
        public string $pdfBinary,
        public ?string $userMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quotation '.$this->quotation->number.' — '.$this->business->name,
        );
    }

    public function content(): Content
    {
        $msg = $this->userMessage !== null && $this->userMessage !== ''
            ? '<blockquote style="border-left:3px solid #cbd5e1;padding-left:12px;margin:16px 0;color:#334155;">'.e($this->userMessage).'</blockquote>'
            : '';

        return new Content(
            htmlString: '<p style="font-family:system-ui,sans-serif;font-size:15px;">'
                .e($this->business->name).' has sent you a quotation.</p>'
                .$msg
                .'<p style="font-family:system-ui,sans-serif;font-size:15px;">Please see the attached PDF.</p>',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $this->quotation->number) ?? 'quotation';

        return [
            Attachment::fromData(fn () => $this->pdfBinary, $safe.'.pdf', [
                'mime' => 'application/pdf',
            ]),
        ];
    }
}
