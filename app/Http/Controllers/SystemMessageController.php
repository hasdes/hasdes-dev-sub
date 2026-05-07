<?php

namespace App\Http\Controllers;
use App\Models\SystemMessage;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use App\Models\HDLog;

class SystemMessageController extends Controller
{
    public function list(Request $request)
{
    \DB::enableQueryLog();
    // セッションから担当者CDを取得
    $userId = session('担当者CD');

    // 'page' パラメータを除外し、'filter' パラメータを個別に処理
    $queryParams = $request->except('page', 'filter');

    // 'filter' パラメータを取得し、期待する型かどうかをチェック
    $filter = $request->input('filter');
    if (is_scalar($filter)) {
        $queryParams['filter'] = $filter;
    } else {
        \Log::warning('不正なフィルタパラメータが検出されました', ['filter' => $filter]);
    }

    // クエリビルダー作成
    $subQuery = SystemMessage::query()
        ->selectRaw('MAX(HMメッセージ.Mメッセージ_ID) as 最新メッセージID')
        ->groupBy('HMメッセージ.メッセージCD');

    $query = SystemMessage::query()
        ->joinSub($subQuery, 'sub', function ($join) {
            $join->on('HMメッセージ.Mメッセージ_ID', '=', 'sub.最新メッセージID');
        })
        ->leftJoin('M担当者 as 送信者', 'HMメッセージ.送信者CD', '=', '送信者.担当者CD')
        ->leftJoin('M担当者 as 受信者', 'HMメッセージ.受信者CD', '=', '受信者.担当者CD')
        ->leftJoin('M所属 as 送信者所属', '送信者.所属CD', '=', '送信者所属.所属CD')
        ->leftJoin('M部門 as 送信者部門', '送信者.所属部門CD', '=', '送信者部門.部門CD')
        ->leftJoin('M所属 as 受信者所属', '受信者.所属CD', '=', '受信者所属.所属CD')
        ->leftJoin('M部門 as 受信者部門', '受信者.所属部門CD', '=', '受信者部門.部門CD')
        ->select(
            'HMメッセージ.*',
            'HMメッセージ.送信者CD as 送信者CD',
            'HMメッセージ.受信者CD as 受信者CD',
            '送信者.担当者名 as 送信者名',
            '受信者.担当者名 as 受信者名',
            '送信者所属.所属名_社内用 as 送信者所属名_社内用',
            '送信者部門.部門略称名 as 送信者部門略称名',
            '受信者所属.所属名_社内用 as 受信者所属名_社内用_受信者',
            '受信者部門.部門略称名 as 受信者部門略称名',
            '送信者.所属部門CD as 送信者所属部門CD'
        );

        $isSearch = !empty($queryParams['filter']) ||
        !empty($queryParams['name']) ||
        !empty($queryParams['department']) ||
        !empty($queryParams['section']);

        if ($isSearch) {
            $logkind = 2;
            $do = 'システムメッセージ検索';
        } else {
            $logkind = 1;
            $do = 'システムメッセージ表示';
        }

        if (!empty($queryParams['filter'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('HMメッセージ.送信者CD', 'LIKE', '%' . $queryParams['filter'] . '%')
                      ->orWhere('HMメッセージ.受信者CD', 'LIKE', '%' . $queryParams['filter'] . '%');
            });
        }
        
       
        if (!empty($queryParams['name'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('送信者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%')
                      ->orWhere('受信者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%');
            });
        }
        
        if (!empty($queryParams['department'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('送信者.所属部門CD', $queryParams['department'])
                      ->orWhere('受信者.所属部門CD', $queryParams['department']);
            });
        }
        if (!empty($queryParams['section'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('送信者.所属CD', $queryParams['section'])
                      ->orWhere('受信者.所属CD', $queryParams['section']);
            });
        }


    // ソート条件の処理
    $sortColumn = $request->input('sortColumn', 'HMメッセージ.送信日時'); // 初期表示時のデフォルトソートカラム
    $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順
    if ($sortColumn === '送信者CD') {
        $query->orderByRaw('CAST(送信者CD AS UNSIGNED) ' . $sortOrder);
    } else {
        $query->orderBy($sortColumn, $sortOrder);
    }

    // クエリにソートを追加
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

    // データのページネーション
    return $result;
}


    public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            $messageId = $request->query('key');


            $messages = SystemMessage::where('メッセージCD', $messageId)
                ->leftJoin('M担当者 as 送信者', 'HMメッセージ.送信者CD', '=', '送信者.担当者CD')
                ->leftJoin('M担当者 as 受信者', 'HMメッセージ.受信者CD', '=', '受信者.担当者CD')
                ->select(
                    'HMメッセージ.*',
                    '送信者.担当者名 as 送信者名',
                    '受信者.担当者名 as 受信者名'
                )
                ->get();

            
            // メッセージが見つからない場合は404を返す
            if ($messages->isEmpty()) {
                return response()->json(['message' => 'メッセージが見つかりません。'], 404);
            }
    
            // メッセージを適切な構造で返す
            $result =  response()->json([
                'data' => $messages,
            ]);

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
                $do = 'システムメッセージ詳細表示';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            return $result;

    
    
        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }

}
