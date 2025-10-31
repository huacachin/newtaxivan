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

    public function create(){
        return view('users.create');
    }

    public function edit($id){
        return view('users.edit',compact('id'));
    }

    public function perms($id){
        return view('users.perms',compact('id'));
    }
}
