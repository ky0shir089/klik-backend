<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GL;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceExternal;
use App\Models\RV;
use App\Models\Settlement;
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
                "spp.detail",
                "spp.detail.pv:spp_id,pv_no,paid_date"
            ])
            ->whereHas("auction", function ($query) use ($from, $to) {
                $query->whereBetween("auction_date", [$from, $to]);
            })
            ->oldest("id")
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
                'No PV' => $row->spp?->detail?->pv?->pv_no,
                'Tgl PV' => $row->spp?->detail?->pv?->paid_date,
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

        $id = "reports/bank/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $gl = GL::query()
            ->with("coa")
            ->where("coa_id", $request->bank)
            ->where("date", "<", $from)
            ->get();

        $saldoAwal = $gl->sum("debit") - $gl->sum("credit");

        $data = GL::query()
            ->with("coa")
            ->where("coa_id", $request->bank)
            ->whereBetween("date", [$from, $to])
            ->orderBy("date", "asc")
            ->orderBy("id", "asc")
            ->get();

        $saldoAkhir = $saldoAwal + $data->sum("debit") - $data->sum("credit");

        $headerTitle = [
            'date' => 'Laporan Bank',
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerBank = [
            'date' => count($gl) > 0 ? $gl[0]->coa->description : $data[0]->coa->description,
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerDate = [
            'date' => 'Periode ' . $request->from . ' - ' . $request->to,
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerSpace = [
            'date' => '',
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $startingBalance = [
            'date' => '',
            'description' => 'BEGINNING BALANCE',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => (int)$saldoAwal,
        ];

        $endingBalance = [
            'date' => '',
            'description' => 'ENDING BALANCE',
            'gl_no' => '',
            'debit' => (int)$data->sum("debit"),
            'credit' => (int)$data->sum("credit"),
            'balance' => $saldoAkhir,
        ];

        $exportRows = collect()
            ->push($headerTitle)
            ->push($headerBank)
            ->push($headerDate)
            ->push($headerSpace)
            ->push($startingBalance)
            ->merge($data)
            ->push($endingBalance);

        $columns = function ($row) {
            return [
                'Tanggal' => $row["date"],
                'Deskripsi' => $row["description"],
                'No Dokumen' => $row["gl_no"],
                'Uang Masuk' => $row["debit"] == '' ? '' : (int)$row["debit"],
                'Uang Keluar' => $row["credit"] == '' ? '' : (int)$row["credit"],
                'Saldo' => $row["balance"],
            ];
        };

        $this->generateExcelReport($exportRows, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "bank-report.xlsx");
    }

    public function reportGl(Request $request)
    {
        if (!auth()->user()->tokenCan('report-gl:download')) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/gl/" . Str::random(6) . ".xlsx";
        $from = $request->from;
        $to = $request->to;

        $data = ChartOfAccount::select("id", "code", "description")
            ->withSum([
                "gl as saldo_awal" => function ($query) use ($from) {
                    $query->where("date", "<", $from);
                }
            ], "debit")
            ->withSum([
                "gl as saldo_akhir" => function ($query) use ($from) {
                    $query->where("date", "<", $from);
                }
            ], "credit")
            ->with([
                "gl" => function ($query) use ($from, $to) {
                    $query->whereBetween("date", [$from, $to])
                        ->orderBy("date", "asc")
                        ->orderBy("id", "asc");
                }
            ])
            ->withSum([
                "gl" => function ($query) use ($from, $to) {
                    $query->whereBetween("date", [$from, $to]);
                }
            ], "debit")
            ->withSum([
                "gl" => function ($query) use ($from, $to) {
                    $query->whereBetween("date", [$from, $to]);
                }
            ], "credit")
            ->whereHas("gl", function ($query) use ($from, $to) {
                $query->whereBetween("date", [$from, $to]);
            })
            ->oldest("id")
            ->get();

        $rows = collect();

        foreach ($data as $coa) {
            $startingBalance = $coa->saldo_awal - $coa->saldo_akhir;

            $rows->push([
                'Keterangan' => $coa->code . '.' . $coa->description,
                'Kode Akun' => '',
                'No Jurnal' => '',
                'Tanggal' => '',
                'Debit' => '',
                'Credit' => '',
                'Balance' => $startingBalance,
            ]);

            foreach ($coa->gl as $gl) {
                $totalDebit = $coa->gl_sum_debit;
                $totalCredit = $coa->gl_sum_credit;
                $endingBalance = $startingBalance + $totalDebit - $totalCredit;

                $rows->push([
                    'Keterangan' => $gl->description,
                    'Kode Akun' => $coa->code,
                    'No Jurnal' => $gl->gl_no,
                    'Tanggal' => Carbon::parse($gl->date)->format('d-m-Y'),
                    'Debit' => $gl->debit,
                    'Credit' => $gl->credit,
                    'Balance' => ''
                ]);
            }

            $rows->push(
                [
                    'Keterangan' => 'TOTAL ' .  $coa->code . '.' . $coa->description,
                    'Kode Akun' => '',
                    'No Jurnal' => '',
                    'Tanggal' => '',
                    'Debit' =>  (int)$totalDebit,
                    'Credit' => (int)$totalCredit,
                    'Balance' => $endingBalance
                ],
                [
                    'Keterangan' => '',
                    'Kode Akun' => '',
                    'No Jurnal' => '',
                    'Tanggal' => '',
                    'Debit' => '',
                    'Credit' => '',
                    'Balance' => '',
                ],

            );
        }

        $columns = function ($row) {
            return [
                'Keterangan' => $row['Keterangan'],
                'Kode Akun' => $row['Kode Akun'],
                'No Jurnal' => $row['No Jurnal'],
                'Tanggal' => $row['Tanggal'],
                'Debit' => $row['Debit'] == '' ? '' : (int)$row['Debit'],
                'Credit' => $row['Credit'] == '' ? '' : (int)$row['Credit'],
                'Balance' => $row['Balance'],
            ];
        };

        $this->generateExcelReport($rows, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "gl-report.xlsx");
    }

    public function reportKas(Request $request)
    {
        if (!auth()->user()->tokenCan($request->permission)) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/kas/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $gl = GL::query()
            ->with("coa")
            ->where("coa_id", $request->cash)
            ->where("date", "<", $from)
            ->get();

        $saldoAwal = $gl->sum("debit") - $gl->sum("credit");

        $data = GL::query()
            ->with("coa")
            ->where("coa_id", $request->cash)
            ->whereBetween("date", [$from, $to])
            ->orderBy("date", "asc")
            ->orderBy("id", "asc")
            ->get();

        $saldoAkhir = $saldoAwal + $data->sum("debit") - $data->sum("credit");

        $headerTitle = [
            'date' => 'Laporan Kas',
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerKas = [
            'date' => count($gl) > 0 ? $gl[0]->coa->description : $data[0]->coa->description,
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerDate = [
            'date' => 'Periode ' . $request->from . ' - ' . $request->to,
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $headerSpace = [
            'date' => '',
            'description' => '',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
        ];

        $startingBalance = [
            'date' => '',
            'description' => 'BEGINNING BALANCE',
            'gl_no' => '',
            'debit' => '',
            'credit' => '',
            'balance' => (int)$saldoAwal,
        ];

        $endingBalance = [
            'date' => '',
            'description' => 'ENDING BALANCE',
            'gl_no' => '',
            'debit' => (int)$data->sum("debit"),
            'credit' => (int)$data->sum("credit"),
            'balance' => $saldoAkhir,
        ];

        $exportRows = collect()
            ->push($headerTitle)
            ->push($headerKas)
            ->push($headerDate)
            ->push($headerSpace)
            ->push($startingBalance)
            ->merge($data)
            ->push($endingBalance);

        $columns = function ($row) {
            return [
                'Tanggal' => $row["date"],
                'Deskripsi' => $row["description"],
                'No Dokumen' => $row["gl_no"],
                'Uang Masuk' => $row["debit"] == '' ? '' : (int)$row["debit"],
                'Uang Keluar' => $row["credit"] == '' ? '' : (int)$row["credit"],
                'Saldo' => $row["balance"],
            ];
        };

        $this->generateExcelReport($exportRows, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "kas-report.xlsx");
    }

    public function reportInvoice(Request $request)
    {
        if (!auth()->user()->tokenCan("report-invoice:download")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/invoice/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);
        $isAdminKlik = auth()->user()->role->id == 3;

        $data = InvoiceDetail::query()
            ->with([
                "invoice",
                "invoice.type_trx",
                "invoice.supplier",
                "invoice.supplier_account",
                "invoice.supplier_account.bank",
                "invoice.pv:processable_id,pv_no",
                "invoice.user:id,name",
            ])
            ->whereHas("invoice", function ($query) use ($from, $to) {
                $query->whereBetween("date", [$from, $to]);
            })
            ->when($isAdminKlik, function ($query) {
                $query->whereHas("user", function ($query) {
                    $query->whereHas("role", function ($query) {
                        $query->where("roles.id", 3);
                    });
                });
            })
            ->oldest()
            ->get();

        $columns = function ($row) {
            return [
                'Invoice No' => $row->invoice->invoice_no,
                'Date' => $row->invoice->date,
                'Tipe Trx' => $row->invoice->type_trx->name,
                'Supplier' => $row->invoice->supplier->name,
                'Payment Method' => $row->invoice->payment_method,
                'Bank' => $row->invoice->supplier_account->bank->name,
                'Nomor Rekening' => $row->invoice->supplier_account->account_number,
                'Description' => $row->description,
                'Total Amount' => (int)$row->total_amount,
                'Status' => $row->invoice->status,
                'PV No' => $row->invoice->pv?->pv_no,
                'Created By' => $row->invoice->user->name,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "invoice-report.xlsx");
    }

    public function reportPrepayment(Request $request)
    {
        if (!auth()->user()->tokenCan("report-prepayment:download")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/prepayment/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $data = Settlement::query()
            ->with([
                "pv:id,pv_no,description,supplier_id,supplier_account_id,paid_date,processable_id",
                "pv.supplier:id,name",
                "pv.supplier_account:id,account_number,bank_id",
                "pv.supplier_account.bank:id,name",
                "invoice:id,invoice_no,status",
                "byhmd:id,invoice_no,status",
                "byhmd.pv:processable_id,pv_no",
            ])
            ->whereHas("pv", function ($query) use ($from, $to) {
                $query->whereBetween("paid_date", [$from, $to]);
            })
            ->oldest()
            ->get();

        $columns = function ($row) {
            return [
                'PV No' => $row->pv->pv_no,
                'Tanggal Prepayment' => $row->pv->paid_date,
                'Keterangan' => $row->pv->description,
                'Supplier' => $row->pv->supplier->name,
                'Bank' => $row->pv->supplier_account->bank->name,
                'Nomor Rekening' => $row->pv->supplier_account->account_number,
                'LPJ Invoice No' => $row->invoice?->invoice_no,
                'LPJ Amount' => $row->lpj_amount,
                'Balance' => $row->balance,
                'BYHMD PV No' => $row->byhmd?->pv?->pv_no,
                'BYHMD Amount' => $row->byhmd_amount,
                'Status' => $row->status,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "prepayment-report.xlsx");
    }

    public function listUnitPelunasan(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice-external:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/list-unit-pelunasan/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $data = Unit::query()
            ->with([
                "auction",
                "auction.customer",
            ])
            ->where("payment_status", "LUNAS")
            ->whereRelation("spp", "status", "PAID")
            ->whereHas("auction", function ($query) use ($from, $to) {
                $query->whereBetween("auction_date", [$from, $to]);
            })
            ->doesntHave("external")
            ->oldest()
            ->get();

        $columns = function ($row) {
            return [
                'Tgl Lelang' => $row->auction->auction_date->format('d-m-Y'),
                'Nama Bidder' => $row->auction->customer->name,
                'Nopol' => $row->police_number,
                'Noka' => $row->chassis_number,
                'Nosin' => $row->engine_number,
                'Harga Terbentuk' => $row->price,
                'Fee Lelang' => $row->fee_amount,
                'Cabang' => $row->auction->branch_name,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "list-unit-pelunasan.xlsx");
    }

    public function reportInvoiceExternal(Request $request)
    {
        if (!auth()->user()->tokenCan("report-invoice-external:download")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $id = "reports/invoice-external/" . Str::random(6) . ".xlsx";
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $data = InvoiceExternal::query()
            ->with([
                'supplier',
            ])
            ->whereBetween("date", [$from, $to])
            ->oldest()
            ->get();

        $columns = function ($row) {
            return [
                'Invoice External No' => $row->invoice_external_no,
                'Tanggal Pengajuan' => $row->date,
                'Tanggal Jatuh Tempo' => $row->due_date,
                'Keterangan' => $row->description,
                'Supplier' => $row->supplier->name,
                'Total Unit' => $row->total_unit,
                'Total Amount Real' => $row->total_amount_real,
                'Total Amount Tagihan' => $row->total_amount_manual,
                'PPN' => $row->ppn,
                'PPH23' => $row->pph23,
                'Netto' => $row->grand_total,
                'Status' => $row->status,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "invoice-external-report.xlsx");
    }
}
