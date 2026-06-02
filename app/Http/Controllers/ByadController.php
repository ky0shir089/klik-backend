<?php

namespace App\Http\Controllers;

use App\Http\Requests\ByadRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Models\ByadHeader;
use App\Models\ByadPaymenDetail;
use App\Models\ByadPayment;
use App\Models\Invoice;
use App\Models\Unit;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ByadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("byad:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = ByadHeader::query()
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "date",
                    "branch",
                    "description",
                    "total_unit",
                    "total_amount",
                    "status",
                ], "ilike", "%$search%");
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ByadRequest $request)
    {
        if (!auth()->user()->tokenCan("byad:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request) {
            $authId = auth()->id();

            $details = json_decode($request->details, true);
            $totalUnit = count($details);
            $detailCollection = collect($details);

            $unitIds = $detailCollection->pluck("unit_id");
            $units = Unit::whereIn("id", $unitIds)->get();
            $byadAmount = round($units->sum("byad_amount"));
            $totalAmount = $units->sum("price");

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $sql = ByadHeader::create($request->safe()->except(["attachment"]) + [
                'file_upload_id' => $file->id ?? null,
                'total_unit' => $totalUnit,
                'byad_amount' => $byadAmount,
                'total_amount' => $totalAmount,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            foreach ($details as $unit) {
                $rows[] = [
                    'unit_id' => $unit["unit_id"],
                    'created_by' =>  $authId,
                    'updated_at' => null,
                ];

                Unit::find($unit["unit_id"])->update([
                    'byad_status' => 'NEW',
                    'updated_by' => $authId,
                ]);
            }

            $sql->details()->createMany($rows);
        });

        return response()->json([
            "success" => true,
            "message" => "Byad created successfully"
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ByadHeader $byad)
    {
        if (!auth()->user()->tokenCan("byad:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($byad->load([
            "attachment",
            "details",
            "details.unit:id,auction_id,police_number,chassis_number,engine_number,price,byad_amount",
            "details.unit.auction:klik_auction_id,auction_date,customer_id",
            "details.unit.auction.customer:klik_bidder_id,name",
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ByadRequest $request, ByadHeader $byad)
    {
        if (!auth()->user()->tokenCan("byad:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request, $byad) {
            $authId = auth()->id();

            $details = json_decode($request->details, true);
            $detailCollection = collect($details);
            $totalUnit = count($details);
            $totalAmount = round($detailCollection->sum("amount") * 0.006);
            $unitIds = $detailCollection->pluck("unit_id");

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $byad->update($request->safe()->except(["attachment"]) + [
                'file_upload_id' => $file->id ?? $byad->file_upload_id,
                'total_unit' => $totalUnit,
                'total_amount' => $totalAmount,
                'status' => $request->status,
                'updated_by' => $authId,
            ]);

            if ($request->status == "NEW") {
                Unit::whereIn("id", $unitIds)->update([
                    'byad_status' => NULL,
                ]);

                $byad->details()->delete();

                foreach ($details as $unit) {
                    $rows[] = [
                        'unit_id' => $unit["unit_id"],
                        'amount' => round($unit["amount"] * 0.006),
                        'created_by' =>  $authId,
                        'updated_at' => null,
                    ];

                    Unit::find($unit["unit_id"])->update([
                        'byad_status' => 'NEW',
                        'updated_by' => $authId,
                    ]);
                }

                $byad->details()->createMany($rows);
            }
        });

        return response()->json([
            "success" => true,
            "message" => "Byad updated successfully"
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ByadHeader $byad)
    {
        if (!auth()->user()->tokenCan("byad:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($byad) {
            $unitIds = $byad->details()->pluck("unit_id");
            Unit::whereIn("id", $unitIds)->update([
                'byad_status' => NULL,
            ]);

            $byadPaymentDetail = ByadPaymenDetail::select("byad_payment_id")
                ->where("byad_id", $byad->id)
                ->get()
                ->pluck("byad_payment_id");

            if ($byadPaymentDetail) {
                $byadPaymentHeader = ByadPayment::select("invoice_id")
                    ->whereIn("id", $byadPaymentDetail)
                    ->get()
                    ->pluck("invoice_id");

                Invoice::whereIn("id", $byadPaymentHeader)->delete();
            }

            $byad->delete();
        });

        return new DeleteResource($byad);
    }
}
