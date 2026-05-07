<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserFlg;//追加
use App\Models\Message;
use App\Models\Stock;
use App\Models\Dispatch;//D出荷予定
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\HDLog;

class UsersController extends Controller
{
    public function list()
    {
        $userId = session('担当者CD'); // セッションから担当者CDを取得
        
        // $query = User::query()
        //     ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
        //     ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
        //     ->leftJoin('HM担当者フラグ', 'M担当者.担当者CD', '=', 'HM担当者フラグ.担当者CD')//追加
        //     ->select('M担当者.*','HM担当者フラグ.*', 'M所属.所属名_社内用', 'M部門.部門略称名')
        //     ->where('M担当者.担当者CD', '=', $userId);
        //     $result = $query->paginate(1);

        // 対象のフラグカラム一覧
        $flagColumns = [
            'PASSWORD',
            'ホーム',
            'メッセージ送受信',
            '在庫表示',
            '商品表示',
            '直送配車計画',
            '配車可_不可',
            '販売管理',
            '集荷処理',
            '新規登録',
            '編集',
            '社内ヘルプデスク',
            '従業員マスタメンテ',
            '顧客企業マスタメンテ',
            'メッセージ管理',
            'ログ管理',
            'ユーザー設定',
            '従業員区分',
            'ロックカウント',
        ];

        // サブクエリの select に使う配列を動的生成
        $subSelects = ['担当者CD'];
        foreach ($flagColumns as $col) {
            $subSelects[] = DB::raw("MAX(`{$col}`) as `{$col}`");
        }

        // サブクエリ作成
        $subFlags = DB::table('HM担当者フラグ')
            ->select($subSelects)
            ->groupBy('担当者CD');

        // メインクエリ作成
        $mainSelects = array_merge(
            ['M担当者.*', 'M所属.所属名_社内用', 'M部門.部門略称名'],
            array_map(fn($col) => "フラグ集約.{$col}", $flagColumns)
        );

        $query = User::query()
            ->leftJoin('M所属', 'M担当者.所属CD', '=', 'M所属.所属CD')
            ->leftJoin('M部門', 'M担当者.所属部門CD', '=', 'M部門.部門CD')
            ->leftJoinSub($subFlags, 'フラグ集約', function ($join) {
                $join->on('M担当者.担当者CD', '=', 'フラグ集約.担当者CD');
            })
            ->select($mainSelects)
            ->where('M担当者.担当者CD', '=', $userId);

        $result = $query->paginate(1);

        return $result;
    }

    //更新時間
    public function listtime()
    {
        try {
            // Stockの最新更新日時を取得
            // $timequery = Stock::query()
            //     ->orderBy('更新日時', 'desc')
            //     ->select('更新日時')
            //     ->first();

            // C商品月間の最新更新日時
            $stockTime = Stock::max('更新日時');

            // D出荷予定の最新更新日時
            $dispatchTime = Dispatch::max('更新日');

            // nullを避けつつ最大値を比較
            $timequery = collect([$stockTime, $dispatchTime])
                ->filter() // nullを除外
                ->max();

            // データを返却
            return response()->json($timequery);
            // return response()->json(['data' => $timequery]);
            
        } catch (\Exception $e) {
            \Log::error('エラーが発生しました:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    //メッセージ
    public function listcount()
    {
        $userId = session('担当者CD'); // セッションから担当者コードを取得
        if (!session()->has('担当者CD') || empty($userId)) {
            // セッションがない、または空の場合 401エラーを返す
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        $count = Message::where('受信者CD', $userId)
            ->where('HMステータス', 1)
            ->count();
        return response()->json(['data' => $count]); // JSONでデータを返す
    }

    //パスワード更新
    public function update(Request $request)
    {
        \DB::enableQueryLog();
        // バリデーション
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => [
            'required',
            'confirmed',
            'min:12',
            'max:16',
            'regex:/^(?=.*[a-z])(?=.*[0-9]).+$/',
            ],
        ]);

        if ($validator->fails()) {
            // Log::debug('バリデーションエラー:', $validator->errors()->toArray());//

            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 1,
                '実行内容' => 'パスワード変更失敗(bd)',
                'SQL種別' => 0,
                'エラー' => 0,
            ]);
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        // セッションから担当者CDを取得
        $userId = session('担当者CD');

        // 担当者CDに対応するユーザーを取得
        // $user = User::where('担当者CD', $userId)->first();
        $user = UserFlg::where('担当者CD', $userId)->first(); // 7/25修正

        // ユーザーが見つかった場合
        if ($user) {
            // 現在のパスワードが一致しているか確認
            if (!Hash::check($request->input('current_password'), $user->PASSWORD)) {
                // Log::debug('パスワード不一致: 入力=' . $request->input('current_password') . ', DB=' . $user->PASSWORD);//

                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => 'パスワード変更失敗(不一致)',
                    'SQL種別' => 0,
                    'エラー' => 0,
                ]);
                return response()->json(['success' => false, 'message' => '現在のパスワードが一致しません。'], 400);
            }

            // Log::debug('パスワード更新前: ' . $user->PASSWORD);//

            // パスワードの更新
            $user->PASSWORD = Hash::make($request->input('password'));
            $user->save();

            // Log::debug('パスワード更新後: ' . $user->PASSWORD);//

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
                    'ログ種別' => 3,
                    '実行内容' => 'パスワード変更',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            }


            return response()->json(['success' => true, 'message' => 'パスワードが正常に更新されました。']);
        } else {
            return response()->json(['success' => false, 'message' => 'ユーザーが見つかりませんでした。'], 404);
        }
    }

    public function getUserId()
    {
        $userId = session('担当者CD');
        return response()->json(['userId' => $userId]);
    }
    
    //ホームの権限取得
    public function getHomePermission(Request $request)
    {
        $userId = $request->input('担当者CD');

        $user = UserFlg::where('担当者CD', $userId)->first();
        if ($user) {
            return response()->json(['ホーム' => $user->ホーム]);
        }
    }


}
