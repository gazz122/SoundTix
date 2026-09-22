<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerRefundPageController extends Controller
{
    public function index(Request $request): Response
    {
        $refunds = Refund::query()
            ->whereHas('order.orderItems.ticketCategory.event', fn ($query) => $query->where('organizer_id', $request->user()->id))
            ->with([
                'order.customer:id,name,email',
                'order.orderItems.ticketCategory.event:id,title,event_date',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Refund $refund) => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'reason' => $refund->reason,
                'created_at' => $refund->created_at?->format('d M Y H:i'),
                'order_code' => $refund->order->order_code,
                'customer_name' => $refund->order->customer?->name,
                'customer_email' => $refund->order->customer?->email,
                'event_title' => $refund->order->orderItems->first()?->ticketCategory?->event?->title,
                'event_date' => $refund->order->orderItems->first()?->ticketCategory?->event?->event_date?->format('d M Y'),
            ])
            ->values()
            ->all();

        return Inertia::render('Organizer/Refunds/Index', ['refunds' => $refunds]);
    }
}
