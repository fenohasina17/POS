<?php

namespace App\Http\Controllers;

use App\Models\RemoteCashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SessionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = RemoteCashRegisterSession::query()
            ->when($request->terminal_id,   fn($q) => $q->where('terminal_id',   $request->terminal_id))
            ->when($request->restaurant_id, fn($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->when($request->date_from,     fn($q) => $q->where('remote_opened_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->date_to,       fn($q) => $q->where('remote_opened_at', '<=', $request->date_to   . ' 23:59:59'))
            ->orderByDesc('remote_opened_at')
            ->paginate($request->get('per_page', 30));

        return response()->json($sessions);
    }
}
