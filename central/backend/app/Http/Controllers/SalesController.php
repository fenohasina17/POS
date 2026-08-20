<?php

namespace App\Http\Controllers;

use App\Models\RemoteSale;
use App\Models\RemoteOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RemoteSale::with([])
            ->when($request->terminal_id,   fn($q) => $q->where('terminal_id',   $request->terminal_id))
            ->when($request->restaurant_id, fn($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->when($request->date_from,     fn($q) => $q->where('remote_created_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->date_to,       fn($q) => $q->where('remote_created_at', '<=', $request->date_to   . ' 23:59:59'))
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->seller_name,   fn($q) => $q->where('seller_name', $request->seller_name))
            ->orderByDesc('remote_created_at');

        $paginated = $query->paginate($request->get('per_page', 50));

        return response()->json($paginated);
    }

    public function lines(Request $request, string $saleId): JsonResponse
    {
        $lines = RemoteOrderLine::where('sale_id_remote', $saleId)
            ->when($request->terminal_id, fn($q) => $q->where('terminal_id', $request->terminal_id))
            ->orderBy('id')
            ->get();

        return response()->json($lines);
    }
}
