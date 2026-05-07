<?php

namespace App\Http\Controllers;
use App\Models\Syozokubumon;
use Illuminate\Http\Request;

class SyozokubumonController extends Controller
{
    public function getDepartments()
    {
        // データベースから所属のリストを取得
        $departments = Syozokubumon::all(); // 例: Departmentモデルから全ての所属を取得

        return response()->json($departments);
    }

    public function getSalesOffice()
    {
        // 営業所のみ
        // $departments = Syozokubumon::whereIn('部門CD', [10,20,30,40,60,70])->get();
        $departments = Syozokubumon::whereIn('部門CD', [10,20,30,40,50,60,70])->get();

        return response()->json($departments);
    }
}

