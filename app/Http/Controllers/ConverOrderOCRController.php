<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

use Illuminate\Support\Facades\Log; //デバック用
use App\Services\Ocr\ConverOrderOCRService;//サービス（こちらに関数まとめています）


class ConverOrderOCRController extends Controller
{
    // public function create(Request $request, ConverOrderOCRService $orderService)
    // {
    //     try {
    //         $orderNo = $orderService->createOrder($request->all(), session('担当者CD'));
    //         return response()->json(['orderNo' => $orderNo], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => '登録中にエラーが発生しました',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function create(Request $request, ConverOrderOCRService $orderService)
    {
        try {
            
            $result = $orderService->createOrder($request->all(), session('担当者CD'));

            // サービス層から「重複商品エラーのJSONレスポンス」が返ってきた場合
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result;
            }

            // 正常登録時
            return response()->json(['orderNo' => $result], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '登録中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}

