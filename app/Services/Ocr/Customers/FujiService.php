<?php
// namespace App\Services;
namespace App\Services\Ocr\Customers;
use App\Services\Ocr\DocumentAiHelperService;
use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;


//========================
//冨士機材
//========================
class FujiService
{
    // ------------------
    // 注文情報抽出
    // ------------------
    public static function fujiOrderInfo(string $text, Document $document): array
    {
        $log = Log::channel('orders_daily');//ログ

        try {
            //初期化
            $orderNumber = $customerName = $shippingName = $dueDate = $saturday = $siteName = $shippingNumber = null;

            // 1. 発注番号の抽出　[発注番号:]の横にある英数字（例：SS072707）
            if (preg_match('/発注番号\s*[:：]?\s*([A-Z0-9]+)/u', $text, $m)) {
                $orderNumber = trim($m[1]);
            }

            // 2. 得意先名の抽出　→ 「冨士機材株式会社 ◯◯支店」という表記のうち「◯◯支店」だけを取得し、会社名と結合
            if (preg_match('/[冨富]士機材\s*株式\s*会社\s*(本社|.+支店|.+営業所|.+営業部|.+出張所|.+業務部|.+センター|共立全社|商品本部\s*管材部)/u', $text, $m)) {
                $customerName = '冨士機材株式会社 ' . trim($m[1]);
            }
            //共立株式会社
            elseif (preg_match('/共立\s*株式\s*会社\s*(本社|.+支店|.+営業所)/u', $text, $m)) {
                $customerName = '共立株式会社 ' . trim($m[1]);
            }

            // 3. 出荷先名の抽出 → [納入場所] のラベルのすぐ右に記載された住所や会社名 
            $shippingName = null;


            if (preg_match('/納入場所\s*[>：]?\s*\n?(.+)/u', $text, $m)) {

                $firstLine = trim($m[1]);

                // 数字・半角正規化（最初にやる）
                $firstLineNorm = mb_convert_kana($firstLine, 'asKVn');

                // 郵便番号か判定（〒 / T 両対応）
                $isZipOnly = preg_match('/^\s*[T〒]?\s*\d{3}-\d{4}\s*$/u', $firstLineNorm);

                // 郵便番号正規化（T → 〒）
                $normalizedZip = null;
                if ($isZipOnly) {
                    $normalizedZip = preg_replace('/^\s*T\s*/u', '〒', $firstLineNorm);
                }

                // ------------------
                // ① 郵便番号じゃない
                // ------------------
                if (!$isZipOnly) {

                    $shippingName = preg_replace(
                        [
                            '/御中/u',
                            '/(?:\(?株\)?|株式会社|㈱)\s*[ﾊハ][ｽズ][ﾞ゛]?/u',
                            '/[ﾊハ][ｽズ][ﾞ゛]?/u',
                            '/[>|]/u',
                            '/\s+/u'
                        ],
                        [
                            '',
                            '',
                            '',
                            '',
                            ' '
                        ],
                        $firstLineNorm
                    );

                // ------------------
                // ② 郵便番号だけ
                // ------------------
                } else {

                    if (preg_match(
                        '/納入場所\s*[>：]?\s*((?:.|\n)+?)(?:様分|顧客名)/u',
                        $text,
                        $mm
                    )) {

                        $norm = mb_convert_kana($mm[1], 'asKVn');

                        // ★ 既存の郵便番号を完全除去
                        $norm = preg_replace(
                            '/\b[T〒]\s*[0-9０-９]{3}[-－ー][0-9０-９]{4}\b/u',
                            '',
                            $norm
                        );

                        // ★ 正規化した郵便番号を1つだけ付与
                        $norm = $normalizedZip . ' ' . $norm;

                        $shippingName = preg_replace(
                            [
                                '/御中/u',
                                '/(?:\(?株\)?|株式会社|㈱)\s*[ﾊハ][ｽズ][ﾞ゛]?/u',
                                '/[ﾊハ][ｽズ][ﾞ゛]?/u',
                                '/[>|]/u',
                                '/\s+/u'
                            ],
                            [
                                '',
                                '',
                                '',
                                '',
                                ' '
                            ],
                            trim($norm)
                        );
                    }
                }
                $shippingName = isset($shippingName) ? trim($shippingName) : null;
            }


            $dueDates = [];

            // 4. 納期（優先②）→ テーブルの中の [納期] カラムの値を見つけて取得　（備考で取得できなかった場合）
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {
                    $headerRows = $table->getHeaderRows();
                    $bodyRows = $table->getBodyRows();

                    if (empty($headerRows) || !isset($headerRows[0])) {
                        $log->info('スキップ: ヘッダー行が存在しない');              
                        continue;
                    }

                    // テーブルのヘッダー列（1行目）から [納期] カラムの位置を特定
                    $headerTexts = [];
                    foreach ($headerRows[0]->getCells() as $i => $cell) {
                        $label = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());
                        $headerTexts[$i] = preg_replace('/\s+/u', '', $label);
                    }

                    // 納期列のインデックス特定
                    $dueCol = null;
                    foreach ($headerTexts as $i => $label) {
                        if (preg_match('/納期|納|期/u', $label)) {
                            $dueCol = $i;
                            // $log->info('$dueCol:'.$dueCol);              
                            break;
                        }
                    }

                    // --- ① 納期カラムから取得 ---
                    // テーブル行から該当カラムのデータを抽出し、日付形式なら納期としてセット
                    if ($dueCol !== null) {
                        foreach ($bodyRows as $row) {
                            $cells = $row->getCells();
                            if (!isset($cells[$dueCol])) continue;

                            $rawDate = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$dueCol]->getLayout()->getTextAnchor());

                            // 改行・余分な空白を整理
                            $raw = preg_replace('/\s+/u', ' ', trim($rawDate));

                            // 管理No（例: 2501130886-1）を除去
                            $raw = preg_replace('/\d+-\d+/u', '', $raw);
                            $raw = trim($raw);

                            // YYYY/MM/DD が含まれていない場合はスキップ（YYYY/MM は除外）
                            if (!preg_match('/\d{4}\/\d{1,2}\/\d{1,2}/u', $raw, $m)) {
                                continue;
                            }

                            // 日付だけ抽出
                            $dateOnly = $m[0];

                            $formatted = DocumentAiHelperService::formatDateToNumber($dateOnly, $saturday);

                            if ($formatted) {
                                $dueDates[] = $formatted;
                                // break 2; // 最初の1件でOK
                                break 3; // 最初の1件でOK
                            }

                        }                        
                    }
                }
            }


            // テーブルに納期がない場合
            if (empty($dueDates)) {

                $remarkBlocks = [];

                // 備考
                if (preg_match('/備考\s*[:：]?\s*(.+?)(\n|$)/u', $text, $m)) {
                    $remarkBlocks[] = trim($m[1]);
                }

                // 通信欄（複数行想定）
                if (preg_match('/通信欄\s*(.+?)(?:\n\S|$)/us', $text, $m)) {
                    $remarkBlocks[] = trim($m[1]);
                }


                foreach ($remarkBlocks as $remarksText) {

                    // ノイズ除外
                    if (
                        mb_strpos($remarksText, '管理No') !== false ||
                        mb_strpos($remarksText, '数量') !== false ||
                        // mb_strpos($remarksText, 'No') !== false || 
                        // mb_strpos($remarksText, 'NO') === false
                        preg_match('/\bNo\b/i', $remarksText) ||
                        preg_match('/\bNO\b/i', $remarksText)
                    ) {
                        continue;
                    }
                    // ★ 日付っぽい表現を抽出
                    if ($dateLike = self::extractDateLikeText($remarksText)) {
                        $dueDates[] = $dateLike;
                        break; // 最優先なので1件取れたら終了
                    }
                }
            }


            //　納期取得
            $dueDate = !empty($dueDates) ? DocumentAiHelperService::formatDateToNumber(min($dueDates), $saturday) : null; 
            //  出荷日
            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;


            // 5. 出荷先用番号（[先方注番]の列）：9桁数字の右にある番号や文字列
            if (preg_match('/\b\d{9}\s+([^\n]+)/u', $text, $m)) {

                // 9桁の数字の後のテキストを取得して整形
                $line = trim(preg_replace('/\s+/u', ' ', $m[1]));

                //発注番号が含まれていたら削除
                $line = preg_replace('/発注\s*(番号|No)\s*[:：]?\s*[A-Za-z0-9_-]+/u', '', $line);
                // 「管理N」が含まれていたら全体削除
                if (mb_strpos($line, '管理N') !== false) {
                    $line = '';
                }
                // 日付を除外
                if (preg_match('/\b\d{4}[\/／]\d{1,2}[\/／]\d{1,2}\b/u', $line)) {
                    $line = '';
                }
                // 再トリムして余分な空白を除去
                $shippingNumber = trim($line);

            }

            // 6. 営業用備考（[現場名] の横）：次行が「備考」でなければ現場名として利用
            if (preg_match('/現場名\s*[:：]?\s*([^\n]*)/u', $text, $m)) {
                $line = trim($m[1]);

                if ($line !== '' && !preg_match('/^備考/u', $line) && !preg_match('/^発注番号/u', $line)) {
                    // 「様」だけ、または「〇〇様」の場合は除外（様だけを null に）
                    if (preg_match('/^様$/u', $line)) {
                        $siteName = null;
                    } else {
                        $siteName = $line;
                    }
                }
            }

            // [共通]　出荷先に「当社」または「弊社」が含まれていたら得意先名に置き換え
            $shippingName = DocumentAiHelperService::formatshippingName($customerName ?? '', $shippingName ?? '');

            // ===== 結果を返却 =====
            return [
                'orderNumber'      => $orderNumber,//注文番号
                'customerName'     => $customerName,//得意先
                'shippingName' => $shippingName,//出荷先
                'shipDate'         => $shipDate,//出荷日
                'dueDate'          => $dueDate,//納期
                'saturday'          => $saturday,//土曜指定
                'shippingNumber'      => $shippingNumber,//出荷先用
                'salesBikou'       => $siteName,//営業用備考（現場名）
            ];

        } catch (\Throwable $e) {
            // エラー内容をログ出力して終了
            $log->error('fujiOrderInfo 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            // 上位（コマンド側）でDB保存・PDF移動を継続させるため再スロー
            throw $e;
        }

    }



    // ------------------
    // 商品明細抽出
    // ------------------
    public static function fujiOrderItems(string $text, Document $document): array 
    {

        $log = Log::channel('orders_daily'); // ← これ必須！
        $items = [];
        $seenTables = []; // ← ページループの外に移動（重要）
        $productNamesFromText = [];//商品名取得

        try {

            foreach ($document->getPages() as $pageIndex => $page) { // PDF ドキュメント内の すべてのページをループ処理します

                // ★ ここでページ単位に作る
                $productNamesFromText = self::extractProductNamesFromPageText($page, $text);

                // $log->info("Page {$pageIndex} products="
                //     . json_encode($productNamesFromText, JSON_UNESCAPED_UNICODE)
                // );

                foreach ($page->getTables() as $tableIndex => $table) { //そのページ内にある すべてのテーブルをループ処理します。

                    // テーブルテキストを一意化キーにする
                    $tableText = trim(DocumentAiHelperService::extractTextFromAnchor($text, $table->getLayout()->getTextAnchor()));
                    $length = mb_strlen($tableText);

                    // // ← ★ここにログを入れる
                    // $log->info(sprintf(
                    //     'Page=%d Table=%d length=%d',
                    //     $pageIndex,
                    //     $tableIndex,
                    //     mb_strlen($tableText)
                    // ));
                    // $log->info('TABLE_TEXT: ' . str_replace("\n", ' / ', mb_substr($tableText, 0, 300)));

                    // 長さが小さい（＝空や断片）テーブルはスキップ
                    if ($length < 100) { // ← 適宜調整。100文字以下ならゴミ扱いでOK
                        // $log->info("空または断片テーブルをスキップ（length={$length}）");
                        continue;
                    }
                    if (in_array($tableText, $seenTables, true)) {
                        // $log->info('⚠ 同一テーブルをスキップ');
                        continue;
                    }
                    $seenTables[] = $tableText;

                    //確認用
                    // $log->info("Table text hash: " . md5($tableText));
                    // $log->info("Table length: " . mb_strlen($tableText));

                    try {
                        $headerRows = $table->getHeaderRows();// ヘッダー取得
                        $bodyRows = [];//テーブルの明細データ（行ごとの商品情報など）を保持する配列です。
                        $headerTexts = [];//テーブルのヘッダー（列名）を保持する配列

                        if (count($headerRows) === 0 && count($table->getBodyRows()) > 0) {
                            // ヘッダーがない場合、1行目をヘッダー扱いにして整形
                            $firstBodyRow = $table->getBodyRows()[0];
                            foreach ($firstBodyRow->getCells() as $i => $cell) {
                                $headerTexts[$i] = mb_ereg_replace('\\s+', '', DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor()));
                            }
                            $bodyRows = array_slice($table->getBodyRows(), 1);
                        } else {
                            if (count($headerRows) > 0 && count($headerRows[0]->getCells()) > 0) {
                                foreach ($headerRows[0]->getCells() as $i => $cell) {
                                    $headerTexts[$i] = mb_ereg_replace('\\s+','',DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor()));
                                }
                            }
                            $bodyRows = $table->getBodyRows();
                        }

                        // ===== ヘッダーから列インデックス（商品名・数量）を特定 =====                
                        $productCol = $qtyCol = null;

                        foreach ($headerTexts as $i => $label) {
                            // 改行をまたぐケース対応 + 空白除去 + 表記ゆれ対応
                            $lines = preg_split('/\R/u', $label);
                            foreach ($lines as $line) {
                                $line = trim($line); // 空白除去
                                if ($line === '') continue;

                                if (preg_match('/商品名/u', $line)) $productCol = $i;
                                if (preg_match('/数量/u', $line))   $qtyCol     = $i;
                            }
                        }


                        // --- 本文ループ ---
                        foreach ($bodyRows as $rowIndex => $row) {
                            try {
                                $cells = $row->getCells();

                                // ログで行全体を確認
                                $rowTexts = [];
                                foreach ($cells as $cellIndex => $cell) {
                                    $cellText = trim(DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor()));
                                    $rowTexts[$cellIndex] = $cellText;
                                }

                                //===========
                                // 1.商品名
                                //===========
                                // ★ ここで$productNamesFromTextから1件消費
                                $productName = array_shift($productNamesFromText);

                                if (empty($productName)) continue;//商品名がなければスキップ

                                //===========
                                // 2.数量
                                //===========
                                $rawQty = $qtyCol !== null && $qtyCol < count($cells)
                                    ? trim(DocumentAiHelperService::extractTextFromAnchor($text, $cells[$qtyCol]->getLayout()->getTextAnchor()))
                                    : '';

                                // $log->info("▶ テーブル行{$rowIndex}: product={$productName}, rawQty={$rawQty}");//確認用

                                $rawQty = str_replace(['Ⅰ', 'l', 'ｌ', 'Ｉ'], '1', $rawQty);//記号を1に変換
                                preg_match('/\d+/', $rawQty, $m);//1桁以上の数字（0〜9）が連続した部分を探す
                                $quantity = $m[0] ?? null;
                                $quantity = mb_convert_kana($quantity, 'n');//全角→半角

                                //===========
                                // 3.呼び径（商品名から抽出）
                                //===========
                                $sizes = [];

                                // 呼び径抽出（セル全文）
                                $size = self::extractCallSizeFromText($productName) ?? [
                                    'productName' => '',
                                    // 'callSize'     => '',
                                    'size1'        => '',
                                    'size2'        => '',
                                    'size3'        => '',
                                    'removedWords' => [],
                                ];

                                $productName = DocumentAiHelperService::normalizeProductName($size['productName'], false);


                                //結果
                                $items[] = [
                                    '商品名'  => $productName,
                                    '呼び径1' => $size['size1'],
                                    '呼び径2' => $size['size2'],
                                    '呼び径3' => $size['size3'],
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

            // 「送料無」があれば送料系は一切追加しない
            if (mb_strpos($text, '送料無') !== false) {
                // $log->info('送料無のため送料処理スキップ');
            } else {
                $shippingPatterns = [
                    ['keyword' => '送料', 'product' => 'ハズ 送料',],
                    ['keyword' => '荷造運賃', 'product' => '荷造運賃',],
                ];

                foreach ($shippingPatterns as $sp) {

                    $hasInText = mb_strpos($text, $sp['keyword']) !== false;
                    $hasInList = !empty(array_filter(
                        $productNamesFromText,
                        fn($p) => mb_strpos($p, $sp['keyword']) !== false
                    ));

                    // $log->info('D: flags', [
                    //     'keyword' => $sp['keyword'],
                    //     'hasInText' => $hasInText,
                    //     'hasInList' => $hasInList,
                    //     'productNamesFromText' => $productNamesFromText,
                    // ]);

                    if ($hasInText && !$hasInList) {
                        $items[] = [
                            '商品名'  => $sp['product'],
                            '呼び径'  => '',
                            '呼び径1' => '',
                            '呼び径2' => '',
                            '呼び径3' => '',
                            '数量'    => '1',
                            '備考'    => null,
                        ];
                    }

                }
            }
            

            //確認用
            // $log->info('items1: ' . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            // $items = array_values(array_map('unserialize', array_unique(array_map('serialize', $items))));// 最後に重複除去（保険）
            // $log->info('hasShippingInText: ' . ($hasShippingInText ? 'true' : 'false'));
            // $log->info('hasShippingInList: ' . json_encode($hasShippingInList, JSON_UNESCAPED_UNICODE));
            // $log->info('items2: ' . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $items;

        } catch (\Throwable $e) {
            $log->error('fujiOrderItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e; // 上位 に伝播
        }

    }


    // ------------------
    // 富士機材用 商品名、呼び径抽出
    // ------------------
    private static function extractCallSizeFromText(string $text): array
    {
        $norm = DocumentAiHelperService::normalizeText($text, $flg1 = false);

        // 入力正規化
        $norm = preg_replace('/^[ﾞﾟ_ー―‐\-〜〜・.,\s]+/u', '', $norm);// 先頭アンダースコアを除去                
        $norm = preg_replace('/形式\d+(?:\.\d+)?K?/u', '', $norm);// 品番や「形式」など不要なキーワード除去
        $norm = preg_replace('/\d{6,}/u', '', $norm);// 6桁以上の数字を商品名から除去（商品コードなど）
        //置換
        $norm  = str_replace('押輪輪·T頭BN', '押輪·ゴム輪・T頭BN', $norm);
        
        // 呼び径抽出（数字+L/H/M含む文字列丸ごと）
        $callSizes = [];
        $rawCallSize = '';
        $cleanedCallSizes = []; // L/H/M除外した数字のみの配列
        //除去ワード配列
        $removedWords = [];

        // 角度（×あり・なし両方対応　removedWords へ）
        $angleValues = ['90','45','22','11','5','05'];// 対象角度：90, 45, 22, 11, 5, 05

        foreach ($angleValues as $a) {

            // ×付き
            $patternWithX = '/×\s*' . preg_quote($a, '/') . '\b/u';
            if (preg_match_all($patternWithX, $norm, $mm)) {
                foreach ($mm[0] as $match) {
                    $removedWords[] = ltrim(str_replace('×','',$match), '0') ?: '0';
                }
            }
            $norm = preg_replace($patternWithX, '', $norm);

            // 単体（×なし）
            $patternSingle = '/(?<!\d)\s*' . preg_quote($a, '/') . '\b(?!\d)/u';
            if (preg_match_all($patternSingle, $norm, $mm2)) {
                foreach ($mm2[0] as $match2) {
                    $val = trim($match2);
                    $removedWords[] = ltrim($val, '0') ?: '0';
                }
            }
            $norm = preg_replace($patternSingle, '', $norm);
        }

        // 規格・圧力コード（×ごと除去し removedWords へ）
        foreach ([
            '/×\s*K\d+/iu',
            '/×\s*\d+K/u',
            '/×\s*G[F]?\d+(\.\d+)?K?/iu',
            '/×\s*RF\d+(\.\d+)?K?/iu',
            '/×\s*R\d+(\.\d+)?/iu',
            '/×\s*7\.5/iu',
        ] as $pattern) {
            if (preg_match_all($pattern, $norm, $mm)) {
                foreach ($mm[0] as $match) $removedWords[] = trim(str_replace('×', '', $match));
            }
            $norm = preg_replace($pattern, '', $norm);
        }


        // パターン1: M20×110（Mボルトなど）
        if (preg_match('/M\d{1,4}×\d{1,4}/u', $norm, $m)) {
            $rawCallSize = $m[0];
            $norm = str_replace($rawCallSize, '', $norm);
            $callSizes = explode('×', $rawCallSize);
            $cleanedCallSizes = $callSizes; // Mはそのまま残す
        }
        // パターン2: 100×H300, 75×L300（数字×H/L+数字）
        elseif (preg_match('/\d{1,4}×[LH]\d{1,4}/u', $norm, $m)) {
            $rawCallSize = $m[0];
            $norm = str_replace($rawCallSize, '', $norm);
            $callSizes = explode('×', $rawCallSize);
            foreach ($callSizes as $size) {
                $cleanedCallSizes[] = preg_replace('/[LH]/i', '', $size);
            }
        }
        // パターン3: 100×300H,75×300L×G7.5（数字×数字+L×規格）
        elseif (preg_match('/\d{1,4}×\d{1,4}[LH]/u', $norm, $m)) {
            $rawCallSize = $m[0];
            $norm = str_replace($rawCallSize, '', $norm);
            $callSizes = explode('×', $rawCallSize);
            foreach ($callSizes as $size) {
                $cleanedCallSizes[] = preg_replace('/[LH]/i', '', $size);
            }
        }
        // パターン4: 100×RF10K など（単一サイズ+規格コード）
        elseif (preg_match('/\d{1,4}(?=×(?:RF|GF?|R|K)\d)/u', $norm, $m)) {
            $rawCallSize = $m[0];
            $norm = str_replace($rawCallSize . '×', '', $norm);
            $cleanedCallSizes = [$rawCallSize];
        }
        // パターン5: 50×40×30 など（通常の複数サイズ）
        elseif (preg_match('/\d{1,4}×\d{1,4}(?:×\d{1,4})?/u', $norm, $m)) {
            $rawCallSize = $m[0];
            $norm = str_replace($rawCallSize, '', $norm);
            $callSizes = explode('×', $rawCallSize);
            $cleanedCallSizes = $callSizes;
        }
        // パターン6: 単独数値（漢字や文字に隣接していてもOKにする）
        elseif (preg_match_all('/(?<![A-Za-z0-9])(\d{2,4})(?![A-Za-z0-9])/u', $norm, $matches)) {
            foreach ($matches[1] as $candidate) {
                // 数字の後ろが "K" や小数などなら除外
                if (preg_match('/' . preg_quote($candidate, '/') . '(?:\.\d+|K)/u', $norm)) continue;

                $rawCallSize = $candidate;
                $norm = str_replace($rawCallSize, '', $norm);
                $callSizes = [$rawCallSize];
                $cleanedCallSizes = $callSizes;
                break;
            }
        }
        
        // 商品名整形（既に呼び径は削除済み）
        $productName = trim($norm);

        // 単独ドット除去
        $productName = preg_replace('/(?:^|\s)[\.．。](?=\s|$)/u', ' ', $productName);
        // 末尾ドット除去
        $productName = preg_replace('/[\.．。]+$/u', '', $productName);

        // 規格・圧力コードを商品名の後ろに追加
        if (!empty($removedWords)) {
            $productName = trim($productName . implode('', $removedWords));//空白なし
        }

        //連続する半角スペースを1つにまとめる
        $productName = preg_replace('/\s{2,}/u', ' ', trim($productName));

        // 呼び径（最大3つ） 
        $cleanedCallSizes = DocumentAiHelperService::cleanCallSizes($cleanedCallSizes);


        //戻り値
        return [
            'productName' => $productName,
            'size1' => $cleanedCallSizes[0] ?? '',//呼び径1
            'size2' => $cleanedCallSizes[1] ?? '',//呼び径2
            'size3' => $cleanedCallSizes[2] ?? '',//呼び径3
            'removedWords' => $removedWords,//除去ワード（商品名に加える）
        ];
    }





    // ------------------
    // テキスト全文から正確な商品名＋呼び径を抽出（テーブルだと商品名が壊れているため）
    // ------------------
    private static function extractProductNamesFromPageText(
        \Google\Cloud\DocumentAI\V1\Document\Page $page,
        string $fullText
    ): array {

        $pageText = DocumentAiHelperService::extractTextFromAnchor(
            $fullText,
            $page->getLayout()->getTextAnchor()
        );

        $pageText = mb_convert_kana($pageText, 'as'); //全角→半角
        $lines = preg_split("/\R/u", $pageText);

        $products = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^\s*\(株\)/u', $line)) continue;

            //商品セル抽出
            if (preg_match('/.*?[ﾊハ][ｽス](?:゛|ﾞ)?\s*(.+?)$/u', $line, $m)) {
                $detected = trim('ハズ ' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出1: {$detected}");
            } 
            elseif (preg_match('/(?:ﾊｽﾞ|ハズ|ﾊｽﾞ|ハｽﾞ|ハス|ﾊズ)\s*(.+)/u', $line, $m)) {
                $detected = trim('ハズ ' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出2: {$detected}");
            }
            elseif (preg_match('/.*?小ス\s*(.+?)$/u', $line, $m)) {
                $detected = trim('ハズ ' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出3: {$detected}");
            } elseif (preg_match('/.*?ス°\s*(.+?)$/u', $line, $m)) {
                $detected = trim('ハズ ' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出4: {$detected}");
            } elseif (preg_match('/.*?GX\s*(.+?)$/u', $line, $m)) {
                $detected = trim('GX' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出5: {$detected}");
            } elseif (preg_match('/.*?K形\s*(.+?)$/u', $line, $m)) {
                $detected = trim('K形' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出6: {$detected}");
            } elseif (preg_match('/.*?F形\s*(.+?)$/u', $line, $m)) {
                $detected = trim('F形' . $m[1]);
                $products[] = $detected;
                // $log->info("★商品検出7: {$detected}");
            }
        }
        return array_values($products);
    }

    
    //日付らしきもの
    private static function extractDateLikeText(string $text): ?string
    {
        $patterns = [
            // ① YYYY年MM月DD日（最優先・日本語完全日付）
            '/\b\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日\b/u',

            // ② YYYY/MM/DD, YYYY-MM-DD
            '/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\b/u',

            // ③ MM月DD日（年なし日本語）
            '/\b\d{1,2}\s*月\s*\d{1,2}\s*日\b/u',

            // ④ MM/DD（年なし）
            '/\b\d{1,2}[\/\-]\d{1,2}\b/u',

            // ⑤ YYYY/MM（年月）
            '/\b\d{4}[\/\-]\d{1,2}\b/u',

            // ⑥ 月＋旬
            '/\b\d{1,2}\s*月\s*(初旬|上旬|中旬|下旬|半ば)\b/u',

            // ⑦ 即納・急ぎ系（最後）
            '/\b(即日|当日|本日|至急|早急|最短|最短納期)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[0]);
            }
        }

        // return $text;
        return null;
    }


}