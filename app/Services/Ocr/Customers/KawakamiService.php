<?php
namespace App\Services\Ocr\Customers;
use App\Services\Ocr\DocumentAiHelperService;
use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;


//========================
//　河上商事
//========================
class KawakamiService
{
    // ------------------
    // 注文情報抽出
    // ------------------

    public static function KawakamiOrderInfo(string $text, Document $document): array
    {

        $log = Log::channel('orders_daily');//ログ

        try {

            $orderNumber = $customerName = $shippingName = $dueDate = $saturday = $shippingNumber = $siteName = $shipDate = null;
            $candidateDates = [];

            // 1. 発注番号（8桁数字）
            if (preg_match('/注文No\s*:\s*(\d{8})/u', $text, $m)) {
                $orderNumber = $m[1];
            }

            // 2. 得意先
            $customerName = '河上商事株式会社';//得意先は常に「河上商事株式会社」とする


            // 5. 出荷先用番号（担当：の横）
            if (preg_match('/担当:\s*([^\n]+)/u', $text, $m)) {
                $shippingNumber = trim(mb_convert_kana($m[1] ?? ''));
            }

            // 3. 出荷先（納入先）
            if (preg_match('/通信欄\s*(.+)/u', $text, $m)) {
                $shippingName = trim($m[1]);
            }

            // === テーブル内から納期を抽出 ===
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {
                    $headerTexts = [];
                    $headerRows = $table->getHeaderRows();

                    if ($headerRows && count($headerRows) > 0) {
                        foreach ($headerRows[0]->getCells() ?? [] as $i => $cell) {
                    // if (!empty($table->getHeaderRows())) {
                    //     foreach ($table->getHeaderRows()[0]->getCells() ?? [] as $i => $cell) {
                            try {
                                $label = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());
                                $headerTexts[$i] = preg_replace('/\s+/u', '', $label);
                            } catch (\Throwable $e) {
                                $log->warning("ヘッダーセルの読み取り失敗: " . $e->getMessage());
                            }
                        }
                    }

                    // 列インデックスを特定
                    $colDue = null;
                    foreach ($headerTexts as $i => $label) {
                        if ($colDue === null && preg_match('/納期/u', $label)) $colDue = $i;
                    }

                    // 明細行処理
                    foreach ($table->getBodyRows() as $rowIndex => $row) {
                        try {
                            $cells = $row->getCells();

                            foreach ($cells as $i => $cell) {

                                // 納期
                                if ($colDue !== null && $i === $colDue) {
                                    $raw = DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    );
                                    // =========================
                                    // 許可フォーマットのみ抽出
                                    // =========================
                                    // 例：2026/02/09 （区切り前後の空白を許可）
                                    if (preg_match(
                                        '/\b(\d{4})\s*\/\s*(\d{2})\s*\/\s*(\d{2})\b/u',
                                        $raw,
                                        $m
                                    )) {
                                        $ymd = sprintf('%04d%02d%02d', $m[1], $m[2], $m[3]);
                                        $candidateDates[] = $ymd;
                                        // $log->info('$candidateDates1:'.$candidateDates);
                                        continue;
                                    }
                                }

                            }

                        } catch (\Throwable $e) {
                            $log->error("wpOrderInfo: 明細行 {$rowIndex} の処理中にエラー: " . $e->getMessage());
                            continue;
                        }
                    }

                }
            }
            // 納期決定（最も早い日付を使用）
            if (!empty($candidateDates)) {
                $candidateDates = array_unique($candidateDates);
                // $log->info('$candidateDates3:'.$candidateDates);
                sort($candidateDates); // ← これで文字列としても日付順になる
                $dueDate = $candidateDates[0] ?? null;
            }

            // ======================================
            // ★ テーブルで取れなかった場合のみ text を見る
            // ★ 「2026/01/23」形式のみ対象
            // ======================================
            if (empty($dueDate)) {
                if (preg_match('/\b(\d{4})\s*\/\s*(\d{2})\s*\/\s*(\d{2})\b/u', $text, $m)) {
                    $y = $m[1];
                    $mth = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                    $d = str_pad($m[3], 2, '0', STR_PAD_LEFT);
                    $dueDate = "{$y}{$mth}{$d}";
                    // $log->info('$dueDate:'.$dueDate);
                }
            }

            // 土曜判定
            $saturday = (!empty($dueDate) && DocumentAiHelperService::isSaturday($dueDate)) ? $dueDate : null;

            // \Log::info('$saturday:'.$saturday);//確認用

            // 出荷日（納期の前営業日）
            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;

            // 出荷先に「本社」が含まれていたら得意先名に置き換え
            if (!empty($shippingName) && (mb_strpos($shippingName, '本社') !== false)) {
                $shippingName = $customerName;
            // 出荷先に「岡山」が含まれていたら「河上商事株式会社 岡山支店」に置き換え
            }elseif (!empty($shippingName) && (mb_strpos($shippingName, '岡山') !== false)) {
                $shippingName = '河上商事株式会社 岡山支店';
            // 出荷先に「当社」または「弊社」が含まれていたら得意先名に置き換え
            }else{
                $shippingName = DocumentAiHelperService::formatshippingName($customerName ?? '', $shippingName ?? '');
            }

            // ------ 最終レコード作成 ------
            return [
                'orderNumber'    => $orderNumber,//注文番号
                'customerName'   => $customerName,//得意先
                'shippingName'   => $shippingName,//出荷先
                'shipDate'       => $shipDate,//出荷日
                'dueDate'        => $dueDate,//納期
                'saturday'        => $saturday,//納期
                'shippingNumber' => $shippingNumber,//出荷先用
                'salesBikou'     => $siteName,//備考
            ];

        } catch (\Throwable $e) {
            // エラー内容をログ出力して終了
            $log->error('KawakamiOrderInfo 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            // 上位（コマンド側）でDB保存・PDF移動を継続させるため再スロー
            throw $e;
        }

    }



    // ------------------
    // 商品明細抽出
    // ------------------

    public static function KawakamiOrderItems(string $text, Document $document): array
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
                        $qtyCol = $nameCol = $specCol = $qtyCol2 = null;
                        $nameCols = [];
                        foreach ($headerTexts as $i => $label) {
                            $lines = preg_split('/\R/u', $label); // 改行で分割
                            $joined = trim(preg_replace('/\s+/u', '', $label));

                            foreach ($lines as $line) {
                                $line = trim($line);
                                if ($line === '') continue;// 空行をスキップ
                                    // if (preg_match('/商品名|商品/u', $line)) $nameCols = $i;

                                // 商品名
                                if (
                                    preg_match('/商品名|商品/u', $line) ||
                                    preg_match('/^名$/u', $line) ||
                                    preg_match('/名規/u', $joined) // ←追加
                                ) {
                                    if (!in_array($i, $nameCols, true)) {
                                        $nameCols[] = $i;
                                    }
                                }     
                                //商品名がない場合
                                if (empty($nameCols)) {
                                    // fallback
                                    if ($nameCol !== null) {
                                        $nameCols = [$nameCol];
                                    }
                                }

                                if (preg_match('/規格|規|格/u', $line)) $specCol = $i; //呼び径
                                if (preg_match('/数量|数里|数/u', $line)) $qtyCol = $i; //「実数」（数量列）を見つけたら、その列番号を $qtyCol に保存。
                                if (preg_match('/里|量/u', $line)) $qtyCol2 = $i;//数量列が複数ある場合の予備（「数量」列が見つからない、または数量が取れない場合にこちらも見る）

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

                                // $pNorm = preg_replace('/\s+/u', '', $p);
                                $pNorm = $p;
                                // 商品名判定用に「先頭の数量」を除去
                                $pForJudge = preg_replace('/^\d+\s*/u', '', $pNorm);

                                // =========================
                                // GX商品名検出
                                // =========================
                                if (preg_match('/^(?:粉|GX|ＧＸ|K形|Ｋ形|F形|Ｆ形|T形|フランジ|上水合|合フランジ)/u', $pForJudge)) {

                                    $gxItems[] = [
                                        'name'   => $pForJudge,
                                        // 'remark' => null,
                                    ];

                                    $currentGxIndex = count($gxItems) - 1;
                                    $currentGxHeaderCol = $colIndex;

                                    // $log->info("ヘッダーから商品検出 col={$colIndex} name={$pForJudge}");
                                    continue;
                                }

                            }
                        }
                    }

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
                                '備考'   => null,
                            ];
                        }
                    }

                    // $log->info('$nameCol：'. $nameCol);//確認用

                        // ===== 明細行ループ（行ごとにエラーハンドリング） =====
                        foreach ($bodyRows as $rowIndex => $row) {

                            try {
                                $cells = $row->getCells();

                                // // 商品名の取得-----------------------------------------------------------------------------------------------------
                                $productText = null;
                                $productParts = [];
                                $name = '';
                                $productParts = [];

                                foreach ($nameCols as $col) {
                                    if ($col < count($cells)) {
                                        $txt = trim(DocumentAiHelperService::extractTextFromAnchor(
                                            $text,
                                            $cells[$col]->getLayout()->getTextAnchor()
                                        ));

                                        if ($txt !== '') {
                                            $productParts[] = $txt;
                                        }
                                    }
                                }

                                // 結合
                                $productText = implode(' ', $productParts);
                                // 商品名整形
                                $productText = DocumentAiHelperService::normalizeProductName($productText, true);

                                // 河上商事オリジナル　商品名に呼び径が混じっている場合除去 -------------------------------------
                                $productName = $productText;
                                // ① ボルト規格削除
                                $productName = preg_replace('/M\s*\d+(?:\s*X\s*\d+)*|\d+(?:X\d+)*M/iu', '', $productName);
                                // ② 呼び径（Xあり）
                                $productName = preg_replace('/\d{2,4}(?:X\d{2,4})+°?/u', '', $productName);
                                // ③ 単体の数字（独立してるやつだけ）
                                $productName = preg_replace('/(?<=\s)\d{2,4}(?=\s|$)/u', '', $productName);
                                // ④ ゴミ「°」
                                $productName = preg_replace('/(^|\s)°(\s|$)/u', ' ', $productName);
                                //--------------------------------------------------------------------------------------

                                // 前後空白除去＆連続スペースを1つに
                                $productName = trim(preg_replace('/\s+/u', ' ', $productName));

                                // ▼ フィルタ：非商品行スキップ
                                if (preg_match('/(送り先)/u', $productName)) {
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

                                // ▼ 0) 数量列から直接取得（最優先）
                                if ($qtyCol !== null && $qtyCol < count($cells)) {
                                    $qtyText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$qtyCol]->getLayout()->getTextAnchor()
                                    ));

                                    // 数字だけ抽出
                                    if (preg_match('/\d+/u', $qtyText, $m)) {
                                        $quantity = $m[0];
                                    }
                                }

                                // ▼ 0-2) 数量列が複数ある場合の予備（「数量」列が見つからない、または数量が取れない場合にこちらも見る）
                                if ($quantity == null && $qtyCol2 !== null) {
                                    $qtyText2 = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cells[$qtyCol2]->getLayout()->getTextAnchor()
                                    ));

                                    // 数字だけ抽出
                                    if (preg_match('/\d+/u', $qtyText2, $m)) {
                                        $quantity = $m[0];
                                    }
                                }

                                // ▼ 0-3) 行内のどこかに「◯個」があれば最優先で採用
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

                                // ▼ 1) 商品名に「○個」と書かれている場合（次に優先）
                                if ($quantity === null && preg_match('/(\d+)\s*個/u', $productName, $m)) {
                                    $quantity = $m[1];

                                    //$productNameから「個」を除去
                                    $productName = preg_replace('/個/u', '', $productName);
                                    // $log->info('$quantity2:'. $quantity);
                                }

                                // ▼ 2) 商品名末尾の数字（例：『… 可 2』『… 粉体 4』）
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

                                // ▼ 3) 商品名中の「一桁の数字」を数量とみなす（最終手段）
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
                                    elseif (preg_match('/\d+\s*号$/u', $productName)) {
                                        // skip
                                    }
                                    else {
                                        $quantity = $m[1];
                                    }
                                    // $log->info('$quantity4:'. $quantity);
                                }

                                // ▼ 4) 商品名から数量を削除（数量が商品名に混ざっているのが嫌なので）
                                if ($quantity !== null) {
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


                                // ▼ 5) 特殊記号だけの実数列は「1」とみなす（従来ロジック維持）
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
                                if ($specCol !== null && $specCol < count($cells)) {
                                    $specText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$specCol]->getLayout()->getTextAnchor()
                                    ));
                                    $size = self::extractCallSizeFromText($specText) ?? [
                                        'size1'=>null,'size2'=>null,'size3'=>null,'removedWords'=>[]];
                                    // 除去語を商品名に追記
                                    if (!empty($size['removedWords'])) {
                                        $productName .= ' ' . implode(' ', $size['removedWords']);
                                    }
                                    // ψ→4 誤読の補正：size1 が 4始まり or 450超 なら先頭1桁削る
                                    // if (!empty($size['size1'])) {
                                    //     $str = (string)$size['size1'];
                                    //     if ($str[0] === '4' || (int)$str > 450) {
                                    //         $size['size1'] = (int)substr($str, 1);
                                    //     }
                                    // }
                                }      

                                // 結果に追加
                                $items[] = [
                                    '商品名'   => $productName,
                                    '呼び径1'  => $size['size1'] ?? null,
                                    '呼び径2'  => $size['size2'] ?? null,
                                    '呼び径3'  => $size['size3'] ?? null,
                                    '数量'    => $quantity,
                                    '備考'    => null,
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
            $log->error('extractDefaultKawakamiItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e;
        }
    }



    // 商品名、呼び径抽出
    public static function extractCallSizeFromText(string $text): array
    {

        $log = Log::channel('orders_daily');

        try {

            // 入力正規化
            $norm = DocumentAiHelperService::normalizeText($text, $flg1 = false);

            // 「22 1/2」「221/2」などの角度表記を統一（°を付与）
            $norm = DocumentAiHelperService::normalizeHalfDegree($norm);

            // 呼び径抽出（数字+L/H/M含む文字列丸ごと）
            $cleanedCallSizes = []; // L/H/M除外した数字のみの配列

            // === 規格・圧力コード抽出（×も含めて削除） ===
            [$norm, $removedWords] = DocumentAiHelperService::extractAll($norm);

            //呼び径抽出
            [$norm, $cleanedCallSizes] = DocumentAiHelperService::extractCallSizes($norm);

            // 先頭の 0 を除去（ただし "0" 単体にはならないよう注意）
            $cleanedCallSizes = DocumentAiHelperService::cleanCallSizes($cleanedCallSizes);

            //戻り値
            return [
                // 'productName' => $productName,
                'size1' => $cleanedCallSizes[0] ?? '',//呼び径1
                'size2' => $cleanedCallSizes[1] ?? '',//呼び径2
                'size3' => $cleanedCallSizes[2] ?? '',//呼び径3
                'removedWords' => $removedWords,//除去ワード（商品名に加える）
            ];

        } catch (\Throwable $e) {
            $log->error('extractCallSizeFromText エラー: ' . $e->getMessage());
            return [
                // 'productName'   => trim($text),
                'size1'         => null,
                'size2'         => null,
                'size3'         => null,
                'removedWords'  => [],
            ];
        }
    }


}