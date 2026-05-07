<?php
namespace App\Services\Ocr;
use App\Models\OrderSlipOCR;//HD受注伝票_OCR
use App\Models\OrderDetailOCR;//HD受注明細_OCR
use App\Models\ConverOrderSlipOCR;
use App\Models\ConverOrderDetailOCR;
use App\Models\ConverCustomer;
use App\Models\ConverShipping;
use App\Models\ConverProduct;
use App\Models\HDLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\FormatSpaceService;


// 受注入力変換(HASDESから基幹システムへ)
class ConverOrderOCRService
{
    private FormatSpaceService $space;

    public function __construct(FormatSpaceService $space)
    {
        $this->space = $space;
    }

    //受注入力データ（登録、更新）
    public function createOrder(array $data, $tantosyaCD)
    {
        DB::beginTransaction();

        try {

            // --- 0. 部門CD／担当者CD ---
            $bumonCD = $data['管轄部門CD'];
            if (!is_numeric($bumonCD)) {
                return response()->json(['error' => '管轄部門CDが無効です'], 422);
            }
            $tantosyaCD = ($tantosyaCD == 'HASDES') ? 9999 : $tantosyaCD;//開発用

            // 出荷日が当日出荷締め切りを過ぎている場合は警告ポップアップを表示させ、登録を阻止させる。
            $shipping_date = $data['shippingDate'];
            $deadline_date = $data['deliveryDate'];
            $now = Carbon::now();
            $today = $now->format('Ymd');
            $cutoff = '14:30'; //工場、デポ共に14:30

            //エラー出す
            if ($shipping_date === $today && $now->format('H:i') >= $cutoff) {
                return response()->json([
                    'error' => "出荷日の締め切り時刻（{$cutoff}）が過ぎています。"
                ], 422);
            }

            // if($shipping_date >= $deadline_date) {
            //     return response()->json(['error' => 'こちらの希望出荷日、納期では登録できません。'], 422);
            // }

            // --- 1. OCR伝票取得 ---
            $ocrId = $data['HD受注伝票_OCR_ID'];
            $record = $this->getOcrSlip($ocrId);
            if ($record instanceof \Illuminate\Http\JsonResponse) {
                return $record; // ★ エラーで返す
            }            

            // --- 2. 得意先 ---
            $customer = $this->getCustomer($data['customerName'], $data['customerCD']);
            if ($customer instanceof \Illuminate\Http\JsonResponse) {
                return $customer; // ★ エラーで返す
            }

            // --- 3. 出荷先 ---
            $shipping = $this->getShipping($data['shippingName'], $data['shippingCD']);
            if ($shipping instanceof \Illuminate\Http\JsonResponse) {
                return $shipping; // ★ エラーで返す
            }

            // --- 4. 商品 ---
            $validatedProducts = $this->validateProducts($data['products'], $this->space);

            if ($validatedProducts instanceof \Illuminate\Http\JsonResponse) {
                return $validatedProducts; // ★ エラーで返す
            }


            // 担当者CDが不正な場合もエラーレスポンスとして返す
            if (empty($tantosyaCD) || !is_numeric($tantosyaCD)) {
                return response()->json(['error' => '担当者CDが無効です。'], 422);
            }

            // --- 6. 受注NO生成 ---
            $orderNo = $this->generateOrderNo($bumonCD);
            DB::enableQueryLog(); // SQLログ開始

            // --- 7. 登録処理 ---
            $this->insertHeader($bumonCD, $orderNo, $data, $customer, $shipping, $tantosyaCD);
            $this->insertDetails($bumonCD, $orderNo, $validatedProducts, $customer);
            $this->updateOcrData($ocrId, $bumonCD, $orderNo, $data);
            $this->updateOcrDetail($ocrId, $data['products']);
            $this->insertHdLog($tantosyaCD);

            DB::commit();
            return $orderNo;

        } catch (\Exception $e) {
            Log::error('createOrder ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            throw $e;
        }
    }


    // HDOCR伝票_OCRよりID取得
    private function getOcrSlip($ocrId)
    {
        $record = OrderSlipOCR::find($ocrId); //HD受注伝票_OCR

        if (!$record) {
            return response()->json(['error' => 'OCR伝票IDが見つかりません'], 422);
        }
        if (!is_null($record->OCR受注伝票NO)) {
            return response()->json(['error' => 'OCR受注伝票NOはすでに登録されています'], 422);
        }

        return $record;
    }


    // 得意先変換マスタ
    private function getCustomer($name, $customerCD)
    {
        // まず変換名に一致する候補を取得
        $customers = ConverCustomer::query()
            ->leftJoin('M得意先', 'M得意先.得意先CD', '=', 'HD変換得意先.得意先CD')
            ->select('M得意先.得意先CD', 'M得意先.得意先名', 'M得意先.得意先支店名')
            ->where('変換名', $name)
            ->whereNull('HD変換得意先.消去日時')
            ->get();

        // 変換マスタに存在しない
        if ($customers->isEmpty()) {
            return response()->json(['error' => '得意先が変換マスタに存在しません'], 422);
        }

        // 得意先CDが指定されている場合は一致するものを探す
        if (!empty($customerCD)) {
            $customer = $customers->firstWhere('得意先CD', $customerCD);
            if ($customer) {
                return $customer; // 一致すればそのまま返す
            }
            // 不一致でも候補が1件ならそのまま返す
            if ($customers->count() === 1) {
                return $customers->first();
            }

            // 複数件ある場合は選択用に返す
            return response()->json([
                'reason' => 'データ重複',
                'type' => '得意先',
                'name' => $name,
                'choices' => $customers->map(fn($c) => [
                    '得意先CD' => $c->得意先CD,
                    '得意先名' => $c->得意先名.$c->得意先支店名,
                ])
            ], 409);
        }

        // 得意先CD指定なしの場合
        if ($customers->count() > 1) {

            return response()->json([
                'reason' => 'データ重複',
                'type' => '得意先',
                'name' => $name,
                'choices' => $customers->map(fn($c) => [
                    '得意先CD' => $c->得意先CD,
                    '得意先名' => $c->得意先名.$c->得意先支店名,
                ])
            ], 409);
        }


        // 1件ならそのまま返す
        return $customers->first();
    }

    //出荷先変換マスタ
    private function getShipping($name, $shippingCD)
    {
        $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD
        $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

        // まず変換名に一致する候補を取得
        $shippings = ConverShipping::query()
                ->leftJoin('M出荷先', 'M出荷先.出荷先_エンドユーザーCD', '=', 'HD変換出荷先.出荷先_エンドユーザーCD')
                ->select('M出荷先.出荷先_エンドユーザーCD', 'M出荷先.略名')
                ->where('HD変換出荷先.管轄部門CD', $jurisdictionCD) //ログインユーザー所属部門CD
                ->where('HD変換出荷先.変換名', $name)
                ->whereIn('M出荷先.管轄部門CD', [0, $jurisdictionCD])
                ->where('M出荷先.得意先区分', 2)
                ->whereNull('HD変換出荷先.消去日時')
                ->get();

        // 変換マスタに存在しない
        if ($shippings->isEmpty()) {
            return response()->json(['error' => '出荷先が変換マスタに存在しません'], 422);
        }

        // 出荷先CDが指定されている場合は一致するものを探す
        if (!empty($shippingCD)) {
            $shipping= $shippings->firstWhere('出荷先_エンドユーザーCD', $shippingCD);
            if ($shipping) {
                return $shipping; // 一致すればそのまま返す
            }

            // 不一致でも候補が1件ならそのまま返す
            if ($shippings->count() === 1) {
                return $shippings->first();
            }

            // 複数件ある場合は選択用に返す
            return response()->json([
                'reason' => 'データ重複',
                'type' => '出荷先',
                'name' => $name,
                'choices' => $shippings->map(fn($s) => [
                    '出荷先CD' => $s->出荷先_エンドユーザーCD,
                    '出荷先名' => $s->略名,
                ])
            ], 409);
        }

        // 得意先CD指定なしの場合
        if ($shippings->count() > 1) {

            return response()->json([
                'reason' => 'データ重複',
                'type' => '出荷先',
                'name' => $name,
                'choices' => $shippings->map(fn($s) => [
                    '出荷先CD' => $s->出荷先_エンドユーザーCD,
                    '出荷先名' => $s->略名,
                ])
            ], 409);
        }

        // 1件ならそのまま返す
        return $shippings->first();
    }



    //商品変換マスタ
    private function validateProducts(array $productsInput, FormatSpaceService $space)
    {
        if (empty($productsInput)) {
            return response()->json(['error' => '商品情報がありません'], 422);
        }

        $validated = [];

        foreach ($productsInput as $index => $p) {
            $m = null; //空にする
            
            // 呼び径を右詰めで整形
            $y1 = $space->leftSpace($p['呼び径1'] ?? '', 4); // MAX4文字
            $y2 = $space->leftSpace($p['呼び径2'] ?? '', 4); // MAX4文字
            $y3 = $space->leftSpace($p['呼び径3'] ?? '', 3); // MAX3文字

            $inputProductCD = isset($p['商品CD']) ? trim($p['商品CD']) : null;
            $inputProductName = trim($p['商品名'] ?? '');

            // まず変換マスタに一致する候補を取得
            // $matches = ConverProduct::query()
            //     ->leftJoin('M商品', 'M商品.商品CD', '=', 'HD変換商品.商品CD')
            //     ->select(
            //         DB::raw('M商品.商品CD'),
            //         DB::raw('MAX(M商品.商品名_社内用) AS 商品名_社内用')
            //     )
            //     ->where('HD変換商品.変換名', $inputProductName)
            //     ->whereNull('HD変換商品.消去日時')
            //     ->groupBy('M商品.商品CD')
            //     ->get()
            //     ->map(function ($m) {
            //         $m->商品CD = trim($m->商品CD);
            //         $m->商品名_社内用 = trim($m->商品名_社内用);
            //         return $m;
            //     });
            $matches = ConverProduct::query()
                ->leftJoin('M商品', 'M商品.商品CD', '=', 'HD変換商品.商品CD')
                ->select(
                    'M商品.商品CD',
                    'M商品.商品名_社内用',
                    DB::raw('COUNT(*) as cnt')
                )
                ->where('HD変換商品.変換名', $inputProductName)
                ->whereNull('HD変換商品.消去日時')
                ->groupBy('M商品.商品CD', 'M商品.商品名_社内用')
                ->orderByDesc('cnt') // ← これが重要
                ->get()
                ->groupBy('商品CD')
                ->map(function ($group) {
                    $m = $group->first(); // 件数最大
                    $m->商品CD = trim($m->商品CD);
                    $m->商品名_社内用 = trim($m->商品名_社内用);
                    return $m;
                })
                ->values();

            if ($matches->isEmpty()) {
                return response()->json(['error' => "商品名「{$inputProductName}」が変換マスタに存在しません"], 422);
            }

            // 商品CDが入力されている場合
            if ($inputProductCD) {

                $product = $matches->firstWhere('商品CD', $inputProductCD);

                // if ($product) {
                //     // 入力側の商品名は無視して、マスタの商品名_社内用で上書き
                //     $product->商品名_社内用 = trim($product->商品名_社内用);
                // } else {
                //     // 不一致でも候補が1件ならそのまま採用
                //     if ($matches->count() === 1) {
                //         $product = $matches->first();
                //     } else {
                //         // CD不一致 + 複数候補あり → モーダル選択用
                //         return response()->json([
                //             'reason' => 'データ重複',
                //             'type' => '商品',
                //             'name' => $inputProductName,
                //             'choices' => $matches->map(fn($m) => [
                //                 '商品CD' => $m->商品CD,
                //                 '商品名' => $m->商品名_社内用,
                //             ]),
                //             'rowIndex' => $index,
                //         ], 409);
                //     }
                // }
                if(!$product) {
                    // 不一致でも候補が1件ならそのまま採用
                    if ($matches->count() === 1) {
                        $product = $matches->first();
                    } else {
                        // CD不一致 + 複数候補あり → モーダル選択用
                        return response()->json([
                            'reason' => 'データ重複',
                            'type' => '商品',
                            'name' => $inputProductName,
                            'choices' => $matches->map(fn($m) => [
                                '商品CD' => $m->商品CD,
                                '商品名' => $m->商品名_社内用,
                            ]),
                            'rowIndex' => $index,
                        ], 409);
                    }
                }


                // 呼び径チェック（996 000以外）
                if (trim($product->商品CD) !== '996  000') {
                    $m = DB::table('M商品')
                        ->where('商品CD', $product->商品CD)
                        ->where('呼び径1', $y1)
                        ->where('呼び径2', $y2)
                        ->where('呼び径3', $y3)
                        ->first();

                    if (!$m) {
                        return response()->json([
                            'error' => '商品' . ($index + 1) . '「' . (string)$inputProductName . '」の呼び径が一致する商品が存在しません'
                        ], 422);
                    }
                }

            } else {
                // 商品CD未入力の場合
                if ($matches->count() > 1) {
                    // 複数候補 → モーダル選択用
                    return response()->json([
                        'reason' => 'データ重複',
                        'type' => '商品',
                        'name' => $inputProductName,
                        'choices' => $matches->map(fn($m) => [
                            '商品CD' => $m->商品CD,
                            '商品名' => $m->商品名_社内用,
                        ]),
                        'rowIndex' => $index,
                    ], 409);
                }

                // 候補1件ならそのまま
                $product = $matches->first();

                if (trim($product->商品CD) !== '996  000') {
                    $m = DB::table('M商品')
                        ->where('商品CD', $product->商品CD)
                        ->where('呼び径1', $y1)
                        ->where('呼び径2', $y2)
                        ->where('呼び径3', $y3)
                        ->first();

                    if (!$m) {
                        return response()->json([
                            'error' => '商品' . ($index + 1) . '「' . (string)$inputProductName . '」の呼び径が一致する商品が存在しません'
                        ], 422);
                    }
                }
            }

            $productName = $m->商品名_社内用 ?? $product->商品名_社内用; // 呼び径チェックで見つかった商品名_社内用があれば優先、なければ変換マスタの商品名_社内用

            $validated[] = [
                '商品CD' => $product->商品CD,
                // '商品名' => $product->商品名_社内用,
                '商品名' => $productName,
                '呼び径1' => $y1,
                '呼び径2' => $y2,
                '呼び径3' => $y3,
                '数量'   => $p['数量'] ?? 0,
                '備考'   => $p['備考'] ?? '',
            ];
        }

        return $validated;
    }


    //受注NO生成
    private function generateOrderNo($bumonCD)
    {
        $year = date('y');
        $lastOrder = ConverOrderSlipOCR::where('管轄部門CD', $bumonCD)
            ->whereRaw('LEFT(OCR受注伝票NO, 2) = ?', [$year])
            ->orderByDesc('OCR受注伝票NO')
            ->first();
        $lastSeq = $lastOrder ? (int)substr($lastOrder->OCR受注伝票NO, 2) : 0;
        return $year . str_pad($lastSeq + 1, 6, '0', STR_PAD_LEFT);
    }

    //D受注伝票_OCR登録
    private function insertHeader($bumonCD, $orderNo, $data, $customer, $shipping, $tantosyaCD)
    {    
        // 今日の日付をYYYYMMDD形式で取得
        $today = Carbon::now()->format('Ymd');
        // 現在の時刻をHHIISS形式で取得
        $his = Carbon::now()->format('His');

        ConverOrderSlipOCR::create([
            '管轄部門CD' => $bumonCD,
            'OCR受注伝票NO' => $orderNo,
            '受注日付' => $today,
            'ヘッダ希望納期' => $data['shippingDate'],
            '土日着日指定' => $data['saturdayDelivery'] ?? '',
            '得意先CD' => $customer->得意先CD ?? '',
            '出荷先CD' => $shipping->出荷先_エンドユーザーCD ?? '',
            '送り状印字内容' => $data['deliveryDate'] ?? '',
            '担当者CD' => $tantosyaCD,
            '営業部門CD' => $bumonCD,
            '相手先注文NO_得意先' => $data['orderNo'] ?? '',
            '相手先注文NO_出荷先' => $data['shippingNote'] ?? '',
            '営業用備考' => $data['salesNote'] ?? '',
            '予備文字項目1' => '',
            '予備文字項目2' => '',
            '予備文字項目3' => '',
            '登録日' => $today,
            '最終更新日' => $today,
            '最終更新時間' => $his,
            '最終更新ID' => '',
        ]);
    }

    //D受注明細_OCR登録
    private function insertDetails($bumonCD, $orderNo, $validatedProducts, $customer)
    {
        $today = Carbon::now()->format('Ymd');// 今日の日付をYYYYMMDD形式で取得
        $his = Carbon::now()->format('His');// 現在の時刻をHHIISS形式で取得

        foreach ($validatedProducts as $k => $v) {


            ConverOrderDetailOCR::create([
                '管轄部門CD' => $bumonCD,
                'OCR受注伝票NO' => $orderNo,
                'OCR受注伝票行NO' => $k + 1,
                '商品CD' => $v['商品CD'],
                '呼び径1' => $v['呼び径1'] ?? '',
                '呼び径2' => $v['呼び径2'] ?? '',
                '呼び径3' => $v['呼び径3'] ?? '',
                '商品名' => $v['商品名'] ?? '',
                '数量' => $v['数量'] ?? 0,
                '備考' => $v['備考'] ?? '',
                '得意先CD' => $customer->得意先CD ?? '',
                '予備文字項目1' => '',
                '予備文字項目2' => '',
                '予備文字項目3' => '',
                '登録日' => $today,
                '最終更新日' => $today,
                '最終更新時間' => $his,
                '最終更新ID' => '',
            ]);
        }
    }

    //HD受注伝票_OCR
    private function updateOcrData($ocrId, $bumonCD, $orderNo, $data)
    {
        $record = OrderSlipOCR::find($ocrId);
        $record->update([
            '受注日付' => $data['shippingDate'],
            'ヘッダ希望納期' => $data['shippingDate'],
            '土日着日指定' => $data['saturdayDelivery'],
            '得意先名' => $data['customerName'],
            '出荷先名' => $data['shippingName'],
            '送り状印字内容' => $data['deliveryDate'],
            '相手先注文NO_得意先' => $data['orderNo'],
            '相手先注文NO_出荷先' => $data['shippingNote'],
            '営業用備考' => $data['salesNote'],
            'OCR受注伝票NO' => $orderNo,
        ]);

    }

    // HD受注明細_OCR更新
    public function updateOcrDetail($ocrId, array $newProducts)
    {
        $records = OrderDetailOCR::where('HD受注伝票_OCR_ID', $ocrId)
            ->orderBy('HD受注明細_OCR_ID')
            ->get();

        $existingCount = $records->count();
        $newCount = count($newProducts);
        $now = now();

        foreach ($newProducts as $k => $v) {
            if ($k < $existingCount) {
                // 既存レコードを更新
                $record = $records[$k];
                $record->商品名 = $v['商品名'];
                $record->呼び径1 = $v['呼び径1'];
                $record->呼び径2 = $v['呼び径2'];
                $record->呼び径3 = $v['呼び径3'];
                $record->数量   = $v['数量'];
                $record->備考   = $v['備考'];
                $record->消去日時 = null;
                $record->save();
            } else {
                // 足りない分は新規登録
                OrderDetailOCR::create([
                    'HD受注伝票_OCR_ID' => $ocrId,
                    '商品名' => $v['商品名'],
                    '呼び径1' => $v['呼び径1'],
                    '呼び径2' => $v['呼び径2'],
                    '呼び径3' => $v['呼び径3'],
                    '数量' => $v['数量'],
                    '備考' => $v['備考'],
                ]);
            }
        }

        // 余った既存レコードは論理削除
        if ($newCount < $existingCount) {
            for ($i = $newCount; $i < $existingCount; $i++) {
                $records[$i]->update(['消去日時' => $now]);
            }
        }
    }

    //HDログ記録
    private function insertHdLog($tantosyaCD)
    {
        $queries = DB::getQueryLog();
        $detailInserts = []; // 明細 insert 用
        $detailUpdates = []; // 明細 update 用

        foreach ($queries as $q) {
            $sqlquery = $q['query'];
            $bindings = $q['bindings'] ?? [];

            // プレースホルダ置換
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
            }

            $sqlLower = strtolower($sqlquery);
            $do = null;
            $logkind = null;

            // INSERT 系
            if (str_contains($sqlLower, 'insert into `d受注伝票_ocr`')) {
                $do = '受注入力データ変換（D受注伝票OCR登録）';
                $logkind = 4;
            }
            elseif (str_contains($sqlLower, 'insert into `d受注明細_ocr`')) {
                $detailInserts[] = $sqlquery;
                continue;
            }
            // UPDATE 系
            elseif (str_contains($sqlLower, 'update `hd受注伝票_ocr`')) {
                $do = '受注入力データ変換（HD受注伝票OCR更新）';
                $logkind = 3;
            }
            elseif (str_contains($sqlLower, 'update `hd受注明細_ocr`')) {
                $detailUpdates[] = $sqlquery;
                continue;
            }

            // INSERT/UPDATE の場合のみログ作成（個別ログ）
            if ($do && $logkind) {
                HDLog::create([
                    '担当者CD' => $tantosyaCD,
                    'ログ種別' => $logkind,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            }
        }

        // 明細 insert まとめてログ
        if (!empty($detailInserts)) {
            $combinedSql = implode(";\n", $detailInserts);
            HDLog::create([
                '担当者CD' => $tantosyaCD,
                'ログ種別' => 4,
                '実行内容' => '受注入力データ変換（D受注明細OCR登録まとめ）',
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $combinedSql,
            ]);
        }

        // 明細 update まとめてログ
        if (!empty($detailUpdates)) {
            $combinedSql = implode(";\n", $detailUpdates);
            HDLog::create([
                '担当者CD' => $tantosyaCD,
                'ログ種別' => 3,
                '実行内容' => '受注入力データ変換（HD受注明細OCR更新まとめ）',
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $combinedSql,
            ]);
        }
    }
}
