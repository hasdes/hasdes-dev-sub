<?php
namespace App\Http\Controllers;
use App\Models\OrderDetailOCR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

use Illuminate\Support\Facades\Log; //デバック用


class OrderDetailOCRController extends Controller
{
    //商品一覧
    public function list(Request $request) {

        \DB::enableQueryLog();

        // URLのクエリパラメータ 'key' からHD受注伝票_仮_IDを取得
        $ocrId = $request->query('key');

        // $ocrIdのバリデーション
        if (empty($ocrId) || !is_numeric($ocrId)) {
            return response()->json(['message' => '無効なHD受注伝票_OCR_IDです。'], 400);
        }

        // 'HD受注伝票_OCR_ID' に一致する情報を取得
        $result = OrderDetailOCR::where('HD受注伝票_OCR_ID', $ocrId)
            ->whereNull('消去日時')
            ->get();

        $queries = \DB::getQueryLog();
        $lastQuery = end($queries); // 最後に実行されたクエリを取得

        // 取得したクエリのSQL文とパラメータを変数に格納
        $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
        $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];

        if ($sqlquery) {
            // バインド変数をSQL文に埋め込む処理
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
            }

            $logkind = 1;
            $do = 'HD受注伝票_明細データ表示';
            
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => $logkind,
                '実行内容' => $do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery, // 生成されたSQL文を保存
            ]);
        } else {
            \Log::error('SQLクエリが取得できませんでした。');
        }
        // return $result;
        return response()->json([
            'data' => $result
        ]);

    }
}




