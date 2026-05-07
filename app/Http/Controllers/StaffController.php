<?php
namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffFlg;//追加
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;

class StaffController extends Controller
{
    //従業員一覧
    public function list(Request $request)
    {

        \DB::enableQueryLog();
        // 'page' パラメータを除外し、'filter' パラメータを個別に処理
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // 'filter' パラメータを取得し、期待する型かどうかをチェック
        $filter = $request->input('filter');
        if (is_scalar($filter)) {
            $queryParams['filter'] = $filter;
        }

        // // クエリビルダー作成
        // $query = Staff::query()
        //     ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
        //     ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
        //     ->leftJoin('HM担当者フラグ', 'M担当者.担当者CD', '=', 'HM担当者フラグ.担当者CD')//追加
        //     ->select('M担当者.*', 'HM担当者フラグ.*', 'M所属.所属名_社内用', 'M部門.部門略称名')//HM担当者フラグ追加
        //     ->where('M担当者.担当者CD', '!=', 'HASDES');

$flags = DB::table('HM担当者フラグ as f')
    ->select(
        'f.担当者CD', 
        DB::raw('MAX(f.従業員区分) as 従業員区分'),
        DB::raw('MAX(f.ロックカウント) as ロックカウント') 
    ) // ★単一に集約
    
    ->groupBy('f.担当者CD');

$query = Staff::query()
    ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
    ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
    ->leftJoinSub($flags, '最新区分', function ($join) {
        $join->on('M担当者.担当者CD', '=', '最新区分.担当者CD');
    })
    ->select(
        'M担当者.*',
        'M所属.所属名_社内用',
        'M部門.部門略称名',
        DB::raw('CAST(最新区分.従業員区分 AS UNSIGNED) as 従業員区分'), // ★フロント用に数値で返す
        DB::raw('CAST(最新区分.ロックカウント AS UNSIGNED) as ロックカウント') // ★追加
    )
    ->where('M担当者.担当者CD', '!=', 'HASDES');


        $isSearch = !empty($request->input('filter')) ||
        // $request->filled('name')||
        $request->filled('name')||
        $request->filled('department')||
        $request->filled('section');

        if ($isSearch) {
            $logkind = 2;
            $do = '従業員マスターデータ検索';
            } else {
            $logkind = 1;
            $do = '従業員マスターデータ表示';
        }

        if (!empty($queryParams['filter'])) {
            $query->where('M担当者.担当者CD', $queryParams['filter']);
        }

        if (!empty($queryParams['name'])) {
            $query->where('M担当者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%');
        }

        if (!empty($queryParams['department'])) {
            $query->where('M担当者.所属部門CD', '=',$queryParams['department']);
        }

        if (!empty($queryParams['section'])) {
            $query->where('M担当者.所属CD', '=',$queryParams['section']);
        }

        // if (isset($queryParams['userType'])) {
        //     $query->where('HM担当者フラグ.従業員区分', '=', $queryParams['userType']);
        // }
        if (isset($queryParams['userType'])) {
            $query->whereRaw('CAST(最新区分.従業員区分 AS UNSIGNED) = ?', [$queryParams['userType']]);
        }

        
        $sortColumn = $request->input('sortColumn', '担当者CD'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 担当者CDを数値としてキャストしてソートする
        if ($sortColumn === '担当者CD' || $sortColumn === 'M担当者.担当者CD') {
            // $query->orderByRaw('CAST(担当者CD AS UNSIGNED) ' . $sortOrder);
            $query->orderByRaw('CAST(`M担当者`.`担当者CD` AS UNSIGNED) ' . $sortOrder);//修正
        } else {
            $query->orderBy('M担当者.' . $sortColumn, $sortOrder);
        }

        $sortColumn = $request->input('sortColumn', 'M得意先_ID'); // 初期表示時のデフォルトソートカラム
        $sortOrder  = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 得意先CDを数値としてキャストしてソートする
        if ($sortColumn === '得意先CD') {
            $query->orderByRaw('CAST(得意先CD AS UNSIGNED) ' . $sortOrder);
        } else {
            $query->orderBy('M担当者.' . $sortColumn, $sortOrder);
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
    
    //権限更新
    public function updatePermissions(Request $request)
    {
        \DB::enableQueryLog();
        // 担当者CDを数値から文字列へキャスト（リクエストが文字列として処理される場合）
        $staffCD = (string) $request->input('担当者CD');
        $permissions = $request->input('権限情報');
    
        // すべての値を文字列 '1' または '0' として扱う
        $updateData = [
            'ホーム' => (string) $permissions['ホーム'],
            'メッセージ送受信' => (string) $permissions['メッセージ送受信'],
            '在庫表示' => (string) $permissions['在庫表示'],
            '商品表示' => (string) $permissions['商品表示'],
            '直送配車計画' => (string) $permissions['直送配車計画'],
            '配車可_不可' => (string) $permissions['配車可_不可'],
            '販売管理' => (string) $permissions['販売管理'],
            '集荷処理' => (string) $permissions['集荷処理'],
            '新規登録' => (string) $permissions['新規登録'],
            '編集' => (string) $permissions['編集'],
            '社内ヘルプデスク' => (string) $permissions['社内ヘルプデスク'],
            '従業員マスタメンテ' => (string) $permissions['従業員マスタメンテ'],
            '顧客企業マスタメンテ' => (string) $permissions['顧客企業マスタメンテ'],
            'メッセージ管理' => (string) $permissions['メッセージ管理'],
            'ログ管理' => (string) $permissions['ログ管理'],
            'ユーザー設定' => (string) $permissions['ユーザー設定'],
        ];
    
        // Eloquentを使ったアップデート処理
        // $query = Staff::where('担当者CD', $staffCD);
        $query = StaffFlg::where('担当者CD', $staffCD);//修正
        
        // 更新処理を実行
        $query->update($updateData);
        

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
            $do = '従業員権限変更';
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
    
        return response()->json(['message' => '権限が正常に更新されました'], 200);
    }

    //パスワード初期化
    public function initializePassword(Request $request)
    {
        \DB::enableQueryLog();
        // リクエストで送信された担当者CDを取得
        $staffCD = $request->input('staffCD');

        // 担当者CDに対応するユーザーを取得
        // $user = Staff::where('担当者CD', $staffCD)->first();
        $user = StaffFlg::where('担当者CD', $staffCD)->first();

        if ($user) {
            // パスワードを "pass20240705" に初期化
            $user->PASSWORD = Hash::make('pass20240705');
            $user->save();
            
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
                $do = '従業員パスワード初期化変更';
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

            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'ユーザーが見つかりませんでした。']);
        }
    }

    //権限表示
    public function getPermissions($staffCD)
    {
        // 指定された担当者CDの権限情報を取得
        // $staff = Staff::where('担当者CD', $staffCD)->first();
        // $staff = StaffFlg::where('担当者CD', $staffCD)->first();//修正
        
        $staff = \DB::table('HM担当者フラグ')
        ->leftJoin('M担当者', 'M担当者.担当者CD', '=', 'HM担当者フラグ.担当者CD')
        ->select(
            'HM担当者フラグ.*',       // 担当者フラグテーブルは全カラム
            'M担当者.担当者名'       // 担当者テーブルは担当者名のみ
        )
        ->where('HM担当者フラグ.担当者CD', $staffCD) // 条件が必要なら
        ->first();

        if (!$staff) {
            return response()->json(['error' => '担当者が見つかりません'], 404);
        }

        // 権限情報をまとめてレスポンス
        $permissions = [
            'ホーム' => $staff->ホーム,
            'メッセージ送受信' => $staff->メッセージ送受信,
            '在庫表示' => $staff->在庫表示,
            '商品表示' => $staff->商品表示,
            '直送配車計画' => $staff->直送配車計画,
            '配車可_不可' => $staff->配車可_不可,
            '販売管理' => $staff->販売管理,
            '集荷処理' => $staff->集荷処理,
            '新規登録' => $staff->新規登録,
            '編集' => $staff->編集,
            '社内ヘルプデスク' => $staff->社内ヘルプデスク,
            '従業員マスタメンテ' => $staff->従業員マスタメンテ,
            '顧客企業マスタメンテ' => $staff->顧客企業マスタメンテ,
            'メッセージ管理' => $staff->メッセージ管理,
            'ログ管理' => $staff->ログ管理,
            'ユーザー設定' => $staff->ユーザー設定,
            '担当者名' => $staff->担当者名,
        ];

        return response()->json($permissions);
    }

    //従業員区分表示
    public function getjyugyoinkubun($staffCD)
    {
        // 指定された担当者CDの権限情報を取得
        // $staff = Staff::where('担当者CD', $staffCD)->first();
        // $staff = StaffFlg::where('担当者CD', $staffCD)->first();//修正

        $staff = \DB::table('HM担当者フラグ')
        ->leftJoin('M担当者', 'M担当者.担当者CD', '=', 'HM担当者フラグ.担当者CD')
        ->select(
            'HM担当者フラグ.*',
            'M担当者.担当者名'
        )
        ->where('HM担当者フラグ.担当者CD', $staffCD)
        ->first();


        if (!$staff) {
            return response()->json(['error' => '担当者が見つかりません'], 404);
        }

        $permissions = [
            '従業員区分' => $staff->従業員区分, // カラム名が「従業員区分」であることを確認
            '担当者名' => $staff->担当者名,
        ];

        return response()->json($permissions);
    }

    //従業員区分更新
    public function updatejyugyoinkubun(Request $request)
    {
        \DB::enableQueryLog();
        $staffCD = (string) $request->input('担当者CD');
        $permissions = $request->input('権限情報');

        $updateData = [
            '従業員区分' => (string) $permissions['従業員区分'],
        ];

        // クエリビルダを使ったアップデート処理
        // Staff::where('担当者CD', $staffCD)->update($updateData);
        StaffFlg::where('担当者CD', $staffCD)->update($updateData);// 7/25修正

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
            $do = '従業員区分変更';
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

        return response()->json(['message' => '従業員区分が正常に更新されました'], 200);
    }


    //解除更新
    public function rockstatuschenge(Request $request)
    {
        \DB::enableQueryLog();
        // リクエストで送信された担当者CDを取得
        $staffCD = $request->input('staffCD');

        // 担当者CDに対応するユーザーを取得
        // $user = Staff::where('担当者CD', $staffCD)->first();
        $user = StaffFlg::where('担当者CD', $staffCD)->first();//修正

        if ($user) {
            $status = 0;
            $user->ロックカウント = $status;
            $user->save();

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
                $do = '従業員パスワードロック解除';
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

            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'ユーザーが見つかりませんでした。']);
        }
    }

    //メッセージ送受信　検索
    public function listformess(Request $request)
    {

        // 'page' パラメータを除外し、'filter' パラメータを個別に処理
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // 'filter' パラメータを取得し、期待する型かどうかをチェック
        $filter = $request->input('filter');
        if (is_scalar($filter)) {
            $queryParams['filter'] = $filter;
        }

        // クエリビルダー作成
        // $query = Staff::query()
        //     ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
        //     ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
        //     ->leftJoin('HM担当者フラグ', 'M担当者.担当者CD', '=', 'HM担当者フラグ.担当者CD')//追加
        //     ->select('M担当者.*', 'HM担当者フラグ.*', 'M所属.所属名_社内用', 'M部門.部門略称名')
        //     ->where('M担当者.担当者CD', '!=', 'HASDES')
        //     ->where('HM担当者フラグ.従業員区分', '!=', '3')
        //     ->where('HM担当者フラグ.従業員区分', '!=', '2');

        $flags = DB::table('HM担当者フラグ')
            ->select('担当者CD')
            ->groupBy('担当者CD');

        $query = Staff::query()
            ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
            ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
            ->leftJoinSub($flags, 'フラグ集約', function($join) {
                $join->on('M担当者.担当者CD', '=', 'フラグ集約.担当者CD');
            })
            ->select(
                'M担当者.*',
                'M所属.所属名_社内用',
                'M部門.部門略称名'
            )
            ->where('M担当者.担当者CD', '!=', 'HASDES')
            ->where('HM担当者フラグ.従業員区分', '!=', '3')
            ->where('HM担当者フラグ.従業員区分', '!=', '2');

        if (!empty($queryParams['filter'])) {
            $query->where('M担当者.担当者CD', $queryParams['filter']);
        }

        if (!empty($queryParams['name'])) {
            $query->where('M担当者.担当者名', 'LIKE', '%' . $queryParams['name'] . '%');
        }

        if (!empty($queryParams['department'])) {
            $query->where('M担当者.所属部門CD', '=',$queryParams['department']);
        }

        if (!empty($queryParams['section'])) {
            $query->where('M担当者.所属CD', '=',$queryParams['section']);
        }

        $sortColumn = $request->input('sortColumn', '担当者CD'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 担当者CDを数値としてキャストしてソートする
        if ($sortColumn === '担当者CD') {
            // $query->orderByRaw('CAST(担当者CD AS UNSIGNED) ' . $sortOrder);
            $query->orderByRaw('CAST(`M担当者`.`担当者CD` AS UNSIGNED) ' . $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }
        return $query->paginate(200);

    }

    


}

