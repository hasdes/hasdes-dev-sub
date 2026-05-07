<?php

namespace App\Http\Controllers;
use App\Models\Message;
use App\Models\Contents;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

class HomeController extends Controller
{
    public function Messagelist(Request $request)
    {
        \DB::enableQueryLog();
        $userId = session('担当者CD');

        $subQuery = Message::query()
        ->selectRaw('MAX(HMメッセージ.Mメッセージ_ID) as 最新メッセージID')
        ->groupBy('HMメッセージ.メッセージCD');

        $query = Message::query()
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
            '送信者.担当者名 as 送信者名',
            '受信者.担当者名 as 受信者名',
            '送信者所属.所属名_社内用 as 送信者所属名_社内用',
            '送信者部門.部門略称名 as 送信者部門略称名',
            '受信者所属.所属名_社内用 as 受信者所属名_社内用_受信者',
            '受信者部門.部門略称名 as 受信者部門略称名',
            '送信者.所属部門CD as 送信者所属部門CD',
            '送信者.所属CD as 送信者所属CD',
        )
        // 送信者CDもしくは受信者CDがセッションの担当者CDと一致するレコードのみ取得
        
            ->where('HMメッセージ.受信者CD', $userId)
            ->where('HMメッセージ.HMステータス',1);
        


        // ページネーションとクエリの実行
        $result = $query->orderBy('HMメッセージ.Mメッセージ_ID','DESC')
                  ->paginate(10);

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
                      $do = 'TOP新着メッセージ取得';
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

            // 結果を返す
            return response()->json($result);

        
    }

    public function Contentslist(Request $request)
    {
        \DB::enableQueryLog();
        $query = Contents::query()
            ->whereNull('消去日時');
        $result = $query->orderBy('Mコンテンツ_ID', 'DESC')->paginate(3);
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
                      $do = 'TOPコンテンツ取得';
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
    }


}
