<?php

namespace App\Http\Controllers;

use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class OrderLineController extends Controller
{
    // Afficher la liste des lignes de commande
    public function index()
    {

        try {
            $orderLines = OrderLine::all();
            return response()->json($orderLines);
          
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to retrieve order lines'], 500);
        }
    }

    // Afficher une ligne de commande spécifique
    public function show($id)
    {
        try {
            $orderLine = OrderLine::findOrFail($id);
            return response()->json($orderLine);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Order line not found'], 404);
        } catch (Exception $exception) {
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }

    // Créer une nouvelle ligne de commande
    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer',
            'price' => 'required|integer',
            'total' => 'required|integer',
        ]);

        try {
            $orderLine = OrderLine::create($request->all());
           
            return response()->json($orderLine, 201);
            
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to create order line'], 500);
        }
    }

    // Mettre à jour une ligne de commande existante
    public function update(Request $request, $id)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer',
            'price' => 'required|integer',
            'total' => 'required|integer',
        ]);

        try {
            $orderLine = OrderLine::findOrFail($id);
            $orderLine->update($request->all());
            return response()->json($orderLine);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Order line not found'], 404);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to update order line'], 500);
        }
    }

    // Supprimer une ligne de commande
    public function destroy($id)
    {
        try {
            $orderLine = OrderLine::findOrFail($id);
            $orderLine->delete();
            return response()->json(['message' => 'Order line deleted successfully']);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Order line not found'], 404);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to delete order line'], 500);
        }
    }

}