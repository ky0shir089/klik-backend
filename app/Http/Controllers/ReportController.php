<?php

namespace App\Http\Controllers;

use App\Models\GL;
use App\Models\RV;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    private function resultsGenerator($results)
    {
        foreach ($results as $result) {
            yield $result;
        }
    }

    private function generateExcelReport($results, $columns, $filename)
    {
        (new FastExcel($this->resultsGenerator($results)))->configureOptionsUsing(function ($writer) {
            $writer->DEFAULT_COLUMN_WIDTH = 18;
        })->export(storage_path('app/public/' . $filename), function ($row) use ($columns) {
            info($row);
            return $columns($row);
        });
    }

    public function reportRv()
    {
        if (!auth()->user()->tokenCan("report-rv:download")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/rv/" . Str::random(6) . ".xlsx";

        $data = RV::query()
            ->where("coa_id", 58)
            ->where("ending_balance", "!=", 0)
            ->get();

        $columns = function ($row) {
            return [
                'RV No' => $row->rv_no,
                'Date' => $row->date,
                'Description' => $row->description,
                'Ending Balance' => $row->ending_balance,
                'Journal Number' => $row->journal_number,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "rv-report.xlsx");
    }

    public function reportAuction(Request $request)
    {
        if (!auth()->user()->tokenCan("report-auction:download")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/auction/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $data = Unit::query()
            ->with([
                "auction",
                "auction.customer",
                "spp",
                "classifications:unit_id,rv_id",
                "classifications.rv:id,rv_no,date,starting_balance",
            ])
            ->whereRelation("auction", "auction_date", [$from, $to])
            ->get();

        $columns = function ($row) {
            $rvNo = [];
            $rvDate = [];
            $rvAmount = [];

            foreach ($row->classifications as $classification) {
                $rvNo[] = $classification->rv->rv_no;
                $rvDate[] = $classification->rv->date;
                $rvAmount[] = $classification->rv->starting_balance;
            }

            return [
                'Tgl Lelang' => $row->auction->auction_date->format("Y-m-d"),
                'Nopol' => $row->police_number,
                'Noka' => $row->chassis_number,
                'Nosin' => $row->engine_number,
                'Status Transaksi' => $row->payment_status,
                'Status SPP' => $row->spp_status,
                'Harga Terbentuk' => $row->price,
                'Harga Admin' => $row->admin_fee,
                'Harga Total' => $row->final_price,
                'Bidder' => $row->auction->customer->name,
                'KTP' => $row->auction->customer->ktp,
                'VA Number' => $row->auction->customer->va_number,
                'Balai Lelang' => $row->auction->branch_name,
                'No RV' => collect($rvNo)->join(", "),
                'Tgl RV' => collect($rvDate)->join(", "),
                'Nominal RV' => collect($rvAmount)->join(", "),
                'Tgl Spp' => $row->spp?->created_at?->format("Y-m-d"),
                'Harga Distribusi' => $row->distributed_price,
                'Selisih' => $row->diff_price,
                'Nomor Paket' => $row->package_number,
                'Nomor Kontrak' => $row->contract_number,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "auction-report.xlsx");
    }

    public function reportBank(Request $request)
    {
        if (!auth()->user()->tokenCan($request->permission)) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $dirname = dirname(storage_path("app/public/reports/bank"));

        if (!file_exists($dirname)) {
            mkdir(dirname($dirname), 0755, true);
        }

        $id = "reports/bank/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $saldoAwal = GL::query()
            ->where("coa_id", $request->bank)
            ->where("date", "<", $from)
            ->sum(DB::raw("CASE WHEN type='IN' THEN credit ELSE -credit END"));

        $data = GL::query()
            ->where("coa_id", $request->bank)
            ->whereBetween("date", [$from, $to])
            ->oldest()
            ->get();

        $saldoAkhir = $saldoAwal +
            $data->sum(
                fn($row) =>
                $row->type === 'IN' ? $row->credit : -$row->credit
            );

        $startingBalance = [
            'date' => '',
            'description' => 'STARTING BALANCE',
            'gl_no' => '',
            'type' => 'IN',
            'debit' => '',
            'credit' => '',
            'balance' => $saldoAwal,
        ];

        $endingBalance = [
            'date' => '',
            'description' => 'ENDING BALANCE',
            'gl_no' => '',
            'type' => 'OUT',
            'debit' => '',
            'credit' => '',
            'balance' => $saldoAkhir,
        ];

        $exportRows = collect()
            ->push($startingBalance)
            ->merge($data)
            ->push($endingBalance);

        $columns = function ($row) {
            return [
                'Tanggal' => $row["date"],
                'Deskripsi' => $row["description"],
                'No Dokumen' => $row["gl_no"],
                'Uang Masuk' => $row["type"] == 'IN' ? $row["credit"] : '',
                'Uang Keluar' => $row["type"] == 'OUT' ? $row["credit"] : '',
                'Saldo' => $row["balance"],
            ];
        };

        $this->generateExcelReport($exportRows, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "bank-report.xlsx");
    }
}
