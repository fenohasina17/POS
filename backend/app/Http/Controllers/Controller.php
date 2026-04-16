<?php

namespace App\Http\Controllers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // IMPORTATION ESSENTIELLE

abstract class Controller
{
    use AuthorizesRequests;
}
