<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

class CustomerController extends Controller
{
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // フィルタの処理
        $filter = $request->input('filter');
        if (is_scalar($filter)) {
            $queryParams['filter'] = $filter;
        } else {
            \Log::warning('不正なフィルタパラメータが検出されました。', ['filter' => $filter]);
        }

        // 基本クエリの作成
        $query = Customer::query();

        $isSearch = !empty($request->input('filter')) ||
        $request->filled('coname');
        if ($isSearch) {
            $logkind = 2;
            $do = '顧客企業マスターデータ検索';
            } else {
            $logkind = 1;
            $do = '顧客企業マスターデータ表示';
        }
    
        // フィルタ条件に基づく検索
        if (!empty($queryParams['filter'])) {
            $query->where('得意先CD', $queryParams['filter']);
        }

        if (!empty($queryParams['coname'])) {
            // $query->where('得意先名', 'LIKE', '%' . $queryParams['coname'] . '%');
            $query->where('得意先略名', 'LIKE', '%' . $queryParams['coname'] . '%');//修正20250812
        }

        // ソート条件の処理
        $sortColumn = $request->input('sortColumn', 'M得意先_ID'); // 初期表示時のデフォルトソートカラム
        $sortOrder  = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 得意先CDを数値としてキャストしてソートする
        if ($sortColumn === '得意先CD') {
            $query->orderByRaw('CAST(得意先CD AS UNSIGNED) ' . $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }
        // $result = $query->paginate(17);
        
        //修正 20250812 ページングの場合、そうでない場合を区別
        if ($request->has('page')) {
            $result = $query->paginate(17);
        } else {
            $result = ['data' => $query->get()];        
        }


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




    public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から顧客IDを取得
            $customerId = $request->query('key');

            // $customerIdのバリデーション
            if (empty($customerId) || !is_numeric($customerId)) {
                return response()->json(['message' => '無効な顧客IDです。'], 400);
            }

            // 'M得意先_ID' に一致する顧客情報を取得
            $customer = Customer::where('M得意先_ID', $customerId)->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$customer) {
                return response()->json(['message' => '顧客情報が見つかりません。'], 404);
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
                $do = '顧客企業マスターデータ詳細表示';
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
            return response()->json(['data' => $customer]);

        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }
}

