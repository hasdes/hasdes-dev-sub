<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use App\Models\Contents;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

class ItemController extends Controller
{
    public function list(Request $request)
    {
        \DB::enableQueryLog();
         // 'page' パラメータを除外し、'filter' パラメータを個別に処理
         $queryParams = $request->except('page', 'filter');

         // 'filter' パラメータを取得し、期待する型かどうかをチェック
         $filter = $request->input('filter');
         if (is_scalar($filter)) {
             $queryParams['filter'] = $filter;
         } else {
             \Log::warning('不正なフィルタパラメータが検出されました。', ['filter' => $filter]);
         }

        $query = Item::query();

        $isSearch = !empty($request->input('filter')) ||
            $request->filled('name')||
            $request->filled('itemname')||
            $request->filled('yobikei1')||
            $request->filled('yobikei2')||
            $request->filled('yobikei3');

            if ($isSearch) {
                $logkind = 2;
                $do = '商品検索';
            } else {
                $logkind = 1;
                $do = '商品表示';
            }

        if (!empty($queryParams['filter'])) {
            $query->where('商品種別', $queryParams['filter']);
        }

        if (!empty($queryParams['name'])) {
            if($queryParams['radioOption'] == 1){
                $query->where('商品CD', 'LIKE', $queryParams['name'] . '%');
            }else{   
                $query->where('商品CD', 'LIKE', '%' . $queryParams['name'] . '%');
            }
        }

        if (!empty($queryParams['itemname'])) {
            $query->where('商品名_社内用', 'LIKE', '%' . $queryParams['itemname'] . '%');
        }
        if (!empty($queryParams['yobikei1'])) {
            $query->where('呼び径1', 'LIKE', '%' . $queryParams['yobikei1'] . '%');
        }
        if (!empty($queryParams['yobikei2'])) {
            $query->where('呼び径2', 'LIKE', '%' . $queryParams['yobikei2'] . '%');
        }
        if (!empty($queryParams['yobikei3'])) {
            $query->where('呼び径3', 'LIKE', '%' . $queryParams['yobikei3'] . '%');
        }

        $sortColumn = $request->input('sortColumn', '商品種別'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        $query->orderBy($sortColumn, $sortOrder);
        
        $result = $query->paginate(16);
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

    public function detail(Request $request)
{
    \DB::enableQueryLog();
    try {
        // URLのクエリパラメータ 'key' からメッセージIDを取得
        $meesageId = $request->query('key');

        // $messageIdのバリデーション
        if (empty($meesageId) || !is_numeric($meesageId)) {
            return response()->json(['message' => '無効なメッセージIDです。'], 400);
        }

        // 'メッセージCD' に一致するメッセージを全て取得
        $messages = Item::where('M商品_ID', $meesageId)->get();

        // メッセージが見つからない場合は404を返す
        if ($messages->isEmpty()) {
            return response()->json(['message' => '商品が見つかりません。'], 404);
        }
        $hinmeiCD = trim($messages->first()->品名CD ?? '');
        $count = Contents::where('品名CD', $hinmeiCD)
                 ->whereNull('消去日時')
                 ->count();
        $hasContent = $count > 0;


        // メッセージを適切な構造で返す
        $result = [
            'data' => $messages,
            'hasContent' => $hasContent, 
        ];

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
            $do = '商品詳細表示';
            $logkind = 1;
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
        
        return response()->json($result); // JSONレスポンスとして返す

    } catch (\Exception $e) {
        // 予期せぬエラーが発生した場合に500エラーを返す
        return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
    }
}


    
}
