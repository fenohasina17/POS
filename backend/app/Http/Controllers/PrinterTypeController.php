<?php

namespace App\Http\Controllers;

use App\Models\PrinterType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrinterTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $printerTypes = PrinterType::all();

        return response()->json([
            'message' => 'Printer types retrieved successfully',
            'data' => $printerTypes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:printer_types,name',
            ]);

            $printerType = PrinterType::create($validated);

            return response()->json([
                'message' => 'Printer type created successfully',
                'data' => $printerType,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PrinterType $printerType)
    {
        return response()->json([
            'message' => 'Printer type retrieved successfully',
            'data' => $printerType
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PrinterType $printerType)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:printer_types,name,' . $printerType->id,
            ]);

            $printerType->update($validated);

            return response()->json([
                'message' => 'Printer type updated successfully',
                'data' => $printerType,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PrinterType $printerType)
    {
        $printerType->delete();

        return response()->json([
            'message' => 'Printer type deleted successfully',
            'id' => $printerType->id,
        ]);
    }
}
