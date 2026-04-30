<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSaleRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        //  on s'assure que l'utilisateur est connecté.
        return auth()->guard('api')->check();
    }

    /**
     * Les règles de validation.
     */
    public function rules(): array
    {
        return [
            // Infos de base
            'user_id' => 'required|exists:users,id',
            'point_of_sale_id' => 'required|exists:point_of_sales,id',
            'cash_register_session_id' => 'required|exists:cash_register_sessions,id',
            'table_id' => 'nullable|exists:tables,id',

            // Montants
            'total_amount' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numùeric|min:0|max:100',

            // Traçabilité et Paiement
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'amount_received' => 'nullable|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',

            // Le Panier (Items)
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    /**
     * Messages d'erreur personnalisés (Optionnel)
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Le panier ne peut pas être vide.',
            'items.*.product_id.exists' => 'Un des produits sélectionnés n\'existe pas.',
            'total_amount.required' => 'Le montant total est obligatoire.',
        ];
    }

    /**
     * Formatage de la réponse en cas d'échec de validation (pour API)
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => 'Erreur de validation',
            'details' => $validator->errors()
        ], 422));
    }
}