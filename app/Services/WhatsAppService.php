<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    /**
     * Create a new class instance.
     */
    public function __construct($invoice, $phone)
    {
        $detail = $invoice->load("type_trx:id,name", "user:id,name");

        $message = "*KLIK INVOICE*\n\n" .
            "Tanggal: {$invoice->date}\n" .
            "Invoice No: {$invoice->invoice_no}\n" .
            "Type Trx: {$detail->type_trx->name}\n" .
            "Deskripsi: {$invoice->description}\n" .
            "Created By: {$detail->user->name}\n\n" .
            "Link: https://keu.klikinternal.com/workflow/inbox/{$invoice->id}";

        $response = Http::withHeaders([
            'X-Device-Id' => 'klikkeuangandev',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic dXNlcjE6cGFzczE=',
        ])->post('https://wa.dnalab.dev/send/message', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return;
    }
}
