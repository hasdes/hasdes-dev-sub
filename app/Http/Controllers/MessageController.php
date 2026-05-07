<?php

namespace App\Http\Controllers;
use App\Models\Message;
use App\Models\Staff;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

class MessageController extends Controller
{
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        // セッションから担当者CDを取得
        $userId = session('担当者CD');
    
        // 'page' パラメータを除外し、'filter' パラメータを個別に処理
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');
    
        // 'filter' パラメータを取得し、期待する型かどうかをチェック
        $filter = $request->input('filter');
    
        // クエリビルダー作成
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
            ->leftJoin('M所属 as 受信者所属', '受信者.所属CD', '=', '受信者所属.所属CD')
            ->leftJoin('M部門 as 送信者部門', '送信者.所属部門CD', '=', '送信者部門.部門CD')
            ->leftJoin('M部門 as 受信者部門', '受信者.所属部門CD', '=', '受信者部門.部門CD')
            ->select(
                'HMメッセージ.*',
                '送信者.担当者名 as 送信者名',
                '受信者.担当者名 as 受信者名',
                '送信者所属.所属名_社内用 as 送信者所属名_社内用',
                '送信者部門.部門略称名 as 送信者部門略称名',
                '受信者所属.所属名_社内用 as 受信者所属名_社内用',
                '受信者部門.部門略称名 as 受信者部門略称名',
                '送信者.所属部門CD as 送信者所属部門CD',
                '受信者.所属部門CD as 受信者所属部門CD',
                '送信者.所属CD as 送信者所属CD',
                '受信者.所属CD as 受信者所属CD',
            )
            // 送信者CDもしくは受信者CDがセッションの担当者CDと一致するレコードのみ取得
            ->where(function($query) use ($userId) {
                $query->where('HMメッセージ.送信者CD', $userId)
                      ->orWhere('HMメッセージ.受信者CD', $userId);
            });

            $isSearch = !empty($request->input('name')) ||
            $request->filled('section');

            if ($isSearch) {
                $logkind = 2;
                $do = 'メッセージ送受信検索';
                } else {
                $logkind = 1;
                $do = 'メッセージ送受信表示';
            }
    
        // フィルタリング処理
        if (!empty($queryParams['name'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('送信者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%')
                      ->orWhere('受信者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%');
            });
        }
        
        if (!empty($queryParams['section'])) {
            $query->where(function ($query) use ($queryParams) {
                $query->where('送信者.所属CD', $queryParams['section'])
                      ->orWhere('受信者.所属CD', $queryParams['section']);
            });
        }
    
        $sortColumn = $request->input('sortColumn', 'HMメッセージ.送信日時'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'desc'); // 初期表示時のデフォルトソート順

        
        if ($sortColumn === '送信者CD') {
            // 送信者CDでのソート
            $query->orderByRaw("(CASE WHEN HMメッセージ.送信者CD != ? THEN HMメッセージ.送信者CD ELSE HMメッセージ.受信者CD END) $sortOrder", [$userId]);
        } elseif ($sortColumn === '送信者名') {
            // 送信者名でのソート
            $query->orderByRaw("(CASE WHEN HMメッセージ.送信者CD != ? THEN 送信者.担当者名 ELSE 受信者.担当者名 END) $sortOrder", [$userId]);
        } elseif ($sortColumn === '送信者所属名_社内用') {
            // 送信者所属名_社内用でのソート
            $query->orderByRaw("(CASE WHEN HMメッセージ.送信者CD != ? THEN 送信者所属.所属名_社内用 ELSE 受信者所属.所属名_社内用 END) $sortOrder", [$userId]);
        }  else {
            // デフォルトのソート（送信日時など）
            $query->orderBy($sortColumn, $sortOrder);
        }
    
        $result = $query->paginate(17);
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
            $userId = session('担当者CD');
            $messageId = $request->query('key');

            // $messageIdのバリデーション
            if (empty($messageId) || !is_numeric($messageId)) {
                return response()->json(['message' => '無効なメッセージIDです。'], 400);
            }

            $updatequery = Message::where('受信者CD',$userId)
                        ->where('メッセージCD',$messageId);
            $status = 0;
            $updateData = [
                'HMステータス' => $status,
            ];
            $updatequery->update($updateData);

            // 'メッセージCD' に一致するメッセージを全て取得
            $messages = Message::where('メッセージCD', $messageId)->get();

            // メッセージが見つからない場合は404を返す
            if ($messages->isEmpty()) {
                return response()->json(['message' => 'メッセージが見つかりません。'], 404);
            }

            // 送信者CDもしくは受信者CDが$userIdと一致しないものの担当者名を取得
            $otherStaffNames = [];
            foreach ($messages as $message) {
                $otherStaffCd = null;

                if ($message->送信者CD != $userId) {
                    $otherStaffCd = $message->送信者CD;
                } elseif ($message->受信者CD != $userId) {
                    $otherStaffCd = $message->受信者CD;
                }

                if ($otherStaffCd) {
                    $otherStaff = Staff::where('担当者CD', $otherStaffCd)->first();
                    if ($otherStaff) {
                        $otherStaffNames[] = $otherStaff->担当者名;
                    }
                }
            }

            // メッセージを適切な構造で返す
            $result = [
                'data' => $messages,
                'userId' => $userId,  // セッションから取得した担当者CDを追加
                'otherStaffNames' => $otherStaffNames  // $userIdと一致しない担当者名のリスト
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
                $do = 'メッセージ送信詳細表示';
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
                
                return $result;

            } catch (\Exception $e) {
                // 予期せぬエラーが発生した場合に500エラーを返す
                return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
            }
    }


        public function create(Request $request)
        {
            // リクエストのデバッグ
            \DB::enableQueryLog();

            // バリデーション（必須フィールドを確認）
            $request->validate([
                'messagecode' => 'required',
                'sendecode' => 'required',
                'reception' => 'required',
                'Description' => 'required',
            ]);
            $status = 1;
            $sendmessage = Message::create([
                'メッセージCD' => $request->messagecode,
                '送信者CD' => $request->sendecode,
                '受信者CD' => $request->reception,
                'メッセージ' => $request->Description,
                'HMステータス' => $status,
            ]);

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
                $do = 'メッセージ送信';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            return response()->json($sendmessage, 201);
        }

    public function newcreate(Request $request)
    {
        // リクエストのデバッグ
        \DB::enableQueryLog();

        // 担当者リストが配列か確認
        $request->validate([
            'staff' => 'required|array',
            'staff.*.messagecode' => 'required',
            'staff.*.sendecode' => 'required',
            'staff.*.Description' => 'required',
        ]);

        // セッションから受信者CDを取得
        $userId = session('担当者CD');

        // 選択された担当者リストをループ処理して登録
        foreach ($request->staff as $staff) {
            $existingMessage = Message::where(function ($query) use ($userId, $staff) {
                    $query->where('送信者CD', $userId)
                        ->where('受信者CD', $staff['sendecode']);
                })
                ->orWhere(function ($query) use ($userId, $staff) {
                    $query->where('送信者CD', $staff['sendecode'])
                        ->where('受信者CD', $userId);
                })
                ->first();

            if ($existingMessage) {
                // 既存のメッセージが存在する場合、エラーレスポンスを返す
                return response()->json(['error' => 'すでにメッセージが存在します。'], 409);
            }

            // 既存のメッセージがない場合、新しいメッセージを作成
            Message::create([
                'メッセージCD' => $staff['messagecode'],
                '送信者CD' => $userId,
                '受信者CD' => $staff['sendecode'],
                'メッセージ' => $staff['Description'],
                'HMステータス' => 1,
                '送信日時' => now(),
            ]);
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
            $do = 'メッセージ新規追加';
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 4,
                '実行内容' => $do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery, // 生成されたSQL文を保存
            ]);
        } else {
            \Log::error('SQLクエリが取得できませんでした。');
        }

        // 処理終了後にレスポンスを返す
        return response()->json(['message' => '担当者が追加されました。'], 200);
    }


    






}



