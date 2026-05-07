<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fruit;

class FruitsController extends Controller
{
    //
    public function list(Request $request)
    {
        $filter = $request->query('filter');

        if($filter){
            return Fruit::where('name','LIKE','%'.$filter.'%')->orderBy('id')->paginate(10);
        }
        return Fruit::orderBy('id')->paginate(10);
    }

    public function update(Request $request,Fruit $fruit)
    {
        $fruit->update($request->all());

        return $fruit;
    }

    public function create(Request $request)
    {
        return Fruit::create($request->all());
    }

    public function delete(Fruit $fruit)
    {
        $fruit->delete();
        return $fruit;
    }

}