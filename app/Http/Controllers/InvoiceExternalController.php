<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceExternalRequest;
use App\Http\Resources\GetResource;
use App\Models\GL;
use App\Models\InvoiceExternal;
use App\Models\Unit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceExternalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice-external:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = InvoiceExternal::query()
            ->with([
                'supplier',
                'attachment'
            ])
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    'date',
                    'due_date',
                    'invoice_external_no',
                    'description',
                    'total_unit',
                    'total_amount_real',
                    'total_amount_manual',
                    'ppn',
                    'pph23',
                    'grand_total',
                    'status'
                ], "ilike", "%$search%")
                    ->orWhereRelation("supplier", "name", "ilike", "%$search%");
            })
            ->latest("id")
            ->paginate($request->size);;

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceExternalRequest $request)
    {
        if (!auth()->user()->tokenCan("invoice-external:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request) {
            $authId = auth()->id();

            $currentYear = date('y');
            $findLastInvDate = InvoiceExternal::select("created_at")->latest()->first();
            $lastInvDate = $findLastInvDate->date ?? now();
            $lastInvYear = Carbon::parse($lastInvDate)->format('y');
            if ($currentYear > $lastInvYear) {
                $countInv = 1;
            } else {
                $countInv = InvoiceExternal::query()
                    ->whereNotNull("invoice_external_no")
                    ->where("created_at", ">=", date('Y') . "-01-01")
                    ->where("created_at", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $invoice_no = 'INV/KLIK/' . date("Y") . '/' . date("m") . '/' . Str::padLeft($countInv++, 3, '0');

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $dueDate = Carbon::parse($request->date)->addDays(intval($request->due_date))->format('Y-m-d');

            $query = Unit::query()
                ->select("id", "fee_amount")
                ->where("payment_status", "LUNAS")
                ->whereRelation("spp", "status", "PAID")
                ->whereHas("auction", function ($query) use ($request) {
                    $query->whereBetween("auction_date", [$request->from_date, $request->to_date]);
                })
                ->when($request->units, function ($query, $units) {
                    $query->whereNotIn("id", $units);
                })
                ->doesntHave("external");

            $sumFeeAmount = $query->sum("fee_amount");
            $totalUnit = $query->count();
            $totalAmount = $request->total_amount_manual == '0' ? $sumFeeAmount : $request->total_amount_manual;
            $ppn = 0;
            $pph23 = round($totalAmount * 0.02);
            $grandTotal = $totalAmount + $ppn - $pph23;

            $sql = InvoiceExternal::create($request->safe()->except(['due_date', 'units']) + [
                'due_date' => $dueDate,
                'invoice_external_no' => $invoice_no,
                'total_unit' => $totalUnit,
                'total_amount_real' => $sumFeeAmount,
                'total_amount_manual' => (int)$request->total_amount_manual,
                'ppn' => $ppn,
                'pph23' => $pph23,
                'grand_total' => $grandTotal,
                'supplier_id' => $request->supplier_id,
                'file_upload_id' => $file->id ?? null,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            $query->chunkById(1000, function ($units) use ($sql, $authId) {
                $details = [];

                foreach ($units as $unit) {
                    $details[] = [
                        "invoice_external_id" => $sql->id,
                        "unit_id" => $unit->id,
                        'created_by' => $authId,
                        'created_at' => now(),
                    ];
                }

                $sql->units()->insert($details);
            }, "id");

            $ledgers[] = [
                "gl_no" => $invoice_no,
                "date" => $request->date,
                "type" => 'IN',
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
                "description" => $request->description,
                "coa_id" => 11,
                "debit" => $totalAmount,
                "credit" => 0,
            ];

            $ledgers[] = [
                "gl_no" => $invoice_no,
                "date" => $request->date,
                "type" => 'IN',
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
                "description" => $request->description,
                "coa_id" => 68,
                "debit" => 0,
                "credit" => $totalAmount,
            ];

            GL::insert($ledgers);
        });

        return response()->json([
            "success" => true,
            "message" => "Invoice External created successfully",
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(InvoiceExternal $invoiceExternal)
    {
        if (!auth()->user()->tokenCan("invoice-external:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($invoiceExternal->load([
            'supplier',
            'attachment',
            'units',
            'units.unit',
            'units.unit.auction',
            'units.unit.auction.customer',
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InvoiceExternal $invoiceExternal)
    {
        if (!auth()->user()->tokenCan("invoice-external:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($invoiceExternal) {
            $authId = auth()->id();

            $invoiceExternal->update([
                'status' => "REJECT",
                'updated_by' => $authId,
            ]);

            $invoiceExternal->units()->delete();

            $ledgers[] = [
                "gl_no" => $invoiceExternal->invoice_external_no,
                "date" => now(),
                "type" => 'IN',
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
                "description" => $invoiceExternal->description,
                "coa_id" => 11,
                "debit" => 0,
                "credit" => $invoiceExternal->total_amount_manual > 0 ? $invoiceExternal->total_amount_manual : $invoiceExternal->total_amount_real,
            ];

            $ledgers[] = [
                "gl_no" => $invoiceExternal->invoice_external_no,
                "date" => now(),
                "type" => 'IN',
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
                "description" => $invoiceExternal->description,
                "coa_id" => 68,
                "debit" => $invoiceExternal->total_amount_manual > 0 ? $invoiceExternal->total_amount_manual : $invoiceExternal->total_amount_real,
                "credit" => 0,
            ];

            GL::insert($ledgers);
        });

        return response()->json([
            "success" => true,
            "message" => "Invoice External rejected successfully",
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InvoiceExternal $invoiceExternal)
    {
        if (!auth()->user()->tokenCan("invoice-external:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }
    }
}
