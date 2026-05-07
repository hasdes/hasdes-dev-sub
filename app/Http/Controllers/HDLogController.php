<?php

namespace App\Http\Controllers;
use App\Models\HDLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class HDLogController extends Controller
{
    public function list(Request $request)
    {
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');


        // 基本クエリの作成
        $query = HDLog::query()
            ->leftjoin('M担当者','M担当者.担当者CD','=','HDログ.担当者CD');

        if ($request->filled('startdate') && $request->filled('enddate')) {
            // startdateとenddateの両方がある場合
            $query->whereBetween('登録日時', [
                $request->input('startdate'),
                Carbon::parse($request->input('enddate'))->endOfDay()
            ]);
        } elseif ($request->filled('startdate')) {
            // startdateのみがある場合
            $query->where('登録日時', '>=', $request->input('startdate'));
        } elseif ($request->filled('enddate')) {
            // enddateのみがある場合
            $query->where('登録日時', '<=', $request->input('enddate'));
        }
        // フィルタ条件に基づく検索
        if (!empty($queryParams['staffcd'])) {
            $query->where('M担当者.担当者CD', $queryParams['staffcd']);
        }

        if (!empty($queryParams['staffname'])) {
            $query->where('M担当者.担当者名', 'LIKE', '%' . $queryParams['staffname'] . '%');
        }

        if (isset($queryParams['logkind'])) {
            $query->where('ログ種別', '=', $queryParams['logkind']);
        }

        if (isset($queryParams['execution'])) {
            $query->where('実行内容', 'LIKE', '%' . $queryParams['execution'] . '%');
        }
        
        if (isset($queryParams['sqlkind'])) {
            $query->where('SQL種別', '=', $queryParams['sqlkind']);
        }
        
        if (isset($queryParams['erorr'])) {
            $query->where('エラー', '=', $queryParams['erorr']);
        }

        // ソート条件の処理
        $sortColumn = $request->input('sortColumn', '登録日時'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        
        $sortColumnMap = [
            '担当者CD' => 'M担当者.担当者CD',
            '登録日時' => 'HDログ.登録日時',
            // 他のソート対象カラムがあればここに追加
        ];
    
        // マッピングを使ってソート処理
        $query->orderBy($sortColumnMap[$sortColumn] ?? $sortColumn, $sortOrder);
    
        

        // データのページネーション
        return $query->paginate(100);
    }

    public function detail(Request $request)
    {
        try {
            // URLのクエリパラメータ 'key' から顧客IDを取得
            $LOGId = $request->query('key');

            // $customerIdのバリデーション
            if (empty($LOGId) || !is_numeric($LOGId)) {
                return response()->json(['message' => '無効な顧客IDです。'], 400);
            }

            // 'M得意先_ID' に一致する顧客情報を取得
            $log = HDLog::where('HDログID', $LOGId)->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$log) {
                return response()->json(['message' => '顧客情報が見つかりません。'], 404);
            }

            // 顧客情報を適切な構造で返す
            return response()->json(['data' => $log]);

        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }

    public function exportCsv(Request $request)
    {
        $queryParams = $request->except('filter', 'sortColumn', 'sortOrder');
    
        // 基本クエリの作成
        $query = HDLog::query()
            ->leftJoin('M担当者', 'M担当者.担当者CD', '=', 'HDログ.担当者CD');
    
        if ($request->filled('startdate') && $request->filled('enddate')) {
            $query->whereBetween('登録日時', [
                $request->input('startdate'),
                Carbon::parse($request->input('enddate'))->endOfDay()
            ]);
        } elseif ($request->filled('startdate')) {
            $query->where('登録日時', '>=', $request->input('startdate'));
        } elseif ($request->filled('enddate')) {
            $query->where('登録日時', '<=', $request->input('enddate'));
        }
    
        if (!empty($queryParams['staffcd'])) {
            $query->where('M担当者.担当者CD', $queryParams['staffcd']);
        }
    
        if (!empty($queryParams['staffname'])) {
            $query->where('M担当者.担当者名', 'LIKE', '%' . $queryParams['staffname'] . '%');
        }
    
        if (!empty($queryParams['logkind'])) {
            $query->where('ログ種別', '=', $queryParams['logkind']);
        }
    
        if (!empty($queryParams['sqlkind'])) {
            $query->where('SQL種別', '=', $queryParams['sqlkind']);
        }
    
        if (!empty($queryParams['erorr'])) {
            $query->where('エラー', '=', $queryParams['erorr']);
        }
    
        $sortColumn = $request->input('sortColumn', '登録日時');
        $sortOrder = $request->input('sortOrder', 'asc');
    
        $sortColumnMap = [
            '担当者CD' => 'M担当者.担当者CD',
            '登録日時' => 'HDログ.登録日時',
        ];
    
        $query->orderBy($sortColumnMap[$sortColumn] ?? $sortColumn, $sortOrder);
    
        // データの取得
        $data = $query->get();
    
        // CSVのヘッダー行
        $csvData = [['担当者CD', '担当者名', '登録日時', 'ログ種別','実行内容', 'SQL発行', 'エラー','SQL文','登録日時']];
    
        // CSVデータ行
        foreach ($data as $row) {
            // ログ種別の変換
            $logTypes = [
                0 => '認証ログ',
                1 => 'イベントログ',
                2 => '操作ログ',
                3 => '変更・更新ログ',
                4 => '新規追加'
            ];
            $logType = $logTypes[$row->ログ種別] ?? '不明';
        
            // SQL種別の変換
            $sqlTypes = [
                0 => 'なし',
                1 => '有り'
            ];
            $sqlType = $sqlTypes[$row->SQL種別] ?? '不明';
    
            // エラー種別の変換
            $errorTypes = [
                0 => 'なし',
                1 => '有り'
            ];
            $errorType = $errorTypes[$row->エラー] ?? '不明';
        
            // CSVデータに変換済みの値を追加
            $csvData[] = [
                $row->担当者CD,
                $row->担当者名,
                $row->登録日時,
                $logType,
                $row->実行内容,
                $sqlType,
                $errorType,
                $row->SQL文,
                $row->登録日時,
            ];
        }
    
        $filename = 'HDLog.csv';
        $handle = fopen('php://memory', 'r+');
        foreach ($csvData as $line) {
            // Shift_JISエンコーディングに変換
            mb_convert_variables('SJIS', 'UTF-8', $line);
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csvOutput = stream_get_contents($handle);
        fclose($handle);
    
        return Response::make($csvOutput, 200, [
            'Content-Type' => 'text/csv; charset=Shift_JIS',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
    

    public function create(Request $request)
    {
        $request->validate([
            '実行内容' => 'required|string',
        ]);

        HDLog::create([
            '担当者CD' => session('担当者CD'),
            'ログ種別' => 1,
            '実行内容' => $request->input('実行内容'),
            'SQL種別' => 0,
            'エラー' => 0,
        ]);

        return response()->json(['message' => 'ログが保存されました'], 201);
    }


    //メニュー　社内ヘルプデスク押下
    public function logHelpdesk(Request $request)
    {         
        $do = '社内ヘルプデスク押下';
        HDLog::create([
            '担当者CD' => session('担当者CD'),
            'ログ種別' => 1,
            '実行内容' => $do,
            'SQL種別' => 0,
            'エラー' => 0,
        ]);

        return response()->json(['message' => 'ログ記録完了']);
    }

    
}
