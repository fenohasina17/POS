<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Events\CashRegisterSessionOpened;
use App\Events\CashRegisterSessionClosed;
use Illuminate\Support\Facades\Auth;
use App\Services\CashRegisterSessionSummaryService;
use Illuminate\Support\Carbon;
use App\Models\User;
use Illuminate\Validation\Rule;


class CashRegisterSessionController extends Controller
{
    private function userIsManager(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $managerRoles = ['gerant', 'gérant'];
        return collect($managerRoles)->contains(fn($role) => $user->hasRole($role, 'api'));
    }

    public function index(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $query = CashRegisterSession::query();

        $managerRoles = ['gerant', 'gérant'];
        $isManager = collect($managerRoles)->contains(fn($role) => $user->hasRole($role, 'api'));

        if (!$user->hasRole('admin', 'api') && $isManager) {
            $pointOfSaleId = $user->point_of_sale_id;
            if ($pointOfSaleId) {
                $query->whereHas('cashRegister', function ($q) use ($pointOfSaleId) {
                    $q->where('point_of_sale_id', $pointOfSaleId);
                });
            } else {
                $query->where('user_id', $user->id);
            }
        } elseif (!$user->hasRole('admin', 'api') && !$isManager) {
            $query->where('user_id', $user->id);
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->has('status')) {
            if ($request->status === 'open') {
                $query->where('is_closed', false);
            } elseif ($request->status === 'closed') {
                $query->where('is_closed', true);
            }
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->with(['cashRegister', 'user', 'transactions', 'discrepancies', 'closures'])->get();

        return response()->json($sessions);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->guard('api')->user();

        if (!$user || !$user->hasPermissionTo('create.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        if ($this->userIsManager($user)) {
            abort(403, 'Les gérants ne peuvent pas créer de session de caisse.');
        }

        $validated = $request->validate([
            'cash_register_id' => [
                'required',
                Rule::exists('cash_registers', 'id')->where(function ($query) use ($user) {
                    $query->where('point_of_sale_id', $user->point_of_sale_id);
                }),
            ],
            'starting_amount' => 'required|numeric|min:0',
            'expected_cash_amount' => 'nullable|numeric|min:0',
            'start_ticket_number' => 'nullable|integer|min:0',
        ]);

        $openSession = CashRegisterSession::where('cash_register_id', $validated['cash_register_id'])
            ->where('is_closed', false)
            ->first();

        if ($openSession) {
            return response()->json([
                'message' => 'There is already an open session for this cash register.'
            ], Response::HTTP_CONFLICT);
        }

        $expectedAmount = $validated['expected_cash_amount'] ?? $validated['starting_amount'];

        $session = CashRegisterSession::create([
            'cash_register_id' => $validated['cash_register_id'],
            'user_id' => $user->id,
            'starting_amount' => $validated['starting_amount'],
            'expected_cash_amount' => $expectedAmount,
            'start_ticket_number' => $validated['start_ticket_number'] ?? null,
            'is_closed' => false,
            'opened_at' => now(),
        ]);

        event(new CashRegisterSessionOpened($session));

        return response()->json($session, Response::HTTP_CREATED);
    }

    public function show($id, Request $request)
    {
        $query = CashRegisterSession::with(['cashRegister', 'user', 'transactions', 'discrepancies', 'closures']);

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $session = $query->find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $managerRoles = ['gerant', 'gérant'];
        $isManager = collect($managerRoles)->contains(fn($role) => $user->hasRole($role, 'api'));

        if (!$user->hasRole('admin', 'api') && $isManager) {
            $pointOfSaleId = $user->point_of_sale_id;
            if ($pointOfSaleId && optional($session->cashRegister)->point_of_sale_id !== $pointOfSaleId) {
                abort(403, 'This action is unauthorized.');
            }
        }

        return response()->json($session);
    }

    public function update(Request $request, $id)
    {
        $session = CashRegisterSession::find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('update.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $validated = $request->validate([
            'actual_cash_amount' => 'nullable|numeric|min:0',
            'expected_cash_amount' => 'nullable|numeric|min:0',
            'is_closed' => 'nullable|boolean',
            'closed_at' => 'nullable|date',
            'start_ticket_number' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['is_closed']) && $validated['is_closed'] === true) {
            $session->actual_cash_amount = $validated['actual_cash_amount'] ?? $session->actual_cash_amount;
            $session->is_closed = true;
            $session->closed_at = now();
            $session->save();

            event(new CashRegisterSessionClosed($session));
            return response()->json($session);
        }

        $session->update($validated);
        return response()->json($session);
    }

    public function destroy($id)
    {
        $session = CashRegisterSession::find($id);
        if (!$session) return response()->json(['message' => 'Not found'], 404);

        $user = auth()->guard('api')->user();
        if (!$user->hasPermissionTo('delete.cash_register_sessions', 'api')) {
            abort(403);
        }

        $session->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function summary($id)
    {
        $session = CashRegisterSession::with(['cashRegister', 'transactions', 'discrepancies', 'closures', 'user'])->find($id);
        if (!$session) return response()->json(['message' => 'Not found'], 404);

        $service = new CashRegisterSessionSummaryService();
        return response()->json($service->build($session));
    }

    public function myActiveSession()
    {
        $user = Auth::user();
        $session = CashRegisterSession::where('user_id', $user->id)
            ->where('is_closed', false)
            ->with(['cashRegister', 'user'])
            ->first();

        return $session ? response()->json(['data' => $session]) : response()->json(['data' => null], 404);
    }
}