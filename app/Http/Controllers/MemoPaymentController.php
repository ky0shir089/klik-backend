<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class MemoPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Payment $payment)
    {
        $memo = $payment->load([
            "customer",
            "units.unit.auction",
        ]);

        $slug = Str::slug($memo->customer->name);

        $month =  date('m');
        $year = date('y');

        return Pdf::view('memo', [
            'memo' => $memo,
            'memo_no' => 'KLIK/OPR/MP/' . $month . '/' . $year . '/' . $payment->id
        ])
            ->format(Format::A4)
            ->save(storage_path("app/public/memo/memo-{$slug}-{$payment->id}.pdf"));
    }
}
