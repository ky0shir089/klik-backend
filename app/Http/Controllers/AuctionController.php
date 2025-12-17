<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuctionRequest;
use App\Http\Requests\RvClassificationRequest;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Auction;
use App\Models\Customer;
use App\Models\CustomerAuction;
use App\Models\Transaction;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuctionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("repayment:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Auction::query()
            ->with([
                "customer:klik_bidder_id,name",
                "unit:auction_id,police_number,chassis_number,engine_number,price,admin_fee,final_price",
            ])
            ->whereRelation("unit", "payment_status", "UNPAID")
            ->when($request->search, function ($query, $search) {
                $query->whereRelation("customer", "name", "ilike", "%$search%")
                    ->orWhere("branch_name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AuctionRequest $request)
    {
        if (!auth()->user()->tokenCan("auction:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.klik')['token'],
        ])->get('https://api.kliklelang.co.id/api/report/v3/hasil_lelang', [
            'date_start' => $request->auction_date,
            'date_end' => $request->auction_date,
        ]);

        $results = $response["data"] ?? [];

        $customers = [];
        $auctions = [];
        $units = [];
        $transactions = [];
        $authId = auth()->id();

        DB::beginTransaction();

        try {
            foreach ($results as $customer) {
                $customers[] = [
                    'klik_bidder_id' => $customer["id_bidder"],
                    'ktp' => $customer["identitas_ktp"],
                    'name' => $customer["nama_ktp"],
                    'va_number' => $customer["nomor_va"],
                    'created_by' => $authId,
                    'updated_at' => null
                ];

                foreach ($customer['lelang'] as $lelang) {
                    $auctions[] = [
                        'customer_id' => $customer["id_bidder"],
                        'klik_auction_id' => $lelang['id_lelang'],
                        'auction_name' => $lelang['nama_lelang'],
                        'auction_date' => $lelang['tgl_lelang'],
                        'branch_id' => $lelang['id_cabang'],
                        'branch_name' => $lelang['balai_lelang'],
                        'created_by' => $authId,
                        'updated_at' => null
                    ];

                    foreach ($lelang['unit'] as $unit) {
                        $units[] = [
                            'auction_id' => $lelang['id_lelang'],
                            'klik_unit_id' => $unit['id_unit'],
                            'lot_number' => $lelang['no_lot'],
                            'police_number' => $unit['nopol'],
                            'chassis_number' => $unit['noka'],
                            'engine_number' => $unit['nosin'],
                            'price' => $unit['harga'] - $unit['potongan_tiket'],
                            'admin_fee' => $unit['biaya_admin'],
                            'final_price' => $unit['harga_total'],
                            'created_by' => $authId,
                            'updated_at' => null
                        ];

                        if (isset($customer['transaksi'])) {
                            foreach ($customer['transaksi'] as $transaction) {
                                $transactions[] = [
                                    'unit_id' => $unit['id_unit'],
                                    'date' => $transaction['tanggal_upload'],
                                    'receipt_link' => $transaction['url'],
                                    'created_by' => $authId,
                                    'created_at' => now(),
                                    'updated_at' => null
                                ];
                            }
                        }
                    }
                }
            }

            Customer::upsert($customers, ["klik_bidder_id"]);
            Auction::upsert($auctions, ["customer_id", "klik_auction_id"]);
            Unit::upsert($units, ["klik_unit_id"]);
            Transaction::insert($transactions);

            DB::commit();
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 500);
        }

        return new StoreResource($results);
    }

    /**
     * Display the specified resource.
     */
    public function show(Auction $auction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RvClassificationRequest $request, Auction $auction)
    {
        if (!auth()->user()->tokenCan("rv_classification:store")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $auction->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($auction);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Auction $auction)
    {
        //
    }
}
