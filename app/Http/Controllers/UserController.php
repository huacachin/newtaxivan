<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{

    public function __construct(){
        $this->middleware(['auth','permission:configuracion.users'])->only(['index']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('users.index');
    }
}
