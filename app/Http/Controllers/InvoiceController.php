<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Pph;
use App\Models\RV;
use App\Models\Settlement;
use App\Services\LpjService;
use App\Services\FileUploadService;
use App\Services\FonnteService;
use App\Services\WhatsAppService;
use App\Services\WorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Invoice::query()
            ->with([
                "supplier:id,name",
                "supplier_account:id,account_number,bank_id",
                "supplier_account.bank:id,name",
                "type_trx",
            ])
            ->when(auth()->user()->role->id == 3, function ($query) {
                $query->whereHas("user", function ($query) {
                    $query->whereHas("role", function ($query) {
                        $query->where("roles.id", 3);
                    });
                });
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "invoice_no",
                    "description",
                    "total_amount",
                ], "ilike", "%$search%");
            })
            ->when($request->type_trx_id, function ($query, $type_trx_id) {
                $query->where("trx_id", $type_trx_id);
            })
            ->when($request->method, function ($query, $method) {
                $query->where("payment_method", $method);
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function inbox(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Invoice::query()
            ->select('invoices.*')
            ->where('invoices.status', 'REQUEST')
            ->with([
                "supplier:id,name",
                "supplier_account:id,account_number,bank_id",
                "supplier_account.bank:id,name",
                "type_trx",
            ])
            ->join('workflow_approvals', function ($join) {
                $join->on('invoices.id', 'workflow_approvals.processable_id')
                    ->where('workflow_approvals.processable_type', Invoice::class);
            })
            ->whereRelation("wf_histories", function ($query) {
                $query->where("user_id", auth()->id())
                    ->where("status", "PENDING")
                    ->whereRaw('workflow_histories.sequence = workflow_approvals.approve_count + 1');
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "invoice_no",
                    "description",
                    "total_amount",
                ], "ilike", "%$search%");
            })
            ->latest("invoices.created_at")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request)
    {
        if (!auth()->user()->tokenCan("invoice:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $authId = auth()->id();

            $month = Carbon::parse($request->date)->format('m');
            $year = Carbon::parse($request->date)->format('y');
            $prefix = 'KLIK/' .  $month  . '/' . $year . '/';

            $lastInv = Invoice::select("invoice_no")
                ->whereNotNull("invoice_no")
                ->where('invoice_no', 'ilike', "$prefix%")
                ->latest('id')
                ->latest('invoice_no')
                ->first();

            $countInv = $lastInv ? (int) Str::after($lastInv->invoice_no, $prefix) + 1 : 1;
            $invoice_no = $prefix . Str::padLeft($countInv, 3, '0');

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $sql = Invoice::create($request->safe()->except(["attachment", "status", "wf_history_id", "signature", "remark"]) + [
                'invoice_no' => $invoice_no,
                'file_upload_id' => $file->id ?? null,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            $sql->total_amount = $this->createInvoiceDetails($request->details, $sql, $authId);
            $sql->save();

            (new WorkflowService($sql));

            DB::commit();
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], $th instanceof HttpExceptionInterface ? $th->getStatusCode() : 500);
        }

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($invoice->load([
            "type_trx",
            "supplier_account",
            "supplier_account.supplier",
            "supplier_account.bank",
            "attachment",
            "details",
            "details.coa",
            "details.pph",
            "details.rv:id,rv_no,ending_balance",
            "wf_histories",
            "wf_histories.user:id,name",
            "wf_approval:processable_id,approve_count",
            "settlement:lpj_invoice_id,prepayment_pv_id,prepayment_pv_id",
            "settlement.pv:id,pv_no,pv_amount",
            "paid_attachment",
            "paid_attachment.attachment"
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $authId = auth()->id();
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            switch ($request->status) {
                case 'REQUEST':
                    if ($request->hasFile('attachment')) {
                        $file = (new FileUploadService)->handleUpload($request->file('attachment'));
                    }
                    $invoice->update($request->safe()->except(["attachment", "status", "wf_history_id", "signature", "remark"]) + [
                        'file_upload_id' => $file->id ?? $invoice->file_upload_id,
                        'updated_by' => $authId,
                    ]);
                    $this->handleResubmit($request, $invoice, $authId);
                    break;
                case 'APPROVE':
                case 'REJECT':
                    $this->handleWorkflowAction($request, $invoice, $authId);
                    break;
                case 'CANCEL':
                    $invoice->update(['status' => 'CANCEL', 'updated_by' => $authId]);
                    break;
                default:
                    abort(422, 'Invalid invoice action.');
            }

            DB::commit();

            return new UpdateResource($invoice);
        } catch (\Throwable $th) {
            info($th->getMessage());
            DB::rollback();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], $th instanceof HttpExceptionInterface ? $th->getStatusCode() : 400);
        }
    }

    private function createInvoiceDetails(string $detailsJson, Invoice $invoice, int $authId): float
    {
        $details = [];
        $sumTotalAmount = 0;

        foreach (json_decode($detailsJson, true) as $detail) {
            $pph = isset($detail['pph_id']) ? Pph::find($detail['pph_id']) : null;
            $pphAmount = $pph ? round($detail['item_amount'] * ($pph->rate / 100)) : 0;
            $ppnAmount = isset($detail['ppn_rate']) ? round($detail['item_amount'] * ($detail['ppn_rate'] / 100)) : 0;

            $totalAmount = round($detail['item_amount'] - $pphAmount + $ppnAmount);
            $sumTotalAmount += $totalAmount;

            $details[] = [
                'invoice_id' => $invoice->id,
                'inv_coa_id' => $detail['inv_coa_id'],
                'description' => $detail['description'],
                'item_amount' => $detail['item_amount'],
                'pph_id' => $detail['pph_id'] ?? null,
                'pph_amount' => $pphAmount,
                'ppn_rate' => $detail['ppn_rate'] ?? 0,
                'ppn_amount' => $ppnAmount,
                'rv_id' => $detail['rv_id'] ?? null,
                'total_amount' => $totalAmount,
                'created_by' => $authId,
                'created_at' => now(),
                'updated_at' => null,
            ];

            if ($detail["rv_id"]) {
                RV::find($detail['rv_id'])->update(['status' => 'USED']);
            }
        }

        InvoiceDetail::insert($details);
        return (float) $sumTotalAmount;
    }

    private function handleResubmit(InvoiceRequest $request, Invoice $invoice, int $authId): void
    {
        $invoice->details()->delete();
        $invoice->update(['total_amount' => $this->createInvoiceDetails($request->details, $invoice, $authId)]);

        $invoice->wf_histories()->delete();
        $invoice->wf_approval()->delete();

        (new WorkflowService($invoice));
    }

    private function handleWorkflowAction(InvoiceRequest $request, Invoice $invoice, int $authId): void
    {
        abort_unless($invoice->status === 'REQUEST', 409, 'Invoice is not awaiting approval.');
        abort_if($invoice->pv()->exists(), 409, 'Invoice already has a payment voucher.');

        $approval = $invoice->wf_approval()->first();
        $hasWorkflow = $approval || $invoice->wf_histories()->exists();
        $isFinalApproval = false;

        if ($hasWorkflow) {
            abort_unless($approval, 409, 'Invoice has no active workflow.');

            $history = $invoice->wf_histories()->whereKey($request->wf_history_id)->first();
            abort_unless($history, 404, 'Workflow history not found for this invoice.');
            abort_unless((int) $history->user_id === $authId, 403, 'This workflow step belongs to another user.');
            abort_unless($history->status === 'PENDING', 409, 'Workflow step has already been processed.');
            $nextStep = $invoice->wf_histories()->where('status', '!=', 'APPROVE')->first();
            abort_unless($nextStep && $nextStep->is($history), 409, 'Workflow step is out of order.');

            $invoice->update(['updated_by' => $authId]);
            $history->update([
                'status' => $request->status,
                'signature' => $request->signature,
                'remark' => $request->remark
            ]);

            if ($request->status === 'REJECT') {
                $invoice->update(['status' => 'REJECT']);
                if ($invoice->payment_method === 'PREPAYMENT') {
                    Settlement::where("lpj_invoice_id", $invoice->id)
                        ->update([
                            'lpj_invoice_id' => null,
                            'lpj_amount' => 0,
                            'status' => 'NEW'
                        ]);
                }
                return;
            }

            $approvedCount = $invoice->wf_histories()->where('status', 'APPROVE')->count();
            $approval->update(['approve_count' => $approvedCount]);

            // Notify the next unfinished step; only approved histories count toward completion.
            $nextStep = $invoice->wf_histories()->where('status', '!=', 'APPROVE')->first();
            if ($nextStep && $nextStep->status === 'PENDING') {
                (new WhatsAppService($invoice, $nextStep->user->phone));
            }

            $isFinalApproval = $approvedCount > 0 && !$nextStep;
        } else {
            $invoice->update([
                'updated_by' => $authId,
                'signature' => $request->signature ?? $invoice->signature,
            ]);

            if ($request->status === 'REJECT') {
                $invoice->update(['status' => 'REJECT']);
                if ($invoice->payment_method === 'PREPAYMENT') {
                    Settlement::where("lpj_invoice_id", $invoice->id)
                        ->update([
                            'lpj_invoice_id' => null,
                            'lpj_amount' => 0,
                            'status' => 'NEW'
                        ]);
                }
                return;
            }

            $isFinalApproval = true;
        }

        if ($isFinalApproval) {
            $invoice->update(['status' => 'APPROVE']);
            (new WhatsAppService($invoice, '6289518901400'));

            if ($invoice->payment_method !== "PREPAYMENT") {
                $invoice->pv()->create([
                    "payment_method" => $invoice->payment_method,
                    "supplier_id" => $invoice->supplier_id,
                    "supplier_account_id" => ($invoice->payment_method === "BANK") ? $invoice->supplier_account_id : null,
                    "pv_amount" => $invoice->total_amount,
                    "status" => "NEW",
                    "trx_dtl_id" => $invoice->trx_id,
                    "created_by" => $authId,
                ]);
            } else {
                (new LpjService($invoice));
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $invoice->delete();

        return new DeleteResource($invoice);
    }
}
