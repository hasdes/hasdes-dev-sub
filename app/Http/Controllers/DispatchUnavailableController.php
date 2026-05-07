<?php

namespace App\Http\Controllers;

use App\Models\DispatchUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\DB;
use App\Models\HDLog;//HDログ

use Illuminate\Support\Facades\Log; //デバック用

class DispatchUnavailableController extends Controller
{

    // 配車不可取得
    public function list(Request $request)
    {
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

            $targetBumonCd = ($mappedBumon && in_array($mappedBumon->担当工場部門CD, $validBumonCds))
                ? $mappedBumon->担当工場部門CD
                : 1; // デフォルト（本社）

        }


        // 2.期間の取得
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

        // データ取得
        $data = DB::table('HD配車不可')
            ->select('HD配車不可_ID','出荷予定日', '配車不可')
            ->where('工場部門CD', $targetBumonCd)//対象の拠点
            ->where('配車不可', 1)//配車不可ステータス
            ->whereBetween('出荷予定日', [$start, $end])//対象のカレンダー
            ->get();
        return response()->json($data);
    }


    // 配車不可新規登録処理（配車不可にする）
    public function add(Request $request)
    {
        try {
            // リクエストデータのバリデーション
            $request->validate([
                'date' => 'required|string|size:8', // YYYYMMDD形式
            ]);

            // セッションから部門CDを取得
            $validBumonCds = [1, 2, 3, 31, 41, 61];
            $sessionBumonCd = session('部門CD');

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

                $targetBumonCd = ($mappedBumon && in_array($mappedBumon->担当工場部門CD, $validBumonCds))
                ? $mappedBumon->担当工場部門CD
                : 1; // デフォルト（本社）

            }

            // 既に同じ条件で配車不可が登録されているかチェック
            $existingRecord = DispatchUnavailable::where('工場部門CD', $targetBumonCd)
                ->where('出荷予定日', $request->date)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'この日付は既に配車不可に設定されています。'
                ]);
            }

            // 配車不可レコードを新規作成
            DispatchUnavailable::create([
                '工場部門CD' => $targetBumonCd,
                '出荷予定日' => $request->date,
                '配車不可' => 1
            ]);

            // HDログ記録
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 2, // 更新系
                '実行内容' => '配車不可登録',
                'SQL種別' => 2, // INSERT
                'エラー' => 0,
                'SQL文' => "INSERT INTO HD配車不可 (工場部門CD, 出荷予定日, 配車不可) VALUES ({$targetBumonCd}, '{$request->date}', 1)",
            ]);

            return response()->json([
                'success' => true,
                'message' => '配車不可に設定されました。'
            ]);

        } catch (\Exception $e) {
            Log::error('配車不可登録エラー: ' . $e->getMessage());         
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // 配車不可削除処理（配車不可→配車可にする）
    public function delete(Request $request)
    {
        try {

            // リクエストデータのバリデーション
            $request->validate([
                'id' => 'required|integer',
            ]);

            // 該当レコード取得
            $record = DispatchUnavailable::find($request->id);

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => '指定されたデータが見つかりません。'
                ]);
            }

            // 削除前のデータ取得（ログ用）
            $出荷予定日 = $record->出荷予定日;
            $工場部門CD = $record->工場部門CD;

            // レコード削除
            $record->delete();

            // HDログ記録
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 2, // 更新系
                '実行内容' => '配車不可削除（物理）',
                'SQL種別' => 4, // DELETE
                'エラー' => 0,
                'SQL文' => "DELETE FROM HD配車不可 WHERE HD配車不可_ID = {$request->id}"            
            ]);

            return response()->json([
                'success' => true,
                'message' => '配車不可を解除しました。'
            ]);

        } catch (\Exception $e) {
            Log::error('配車不可解除エラー: ' . $e->getMessage());         
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




}
