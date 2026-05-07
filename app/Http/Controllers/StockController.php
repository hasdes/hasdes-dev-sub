<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\HDLog;

class StockController extends Controller
{
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        // ページネーションとフィルタリングパラメータを取得
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // 'filter' パラメータの型チェック
        $filter = $request->input('filter');
        if (is_scalar($filter)) {
            $queryParams['filter'] = $filter;
        } else {
            \Log::warning('不正なフィルタパラメータが検出されました。', ['filter' => $filter]);
        }

        // Itemテーブルのクエリ
        $itemQuery = Item::query();

        $isSearch = !empty($request->input('filter')) ||
            $request->filled('name')||
            $request->filled('itemname')||
            $request->filled('yobikei1')||
            $request->filled('yobikei2')||
            $request->filled('yobikei3');

            if ($isSearch) {
                $logkind = 2;
                $do = '商品一覧検索';
            } else {
                $logkind = 1;
                $do = '商品一覧表示';
            }

        // 各パラメータで検索フィルタを設定
        if (!empty($queryParams['filter'])) {
            $itemQuery->where('商品種別', $queryParams['filter']);
        }

        if (!empty($queryParams['name'])) {
            if($queryParams['radioOption'] == 1){
                $itemQuery->where('商品CD', 'LIKE', $queryParams['name'] . '%');
            }else{   
                $itemQuery->where('商品CD', 'LIKE', '%' . $queryParams['name'] . '%');
            }
        }

        if (!empty($queryParams['itemname'])) {
            $itemQuery->where('商品名_社内用', 'LIKE', '%' . $queryParams['itemname'] . '%');
        }

        if (!empty($queryParams['yobikei1'])) {
            $itemQuery->where('呼び径1', 'LIKE', '%' . $queryParams['yobikei1'] . '%');
        }

        if (!empty($queryParams['yobikei2'])) {
            $itemQuery->where('呼び径2', 'LIKE', '%' . $queryParams['yobikei2'] . '%');
        }

        if (!empty($queryParams['yobikei3'])) {
            $itemQuery->where('呼び径3', 'LIKE', '%' . $queryParams['yobikei3'] . '%');
        }

        $sortColumn = $request->input('sortColumn', '商品CD'); // 初期表示時のデフォルトソートカラム
        $sortColumn2 = $request->input('sortColumn2', '呼び径1');
        $sortColumn3 = $request->input('sortColumn3', '呼び径2');
        $sortColumn4 = $request->input('sortColumn4', '呼び径3');
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        $itemQuery->orderBy($sortColumn, $sortOrder);
        $itemQuery->orderBy($sortColumn2, $sortOrder);
        $itemQuery->orderBy($sortColumn3, $sortOrder);
        $itemQuery->orderBy($sortColumn4, $sortOrder);
        
        $result = $itemQuery->paginate(50);
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

        return $result;
        
    }

    public function stocklist(Request $request)
    {
        \DB::enableQueryLog();
        // 商品CDをリクエストから取得
        $productCode = $request->input('商品CD');
        $diameter1 = $request->input('呼び径1');
        $diameter2 = $request->input('呼び径2');
        $diameter3 = $request->input('呼び径3');

        // ソートカラムとソート順をリクエストから取得（デフォルトは商品CDの昇順）
        $sortColumn = $request->input('sortColumn', 'C商品月間.倉庫部門CD');
        $sortColumn5 = $request->input('sortColumn5', '年号');
        $sortOrder = $request->input('sortOrder', 'asc'); // デフォルトで昇順ソート
        $year = date('Y'); 
        // Stockテーブルのクエリ
        $stockQuery = Stock::query()
            ->join('M部門', 'M部門.部門CD', '=', 'C商品月間.倉庫部門CD')
            ->where('商品CD', 'like', '%' . $productCode . '%')
            ->whereBetween('年号', [$year - 3, $year + 3]);

        if (!empty($diameter1)) {
            $stockQuery->whereRaw("TRIM(C商品月間.呼び径1) = ?", [trim($diameter1)]);
        }
        if (!empty($diameter2)) {
            $stockQuery->whereRaw("TRIM(C商品月間.呼び径2) = ?", [trim($diameter2)]);
        }
        if (!empty($diameter3)) {
            $stockQuery->whereRaw("TRIM(C商品月間.呼び径3) = ?", [trim($diameter3)]);
        }

        // 条件追加: 現在庫_完成品数 - 現在庫_出荷予定数 = 0 かつ 現在庫_完成品数 = 0 のものは表示しない
        $stockQuery->where(function ($query) {
            $query->whereRaw('現在庫_完成品数 - 現在庫_出荷予定数 != 0')
                ->orWhere('現在庫_完成品数', '!=', 0);
        });

        // ソートカラムが正しいかチェックし、不正なカラムが指定された場合はデフォルトにフォールバック
        $validColumns = ['商品CD', '呼び径1', '年号', '現在庫_出荷予定数', '現在庫_完成品数', '部門略称名'];
        if (!in_array($sortColumn, $validColumns)) {
            $sortColumn = 'C商品月間.倉庫部門CD';
        }

        // クエリにソートを適用
        $stockQuery->orderBy($sortColumn, $sortOrder);
        $stockQuery->orderBy($sortColumn5, $sortOrder);
        

        $result = $stockQuery->paginate(12);
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
            $do = '在庫一覧表示';
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' =>  1,
                '実行内容' => $do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery, // 生成されたSQL文を保存
            ]);
        } else {
            \Log::error('SQLクエリが取得できませんでした。');
        }

        // ページネーションを設定して結果を返す
        return $result;
    }

    
}
