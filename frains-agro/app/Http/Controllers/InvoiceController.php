<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\InvoiceService;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceController extends Controller
{
    public function show(Order $order, InvoiceService $service)
    {
        $user = request()->user();
        abort_unless(in_array($user->role?->name, ['ADMIN', 'MANAGER', 'COMMERCIAL'], true) || ($user->role?->name === 'CUSTOMER' && $order->customer_id === $user->customer?->id), 403);
        abort_if(in_array($order->status, ['CANCELLED', 'REFUSED'], true), 422, 'La commande est annulée ou refusée.');
        $invoice = $service->issue($order);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => storage_path('app/public')]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('invoices.pdf', compact('invoice', 'order'))->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"']);
    }
}
