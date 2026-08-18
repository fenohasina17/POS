<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::query()->orderByDesc('created_at');

        if ($request->boolean('active')) {
            $query->active();
        }
        if ($request->filled('terminal_id')) {
            $query->where('terminal_id', $request->terminal_id);
        }
        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        return response()->json($query->limit(200)->get());
    }

    public function resolve(Alert $alert)
    {
        $alert->update(['resolved_at' => now()]);
        return response()->json(['message' => 'Alerte résolue.']);
    }

    public function counts()
    {
        return response()->json([
            'critical' => Alert::active()->where('severity', 'critical')->count(),
            'warning'  => Alert::active()->where('severity', 'warning')->count(),
            'total'    => Alert::active()->count(),
        ]);
    }
}
