<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\DispatchDetail;
use App\Models\DispatchUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;//HDログ

use Illuminate\Support\Facades\Log; //デバック用

class DispatchController extends Controller
{
    //直送配車計画　取得
        public function list(Request $request)
    {

        \DB::enableQueryLog();//HDログ記録

        $start = $request->input('start_date'); // 例: '2025-05-01'
        $end = $request->input('end_date');     // 例: '2025-05-31'

        // start, end が指定されていなければ当月の1日〜末日を使う
        if (!$start || !$end) {
            $start = now()->startOfMonth()->toDateString();
            $end = now()->endOfMonth()->toDateString();
        }

        // 日付を 'Ymd' 形式に変換
        $start = \Carbon\Carbon::parse($start)->format('Ymd'); // '20250501'
        $end = \Carbon\Carbon::parse($end)->format('Ymd');     // '20250531'

        // //デバック用
        // DB::listen(function ($query) {
        //     \Log::debug('SQL:', [
        //         $query->sql,
        //         $query->bindings,
        //         $query->time
        //     ]);
        // });

        // 対象部門CDの一覧
        $validBumonCds = [1, 2, 3, 31, 41, 61];

        // 1. セッションに「部門CD」があれば優先
        $sessionBumonCd = session('部門CD');
        // Log::info($sessionBumonCd);


        if ($sessionBumonCd && in_array($sessionBumonCd, $validBumonCds)) {
            $targetBumonCd = $sessionBumonCd;
        } else {
            // 2. なければ「所属部門CD」からマッピング
            $myCd = session('担当者CD');

            // // 2. M担当者テーブルから所属部門CDを取得
            // $bumonInfo = DB::table('M担当者')
            //     ->where('担当者CD', $myCd)
            //     ->select('所属部門CD')
            //     ->first();
            // $myBumonCd = $bumonInfo ? $bumonInfo->所属部門CD : null;

            // $mappedBumon = null;

            // if ($myBumonCd) {
            //     $mappedBumon = DB::table('M部門')
            //         ->where('担当工場部門CD', $myBumonCd)
            //         ->select('部門CD')
            //         ->first();
            // }

            // $targetBumonCd = $mappedBumon && in_array($mappedBumon->部門CD, $validBumonCds)
            //     ? $mappedBumon->部門CD
            //     : 1; // デフォルト（本社）

            // 3.担当工場部門CDを取得　（M担当者.所属部門CD = M部門.部門CD）
            $mappedBumon = DB::table('M担当者 as t')
                ->leftJoin('M部門 as v', 't.所属部門CD', '=', 'v.部門CD')
                ->select('v.担当工場部門CD')
                ->where('t.担当者CD', '=', $myCd)
                ->first();

            // 4.対象の拠点に当てはまるか確認（当てはまらない場合は本社）
            $targetBumonCd = ($mappedBumon && in_array($mappedBumon->担当工場部門CD, $validBumonCds))
                ? $mappedBumon->担当工場部門CD
                : 1; // デフォルト（本社）

        }

        // メインの出荷予定クエリ
        // $query = Dispatch::query()
        //     ->leftJoin('M得意先', 'D出荷予定.得意先CD', '=', 'M得意先.得意先CD')
        //     ->leftJoin('M部門', 'D出荷予定.工場部門CD', '=', 'M部門.担当工場部門CD')
        //     ->leftJoin('M担当者', 'M担当者.担当者CD', '=', 'D出荷予定.営業担当者CD')
        //     ->select(
        //         'D出荷予定.*',
        //         'M得意先.得意先名',
        //         'M部門.部門名',
        //         'M担当者.担当者名'
        //     )
        //     ->whereBetween('D出荷予定.出荷予定日', [$start, $end])//対象のカレンダー
        //     ->where('M部門.部門CD', $targetBumonCd);//対象の拠点
            // ->where('D出荷予定.出荷形態', '2');//直送


        // $rawData = $query->get();
        // Log::info($rawData);

        $rawData = DB::table('D出荷予定 as ds')
            ->leftJoin('HD出荷詳細 as dh', function ($join) {
                $join->on('ds.出荷予定日', '=', 'dh.出荷予定日')
                    ->on('ds.出荷先CD', '=', 'dh.出荷先CD');
            })

            ->leftJoin('M得意先   as tk', 'tk.得意先CD',        '=', 'ds.得意先CD')
            ->leftJoin('M部門     as bm', 'bm.担当工場部門CD', '=', 'ds.工場部門CD')
            ->leftJoin('M担当者   as tn', 'tn.担当者CD',        '=', 'ds.営業担当者CD')
            ->leftJoin('M商品     as mp', function ($q) {
                $q->on(DB::raw('TRIM(mp.商品CD)'), '=', DB::raw('TRIM(ds.商品CD)'))
                ->on(DB::raw('TRIM(mp.呼び径1)'), '=', DB::raw('TRIM(ds.呼び径1)'))
                ->on(DB::raw('TRIM(mp.呼び径2)'), '=', DB::raw('TRIM(ds.呼び径2)'))
                ->on(DB::raw('TRIM(mp.呼び径3)'), '=', DB::raw('TRIM(ds.呼び径3)'));
            })
            ->selectRaw('
                ds.*,
                dh.車両,
                dh.積み下ろし順,
                dh.その他情報,
                dh.地図リンク,
                tk.得意先名,
                bm.部門名,
                tn.担当者名,
                COALESCE(mp.単重,0) * ds.出荷予定数 as 重量
            ')
            ->whereBetween('ds.出荷予定日', [$start, $end])
            ->where('bm.部門CD', $targetBumonCd)
            // ->where('ds.出荷形態', 2)      // 直送
            ->get();


        // グループ化（出荷予定日 + 出荷先CD）
        $grouped = collect($rawData)->groupBy(function ($item) {
            // return $item['出荷予定日'] . '_' . $item['出荷先CD'];
            return $item->出荷予定日 . '_' . $item->出荷先CD;
        })->map(function ($group) {
            $first = $group[0];
            return array_merge(
                [
                    'D出荷予定_ID' => $first->D出荷予定_ID,
                    '出荷予定日' => $first->出荷予定日,
                    '出荷先CD' => $first->出荷先CD,
                    '出荷先住所1' => $first->出荷先住所1
                ],
                (array)$first, // ← toArray() ではなく (array) キャストの方が確実
                ['items' => $group]
            );
        })->values();

        //HDログ記録******************************************************
        $queries = \DB::getQueryLog();
        $lastQuery = end($queries); // 最後に実行されたクエリを取得

        // 取得したクエリのSQL文とパラメータを変数に格納
        $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
        $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];

        $logkind = 1;
        $do = '直送配車計画表示';

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
        //***************************************************************

        // 部門名をD出荷予定に依存せず取得
        $departmentRecord = DB::table('M部門')
            ->where('部門CD', $targetBumonCd)
            ->select('部門名')
            ->first();

        $departmentName = $departmentRecord ? $departmentRecord->部門名 : '不明';

        return response()->json([
            'shipments' => $grouped,
            'targetBumonCd' => $targetBumonCd, //部門CD
            'departmentName' => $departmentName //部門名
        ]);
    }




    // 拠点一覧取得
    public function getBase()
    {
        // 対象の部門CDリスト
        $targetCodes = [1, 2, 3, 31, 41, 61];

        // データ取得
        $data = DB::table('M部門')
            ->select('部門CD', '部門名')
            ->whereIn('部門CD', $targetCodes)
            ->get();
        return response()->json($data);
    }



    //配車情報更新
    public function edit(Request $request) {
        try {
            \DB::enableQueryLog();

            // 出荷予定（明細）の存在チェック（主にバリデーション目的）
            $dispatch = Dispatch::where('D出荷予定_ID', $request->input('D出荷予定_ID'))->first();
            if (!$dispatch) {
                return response()->json(['message' => '該当する出荷予定が見つかりません'], 404);
            }

            // 出荷詳細（共通情報）の取得 or 作成
            $detail = DispatchDetail::where('出荷予定日', $dispatch->出荷予定日)
                                    ->where('出荷先CD', $dispatch->出荷先CD)
                                    ->first();

            $updateData = [
                '出荷予定日' => $dispatch->出荷予定日,
                '出荷先CD' => $dispatch->出荷先CD,
                '車両' => $request->input('車両'),
                '積み下ろし順' => $request->input('積み下ろし順'),
                'その他情報' => $request->input('その他情報'),
                '地図リンク' => $request->input('地図リンク'),
                '更新日' => now()
            ];

            if ($detail) {
                $detail->update($updateData); // 更新
            } else {
                DispatchDetail::create($updateData); // 新規作成
            }

            // ログ用クエリ取得
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries);

            if ($lastQuery && isset($lastQuery['query'])) {
                foreach ($lastQuery['bindings'] ?? [] as $binding) {
                    $lastQuery['query'] = preg_replace('/\?/', is_numeric($binding) ? $binding : "'$binding'", $lastQuery['query'], 1);
                }

                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,
                    '実行内容' => '直送配車計画編集',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $lastQuery['query'],
                ]);
            }

            return response()->json(['message' => '保存成功しました'], 200);
        } catch (\Exception $e) {
            \Log::error('配車情報更新エラー: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



}
