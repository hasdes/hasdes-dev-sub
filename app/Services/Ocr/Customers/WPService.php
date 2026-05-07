<?php
// namespace App\Services;
namespace App\Services\Ocr\Customers;
use App\Services\Ocr\DocumentAiHelperService;
use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;


//========================
//渡辺パイプ
//========================
class WPService
{
    // ------------------
    // 注文情報抽出
    // ------------------
    public static function wpOrderInfo(string $text, Document $document): array
    {

        $log = Log::channel('orders_daily');//ログ設定

        $orderNumber = $customerName = $shippingName = $dueDate = $shipDate = $saturday = $shippingNumber = $siteName = null;
        $candidateDates = [];
        

        try {
            // === 得意先名（発注部署付き）を抽出 ===
            if (preg_match('/渡辺パイプ株式会社/u', $text)) {
                if (preg_match('/発注部署\s*([^\r\n]+)/u', $text, $deptMatch)) {
                    $customerName = '渡辺パイプ株式会社 ' . trim($deptMatch[1]);
                } else {
                    $customerName = '渡辺パイプ株式会社';
                }
            }

            // === 出荷先名の抽出 ===
            if (preg_match('/届先名\s*([^\r\n]+)/u', $text, $m)) {
                $shippingName = trim($m[1]);
            } elseif (preg_match('/届先名\s*\n\s*(.+)/u', $text, $m)) {
                $shippingName = trim($m[1]);
            } elseif (preg_match('/届\s*先\s*名\s*([^\r\n]+)/u', $text, $m)) {
                $shippingName = trim($m[1]);
            }

            //出荷先名に「荷受人」が含まれていたら空にする
            if ($shippingName && str_contains($shippingName, '荷受人')) {
                $shippingName = null;
            }


            // === 営業所用備考（現場名）の抽出（商品名 or 束/CS 以降のみ） ===
            $lines = preg_split('/\R/u', $text);
            $siteName = null;

            $start = false;   // 「商品名」後に ON

            foreach ($lines as $line) {

                if ($line === '') continue;

                // --- 日付（YYYY/MM/DD）を除外 ---
                $line = preg_replace('/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\b/u', '', $line);
                // 区切り残り対策
                $line = trim(preg_replace('/^[\/\-]\s*|\s*[\/\-]$/u', '', $line));


                // --- 開始判定 ---
                if (preg_match('/コメント|備考|担当者|備考/u', $line)) {
                    $start = true;
                    continue;
                }

                // 開始前は無視
                if (!$start) continue;

                // --- 終了判定 ---
                if (preg_match('/送信先FAX/u', $line)) {
                    break; // 完全終了
                }

                // --- 明確に住所を除外 ---
                if (preg_match('/^(住所|〒|TEL|FAX)/u', $line)) continue;

                // --- 電話番号除外 ---
                if (preg_match('/\b0\d{1,4}-\d{1,4}-\d{3,4}\b/u', $line)) continue;

                // ===========================
                //        抽 出 判 定
                // ===========================
                // ① 工事名/現場分で終わる（最優先）
                if (preg_match('/(工事|布設工事|配水小管移設工事|修繕工事|更新工事|新設工事|移設工事|現場分|追加分)\s*$/u', $line)) {
                    $siteName = $line;
                    break;
                }
                // 号数＋地名（企平23号大町寺坂 など）
                if (preg_match('/[一-龠々]+?\d+号[一-龠々]+/u', $line)) {
                    $siteName = $line;
                    break;
                }

                // ② 市区町村＋番地（港区西新橋1-7 など）
                if (
                    preg_match('/(市|区|町|村)/u', $line) &&
                    preg_match('/\d{1,4}\s*-\s*\d{1,4}/u', $line)
                ) {
                    $siteName = $line;
                    break;
                }

                // ③ 番地/丁目/地内/地先
                if (preg_match('/(丁目|番地|地内|地先)/u', $line)) {
                    $siteName = $line;
                    break;
                }

            }


            // === テーブル内の発注番号・納期・現場名を抽出 ===
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {
                    $headerTexts = [];
                    if (!empty($table->getHeaderRows())) {
                        foreach ($table->getHeaderRows()[0]->getCells() ?? [] as $i => $cell) {
                            try {
                                $label = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());
                                $headerTexts[$i] = preg_replace('/\s+/u', '', $label);
                            } catch (\Throwable $e) {
                                $log->warning("ヘッダーセルの読み取り失敗: " . $e->getMessage());
                            }
                        }
                    }

                    // 列インデックスを特定
                    $orderCol = $colDue = $colSite = null;
                    foreach ($headerTexts as $i => $label) {
                        if ($orderCol === null && preg_match('/発注No/u', $label)) $orderCol = $i;
                        if ($colDue === null && preg_match('/納\s*期/u', $label)) $colDue = $i;
                        if ($colDue === null && preg_match('/納期/u', $label)) $colDue = $i;
                        if ($colSite === null && preg_match('/現場名/u', $label)) $colSite = $i;
                    }

                    // 明細行処理
                    foreach ($table->getBodyRows() as $rowIndex => $row) {
                        try {
                            $cells = $row->getCells();

                            foreach ($cells as $i => $cell) {

                                // 発注番号（商品テーブルの１行目のNoの上６桁）
                                if ($orderNumber === null && $orderCol !== null && $i === $orderCol) {
                                    $v = trim(DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor()));
                                    if ($v !== '') $orderNumber = $v;

                                    if (preg_match('/\b\d{8}\b/u', $v, $m)) {
                                        $orderNumber = $m[0];
                                    } elseif (preg_match('/\d{8,}/u', $v, $m)) {
                                        $orderNumber = substr($m[0], 0, 8);
                                    }
                                    // ★ 最後に上6桁に統一
                                    if (!empty($orderNumber)) {
                                        $orderNumber = substr($orderNumber, 0, 6);
                                    }                                
                                }

                                // 納期
                                if ($colDue !== null && $i === $colDue) {
                                    $raw = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());

                                    if (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/u', $raw, $m)) {
                                        $raw = "{$m[1]}/{$m[2]}/{$m[3]}";
                                    }

                                    foreach (preg_split('/[\s\r\n]+/u', $raw) as $part) {
                                        if (!empty($part)) {
                                            $ymd = DocumentAiHelperService::formatDateToNumber($part);
                                            if ($ymd !== '') $candidateDates[] = $ymd;
                                        }
                                    }
                                }

                                // 現場名
                                if ($siteName === null && $colSite !== null && $i === $colSite) {
                                    try {
                                        $raw = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());
                                    } catch (\Throwable $e) {
                                        $raw = $cell->getLayout()->getText() ?? '';
                                    }

                                    $siteName = preg_replace('/\d{4}\/\d{2}\/\d{2}/u', '', $raw);
                                    $siteName = preg_replace('/\b\d{8}\b/u', '', $siteName);
                                    $siteName = preg_replace('/[#＃]/u', '', $siteName);
                                    $siteName = preg_replace('/納期.*/u', '', $siteName);
                                    $siteName = preg_split('/\R/u', $siteName)[0] ?? $siteName;
                                    $siteName = preg_replace('/\s.+$/u', '', $siteName);
                                    $siteName = trim($siteName);
                                }
                            }

                            // ★★★★★ shippingName 補正は cell foreach の後で行う（重要） ★★★★★
                            // 工事名で終わっている場合、または最初から shippingName が無い場合のみ
                            if (empty($shippingName) || preg_match('/工事$/u', $shippingName)) {

                                foreach ($cells as $cell) {
                                    $cellText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    ));

                                    if ($cellText === '') continue;

                                    // ① 「～サービスセンター」「～センター」
                                    if (preg_match('/(.+?センター)/u', $cellText, $m)) {
                                        $shippingName = preg_replace('/^[\|\s　]+/u', '', trim($m[1]));
                                        $shippingName = trim($shippingName);
                                        break 3;
                                    }

                                    // ② 「～入れ願います」
                                    if (preg_match('/(.+?)\s*(?:入れ\s*願います|入れ願います)/u', $cellText, $m)) {

                                        // 抽出部分（入れ願います より前）
                                        $shippingName = trim($m[1]);

                                        // 先頭の記号 | / など除去
                                        $shippingName = preg_replace('/^[\|\s　]+/u', '', $shippingName);
                                        $shippingName = trim($shippingName);

                                        break 3;
                                    }
                                }

                            }


                        } catch (\Throwable $e) {
                            $log->error("wpOrderInfo: 明細行 {$rowIndex} の処理中にエラー: " . $e->getMessage());
                            continue;
                        }
                    }


                    // ===== 発注番号,日付：最終手段（ヘッダーから探索） =====
                    if (empty($orderNumber) || empty($candidateDates)) {
                        foreach ($table->getHeaderRows() as $headerRow) {
                            foreach ($headerRow->getCells() as $cell) {

                                try {
                                    $raw = DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    );
                                    if ($raw  === '') continue;

                                    //日付
                                    if(empty($candidateDates)) {
                                        // YYYY/MM/DD or YYYY-MM-DD を直接検出
                                        if (preg_match_all('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/u', $raw, $matches, PREG_SET_ORDER)) {
                                            foreach ($matches as $m) {
                                                $ymd = sprintf('%04d%02d%02d', $m[1], $m[2], $m[3]);
                                                $candidateDates[] = $ymd;
                                            }
                                        }
                                    }

                                    //発注NO
                                    if(empty($orderNumber)) {
                                        if (preg_match('/\b\d{8}\b/u', $raw, $m)) {
                                            $orderNumber = substr($m[0], 0, 6);
                                            break 2;
                                        }

                                        if (preg_match('/\d{8,}/u', $raw, $m)) {
                                            $orderNumber = substr($m[0], 0, 6);
                                            break 2;
                                        }
                                    }

                                    if (!empty($orderNumber) && !empty($candidateDates)) {
                                        break 2;
                                    }

                                } catch (\Throwable $e) {
                                    $log->warning('ヘッダ納期探索中にエラー: ' . $e->getMessage());
                                }


                            }
                        }
                    }

                }
            }

            // 納期決定（最も早い日付を使用）
            if (!empty($candidateDates)) {
                $candidateDates = array_unique($candidateDates);

                // 電話番号など除外：日付形式だけに絞る
                $candidateDates = array_filter($candidateDates, function ($v) {
                    return preg_match('/^\d{8}$|^\d{4}[\/\-]\d{2}[\/\-]\d{2}$/u', $v);
                });

                sort($candidateDates); // ← これで文字列としても日付順になる
                $dueDate = $candidateDates[0] ?? null;
            }

            // 土曜判定
            $saturday = (!empty($dueDate) && DocumentAiHelperService::isSaturday($dueDate)) ? $dueDate : null;

            // \Log::info('$saturday:'.$saturday);//確認用

            // 出荷日（納期の前営業日）
            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;


            return [
                'orderNumber'      => $orderNumber,//注文番号
                'customerName'     => $customerName,//得意先名
                'shippingName'     => $shippingName,//出荷先名
                'shipDate'         => $shipDate,//出荷日
                'dueDate'          => $dueDate,//納期
                'saturday'          => $saturday,//土日
                'shippingNumber'   => null, // 出荷先用
                'salesBikou'       => $siteName,//備考
            ];

        } catch (\Throwable $e) {
            // エラー内容をログ出力して終了
            $log->error("wpOrderInfo 強制終了: " . $e->getMessage());
            $log->error($e->getTraceAsString());
            // 上位（コマンド側）でDB保存・PDF移動を継続させるため再スロー
            throw $e;
        }
    }





    /** 商品取得の流れ
    * ① ページとテーブルを順に処理する
    *     PDFドキュメント内のすべてのページ・すべてのテーブルをループ。注文書の「商品明細テーブル」が対象

    * ② ヘッダー（列名）を取得する
    *     ヘッダー行がある場合：その1行目のセルから「列名（商品名・数量など）」を取り出す。       
    *     ヘッダー行がない場合：ボディの1行目を擬似ヘッダーとして扱う。

    * ③ 列インデックスを特定する
    *     ヘッダーの中から「発注No」「実数（数量）」「商品名の終了列」などの列番号（インデックス）を見つける。

    * ④ 明細行を1行ずつ処理する
    *     各行のセルから以下を抽出：
    *     [商品名]...発注Noの次のセルから商品情報の複数行テキストを取得し、整形。
    *     [数量]...「商品名の中に〇個」と書いてあればそれを使用。なければ「実数」列から取得。
    *     [呼び径]...商品名の中から 100X75 などのパターンを見つけて呼び径に設定。
    *     [備考]...発注Noのセルから、下2桁を備考として利用。
    */

    // ------------------
    // 商品明細抽出
    // ------------------
    public static function wpOrderItems(string $text, Document $document): array
    {

        $log = Log::channel('orders_daily');//ログ設定
        $items = [];

        try {

            //各ページに複数テーブルがある可能性があるため、ページ→テーブルの二重ループ
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {

                    try {
                        $headerRows = $table->getHeaderRows();// ヘッダ取得
                        $bodyRows = []; // テーブル格納
                        $headerTexts = [];// ヘッダ格納

                        // ===== ヘッダー処理（なければ1行目を疑似ヘッダーに） =====
                        list($headerTexts, $bodyRows) = DocumentAiHelperService::extractTableHeaderAndBodyRows($table, $headerRows, $text);

                        // ===== ヘッダーから列を特定 =====
                        $orderCol = $qtyCol = $productEnd = $colProduct = null;
                        foreach ($headerTexts as $i => $label) {
                            $lines = preg_split('/\R/u', $label); // 改行で分割
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if ($line === '') continue;// 空行をスキップ
                                    if (preg_match('/発注No\. 商品名/u', $line)) $orderCol = $colProduct = $i;
                                    if ($orderCol === null && preg_match('/発注No/u', $line)) $orderCol = $colOrderNo = $i;// 「発注No」というキーワードが含まれていれば、その列番号 $i を $orderCol に保存
                                    if (preg_match('/(束\/?CS|実数)/u', $line)) $productEnd = $i;// 「束/CS」または「実数」が含まれていれば、その列を「商品名の終了列」としてマーク。
                                    if (preg_match('/実数/u', $line)) $qtyCol = $i; //「実数」（数量列）を見つけたら、その列番号を $qtyCol に保存。
                                    if ($colProduct === null && preg_match('/商品名/u', $label)) $colProduct = $i;
                            }
                        }

                    // //確認用
                    // $log->info('=== HeaderRows dump ===');
                    // foreach ($headerRows as $i => $headerRow) {
                    //     foreach ($headerRow->getCells() as $j => $cell) {
                    //         $txt = trim(DocumentAiHelperService::extractTextFromAnchor(
                    //             $text,
                    //             $cell->getLayout()->getTextAnchor()
                    //         ));
                    //         $log->info("Header[$i][$j]: {$txt}");
                    //     }
                    // }
                    // $log->info('========================');


                    // ===== ヘッダーから商品名、備考を取得する =====
                    $gxItems = [];
                    $currentGxIndex = null;
                    $currentGxHeaderCol = null;
                    $headerOrderNo = null; // ヘッダー全体の発注No（fallback用）

                    foreach ($headerRows as $rowIndex => $headerRow) {
                        foreach ($headerRow->getCells() as $colIndex => $cell) {

                            $raw = trim(DocumentAiHelperService::extractTextFromAnchor(
                                $text,
                                $cell->getLayout()->getTextAnchor()
                            ));
                            if ($raw === '') continue;

                            // =========================
                            // ヘッダー全体の発注No（8桁）を1回だけ確保
                            // =========================
                            if (
                                $headerOrderNo === null &&
                                preg_match('/\b(\d{8})\b/u', $raw, $m)
                            ) {
                                $headerOrderNo = $m[1];
                                // $log->info("Header発注No検出: {$headerOrderNo}");
                            }

                            // セル内分割
                            $parts = preg_split("/[\/|]|\R/u", $raw);

                            foreach ($parts as $p) {
                                $p = trim($p);
                                $p = ltrim($p, "|｜");
                                if ($p === '') continue;

                                $pNorm = preg_replace('/\s+/u', '', $p);
                                // 商品名判定用に「先頭の数量」を除去
                                $pForJudge = preg_replace('/^\d+\s*/u', '', $pNorm);

                                // =========================
                                // GX商品名検出
                                // =========================
                                if (preg_match('/^(?:GX|ＧＸ|K形|Ｋ形|F形|Ｆ形|T形|フランジ|上水合|合フランジ)/u', $pForJudge)) {

                                    $gxItems[] = [
                                        'name'   => $pForJudge,
                                        'remark' => null,
                                    ];

                                    $currentGxIndex = count($gxItems) - 1;
                                    $currentGxHeaderCol = $colIndex;

                                    // $log->info("ヘッダーから商品検出 col={$colIndex} name={$pForJudge}");
                                    continue;
                                }

                                // =========================
                                // 商品専用 備考（8桁）
                                // ※ 商品検出「後」
                                // ※ 同列 or 直後列のみ
                                // =========================
                                if (
                                    $currentGxIndex !== null &&
                                    $gxItems[$currentGxIndex]['remark'] === null &&
                                    preg_match('/^(\d{8})\b/u', $p, $m) &&
                                    (
                                        $colIndex === $currentGxHeaderCol ||
                                        $colIndex === $currentGxHeaderCol + 1
                                    )
                                ) {
                                    $gxItems[$currentGxIndex]['remark']
                                        = self::extractRemarkFromOrderNo($m[1], null);

                                    $log->info(
                                        "GX備考確定 col={$colIndex} remark={$m[1]}"
                                    );

                                    // GX確定
                                    $currentGxIndex = null;
                                    $currentGxHeaderCol = null;
                                    continue;
                                }
                            }
                        }
                    }

                    // =========================
                    // 商品備考 fallback（headerOrderNo）
                    // =========================
                    foreach ($gxItems as &$gx) {
                        if ($gx['remark'] === null && $headerOrderNo !== null) {
                            $gx['remark']
                                = self::extractRemarkFromOrderNo($headerOrderNo, null);
                            // $log->info("ヘッダーから備考 fallback headerOrderNo={$headerOrderNo}");
                        }
                    }
                    unset($gx);
                    // $headerGx = $gxItems;
                    // =========================
                    // 明細なし → ヘッダーの商品から生成
                    // =========================
                    if (!empty($gxItems)) {

                        // $log->info('明細行なし。ヘッダー商品から生成', $gxItems);

                        foreach ($gxItems as $gx) {
                            $items[] = [
                                '商品名'  => $gx['name'],
                                '呼び径'  => null,
                                '呼び径1' => null,
                                '呼び径2' => null,
                                '呼び径3' => null,
                                '数量'   => null,
                                '備考'   => $gx['remark'],
                            ];
                        }
                    }

                    // $log->info('$colProduct：'. $colProduct);//確認用


                        // ===== 明細行ループ（行ごとにエラーハンドリング） =====
                        foreach ($bodyRows as $rowIndex => $row) {

                            try {
                                $cells = $row->getCells();

                                // // 商品名の取得-----------------------------------------------------------------------------------------------------
                                $productText = null;
                                $productParts = [];

                                $rowOrderNo = null; // ★ 行専用発注No（重要）

                                // =====================================================
                                // (A) 発注No. と 商品名が同じ列（例：「18749101 GX形継輪 粉体」）
                                // =====================================================
                                if ($orderCol !== null && $colProduct !== null && $orderCol === $colProduct && isset($cells[$orderCol])) {
                                    $cellText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$orderCol]->getLayout()->getTextAnchor()
                                    ));

                                    // 発注No 抽出
                                    if (preg_match('/\b(\d{8})\b/u', $cellText, $m)) {
                                        $productText = trim(str_replace($m[1], '', $cellText));
                                        $rowOrderNo = $m[1]; // ★ 行専用 orderNo
                                    } else {
                                        $productText = $cellText;
                                    }
                                }

                                // =====================================================
                                // (B) 通常ケース（発注Noと商品名が別列）
                                // =====================================================
                                else {

                                    if ($orderCol !== null && isset($cells[$orderCol + 1])) {
                                        $productText = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$orderCol + 1]->getLayout()->getTextAnchor());
                                    }

                                    // 商品名の取得（$orderCol + 1 ～ $qtyCol or $productEnd - 1 までを結合）
                                    $endCol = $qtyCol ?? $productEnd;

                                    if ($orderCol !== null && $endCol !== null && $endCol > $orderCol) {
                                        // Log::info("=== 商品名抽出：range " . ($orderCol + 1) . " to " . ($endCol - 1) . " ===");//確認用

                                        for ($i = $orderCol + 1; $i < $endCol; $i++) {
                                            if (isset($cells[$i])) {
                                                $cellText = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$i]->getLayout()->getTextAnchor());
                                                // Log::info("[Debug] cells[$i] = " . $cellText);//確認用

                                                if (trim($cellText) !== '') {
                                                    $productParts[] = trim($cellText);
                                                    //  Log::info("productParts" . print_r($productParts,true));//確認用
                                                }
                                            }
                                        }
                                        $productText = implode(' ', $productParts); // 空欄を飛ばして結合
                                        //  Log::info("productText:" . $productText);//確認用
                                    }

                                    $rowOrderNo = null; // ★ 初期化（重要）
                                }



                                // 商品名が空の場合
                                if (empty($productText)) {
                                    $startCol = $orderCol + 1;
                                    $endCol = $qtyCol ?? $productEnd;

                                    if ($endCol !== null && $startCol <= $endCol) {
                                        // Log::info("=== 商品名抽出：range {$startCol} to {$endCol} ===");//確認用
                                        for ($i = $startCol; $i <= $endCol; $i++) {
                                            if (isset($cells[$i])) {
                                                $cellText = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$i]->getLayout()->getTextAnchor());
                                                // Log::info("[Debug] cells[$i] = " . $cellText);//確認用

                                                if (trim($cellText) !== '') {
                                                    $productParts[] = trim($cellText);
                                                }
                                            }
                                        }
                                        $productText = implode(' ', $productParts);
                                    }
                                    elseif ($orderCol !== null && isset($cells[$orderCol + 1])) {
                                        $productText = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$orderCol + 1]->getLayout()->getTextAnchor());
                                    }
                                    // Log::info('$productText:'. $productText);//確認用
                                }


                                // ※ 上記で作った productText だけを使って処理（←ここが重要！）
                                $lines = preg_split('/\R/u', $productText ?? '');
                                $productName = implode(' ', array_filter(array_map('trim', $lines))); // 改行分割→空行除去→結合

                                // $log->info('$productName:'. $productName);//確認用

                                //====================================================
                                // ★ rowOrderNo を colOrderNo から補完
                                //====================================================
                                if ($rowOrderNo === null && $orderCol !== null && isset($cells[$orderCol])) {
                                    $rawOrder = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$orderCol]->getLayout()->getTextAnchor()
                                    ));

                                    if (preg_match('/\b(\d{8})\b/u', $rawOrder, $m)) {
                                        $rowOrderNo = $m[1];
                                    }
                                }

                                // ====== ★ 商品名 前処理：渡辺パイプ系 prefix 削除 ======
                                $removePrefixes = [
                                    '渡辺パイプ株式会社',
                                    '渡辺パイプ株式',
                                    'パイプ株式会社',
                                    'パイプ株式',
                                    '渡辺',
                                ];
                                // 行頭のいらない語句を削除
                                foreach ($removePrefixes as $pre) {
                                    $productName = preg_replace('/^' . preg_quote($pre, '/') . '\s*/u', '', $productName);
                                }

                                // 行中にも紛れ込んでいたら削除する（←重要）
                                foreach ($removePrefixes as $pre) {
                                    $productName = preg_replace('/\s*' . preg_quote($pre, '/') . '\s*/u', ' ', $productName);
                                }

                                // ==== 商品名クリーニング（時間・月日を除外）====
                                // YYYY年を除去（例：2025年 → 削除）
                                $productName = preg_replace('/\b\d{4}\s*年\b/u', '', $productName);

                                // 時刻パターン（11時, 11時32分, 時32分）
                                $productName = preg_replace('/\b\d{1,2}\s*時(\s*\d{1,2}\s*分?)?\b/u', '', $productName);
                                $productName = preg_replace('/\b時\s*\d{1,2}\s*分?\b/u', '', $productName);

                                // 月日（11月1日, 9月, 9月1日）
                                $productName = preg_replace('/\b\d{1,2}\s*月\s*\d{1,2}\s*日?\b/u', '', $productName); // 11月1日
                                $productName = preg_replace('/\b\d{1,2}\s*月\b/u', '', $productName); // 11月
                                $productName = preg_replace('/\b月\s*\d{1,2}\s*日?\b/u', '', $productName); // 月11日

                                // 二重空白を整理
                                $productName = trim(preg_replace('/\s+/u', ' ', $productName));

                                // ▼ フィルタ：非商品行スキップ
                                if (
                                    preg_match('/(FAX|送信|年月|月日|取決|価格|TEL|本日|出荷|住所|土木|パイプ|キャンセル|願|変更|丁目|届先名)/u', $productName)||
                                    // preg_match('/^\s*\d{4}\s*年\s*\d{1,2}(?:\s*月)?\s*$/u', $productName) ||
                                    preg_match('/^\s*\d{4}\s*年\s*\d{1,2}\s*月(?:\s*\d{1,2}\s*日)?\s*$/u', $productName) || //年月日
                                    preg_match('/(都|道|府|県).+(市|区|町|村).*\d+-\d+/u', $productName) ||
                                    preg_match('/^\s*\d{1,2}\/\d{1,2}\s*$/u', $productName) || // 月日（12/26, 1/5）
                                    preg_match('/\d{4}\/\d{1,2}\/\d{1,2}/u', $productName) || //日付
                                    preg_match('/^\s*\/\d{1,2}\/\d{1,2}\s*$/u', $productName) || //スラッシュ始まり
                                    preg_match('/^\s*\d{1,2}:\d{2}\s*$/u', $productName) // 時刻（09:57, 9:07）                         
                                ) {
                                    // $log->info("非商品行スキップ: {$productName}");
                                    continue;
                                }

                                // 商品名が空、または数字だけならスキップ
                                if (empty($productName) || preg_match('/^\d+$/u', $productName)) {
                                    continue;
                                }
                                // Log::info('$productName: ' . print_r($productName,true)); //確認用

                                // -------------------------------------------------------------------------------------------------------------
                                
                                // ===============================
                                // 数量
                                // ===============================
                                $rawQty   = null;
                                $quantity = null;

                                $quantityFromQtyCol = false;
                                // $log->info('$productName:'. $productName);
                                

                                // ▼ 0) 行内のどこかに「◯個」があれば最優先で採用
                                foreach ($cells as $cell) {
                                    $cellText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    ));

                                    if ($cellText === '') continue;

                                    if (preg_match('/\b(\d+)\s*個\b/u', $cellText, $m)) {
                                        $quantity = $m[1];

                                        // 商品名に混ざっていた場合の後処理
                                        if (strpos($productName, $m[0]) !== false) {
                                            $productName = str_replace($m[0], '', $productName);
                                            $productName = trim(preg_replace('/\s+/u', ' ', $productName));
                                        }
                                        break;
                                    }
                                }

                                // ▼ 1) 実数列から取得（最優先）※ヘッダーが商品名と実数が別の場合
                                if ($quantity === null && $qtyCol !== null && isset($cells[$qtyCol]) && $qtyCol != $colProduct) {

                                    $rawQty = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$qtyCol]->getLayout()->getTextAnchor()
                                    ));

                                    if ($rawQty !== '') {
                                        // 最初の数字のかたまりを拾う
                                        if (preg_match('/(\d+)/u', $rawQty, $m)) {
                                            $candidate = $m[1];

                                            // 8桁以上はコード系、2000以上は異常値として除外
                                            if (!preg_match('/^\d{8,}$/u', $candidate) && (int)$candidate < 2000) {
                                                $quantity = $candidate;

                                                $quantityFromQtyCol = true;
                                                // $log->info('$quantity1:'. $quantity);
                                            }
                                        }
                                    }
                                }

                                // ▼ 2) 商品名に「○個」と書かれている場合（次に優先）
                                if ($quantity === null && preg_match('/(\d+)\s*個/u', $productName, $m)) {
                                    $quantity = $m[1];

                                    //$productNameから「個」を除去
                                    $productName = preg_replace('/個/u', '', $productName);
                                    // $log->info('$quantity2:'. $quantity);
                                }

                                // ▼ 3) 商品名末尾の数字（例：『… 可 2』『… 粉体 4』）
                                if ($quantity === null &&preg_match('/(\d+)\s*$/u', $productName, $m)) {
                                    $num = $m[1];

                                    // 呼び径っぽい数値は除外
                                    if (preg_match('/^(?:\d{2,3})$/u', $num)) {
                                        // skip
                                    }
                                    // 規格コード（GF7.5 / RF7.5 / 10K / 7.5）
                                    elseif (preg_match('/(GF|RF)\s*\d+(\.\d+)?$/u', $productName)) {
                                        // skip
                                    }
                                    elseif (preg_match('/\d+(\.\d+)?K$/u', $productName)) {
                                        // skip
                                    }
                                    elseif (preg_match('/\d+\.\d+$/u', $productName)) {
                                        // skip（小数は数量にしない）
                                    }
                                    else {
                                        $quantity = $num;
                                        // $log->info('$quantity3:' . $quantity);
                                    }
                                }

                                // ▼ 4) 商品名中の「一桁の数字」を数量とみなす（最終手段）
                                if ($quantity === null 
                                    && !preg_match('/[1-9]\s*号/u', $productName) // ★ 号が付く場合は除外
                                    && preg_match('/(?<![0-9\/])([1-9])(?![0-9\/])/u', $productName, $m)
                                    ) 
                                {
                                    $pos = strpos($productName, $m[1]);

                                    $before = $productName[$pos - 1] ?? '';
                                    $after  = $productName[$pos + 1] ?? '';

                                    // 小数（GF7.5 / RF7.5）
                                    if ($before === '.' || $after === '.') {
                                        // skip
                                    }
                                    // 分数（5 5/8, 1/2" など）の前の数字は数量にしない
                                    elseif (preg_match('/\b' . $m[1] . '\s+\d+\/\d+/u', $productName)) {
                                        // skip
                                    }
                                    // 規格コード（GF7.5 / RF7.5 / 10K）
                                    elseif (preg_match('/(GF|RF|K)\s*' . $m[1] . '/u', $productName)) {
                                        // skip
                                    }
                                    else {
                                        $quantity = $m[1];
                                    }
                                    // $log->info('$quantity4:'. $quantity);
                                }

                                // ▼ 5) 商品名から数量を削除（数量が商品名に混ざっているのが嫌なので）
                                if ($quantity !== null && !$quantityFromQtyCol) {
                                    $q = preg_quote($quantity, '/');

                                    // 分数（1/2°, 3/4° など）の分母を絶対に消さない
                                    $productName = preg_replace(
                                        '/(?<![\d\/])' . $q . '(?![\d\/号])/u',
                                        '',
                                        $productName
                                    );

                                    // スペース整理
                                    $productName = trim(preg_replace('/\s+/u', ' ', $productName));
                                }

                                // ▼ 6) 特殊記号だけの実数列は「1」とみなす（従来ロジック維持）
                                $specialMarks = ['Ⅰ', '$', '＄', 'l', 'ｌ', 'Ｉ'];
                                if ($quantity === null && in_array(trim((string)$rawQty), $specialMarks, true)) {
                                    $quantity = '1';
                                }

                                $quantity = $quantity !== null ? mb_convert_kana($quantity, 'n') : null; // 全角→半角
                                if ($quantity === '') $quantity = null;

                                //-----------------------------------------------------------------------------------------------------

                                // ===============================
                                // 呼び径
                                // ===============================
                                $size = self::extractCallSizeFromText($productName) ?? [
                                    'productName' => '',
                                    // 'callSize'     => '',
                                    'size1'        => '',
                                    'size2'        => '',
                                    'size3'        => '',
                                    'removedWords' => [],
                                ];
                                // $callSize    = $size['callSize'];



                                // 商品名整形
                                // $productName = self::normalizeProductName($size['productName'], false);
                                $productName = DocumentAiHelperService::normalizeProductName($size['productName'], false);

                                //WP限定:商品整形 ---------------------------------------------------------------------------
                                    $productName = preg_replace('/\d{5,}/u', '', $productName);// 5桁以上の連続数字（連番）を削除

                                    // 除去
                                    $removeWords = [
                                        'HAS',
                                        'NBK',
                                        'クボタ',
                                        'ボタ',
                                        'ニッチュウ',
                                        '母',
                                        '妹',
                                        '斌',
                                        '贼子',
                                        '述文',
                                    ];
                                    $productName = preg_replace([
                                        '/(' . implode('|', array_map('preg_quote', $removeWords)) . ')/iu',
                                        '/\d+\s*個\s*$/u',
                                    ], '', $productName);
                                //--------------------------------------------------------------------------------

                                                            

                                // ===============================
                                // 備考（発注No 下2桁）
                                // ===============================
                                if ($rowOrderNo === null &&
                                    $colOrderNo !== null &&
                                    isset($cells[$colOrderNo])) {

                                    $rawOrder = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$colOrderNo]->getLayout()->getTextAnchor()
                                    ));
                                    //８桁
                                    if (preg_match('/\b(\d{8})\b/u', $rawOrder, $m)) {
                                        $rowOrderNo = $m[1];
                                    }
                                    //８桁以上
                                    elseif (preg_match('/\b(\d{8,})\b/u', $rawOrder, $m)) {
                                        $rowOrderNo = $m[1];
                                    }
                                }

                                $remark = self::extractRemarkFromOrderNo($rowOrderNo, null);

                                // 結果に追加（前にフィルター条件追加）
                                // if ($productName === '' && $quantity === null && $callSize === null) {
                                if ($productName === '' && $quantity === null && $size['size1'] === null) {
                                    continue; // 空白行や商品でない行を無視
                                }

                                // 結果に追加
                                $items[] = [
                                    '商品名'   => $productName,
                                    // '呼び径'   => $callSize,
                                    '呼び径1'  => $size['size1'] ?? null,
                                    '呼び径2'  => $size['size2'] ?? null,
                                    '呼び径3'  => $size['size3'] ?? null,
                                    '数量'    => $quantity,
                                    '備考'    => (string) $remark,
                                ];

                            } catch (\Throwable $e) {
                                $log->warning('行スキップ: ' . $e->getMessage());
                                continue; // 行単位エラー → スキップして次へ
                            }
                        }

                    } catch (\Throwable $e) {
                        // テーブル単位で致命的なら関数ごと終了
                        $log->error('テーブル解析失敗: ' . $e->getMessage());
                        throw $e;
                    }

                }
            }

            return $items;

        } catch (\Throwable $e) {
            $log->error('extractDefaultYamatogawaItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e; // 上位 (YamatogawaOrderItems) に伝播
        }
    }




    //渡辺パイプ用
    private static function extractCallSizeFromText(string $text): array
    {

        $log = Log::channel('orders_daily');

        try {
            
            // 入力正規化
            $norm = DocumentAiHelperService::normalizeText($text, $flg1 = true);

            // -------------------------------------------------------------------------
            //置換
            $norm = str_replace(['45*'], '45°', $norm);
            $norm = str_replace(['90*'], '90°', $norm);
            $norm = str_replace(['/2*'], '/2°', $norm);

            // X45 / ×45 を角度扱いにする
            $norm = preg_replace('/×\s*(45|90)(?!°)/u', '×$1°', $norm);

            $kubotaPatterns = [
                '牛ス',
                'ナス',
                'タポータ',
                'サボタ',
                'タボタ',
                'タポタ',
                '鍼ポーター',
            ];
            $norm = str_replace($kubotaPatterns, 'クボタ', $norm);
            // -------------------------------------------------------------------------


            // 「22 1/2」「221/2」などの角度表記を統一（°を付与）
            $norm = DocumentAiHelperService::normalizeHalfDegree($norm);

            // 22 1/ 内面粉体 2° → 22 1/2°
            $norm = preg_replace_callback(
                '/\b(\d{2,3})\s+1\/\s+([^\s]+)\s+(\d+)°/u',
                function($m){
                    return "{$m[1]} 1/{$m[3]}°";
                },
                $norm
            );


            // ここでまず最初に分断角度補正
            $norm = DocumentAiHelperService::dataCleaning_preCallSize($norm);

            // 呼び径抽出（数字+L/H/M含む文字列丸ごと）
            $cleanedCallSizes = []; // L/H/M除外した数字のみの配列
            [$norm, $removedWords] = DocumentAiHelperService::extractAll($norm);


            // 呼び径パターン
            [$norm, $cleanedCallSizes] = DocumentAiHelperService::extractCallSizes($norm);

            // 呼び径2が角度の可能性チェック
            if (
                isset($cleanedCallSizes[1]) &&
                !preg_match('/M/u', $cleanedCallSizes[0]) && // ← Mが含まれていない
                in_array((int)$cleanedCallSizes[1], [ 45, 90], true)
            ) {
                // 角度として扱う
                $removedWords[] = $cleanedCallSizes[1] . '°';

                // 呼び径2を削除
                unset($cleanedCallSizes[1]);
                $cleanedCallSizes = array_values($cleanedCallSizes);
            }


            // 商品名整形（既に呼び径は削除済み）
            $productName = trim($norm);

            // 規格・圧力コードを商品名の後ろに追加
            if (!empty($removedWords)) {
                $productName = trim($productName . ' ' . implode(' ', $removedWords));
            }

            // 先頭の 0 を除去（ただし "0" 単体にはならないよう注意）
            $cleanedCallSizes = DocumentAiHelperService::cleanCallSizes($cleanedCallSizes);


            //戻り値
            return [
                'productName' => $productName,
                // 'callSize' => $rawCallSize, // 元の呼び径文字列（75×300Lなど）
                'size1' => $cleanedCallSizes[0] ?? '',//呼び径1
                'size2' => $cleanedCallSizes[1] ?? '',//呼び径2
                'size3' => $cleanedCallSizes[2] ?? '',//呼び径3
                'removedWords' => $removedWords,//除去ワード（商品名に加える）
            ];


        } catch (\Throwable $e) {
            $log->error('extractCallSizeFromText エラー: ' . $e->getMessage());
            return [
                'productName'   => trim($text),
                // 'callSize'      => '',
                'size1'         => '',
                'size2'         => '',
                'size3'         => '',
                'removedWords'  => [],
            ];
        }
    }



    /**
     * 発注番号の下2桁を「備考」として返す
     */
    private static function extractRemarkFromOrderNo(?string $orderNo, ?string $fallbackText = null): ?string
    {
        // $log = Log::channel('orders_daily');//ログ設定

        $target = $orderNo ?? $fallbackText ?? '';
        $digits = preg_replace('/\D/u', '', $target); // 数字のみ抽出

        if ($digits === '') return null; // 数字が無ければ null

        // 8桁以上 → 先頭8桁だけを見る
        if(strlen($digits) > 8){
            $digits = substr($digits, 0, 8);
        }

        $last2 = substr($digits, -2); // 下2桁

        return str_pad($last2, 2, '0', STR_PAD_LEFT); // 例: '2' → '02'
    }



}
