<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerReportController extends Controller
{
    public function __construct(private OrderRepository $orders) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        $items = $this->orders->paidOrderItemsForOrganizer($user->id, $from, $to);

        $summary = [
            'total_quantity' => $items->sum('quantity'),
            'total_revenue' => (float) $items->sum('subtotal'),
            'from' => $from,
            'to' => $to,
        ];

        return Inertia::render('Organizer/Reports/Index', [
            'items' => $items->map(fn (OrderItem $item) => [
                'order_code' => $item->order->order_code,
                'event' => $item->ticketCategory->event->title,
                'category' => $item->ticketCategory->name,
                'quantity' => $item->quantity,
                'price_per_unit' => (float) $item->price_per_unit,
                'subtotal' => (float) $item->subtotal,
                'paid_at' => $item->order->paid_at?->format('Y-m-d H:i'),
            ]),
            'summary' => $summary,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $items = $this->orders->paidOrderItemsForOrganizer($user->id, $from, $to);
        $filename = 'laporan-penjualan-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kode Pesanan', 'Event', 'Kategori', 'Jumlah', 'Harga/Item', 'Subtotal', 'Dibayar Pada']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->order->order_code,
                    $item->ticketCategory->event->title,
                    $item->ticketCategory->name,
                    $item->quantity,
                    $item->price_per_unit,
                    $item->subtotal,
                    $item->order->paid_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function print(Request $request): Response
    {
        $user = $request->user();
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();
        $items = $this->orders->paidOrderItemsForOrganizer($user->id, $from, $to);

        $summary = [
            'total_quantity' => $items->sum('quantity'),
            'total_revenue' => (float) $items->sum('subtotal'),
            'from' => $from,
            'to' => $to,
        ];

        return Inertia::render('Organizer/Reports/Print', [
            'items' => $items->map(fn (OrderItem $item) => [
                'order_code' => $item->order->order_code,
                'event' => $item->ticketCategory->event->title,
                'category' => $item->ticketCategory->name,
                'quantity' => $item->quantity,
                'price_per_unit' => (float) $item->price_per_unit,
                'subtotal' => (float) $item->subtotal,
                'paid_at' => $item->order->paid_at?->format('Y-m-d H:i'),
            ]),
            'summary' => $summary,
        ]);
    }
}
