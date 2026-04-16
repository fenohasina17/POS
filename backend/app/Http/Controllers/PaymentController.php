<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PaymentController extends Controller
{
    // Afficher la liste des types de paiement
    public function index()
    {
        try {
            $paymentTypes = Payment::all();
            return response()->json($paymentTypes);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve payment types'], 500);
        }
    }

    // Afficher un type de paiement spécifique
    public function show($id)
    {
        try {
            $paymentType = Payment::findOrFail($id);
            return response()->json($paymentType);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment type not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve payment type'], 500);
        }
    }

    // Créer un nouveau type de paiement
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $paymentType = Payment::create($request->all());
            return response()->json($paymentType, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create payment type'], 500);
        }
    }

    // Mettre à jour un type de paiement existant
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $paymentType = Payment::findOrFail($id);
            $paymentType->update($request->all());
            return response()->json($paymentType);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment type not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update payment type'], 500);
        }
    }

    // Supprimer un type de paiement
    public function destroy($id)
    {
        try {
            $paymentType = Payment::findOrFail($id);
            $paymentType->delete();
            return response()->json(['message' => 'Payment type deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment type not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete payment type'], 500);
        }
    }
}