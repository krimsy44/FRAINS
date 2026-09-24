<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function receive(Order $order, array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['submission_token']) && ($existing = PaymentReceipt::where('order_id', $order->id)->where('submission_token', $data['submission_token'])->first())) {
                return $existing;
            }
            if (in_array($order->status, ['CANCELLED', 'REFUSED'], true)) {
                throw ValidationException::withMessages(['amount' => 'Cette commande ne peut plus recevoir de paiement.']);
            }
            // A legacy PAID payment represents a real receipt, even without a receipt row.
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > $order->balance) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le reste à payer ou est nul.']);
            }
            $receipt = PaymentReceipt::create(['order_id' => $order->id, 'user_id' => auth()->id(), 'reference' => DocumentNumber::next('REG'), 'external_reference' => $data['external_reference'] ?? null, 'submission_token' => $data['submission_token'] ?? null, 'amount' => $amount, 'method' => $data['method'], 'received_at' => now()]);
            $order->refresh();
            $paid = $order->balance <= 0;
            $order->update(['payment_status' => $paid ? 'PAID' : 'PARTIAL']);
            $order->payment?->update(['status' => $paid ? 'PAID' : 'PARTIAL', 'paid_at' => $paid ? now() : null]);
            AuditLog::record('payment.received', 'order:'.$order->id, ['receipt' => $receipt->reference, 'amount' => $amount]);
            $order->customer?->user?->notify(new PlatformNotification('Règlement reçu pour '.$order->order_number.' : '.$amount.' FCFA', route('account.orders.show', $order)));
            PlatformNotice::staff('Paiement reçu pour '.$order->order_number, route('admin.orders.show', $order));

            return $receipt;
        });
    }
}
