<?php

// Run: php tests/invoice_workflow_check.php
require __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SettlementController;
use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\LpjRequest;
use App\Models\Invoice;
use App\Models\Settlement;
use App\Models\User;
use App\Models\WorkflowHeader;
use App\Services\WorkflowService;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function ($app) {
    $app['config']->set('database.default', 'workflow-check');
    $app['config']->set('database.connections', ['workflow-check' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    $app['config']->set('logging.default', 'workflow-check');
    $app['config']->set('logging.channels.workflow-check', [
        'driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class,
    ]);
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
check(DB::connection()->getDatabaseName() === ':memory:', 'Must use an isolated in-memory database.');
Http::preventStrayRequests();
$httpStatus = 200;
Http::fake(function () use (&$httpStatus) {
    return Http::response([], $httpStatus);
});

// ponytail: only workflow tables, without foreign-key coverage; use PostgreSQL integration tests for constraints and row locks.
foreach ([
    '0001_01_01_000000_create_users_table.php',
    '2025_10_31_173003_create_type_trxes_table.php',
    '2025_11_04_143025_create_suppliers_table.php',
    '2025_11_04_143026_create_payment_vouchers_table.php',
    '2025_11_04_143027_create_invoices_table.php',
    '2026_03_31_145202_create_workflows_table.php',
    '2026_04_08_164856_create_settlements_table.php',
    '2025_11_04_133152_create_g_l_s_table.php',
] as $migration) {
    (require __DIR__ . '/../database/migrations/' . $migration)->up();
}
DB::table('users')->insert([
    ['id' => 1, 'user_id' => 'check-1', 'name' => 'Test Approver', 'password' => 'unused', 'phone' => '000'],
    ['id' => 2, 'user_id' => 'check-2', 'name' => 'Other Approver', 'password' => 'unused', 'phone' => '000'],
]);
DB::table('type_trxes')->insert(['id' => 1, 'code' => 'TEST', 'name' => 'Test', 'in_out' => 'OUT', 'is_active' => true, 'created_by' => 1]);
DB::table('suppliers')->insert(['id' => 1, 'name' => 'Test Supplier', 'is_active' => true, 'created_by' => 1]);
$actor = User::findOrFail(1)->withAccessToken(new TransientToken());
auth()->setUser($actor);

$workflow = WorkflowHeader::create(['name' => 'Test Workflow', 'type_trx' => [1], 'min_amount' => 0, 'is_active' => true, 'created_by' => 1]);
foreach ([1, 2, 3] as $sequence) {
    $workflow->details()->create(['sequence' => $sequence, 'user_id' => 1, 'created_by' => 1]);
}
$newInvoice = function (bool $initialize = true, string $method = 'CASH') {
    $invoice = Invoice::create([
        'date' => '2026-09-08', 'invoice_no' => 'TEST-' . (Invoice::count() + 1),
        'trx_id' => 1, 'supplier_id' => 1, 'payment_method' => $method,
        'description' => 'Original', 'total_amount' => 100, 'status' => 'REQUEST', 'created_by' => 1,
    ]);
    if ($initialize) {
        DB::transaction(fn() => new WorkflowService($invoice));
    }
    return $invoice;
};
$act = function (Invoice $invoice, string $status, ?int $historyId = null) use ($app) {
    $request = InvoiceRequest::create('/invoice/' . $invoice->id, 'PATCH', [
        'status' => $status, 'wf_history_id' => $historyId, 'date' => '2026-09-08',
        'trx_id' => 1, 'supplier_id' => 1, 'payment_method' => 'BANK', 'description' => 'Must not overwrite on approval',
        'details' => json_encode([['inv_coa_id' => 1, 'description' => 'Test', 'item_amount' => 100, 'rv_id' => null]]),
    ]);
    $request->setContainer($app);
    $validator = Validator::make($request->all(), $request->rules());
    check($validator->passes(), 'Synthetic request must pass validation: ' . $validator->errors()->toJson());
    $request->setValidator($validator);
    $result = (new InvoiceController())->update($request, $invoice);
    return $result instanceof Illuminate\Http\JsonResponse ? $result->getStatusCode() : $result->toResponse($request)->getStatusCode();
};
$expectConflict = function (callable $operation) {
    try {
        $operation();
        throw new RuntimeException('Expected HTTP 409.');
    } catch (HttpExceptionInterface $error) {
        check($error->getStatusCode() === 409, 'Expected HTTP 409, got ' . $error->getStatusCode());
    }
};

$invoice = $newInvoice();
$steps = $invoice->wf_histories()->get();
$foreign = $newInvoice();
check($act($invoice, 'APPROVE', $foreign->wf_histories()->first()->id) === 404, 'Foreign history must be rejected.');
auth()->setUser(User::findOrFail(2)->withAccessToken(new TransientToken()));
check($act($invoice, 'APPROVE', $steps[0]->id) === 403, 'Wrong user must be rejected.');
auth()->setUser($actor);
check($act($invoice, 'APPROVE', $steps[1]->id) === 409, 'Out-of-order approval must be rejected.');
check($act($invoice, 'REJECT', $steps[1]->id) === 409, 'Out-of-order rejection must be rejected.');
check($invoice->wf_histories()->where('status', 'PENDING')->count() === 3, 'Rejected actions must leave histories unchanged.');
$invoice->wf_approval()->update(['approve_count' => 2]);
check($act($invoice, 'APPROVE', $steps[0]->id) === 200, 'First approval must succeed.');
check((int) $invoice->wf_approval()->first()->approve_count === 1, 'Counter must come from histories, not incremented stale state.');
check(!$invoice->pv()->exists(), 'Partial approval must not create a PV.');
check($invoice->fresh()->status === 'REQUEST', 'Partial approval must keep REQUEST status.');
check($invoice->fresh()->description === 'Original' && $invoice->fresh()->payment_method === 'CASH', 'Approval must not edit invoice financial fields.');
check($act($invoice, 'APPROVE', $steps[0]->id) === 409, 'Replay must not increment counter.');
check((int) $invoice->wf_approval()->first()->approve_count === 1, 'Replay changed counter.');
check($act($invoice, 'APPROVE', $steps[1]->id) === 200, 'Second approval must succeed.');
check(!$invoice->pv()->exists(), 'Second approval must not create a PV.');

$httpStatus = 500;
$sentBeforeFinal = count(Http::recorded());
check($act($invoice, 'APPROVE', $steps[2]->id) === 200, 'Notification failure must not fail committed approval.');
check(count(Http::recorded()) === $sentBeforeFinal + 1, 'Final approval must attempt its notification after commit.');
check($invoice->fresh()->status === 'APPROVE' && $invoice->pv()->count() === 1, 'Final approval must create exactly one PV.');
check((int) $invoice->wf_approval()->first()->approve_count === 3, 'Final count must equal approved histories.');
check($act($invoice, 'APPROVE', $steps[2]->id) === 409, 'Final replay must be rejected.');
check($act($invoice, 'REQUEST') === 409 && $act($invoice, 'CANCEL') === 409, 'Finalized invoice must not reset or delete its PV.');
check($invoice->pv()->count() === 1, 'Existing PV must survive rejected transitions.');

// Also guard legacy inconsistent REQUEST invoices that already have a PV.
$invoice->update(['status' => 'REQUEST']);
check($act($invoice, 'REQUEST') === 409, 'Existing PV must prevent reset even when status is REQUEST.');
$settlement = Settlement::create(['prepayment_pv_id' => $invoice->pv()->first()->id, 'lpj_invoice_id' => $invoice->id, 'created_by' => 1]);
$expectConflict(fn() => (new SettlementController())->update(LpjRequest::create('/settlement', 'PATCH'), $settlement));
check($invoice->wf_histories()->count() === 3 && $invoice->pv()->count() === 1, 'Settlement reset must preserve existing workflow and PV.');
$httpStatus = 200;

$rejected = $newInvoice();
$rejectedSteps = $rejected->wf_histories()->get();
check($act($rejected, 'REJECT', $rejectedSteps[0]->id) === 200, 'Valid rejection must succeed.');
check($act($rejected, 'APPROVE', $rejectedSteps[1]->id) === 409, 'Rejected invoice must not approve.');
check($act($rejected, 'REQUEST') === 200, 'Rejected invoice must remain resubmittable.');
check($rejected->fresh()->status === 'REQUEST' && !$rejected->pv()->exists(), 'Resubmission must restart without a PV.');
check((int) $rejected->wf_approval()->first()->approve_count === 0, 'Resubmission must reset count.');
check($act($rejected, 'APPROVE', $rejectedSteps[0]->id) === 404, 'Old history IDs must not survive reset.');
$missingApproval = $newInvoice();
$missingApproval->wf_approval()->delete();
check($act($missingApproval, 'APPROVE', $missingApproval->wf_histories()->first()->id) === 409, 'Missing approval record must fail closed.');
check(!$missingApproval->pv()->exists(), 'Missing approval record must not create a PV.');
$cancelled = $newInvoice();
check($act($cancelled, 'CANCEL') === 200, 'Pending invoice must be cancellable.');
check($act($cancelled, 'APPROVE', $cancelled->wf_histories()->first()->id) === 409, 'Cancelled invoice must not approve.');

$lpj = $newInvoice(true, 'PREPAYMENT');
$lpjSteps = $lpj->wf_histories()->get();
foreach ($lpjSteps as $step) {
    check($act($lpj, 'APPROVE', $step->id) === 200, 'PREPAYMENT workflow must remain supported.');
}
check($lpj->fresh()->status === 'PAID' && !$lpj->pv()->exists(), 'PREPAYMENT must use LpjService, not create a direct PV.');
check($act($lpj, 'APPROVE', $lpjSteps->last()->id) === 409, 'LPJ finalization must not replay.');

$workflow->update(['is_active' => false]);
$unconfigured = $newInvoice(false);
$expectConflict(fn() => DB::transaction(fn() => new WorkflowService($unconfigured)));
check(!$unconfigured->pv()->exists() && !$unconfigured->wf_histories()->exists(), 'Missing workflow must fail without creating financial records.');
$workflow->update(['is_active' => true]);
$workflow->details()->delete();
$expectConflict(fn() => DB::transaction(fn() => new WorkflowService($unconfigured)));
$workflow->details()->create(['sequence' => 2, 'user_id' => 1, 'created_by' => 1]);
$expectConflict(fn() => DB::transaction(fn() => new WorkflowService($unconfigured)));
check(!$unconfigured->pv()->exists() && !$unconfigured->wf_histories()->exists(), 'Empty/malformed workflow must not partially initialize.');

$workflow->details()->update(['sequence' => 1]);
$sent = count(Http::recorded());
DB::beginTransaction();
new WorkflowService($unconfigured);
check(count(Http::recorded()) === $sent, 'Notification must wait for commit.');
DB::rollBack();
check(count(Http::recorded()) === $sent, 'Rolled-back workflow must not send a notification.');
check(!$unconfigured->wf_histories()->exists(), 'Rollback must undo workflow initialization.');

print "Invoice workflow checks passed. PostgreSQL concurrency is not covered.\n";
