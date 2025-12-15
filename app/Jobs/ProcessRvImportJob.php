<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\GL;
use App\Models\RV;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProcessRvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $excelData;
    public int $year;
    public int $countRv;
    public int $authId;

    /**
     * Create a new job instance.
     */
    public function __construct($excelData, $year, $countRv, $authId)
    {
        $this->excelData = $excelData;
        $this->year = $year;
        $this->countRv = $countRv;
        $this->authId = $authId;

        // optional: extend timeout or memory
        $this->timeout = 300; // 5 minutes
        $this->tries = 3;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $excel_data = $this->excelData;
        $year = $this->year;
        $count_rv = $this->countRv;
        $authId = $this->authId;

        foreach ($excel_data as $date => $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.klik')['token'],
            ])->get('https://api.kliklelang.co.id/api/report/v3/hasil_lelang', [
                'date_start' => $date,
                'date_end' => $date,
            ]);

            $result = $response["data"] ?? [];

            if (!$result) continue;

            // ✔ Build VA lookup table for instant O(1) access
            $vaLookup = [];
            foreach ($result as $row) {
                $vaLookup[$row["nomor_va"]] = $row;
            }

            foreach ($chunk as $va => $rvRows) {
                // ✔ No more slow collect()->filter()
                $filter = $vaLookup[$va] ?? null;
                $customerId = null;

                if ($filter) {
                    // ✔ Use upsert instead of firstOrCreate
                    $customer = Customer::firstOrCreate(
                        ['ktp' => $filter["identitas_ktp"]],
                        [
                            'klik_bidder_id' => $filter["id_bidder"],
                            'name' => $filter["nama_ktp"],
                            'va_number' => $filter["nomor_va"],
                            'created_by' => $authId,
                            'created_at' => now(),
                            'updated_at' => null
                        ]
                    );

                    $customerId = $customer->klik_bidder_id;

                    $unitInsert = [];

                    foreach ($filter['lelang'] as $lelang) {
                        $auction = new Auction();
                        $auction->customer_id = $filter["id_bidder"];
                        $auction->klik_auction_id = $lelang['id_lelang'];
                        $auction->auction_name = $lelang['nama_lelang'];
                        $auction->auction_date = $lelang['tgl_lelang'];
                        $auction->branch_id = $lelang['id_cabang'];
                        $auction->branch_name = $lelang['balai_lelang'];
                        $auction->created_by = $authId;
                        $auction->updated_at = null;
                        $auction->save();

                        foreach ($lelang['unit'] as $unit) {
                            $unitInsert[] = [
                                'auction_id' => $auction->id,
                                'lot_number' => $lelang['no_lot'],
                                'police_number' => $unit['nopol'],
                                'chassis_number' => $unit['noka'],
                                'engine_number' => $unit['nosin'],
                                'price' => $unit['harga'],
                                'admin_fee' => $unit['biaya_admin'],
                                'final_price' => $unit['harga_total'],
                                'created_by' => $authId,
                                'created_at' => now(),
                                'updated_at' => null
                            ];
                        }
                    }

                    // ✔ bulk insert auctions + units
                    Unit::insert($unitInsert);
                }

                // ---------- RV + GL HANDLING ----------
                $rvInsert = [];
                $glInsert = [];

                foreach ($rvRows as $row) {
                    $glNo = 'RV' . $year . Str::padLeft($count_rv, 5, '0');
                    // ✔ aggregate both debit + credit into one insert batch
                    $glInsert[] = [
                        "gl_no" => $glNo,
                        "date" => $row["payment_date"],
                        "type" => 'IN',
                        "description" => 'Terima Titipan Pelunasan#' . $row["va_number"],
                        "coa_id" => 58,
                        "debit" => $row["starting_balance"],
                        "credit" => 0,
                        "created_by" => $authId,
                        "created_at" => now(),
                        "updated_at" => null,
                    ];

                    $glInsert[] = [
                        "gl_no" => $glNo,
                        "date" => $row["payment_date"],
                        "type" => 'IN',
                        "description" => 'Terima Titipan Pelunasan#' . $row["va_number"],
                        "coa_id" => 8,
                        "debit" => 0,
                        "credit" => $row["starting_balance"],
                        "created_by" => $authId,
                        "created_at" => now(),
                        "updated_at" => null,
                    ];

                    $customerName = Str::replace("KLIKLELANG-", "", $row["customer_name"]);

                    $rvInsert[] = [
                        "rv_no" => 'RV' . $year . Str::padLeft($count_rv++, 5, '0'),
                        "date" => Carbon::parse($row["payment_date"]),
                        "type_trx_id" => 1,
                        "description" => 'Terima Titipan Pelunasan#' . $row["va_number"] . "_" . $customerName,
                        "bank_account_id" => $row["bank_account_id"],
                        "coa_id" => 58,
                        "starting_balance" => $row["starting_balance"],
                        "ending_balance" => $row["starting_balance"],
                        "journal_number" => $row["journal_number"],
                        "customer_id" => $customerId,
                        "created_by" => $authId,
                        "created_at" => now(),
                        "updated_at" => null
                    ];
                }

                // ✔ MASS INSERT — super fast
                GL::insert($glInsert);
                RV::insert($rvInsert);
            }
        }
    }
}
