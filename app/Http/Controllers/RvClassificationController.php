<?php

namespace App\Http\Controllers;

use App\Http\Requests\RvClassificationRequest;
use App\Http\Requests\VaInstantRequest;
use App\Http\Resources\GetResource;
use App\Models\Customer;
use App\Models\GL;
use App\Models\RV;
use App\Models\RvClassification;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RvClassificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->tokenCan('rv-classification:browse')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = Customer::select('klik_bidder_id', 'name', 'va_number')
            ->withCount([
                'units' => function ($query) {
                    $query->where('payment_status', 'LUNAS')
                        ->where(function ($query) {
                            $query->whereNull('spp_status')
                                ->orWhere('spp_status', 'UPLOADED');
                        });
                },
            ])
            ->withSum([
                'units' => function ($query) {
                    $query->where('payment_status', 'LUNAS')
                        ->where(function ($query) {
                            $query->whereNull('spp_status')
                                ->orWhere('spp_status', 'UPLOADED');
                        });
                },
            ], 'price')
            ->withSum([
                'units' => function ($query) {
                    $query->where('payment_status', 'LUNAS')
                        ->where(function ($query) {
                            $query->whereNull('spp_status')
                                ->orWhere('spp_status', 'UPLOADED');
                        });
                },
            ], 'admin_fee')
            ->withSum([
                'units' => function ($query) {
                    $query->where('payment_status', 'LUNAS')
                        ->where(function ($query) {
                            $query->whereNull('spp_status')
                                ->orWhere('spp_status', 'UPLOADED');
                        });
                },
            ], 'final_price')
            ->withSum([
                'units' => function ($query) {
                    $query->where('payment_status', 'LUNAS')
                        ->where(function ($query) {
                            $query->whereNull('spp_status')
                                ->orWhere('spp_status', 'UPLOADED');
                        });
                },
            ], 'diff_price')
            ->whereHas('units', function ($query) use ($request) {
                $query->where('payment_status', 'LUNAS')
                    ->where(function ($query) {
                        $query->whereNull('spp_status')
                            ->orWhere('spp_status', 'UPLOADED');
                    })
                    ->when($request->diff == 1, function ($query) {
                        $query->where('diff_price', '!=', 0);
                    });
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    'name',
                    'va_number',
                ], 'ilike', "%$search%");
            })
            ->oldest('id')
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RvClassificationRequest $request)
    {
        if (!auth()->user()->tokenCan('rv-classification:add')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $units = Unit::select('units.id', 'engine_number', 'price', 'admin_fee', 'final_price')
            ->join('auctions', 'units.auction_id', '=', 'auctions.klik_auction_id')
            ->whereIn('units.id', $request->units)
            ->oldest('final_price')
            ->oldest('auction_date')
            ->oldest('id')
            ->get();

        $rvs = RV::select('id', 'rv_no', 'date', 'description', 'starting_balance', 'used_balance', 'admin_fee', 'ending_balance')
            ->whereIn('id', $request->rvs)
            ->oldest('ending_balance')
            ->oldest('date')
            ->oldest('id')
            ->get();

        $authId = auth()->id();

        $totalAmount = $units->sum('final_price');
        $totalRv = $rvs->sum('ending_balance');

        if ($totalRv < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah RV Kurang',
            ], 400);
        }

        DB::transaction(function () use ($units, $rvs, $authId) {
            $classifications = [];
            $rvUsedAmounts = []; // Track how much price amount each RV covers
            $rvAdminFees = []; // Track how much admin fee each RV covers
            $glInsert = [];

            // Initialize tracking arrays
            foreach ($rvs as $rv) {
                $rvUsedAmounts[$rv->id] = 0;
                $rvAdminFees[$rv->id] = 0;
            }

            foreach ($units as $unit) {
                $amountNeeded = $unit->price;
                $adminFeeNeeded = $unit->admin_fee;
                $totalPriceUsed = 0;
                $totalAdminFeeUsed = 0;

                foreach ($rvs as $rv) {
                    if ($amountNeeded <= 0 && $adminFeeNeeded <= 0) {
                        break;
                    }

                    // Get original ending balance and calculate available balance
                    $originalEndingBalance = $rv->ending_balance;
                    $availableBalance = $originalEndingBalance - $rvUsedAmounts[$rv->id] - $rvAdminFees[$rv->id];

                    if ($availableBalance <= 0) {
                        continue;
                    }

                    // Calculate how much price to use from this RV
                    $priceToUse = min($availableBalance, $amountNeeded);
                    $rvUsedAmounts[$rv->id] += $priceToUse;
                    $amountNeeded -= $priceToUse;
                    $totalPriceUsed += $priceToUse;

                    // Calculate how much admin fee to use from remaining RV balance
                    $remainingRvBalance = $availableBalance - $priceToUse;
                    $adminFeeToUse = 0;
                    if ($adminFeeNeeded > 0 && $remainingRvBalance > 0) {
                        $adminFeeToUse = min($remainingRvBalance, $adminFeeNeeded);
                        $rvAdminFees[$rv->id] += $adminFeeToUse;
                        $adminFeeNeeded -= $adminFeeToUse;
                        $totalAdminFeeUsed += $adminFeeToUse;
                    }

                    // Calculate RV balance after this classification
                    $newRvBalance = $originalEndingBalance - $rvUsedAmounts[$rv->id] - $rvAdminFees[$rv->id];

                    $classifications[] = [
                        'unit_id' => $unit->id,
                        'rv_id' => $rv->id,
                        'rv_amount' => $availableBalance,
                        'unit_final_price' => $unit->final_price,
                        'rv_balance' => $newRvBalance < 0 ? 0 : $newRvBalance,
                        'created_by' => $authId,
                        'created_at' => now(),
                    ];
                }

                $unit->payment_status = 'LUNAS';
                $unit->updated_by = $authId;
                $unit->save();

                $glInsert[] = [
                    "gl_no" => "AR#" . $unit->id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "AR Bidder #" . $unit->engine_number,
                    "coa_id" => 157,
                    "debit" => $unit->final_price,
                    "credit" => 0,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => "AR#" . $unit->id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Terima Titipan Pelunasan #" . $unit->engine_number,
                    "coa_id" => 58,
                    "debit" => 0,
                    "credit" => $unit->price,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => "AR#" . $unit->id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Terima Titipan Admin #" . $unit->engine_number,
                    "coa_id" => 59,
                    "debit" => 0,
                    "credit" => $unit->admin_fee,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];
            }

            // Apply all RV updates
            foreach ($rvs as $rv) {
                $rv->used_balance += $rvUsedAmounts[$rv->id];
                $rv->admin_fee += $rvAdminFees[$rv->id];
                $rv->ending_balance = $rv->starting_balance - $rv->used_balance - $rv->admin_fee;
                if ($rv->ending_balance < 0) {
                    $rv->ending_balance = 0;
                }
                $rv->status = $rv->ending_balance == 0 ? 'CLOSED' : 'NEW';
                $rv->updated_by = $authId;
                $rv->save();
            }

            GL::insert($glInsert);
            RvClassification::insert($classifications);
        });

        return response()->json([
            'success' => true,
            'message' => 'Rv classification created successfully',
        ]);
    }

    public function vaInstant(VaInstantRequest $request)
    {
        if (!auth()->user()->tokenCan('rv-classification:add')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::transaction(function () use ($request) {
            $authId = auth()->id();

            $rv = RV::findOrFail($request->rv_id);

            $references = Unit::select("id", "price", "admin_fee", "final_price", "reference_id", "paid_date")
                ->whereIn("reference_id", $request->references);
            $references->update([
                "payment_status" => "LUNAS",
                "updated_by" => $authId,
            ]);
            $units = $references->get();
            $transactions = $references->select("reference_id", "paid_date", DB::raw("SUM(price) as sum_price, SUM(admin_fee) as sum_admin_fee, SUM(final_price) as sum_final_price"))
                ->oldest("paid_date")
                ->groupBy("reference_id", "paid_date")
                ->get();

            $titipanPelunasan = 0;
            $titipanAdmin = 0;

            $glInsert[] = [
                "gl_no" => $rv->rv_no,
                "date" => $rv->date,
                "type" => 'IN',
                "description" => 'PELUNASAN #' . $rv->journal_number,
                "coa_id" => 8,
                "debit" => $rv->starting_balance,
                "credit" => 0,
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
            ];

            $glInsert[] = [
                "gl_no" => $rv->rv_no,
                "date" => $rv->date,
                "type" => 'IN',
                "description" => 'PELUNASAN #' . $rv->journal_number,
                "coa_id" => 58,
                "debit" => 0,
                "credit" => $rv->starting_balance,
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
            ];

            foreach ($units as $unit) {
                $rv->used_balance += $unit->price;
                $rv->admin_fee += $unit->admin_fee;
                $rv->ending_balance = $rv->starting_balance - $rv->used_balance - $rv->admin_fee;

                $titipanPelunasan += $unit->price;
                $titipanAdmin += $unit->admin_fee;

                $classifications[] = [
                    'unit_id' => $unit->id,
                    'rv_id' => $rv->id,
                    'rv_amount' => $rv->ending_balance,
                    'unit_final_price' => $unit->final_price,
                    'rv_balance' => $rv->ending_balance,
                    'created_by' => $authId,
                    'created_at' => now(),
                ];
            }

            foreach ($transactions as $transaction) {
                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "AR Pelunasan #" . $transaction->reference_id,
                    "coa_id" => 158,
                    "debit" => $transaction->sum_final_price,
                    "credit" => 0,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Terima Titipan Pelunasan #" . $transaction->reference_id,
                    "coa_id" => 58,
                    "debit" => 0,
                    "credit" => $transaction->sum_price,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Terima Titipan Admin #" . $transaction->reference_id,
                    "coa_id" => 59,
                    "debit" => 0,
                    "credit" => $transaction->sum_admin_fee,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Pendapatan Lain-lain #" . $transaction->reference_id,
                    "coa_id" => 140,
                    "debit" => 0,
                    "credit" => 6000,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "Biaya Aplikasi Xendit #" . $transaction->reference_id,
                    "coa_id" => 159,
                    "debit" => 4000,
                    "credit" => 0,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                $glInsert[] = [
                    "gl_no" => $transaction->reference_id,
                    "date" => now(),
                    "type" => 'IN',
                    "description" => "PPn Masukan #" . $transaction->reference_id,
                    "coa_id" => 151,
                    "debit" => 440,
                    "credit" => 0,
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];
            }

            $xenditFee = $rv->starting_balance - $titipanPelunasan - $titipanAdmin;
            $rv->xendit_fee = $xenditFee;
            $rv->ending_balance = $rv->ending_balance - $xenditFee;

            $rv->status = 'CLOSED';
            $rv->updated_by = $authId;
            $rv->save();

            GL::insert($glInsert);
            RvClassification::insert($classifications);
        });

        return response()->json([
            'success' => true,
            'message' => 'Rv classification created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $rvClassification)
    {
        if (! auth()->user()->tokenCan('rv-classification:read')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return new GetResource($rvClassification->load([
            'units' => function ($query) {
                $query->where('payment_status', 'LUNAS')
                    ->where(function ($query) {
                        $query->whereNull('spp_status')
                            ->orWhere('spp_status', 'UPLOADED');
                    })
                    ->oldest('id');
            },
            'units.auction' => function ($query) {
                $query->oldest('auction_date');
            },
            'rvs' => function ($query) {
                $query->select('customer_id', 'id', 'rv_no', 'date', 'description', 'starting_balance')
                    ->where('status', '!=', 'NEW')
                    ->oldest('date');
            },
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
