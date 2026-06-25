<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FonnteService
{
    /**
     * Create a new class instance.
     */
    public function __construct($invoice, $phone)
    {
        $detail = $invoice->load("type_trx:id,name", "user:id,name");

        $truncated = Str::limit($invoice->description, 20, preserveWords: true);

        $message = "*KLIK INVOICE*\n\n" .
            "Tanggal: {$invoice->date}\n" .
            "Invoice No: {$invoice->invoice_no}\n" .
            "Type Trx: {$detail->type_trx->name}\n" .
            "Deskripsi: {$truncated}\n" .
            "Created By: {$detail->user->name}\n\n" .
            "Link: https://keu.klikinternal.com/workflow/inbox/{$invoice->id}";

        $alldata = [
            [
                'target' => $phone,
                'message' => $message,
                'delay' => '1-3',
            ],
        ];

        $data = [
            "data" => json_encode($alldata)
        ];

        Http::withHeaders([
            'Authorization' => "xfyrovSRhZvKi9IvsYK9",
        ])->post('https://api.fonnte.com/send', [
            'data' => $data['data'],
        ])->json();

        return;
    }
}
