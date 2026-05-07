<?php

namespace App\Http\Controllers;
use App\Models\Syozoku;
use Illuminate\Http\Request;

class SyozokuController extends Controller
{
    public function getSections()
    {
        // データベースから所属のリストを取得
        $departments = Syozoku::all(); // 例: Departmentモデルから全ての所属を取得

        return response()->json($departments);
    }
}
