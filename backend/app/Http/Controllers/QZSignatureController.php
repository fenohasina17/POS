<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QZSignatureController extends Controller
{
    /**
     * Sign the request from QZ Tray.
     */
    public function sign(Request $request)
    {
        $request->validate([
            'request' => 'required|string',
        ]);

        $dataToSign = $request->input('request');
        $privateKeyPath = storage_path('app/qz-private-key.pem');

        if (!file_exists($privateKeyPath)) {
            return response()->json(['error' => 'Private key not found'], 500);
        }

        $privateKey = openssl_get_privatekey(file_get_contents($privateKeyPath));
        
        $signature = '';
        openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        
        return base64_encode($signature);
    }
}
