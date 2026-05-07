<?php

namespace App\Http\Controllers;

use App\Models\Hinmei;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HinmeiController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');
        \Log::info("Search Query: {$query}");
    
        // 品名CDまたは名称_正式印刷用が$queryで始まり、かつM商品テーブルに存在するもの
        $results = Hinmei::where(function ($q) use ($query) {
                            $q->where('品名CD', 'LIKE', "{$query}%")
                              ->orWhere('名称_正式印刷用', 'LIKE', "{$query}%");
                        })
                        ->whereExists(function ($subquery) {
                            $subquery->select(DB::raw(1))
                                     ->from('M商品')
                                     ->whereColumn('M商品.品名CD', 'M品名.品名CD');
                        })
                        ->select('品名CD', '名称_正式印刷用') // 必要なカラムのみ選択
                        ->get();
    
        return response()->json($results);
    }
    
}
