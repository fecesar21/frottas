<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumoOperacionalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array  $dados  Saída de ResumoOperacionalService::gerar() + titulo/periodo
     */
    public function __construct(public array $dados, public string $nomeArquivo) {}

    public static function pdf(array $dados): string
    {
        return Pdf::loadView('relatorios.pdf.resumo-operacional', $dados)->output();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Health Drive — '.$this->dados['titulo']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.resumo-operacional', with: $this->dados);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => self::pdf($this->dados), $this->nomeArquivo)->withMime('application/pdf'),
        ];
    }
}
