<?php
namespace App\Http\Controllers;
use App\Models\OrderSlipOCR;
use App\Models\ConverOrderSlipOCR;
use App\Models\HDLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; //デバック用
use Illuminate\Support\Facades\Validator; //バリデーション
use Carbon\Carbon; //日付判定
use App\Services\ShippingDateService; //受付締め切りによる納期、出荷日判定
use App\Services\Ocr\OCR_OrderService;//OCR読み取り



class OrderSlipOCRController extends Controller
{
    //受注入力一覧
    public function list(Request $request) {

        \DB::enableQueryLog();
        // 'page' パラメータを除外し、'filter' パラメータを個別に処理
        $queryParams = $request->except('page', 'sortColumn', 'sortOrder');

        $query = OrderSlipOCR::query();
        $isSearch = !empty($request->input('filter')) || $request->filled('sales_office') || $request->filled('status');

        if ($isSearch) {
            $logkind = 2;
            $do = '注文書検索';
        } else {
            $logkind = 1;
            $do = '注文書表示';
        }

        //営業所
        if (!empty($queryParams['sales_office'])) {
            // Log::info('営業所:'. $queryParams['sales_office']);

            $query->where('管轄部門CD', $queryParams['sales_office']);
            $targetBumonCd = $queryParams['sales_office'];
        }else{

            $myBumonCd = session('所属部門CD'); // 所属部門CD
            $allowedBumonCd = [10, 20, 30, 40, 60, 70]; // 許可する部門CD
            $targetBumonCd = 10; // デフォルト値

            if ($myBumonCd) {
                $mappedBumon = DB::table('M部門')
                    ->where('部門CD', $myBumonCd)
                    ->select('部門CD')
                    ->first();

                if ($mappedBumon && !empty($mappedBumon->部門CD) && in_array((int)$mappedBumon->部門CD, $allowedBumonCd, true)) {
                    $targetBumonCd = (int)$mappedBumon->部門CD;
                }
            }

            $query->where('管轄部門CD', $targetBumonCd ? $targetBumonCd : 10);
        }

        //チェック状態
        if (array_key_exists('status', $queryParams) && $queryParams['status'] !== null && $queryParams['status'] !== '') {

            if ($queryParams['status'] == 2) {
                // 何もしない（全て）
            } elseif ($queryParams['status'] == 1) {
                // Log::info('ステータス: ' . $queryParams['status']);
                $query->whereNotNull('OCR受注伝票NO'); //確認済み
            }else{
                $query->whereNull('OCR受注伝票NO');// 未確認
            }
        } else {
                $query->whereNull('OCR受注伝票NO');//デフォルト
        }

        $sortColumn = $request->input('sortColumn', '登録日時'); // 初期表示時のデフォルトソートカラム
        $sortOrder  = $request->input('sortOrder', 'desc');     // デフォルトはdesc
        if ($sortOrder === 'asc') {
            $query->orderBy($sortColumn, 'asc');
        } else {
            $query->orderBy($sortColumn, 'desc');
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

        return response()->json([
            'result' => $result,
            'targetBumonCd' => $targetBumonCd //管轄部門CD
        ]);

    }


    //選択したOCR受注データ
        public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' からHD受注伝票_OCR_IDを取得
            $ocrId = $request->query('key');

            // $ocrIdのバリデーション
            if (empty($ocrId) || !is_numeric($ocrId)) {
                return response()->json(['message' => '無効なHD受注伝票_OCR_IDです。'], 400);
            }

            // 'HD受注伝票_OCR_ID' に一致する顧客情報を取得
            $data = OrderSlipOCR::where('HD受注伝票_OCR_ID', $ocrId)->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$data) {
                return response()->json(['message' => 'データが見つかりません。'], 404);
            }

            // -----------------------------------
            // Yasumiを使った締切チェック＆翌営業日反映
            // -----------------------------------
            // OCR受注伝票NOがないときだけ、出荷日・納期として反映
            if (empty($data['OCR受注伝票NO'])) {
                
                $service = new ShippingDateService();

                //ヘッダ納期（出荷日）
                $shippingDate = $service->adjustShippingDate($data['ヘッダ希望納期'] ?? '', $data['管轄部門CD'] ?? 0);
                $dueDate = $data['送り状印字内容'] ?? null;

                // 出荷日の翌日が土日祝の場合、翌営業日に調整する（条件：土日着日指定ではない、送り状印字内容がnullではない、送り状印字内容がYYYYMMDDではない）
                if (empty($data['土日着日指定']) && !empty($data['送り状印字内容']) && !preg_match('/^\d{8}$/', $data['送り状印字内容'])) {
                
                    // 送り状印字内容（納期）は、出荷日の翌営業日（土日祝以外）
                    try {
                        $dueDate = $service->getNextBusinessDay(Carbon::createFromFormat('Ymd', $shippingDate));
                    } catch (\Exception $e) {
                        //$data['送り状印字内容']がYmdフォーマットじゃなければそのまま表示
                    }
                }

                $data['ヘッダ希望納期'] = $shippingDate;//出荷日

                // try {
                //     // いろんなフォーマットを許容してパース
                //     $date = Carbon::parse($dueDate);
                //     // m/d AM必着 に変換
                //     $data['送り状印字内容'] = $date->format('n/j') . ' AM必着';

                // } catch (\Exception $e) {
                //     // パースできなかった場合はそのまま or 空にする
                //     $data['送り状印字内容'] = $dueDate;
                // }
                
                if (!empty($dueDate) && preg_match('/^\d{8}$/', $dueDate)) {
                    try {
                        // いろんなフォーマットを許容してパース
                        $date = Carbon::parse($dueDate);
                        // m/d AM必着 に変換
                        $data['送り状印字内容'] = $date->format('n/j') . ' AM必着';
                    } catch (\Exception $e) {
                        // パースできなかった場合はそのまま or 空にする
                        $data['送り状印字内容'] = $dueDate;
                    }
                } else {
                    // 空 or YYYYMMDD はそのまま
                    $data['送り状印字内容'] = $dueDate;
                }

            }

            // -----------------------------------
            // SQLログ記録
            // -----------------------------------
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
                $do = 'HD受注伝票_OCRデータ表示';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            // 顧客情報を適切な構造で返す
            return response()->json([
                'data' => $data
            ]);

        } catch (\Exception $e) {
                \Log::error('受注OCR detail処理エラー', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }

    

    // 重複データチェック
    public function search(Request $request)
    {
        try {
            \DB::enableQueryLog();

            $ocrId = $request->query('HD受注伝票_OCR_ID');
            $cd = $request->query('管轄部門CD');
            $no = $request->query('相手先注文NO_得意先');

        // ---- 相手先注文NO_得意先 が空なら重複チェック不要 ----
        if (empty($no)) {
            return response()->json(['count' => 0]);
        }
            // 重複チェック
            $data = OrderSlipOCR::where('管轄部門CD', $cd)
                ->where('相手先注文NO_得意先', $no)
                ->where('HD受注伝票_OCR_ID', '!=', $ocrId)
                ->count();

                // Log::info('件数', ['count' => $data]);

            // SQLログ
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries);
            $sqlquery = $lastQuery['query'] ?? null;
            $bindings = $lastQuery['bindings'] ?? [];

            if ($sqlquery) {
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 2,
                    '実行内容' => '注文書データ重複チェック',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

        return response()->json(['count' => $data]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }




    // 営業所変更
    public function edit(Request $request)
    {
        try {
            \DB::enableQueryLog();

            $hdIDs = $request->input('ids', []);
            $jurisdictionCD = $request->input('管轄部門CD');

            if (empty($hdIDs) || !$jurisdictionCD) {
                return response()->json(['message' => 'パラメータ不正'], 400);
            }

            // 対象件数チェック
            $count = OrderSlipOCR::whereIn('HD受注伝票_OCR_ID', $hdIDs)->count();
            if ($count === 0) {
                return response()->json(['message' => '該当データが見つかりません'], 404);
            }

            // 一括更新
            OrderSlipOCR::whereIn('HD受注伝票_OCR_ID', $hdIDs)
                ->update(['管轄部門CD' => $jurisdictionCD]);

            /** ===== SQLログ ===== */
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries);

            if (!empty($lastQuery)) {
                $sql = $lastQuery['query'];
                foreach ($lastQuery['bindings'] as $binding) {
                    $sql = preg_replace('/\?/', is_numeric($binding) ? $binding : "'{$binding}'", $sql, 1);
                }

                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,
                    '実行内容' => 'HD受注伝票_OCR 管轄部門一括変更',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sql,
                ]);
            }

            return response()->json(['message' => '更新成功しました'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    // 削除
    public function delete(Request $request)
    {
        try {
            \DB::enableQueryLog();

            $hdIDs = $request->input('ids', []);
            // $jurisdictionCD = $request->input('管轄部門CD');

            if (empty($hdIDs)) {
                return response()->json(['message' => 'パラメータ不正'], 400);
            }

            // 対象件数チェック
            $count = OrderSlipOCR::whereIn('HD受注伝票_OCR_ID', $hdIDs)->count();
            if ($count === 0) {
                return response()->json(['message' => '該当データが見つかりません'], 404);
            }

            // 一括削除
            OrderSlipOCR::whereIn('HD受注伝票_OCR_ID', $hdIDs)
                ->delete();
            OrderDetailOCR::whereIn('HD受注伝票_OCR_ID', $hdIDs)
                ->delete();

            /** ===== SQLログ ===== */
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries);

            if (!empty($lastQuery)) {
                $sql = $lastQuery['query'];
                foreach ($lastQuery['bindings'] as $binding) {
                    $sql = preg_replace('/\?/', is_numeric($binding) ? $binding : "'{$binding}'", $sql, 1);
                }

                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,
                    '実行内容' => '受注入力_OCR削除',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sql,
                ]);
            }

            return response()->json(['message' => '更新成功しました'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // PDFアップロード
    public function upload(Request $request)
    {
        /** -------------------------
         * バリデーション
         * ------------------------- */
        $maxCount = config('upload.max_pdf_count', 30);

        $validator = Validator::make($request->all(), [
            'sales_office' => ['required', 'integer'],
            'pdfs'         => ['required', 'array', "max:{$maxCount}"],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        /** -------------------------
         * 受信値
         * ------------------------- */
        $salesOffice = (int) $request->input('sales_office');
        $files       = $request->file('pdfs');

        /** -------------------------
         * 営業所フォルダ名決定
         * ------------------------- */
        $officeMap = [
            10 => '10_tokyo',
            20 => '20_osaka',
            30 => '30_nagoya',
            40 => '40_kyusyu',
            50 => '50_sapporo',
            60 => '60_hiroshima',
            70 => '70_tohoku',
        ];

        if (!isset($officeMap[$salesOffice])) {
            return response()->json([
                'status' => 'error',
                'errors' => ['無効な営業所CDです。'],
            ], 422);
        }

        $officeName = $officeMap[$salesOffice];

        /** -------------------------
         * 保存先
         * storage/app/public/faxs/{営業所}/
         * ------------------------- */
        $saveDir = "faxs/{$officeName}";
        $savedFiles = [];

        foreach ($files as $file) {

            if (!$file->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => [
                        "アップロードに失敗したPDFがあります：{$file->getClientOriginalName()}",
                        "UPLOAD_ERR = {$file->getError()}",
                    ],
                ], 400);
            }

            $fileName = $file->getClientOriginalName();

            try {
                $path = $file->storeAs($saveDir, $fileName, 'public');

                if (!$path) {
                    throw new \RuntimeException('ファイル保存に失敗しました');
                }

                $savedFiles[] = [
                    'original' => $fileName,
                    'path'     => $path,
                ];

            } catch (\Throwable $e) {
                Log::error('PDF保存失敗', [
                    'file'  => $fileName,
                    'error' => $e->getMessage(),
                ]);

                // ★ ここで即中断
                return response()->json([
                    'status' => 'error',
                    'errors' => [
                        "PDFの保存に失敗しました：{$fileName}",
                        '他のPDFは処理されていません',
                    ],
                ], 500);
            }
        }

        // =========================
        // ★ ここでOCRを実行
        // =========================
        try {
            // 営業所CDと名前
            $officeCd   = (string)$salesOffice;
            $officeName = $officeMap[$salesOffice];
            $ocr = app(\App\Services\Ocr\OCR_OrderService::class);
            $result = $ocr->handleWithOffice((int)$officeCd, $officeName);

        } catch (\Throwable $e) {
            Log::error('OCR実行エラー', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'errors' => ['アップロード後のOCR処理に失敗しました'],
            ], 500);
        }
        return response()->json([
            'status'    => 'success',
            'count'     => count($files),
            'processed' => $result['processed'] ?? 0,
            'duplicate_deleted'  => $result['duplicate_deleted'] ?? 0,
            'errors'    => $result['errors'] ?? [],
        ]);

    }


}

