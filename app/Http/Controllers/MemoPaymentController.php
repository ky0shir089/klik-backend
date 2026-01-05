<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MemoPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Payment $payment)
    {
        $payment->load([
            "spps",
            "spps.spp",
            "spps.spp.customer",
            "spps.spp.details",
            "spps.spp.details.unit",
            "spps.spp.details.unit.auction",
        ]);

        $slug = Str::slug($payment->spp_no);

        $spps = $payment->spps;
        $units = [];

        foreach ($spps as $spp) {
            $details = $spp->spp->details;

            foreach ($details as $detail) {
                $units[] = $detail->unit;
            }
        }

        $groups = collect($units)
            ->sortBy(function ($item) {
                return [
                    $item->auction->branch_name,
                    $item->auction->auction_date,
                ];
            })
            ->groupBy([
                'auction.branch_name',
                function ($item) {
                    return $item->auction->auction_date->format('d M y');
                },
            ]);

        $data = [
            'spp_no' => $payment->spp_no,
            'payment_date' => Carbon::parse($payment->payment_date)->format('d F Y'),
            'groups' => $groups,
            "total_unit" => $payment->total_unit,
            "total_amount" => $payment->total_amount,
        ];

        return Pdf::loadView('memo', $data)->download("memo-{$slug}.pdf");
    }
}
