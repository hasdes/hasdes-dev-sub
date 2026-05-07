<?php
namespace App\Http\Controllers;

use App\Models\Shipping;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;


class ShippingController extends Controller
{
    //出荷先一覧
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // フィルタの処理
        $filter = $request->input('filter');
        if (is_scalar($filter)) {
            $queryParams['filter'] = $filter;
        }

        $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD
        // $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

        // // 基本クエリの作成
        // $query = Shipping::where('得意先区分', 2)//得意先区分　2:出荷先　のみ
        // ->where('管轄部門CD', $jurisdictionCD);

        // 出荷先情報を取得（管轄部門CD = 自部門 or 0）
        $query = Shipping::where('得意先区分', 2) // 2:出荷先
        ->where(function($q) use ($syozokubumonCD) {
            $q->where('管轄部門CD', $syozokubumonCD)
            ->orWhere('管轄部門CD', 0);
        });

        $isSearch = !empty($request->input('filter')) ||
        $request->filled('coname');
        
        if ($isSearch) {
            $logkind = 2;
            $do = '出荷先データ検索';
        } else {
            $logkind = 1;
            $do = '出荷先データ表示';
        }
    
        // フィルタ条件に基づく検索
        if (!empty($queryParams['filter'])) {
            $query->where('出荷先_エンドユーザーCD', $queryParams['filter']);
        }

        if (!empty($queryParams['coname'])) {
            $query->where('略名', 'LIKE', '%' . $queryParams['coname'] . '%');
        }

        // ソート条件の処理
        $sortColumn = $request->input('sortColumn', 'M出荷先_ID'); // 初期表示時のデフォルトソートカラム
        $sortOrder  = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 出荷先CDを数値としてキャストしてソートする
        if ($sortColumn === '出荷先_エンドユーザーCD') {
            $query->orderByRaw('CAST(出荷先_エンドユーザーCD AS UNSIGNED) ' . $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        if ($request->has('page')) {
            $result = $query->paginate(17);
        } else {
            $result = ['data' => $query->get()];        
        }

        $queries = \DB::getQueryLog();
        $lastQuery = end($queries); // 最後に実行されたクエリを取得

        // Log::info($result);

        // 取得したクエリのSQL文とパラメータを変数に格納
        $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
        $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];

        if ($sqlquery) {
            // バインド変数をSQL文に埋め込む処理
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
            }
            
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

        // データのページネーション
        return $result;
    }


    //詳細
    public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から顧客IDを取得
            $ShippingId = $request->query('key');

            $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD
            // $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

            // $ShippingIdのバリデーション
            if (empty($ShippingId) || !is_numeric($ShippingId)) {
                return response()->json(['message' => '無効な顧客IDです。'], 400);
            }

            // // 'M出荷先_ID' に一致する顧客情報を取得
            // $Shipping = Shipping::where('M出荷先_ID', $ShippingId)
            // ->where('得意先区分', 2)//得意先区分　2:出荷先　のみ
            // ->where('管轄部門CD', $jurisdictionCD)//
            // ->first();

            // 出荷先情報を取得（管轄部門CD = 自部門 or 0）
            $Shipping = Shipping::where('M出荷先_ID', $ShippingId)
                ->where('得意先区分', 2) // 出荷先のみ
                ->where(function($q) use ($syozokubumonCD) {
                    $q->where('管轄部門CD', $syozokubumonCD)
                    ->orWhere('管轄部門CD', 0);
                })
                ->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$Shipping) {
                return response()->json(['message' => '出荷先情報が見つかりません。'], 404);
            }

            $queries = \DB::getQueryLog();
            $lastQuery = end($queries); // 最後に実行されたクエリを取得
        
            // 取得したクエリのSQL文とパラメータを変数に格納
            $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
            $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];
        
            // SQL文が取得できた場合のみログに保存
            if ($sqlquery) {
                // バインド変数をSQL文に埋め込む処理
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }
                $do = '出荷先データ詳細表示';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            // 顧客情報を適切な構造で返す
            return response()->json(['data' => $Shipping]);

        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }
}

