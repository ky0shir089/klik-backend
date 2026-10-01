<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppService
{
    public function __construct($invoice = null, ?string $phone = null)
    {
        if ($invoice && $phone) {
            $this->send($invoice, $phone);
        }
    }

    public function send($invoice, ?string $phone): void
    {
        $this->dispatch($phone, fn () => $this->baseMessage($invoice));
    }

    public function reject($invoice, ?string $phone, ?string $remark = null): void
    {
        $this->dispatch($phone, function () use ($invoice, $remark) {
            $reason = $remark ?: '-';

            return $this->baseMessage($invoice) . "\n\n" .
                "Mohon maaf, invoice anda ditolak\n" .
                "Karena: {$reason}\n" .
                "Silahkan periksa kembali invoice anda.";
        });
    }

    private function dispatch(?string $phone, Closure $messageResolver): void
    {
        if (!$phone) {
            return;
        }

        DB::afterCommit(function () use ($phone, $messageResolver) {
            try {
                $this->postMessage($phone, $messageResolver());
            } catch (Throwable $th) {
                report($th);
            }
        });
    }

    private function baseMessage($invoice): string
    {
        $invoice->loadMissing('type_trx:id,name', 'user:id,name');

        return "*KLIK INVOICE*\n\n" .
            "Tanggal: {$invoice->date}\n" .
            "Invoice No: {$invoice->invoice_no}\n" .
            "Type Trx: {$invoice->type_trx?->name}\n" .
            "Deskripsi: {$invoice->description}\n" .
            "Created By: {$invoice->user?->name}\n\n" .
            "Link: https://keu.klikinternal.com/workflow/inbox/{$invoice->id}";
    }

    private function postMessage(string $phone, string $message): void
    {
        Http::withHeaders([
            'X-Device-Id' => 'klikkeuangandev',
            'Authorization' => 'Basic dXNlcjE6cGFzczE=',
        ])
        ->timeout(10)
        ->connectTimeout(3)
        ->retry(2, 100)
        ->post('https://wa.dnalab.dev/send/message', [
            'phone' => $phone,
            'message' => $message,
        ])
        ->throw();
    }
}
