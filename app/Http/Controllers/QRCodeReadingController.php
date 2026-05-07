<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;


class QRCodeReadingController extends Controller
{

    //====================
    // QRコード読み取り・分解（最終版）
    //====================
    private function parseBarcode(string $raw): array
    {
        // 改行のみ除去（スペースは保持）
        $code = str_replace(["\r", "\n"], '', $raw);

        // 後半（呼び径＋年号）は16文字固定
        $tail = substr($code, -16);

        // 呼び径・年号
        $yobi1 = substr($tail, 0, 4);
        $yobi2 = substr($tail, 4, 4);
        $yobi3 = substr($tail, 8, 4);
        $year  = substr($tail, 12, 4);

        // 商品CD
        // 先頭17文字を除外し、末尾16文字も除外
        $productCd = substr($code, 17, strlen($code) - 17 - 16);
        $productCd = rtrim($productCd); // 末尾の区切りスペースのみ削除

        return [
            '商品CD' => $productCd,
            '呼び径1' => $yobi1 === '0000' ? '    ' : (int)$yobi1,
            '呼び径2' => $yobi2 === '0000' ? '    ' : (int)$yobi2,
            '呼び径3' => $yobi3 === '0000' ? '   ' : (int)$yobi3,
            '年号' => $year,
        ];
    }



    //====================
    //  D出荷指示明細と一致するか確認
    //====================
    public function check(Request $request)
    {

        //確認用
        // Log::info('QR check start', [
        //     'barcode' => $request->barcode,
        //     '出荷指示NO' => $request->出荷指示NO,
        // ]);

        // $request->barcode = '25110006500000002G45E 000  0400000000002024'; //後で消す

        $syozokubumonCD = session('所属部門CD');
        $jurisdictionCD  = $syozokubumonCD == 'HASDES' ? 1 : $syozokubumonCD;


        $request->validate([
            'barcode' => 'required|string',
            '出荷指示NO' => 'required|integer',
        ]);

        $parsed = $this->parseBarcode($request->barcode); //バーコード読み取り、分解

        //確認用
        // Log::info('QR分解結果', $parsed);


        $productCd = $parsed['商品CD'];
        $size1 = $parsed['呼び径1'];
        $size2 = $parsed['呼び径2'];
        $size3 = $parsed['呼び径3'];
        $year  = $parsed['年号'];


        return response()->json([
            'status' => 'ok',
            'スキャンデータ' => [
                '商品CD' => $productCd,
                '呼び径1' => $size1,
                '呼び径2' => $size2,
                '呼び径3' => $size3,
                '生産管理NO' => $request->barcode,
            ],
            // 指示年号が空なので QR年号を採用
            'スキャン年号' => $year,
        ]);
    }

}






