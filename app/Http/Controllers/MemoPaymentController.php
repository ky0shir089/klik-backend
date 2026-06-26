<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AliasService;
use App\Services\SignatureSvgService;
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

        $invoice = Invoice::select("id", "invoice_no", "created_by")
            ->with(["user", "wf_histories"])
            ->where("invoice_no", $payment->spp_no)
            ->first();

        $slug = Str::slug($payment->spp_no);

        $username = strtoupper($invoice->user->name);

        $from = (new AliasService())->handle($username);

        $wfHistories = $invoice->wf_histories;

        $approvals = [];

        foreach ($wfHistories as $wf) {
            $wf->load("user:id,name");
            $alias = (new AliasService())->handle(strtoupper($wf->user->name));
            $points = json_decode($wf->signature, true);
            if (isset($points)) {
                $svg = (new SignatureSvgService())->generateSignatureSvg($points);
                $html = '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '"  width="100" height="100" />';
            } else {
                $html = null;
            }
            $approvals[] = [
                'name' => $alias,
                'signature' => $html,
            ];
        }

        if (count($approvals) > 0) {
            $to = $approvals[count($approvals) - 1]['name'];
        } else {
            $to = null;
        }

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
            'from' => $from,
            'to' => $to,
            'approvals' => $approvals,
        ];

        return Pdf::loadView('memo', $data)->download("memo-{$slug}.pdf");
    }
}
