<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;

class FonnteService
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
            "Amount: " .  Number::format($invoice->total_amount) . "\n" .
            "Created By: {$detail->user->name}\n\n" .
            "Link: https://klik-lelang.vercel.app/workflow/inbox/{$invoice->id}";

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
