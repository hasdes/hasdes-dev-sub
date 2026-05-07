<?php
// namespace App\Services;
namespace App\Services\Ocr;
use Google\Cloud\DocumentAI\V1\Document;
use Yasumi\Yasumi;//API
use Carbon\Carbon;//日付判定
use Illuminate\Support\Facades\Log;//ログ出力

//===============================
// 全共通関数
// Google Document AI　OCR 
// ヘッダー
// 明細
//===============================

class DocumentAiHelperService
{
    // --------------------------
    // Google Document AI の「TextAnchor」情報をもとに、元の documentText（ページ全体のテキスト）から対象文字列を抜き出す関数
    // --------------------------
    public static function extractTextFromAnchor(string $documentText, $textAnchor): string
    {
        //テキストがリンクされていない状態なので警告ログを出して空文字を返す。
        if ($textAnchor === null) {
            return '';
        }

        //TextAnchor の TextSegment が空の場合
        $segments = $textAnchor->getTextSegments();
        if (count($segments) === 0) {
            return '';
        }

        $result = '';

        // Document AI が指示するインデックス範囲（start 〜 end）を元に、元テキストから文字列を抜き出して連結。
        foreach ($segments as $segment) {
            $startIndex = $segment->getStartIndex();
            $endIndex = $segment->getEndIndex();
            if ($endIndex > $startIndex) {
                $result .= mb_substr($documentText, $startIndex, $endIndex - $startIndex);
            }
        }

        return $result;
    }

    // --------------------------
    // Google Document AI　Form Parser（テーブル形式）　確認用
    // --------------------------
    public static function layoutText($document, $textAnchor): string
    {
        $textSegments = $textAnchor->getTextSegments();
        $fullText = $document->getText();
        $output = [];

        foreach ($textSegments as $segment) {
            $start = $segment->getStartIndex();
            $end = $segment->getEndIndex();
            $output[] = mb_substr($fullText, $start, $end - $start);
        }

        return trim(implode('', $output));
    }



    // --------------------------
    // 納期判定　（Ymd変更、上旬中旬下旬の日付変換）
    // --------------------------
    public static function formatDateToNumber(string $dateStr, ?string &$saturday = null): string
    {
        $log = Log::channel('orders_daily');//ログ設定

        $saturday = null; // 初期化
        $s = mb_convert_kana($dateStr, 'as'); // 全角→半角にする
        $s = preg_replace('/[年月日]/u', '/', $s);
        $s = preg_replace('/[－―ー]/u', '-', $s);
        $s = str_replace('／', '/', $s);
        $s = preg_replace('/\s*(AM|PM)\s*/iu', '', $s);
        $s = trim($s);

        //  1. 和暦 → 西暦
        $warekiMap = ['令和' => 2018, '平成' => 1988];
        foreach ($warekiMap as $era => $offset) {
            if (preg_match("/{$era}(\d+)[\/\-年]/u", $s, $m)) {
                $year = $offset + (int)$m[1];
                $s = preg_replace("/{$era}\d+[\/\-年]?/u", "{$year}/", $s);
                break;
            }
        }
        
        // --- 2. 「上旬・中旬・下旬」対応. 例）12月上旬 → XX月05日 など（土日祝はずらす） ---
        if (preg_match('/(\d{1,2})\s*[\/月]\s*(上旬|中旬|下旬|初旬|半ば)/u', $s, $m)) {
            $month = (int)$m[1];
            $range = $m[2];
            $year  = (int)date('Y');

            if ($month < (int)date('n')) {
                $year += 1; // 翌年扱い
            }

            $day = match ($range) {
                '初旬', '上旬' => 5,
                '中旬' => 15,
                '半ば' => 15,
                '下旬' => 25,
                default => 15,
            };

            $ymd = sprintf('%04d%02d%02d', $year, $month, $day);
            $date = Carbon::createFromFormat('Ymd', $ymd);

            // Yasumi初期化
            $holidays = null;
            if (class_exists(\Yasumi\Yasumi::class)) {
                try {
                    $holidays = \Yasumi\Yasumi::create('Japan', $year);
                } catch (\Throwable $e) {
                    $log->warning('Yasumi 初期化失敗', ['error' => $e->getMessage()]);
                }
            } else {
                $log->warning('Yasumi ライブラリが存在しません（祝日判定スキップ）');
            }

            // 土日祝なら後ろへずらす
            if ($holidays) {
                while ($date->isWeekend() || $holidays->isHoliday($date)) {
                    $date->addDay();
                }
            } else {
                while ($date->isWeekend()) {
                    $date->addDay();
                }
            }

            $ymd = $date->format('Ymd');

            return $ymd;
        }


        //  3. YYYY/MM/DD or YYYY-MM-DD
        if (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $s, $m)) {
            [$y,$mo,$d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            if (checkdate($mo, $d, $y)) {
                $ymd = sprintf('%04d%02d%02d', $y, $mo, $d);

                //土曜判定
                if (self::isSaturday($ymd)) {
                    $saturday = $ymd;
                }
                return $ymd;
            }
            return '';
        }

        //  4. MM/DD or M/D → 年を補完（OCR補正も）
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})\b/', $s, $m)) {
            $currentYear  = (int)date('Y');
            $currentMonth = (int)date('n');
            $month = (int)$m[1];
            $day  = (int)$m[2];

            // ★ OCR補正: 月が13以上 → 先頭の1を削る
            if (isset($m[1], $m[1][0]) && $month >= 13 && $m[1][0] === '1') {
                $month = (int)ltrim(substr($m[1], 1), '0'); // "19" → "9"
                if ($month === 0) $month = 1; // ltrimでゼロになる場合の保険
            }

            $year = ($month < (int)date('n')) ? (int)date('Y') + 1 : (int)date('Y');


            if (checkdate($month, $day, $year)) {
                $ymd = sprintf('%04d%02d%02d', $year, $month, $day);

                //土曜判定
                if (self::isSaturday($ymd)) {
                    $saturday = $ymd;//土曜指定
                }
                return $ymd;
            }
        }

        // すべて失敗した場合 → 元の文字列をそのまま返す
        return $dateStr;    
    }


    // --------------------------
    // 納期：土曜判定
    // --------------------------
    public static function isSaturday(string $ymd): bool
    {
        $log = Log::channel('orders_daily');//ログ設定

        try {
            // \Log::info('Ymd:'.$ymd);
            return Carbon::createFromFormat('Ymd', $ymd)->isSaturday();
            
        } catch (\Exception $e) {
            // return false;
            $log->info('false');
            return '';
        }
    }

    // --------------------------
    // 出荷日を納期の「前営業日」にする（本来の意味での出荷判定）
    // --------------------------
    public static function getDay(string $ymd): string
    {
        $log = Log::channel('orders_daily');//ログ

        // 値が空、数字のフォーマットじゃない場合、そのまま返す
        if (empty($ymd) || !preg_match('/^\d{8}$/', $ymd)) {
            return $ymd;
        }
        
        // 全角 → 半角変換（数字・記号含む）
        $ymd = mb_convert_kana($ymd, 'as');

        try {
            $date = Carbon::createFromFormat('Ymd', $ymd);
            // $holidays = Yasumi::create('Japan', (int)$date->format('Y'));

            // Yasumi存在チェックを先頭で
            if (!class_exists(\Yasumi\Yasumi::class)) {
                $log->warning('Yasumi ライブラリが存在しません（祝日判定をスキップ）');
                return $ymd;
            }

            //Yasumiで祝日判定
            try {
                $holidays = Yasumi::create('Japan', (int)$date->format('Y'));
            } catch (\Throwable $e) {
                $log->warning('Yasumi 初期化失敗', ['error' => $e->getMessage()]);
                $holidays = new class {
                    public function isHoliday() { return false; }
                };
            }

            // 納期の前日からさかのぼる
            $date->subDay(); // まず前日へ

            while ($date->isWeekend() || $holidays->isHoliday($date)) {
                $date->subDay(); // 前営業日までさかのぼる
            }

            return $date->format('Ymd');

        } catch (\Exception $e) {
            return $ymd;
        }
    }


    // --------------------------
    // 翌営業日を求める
    // --------------------------
    public static function getNextBusinessDay(string $ymd): string
    {
        $log = Log::channel('orders_daily');

        if (empty($ymd) || !preg_match('/^\d{8}$/', $ymd)) {
            return $ymd;
        }

        try {
            $date = Carbon::createFromFormat('Ymd', $ymd);

            // Yasumi（祝日判定）初期化
            if (!class_exists(\Yasumi\Yasumi::class)) {
                $log->warning('Yasumi ライブラリが存在しません（祝日判定スキップ）');
                return $ymd;
            }

            try {
                $holidays = Yasumi::create('Japan', (int)$date->format('Y'));
            } catch (\Throwable $e) {
                $log->warning('Yasumi 初期化失敗', ['error' => $e->getMessage()]);
                $holidays = new class {
                    public function isHoliday() { return false; }
                };
            }

            // 翌営業日を算出
            $date->addDay();
            while ($date->isWeekend() || $holidays->isHoliday($date)) {
                $date->addDay();
            }

            return $date->format('Ymd');

        } catch (\Exception $e) {
            return $ymd;
        }
    }


    // --------------------------
    // 出荷先名の判定　（当社や弊社が含まれていたら、得意先と一緒にする）
    // --------------------------
    public static function formatshippingName(string $customerName, string $shippingName): string
    {
        if (!empty($shippingName) && (mb_strpos($shippingName, '当社') !== false || mb_strpos($shippingName, '弊社') !== false)) {
            $shippingName = $customerName;
        }
        return $shippingName;
    }



    /*************
     * 明細（商品）
     *************/

    // --------------------------
    // ヘッダー処理（なければ1行目を疑似ヘッダーに）
    // --------------------------
    public static function extractTableHeaderAndBodyRows($table, $headerRows, $text)
    {
        $headerTexts = [];
        $bodyRows = [];

        // ヘッダーがない場合 → 1行目を疑似ヘッダー
        if (count($headerRows) === 0 && count($table->getBodyRows()) > 0) {

            $firstBodyRow = $table->getBodyRows()[0];

            foreach ($firstBodyRow->getCells() as $i => $cell) {
                $cellText = DocumentAiHelperService::extractTextFromAnchor(
                    $text,
                    $cell->getLayout()->getTextAnchor()
                );

                $headerTexts[$i] = preg_replace('/\s+/u', '', $cellText);
            }

            // 2行目以降をデータ行
            $bodyRows = array_slice($table->getBodyRows(), 1);

        } else {

            // 通常のbody
            $bodyRows = $table->getBodyRows();

            // ヘッダー行がある場合
            if (count($headerRows) > 0 && count($headerRows[0]->getCells()) > 0) {

                foreach ($headerRows[0]->getCells() as $i => $cell) {

                    $cellText = DocumentAiHelperService::extractTextFromAnchor(
                        $text,
                        $cell->getLayout()->getTextAnchor()
                    );

                    $headerTexts[$i] = preg_replace('/\s+/u', '', $cellText);
                }
            }
        }

        return [$headerTexts, $bodyRows];
    }

    // --------------------------
    // 入力正規化
    // --------------------------
    public static function normalizeText(string $text, $flg1): string
    {
        if ($text === '') return '';

        // ① 基本正規化  全角→半角（カタカナは全角）
        $norm = mb_convert_kana($text, 'asKV');

        // ② 記号系
        if ($flg1) {
            $norm = preg_replace(['/[\ＸxｘX]/u', '/\s*×\s*/u'], '×', $norm); // ×に置換、×の前後の空白を除去(ＸxｘX)
        } else{
            $norm = preg_replace(['/[\*＊ＸxｘX]/u', '/\s*×\s*/u'], '×', $norm); // ×に置換、×の前後の空白を除去(*＊ＸxｘX)
        }
        $norm = str_replace(['$', '＄', 'Φ', '|', 'LQ', 'Q', '_', 'ψ', '♡'], '', $norm); // 記号除去
        $norm = str_replace('／', '/', $norm); //スラッシュ半角

        // ③ 数値系補正
        $norm = preg_replace('/(\d)[,．、]\s*(\d)/u', '$1.$2', $norm); // 小数のカンマ補正　(例：1,5   → 1.5)
        $norm = preg_replace('/(\d)\.\s*(\d)/u', '$1.$2', $norm);  // 小数のカンマと数字の空白補正　（例：1. 5   → 1.5）
        $norm = str_replace([',', '，'], '.', $norm); // カンマを.に統一
        $norm = str_replace('7.6', '7.5', $norm); // 7.6 → 7.5
        $norm = preg_replace('/(\d+(?:\/\d+)?)[\'＇′＂″”"]/u', '$1°', $norm); //例：45" → 45°
        $norm = preg_replace('/(\d{1,3})。/u', '$1°', $norm); // 例：45。 → 45°
        //「5 5/8」だけ残っているもの　「5 5/8」「22 3/4」など → 「5 5/8°」
        $norm = preg_replace_callback(
                '/\b(\d{1,3})\s+(\d{1,2})\/(\d{1,2})\b(?!°)/u',
                function ($m) {
                    return "{$m[1]} {$m[2]}/{$m[3]}°";
                },
                $norm
        );


        // ④ 空白・改行
        $norm = str_replace(["\r", "\n"], ' ', $norm);// 改行除去,半角１ペースに

        // 先頭と末尾の空白を削除&連続する空白を1個のスペースにする
        return preg_replace('/\s+/u', ' ', trim($norm));
    }

    // --------------------------
    // 「22 1/2」「221/2」などの角度表記を統一（°を付与）
    // --------------------------
    public static function normalizeHalfDegree($str)
    {
        return preg_replace('/\b(\d{1,3})\s*1\/2\b(?!°)/u', '$1 1/2°', $str);
    }


    // --------------------------
    // 分断された「1/」 + 「2°」などを結合し、商品名末尾に「1/2°」として貼り直す
    // --------------------------
    public static function dataCleaning_preCallSize(string $name): string
    {
        // $log = Log::channel('orders_daily');//ログ設定
        //=================================
        // 1. 分断角度の結合（° を消す前に実行）
        //=================================
        $parts = preg_split('/\s+/u', trim($name));
        $fixedParts = [];
        $buffer = null;          
        $angleToAppend = null;

        foreach ($parts as $p) {
            $pTrim = trim($p);

            // 「1/」だけ来た場合
            if (preg_match('/^\d+\/$/u', $pTrim)) {
                $buffer = $pTrim;
                continue;
            }

            // 次が「2°」なら →「1/2°」
            if ($buffer !== null && preg_match('/^\d+°$/u', $pTrim)) {
                $num1 = rtrim($buffer, '/');
                $num2 = rtrim($pTrim, '°');
                $fixedParts[] = "{$num1}/{$num2}°";
                $buffer = null;
                continue;
            }

            $fixedParts[] = $pTrim;
            $buffer = null;
        }

        // 生成した文字列を一度まとめる
        $merged = trim(implode(' ', $fixedParts));

        //=================================
        // 2. 本物ではない単独「°」を削除
        //    ※すでに 1/2° のような正しい角度は残る
        //=================================
        // 単語としての単独°のみ削除
        $merged = preg_replace('/(^| )°( |$)/u', ' ', $merged);

        // 2重スペース除去
        $merged = preg_replace('/\s+/u', ' ', trim($merged));

        return $merged;
    }

    // --------------------------
    //  角度、規格・圧力コード抽出（×も含めて削除）
    // --------------------------
    public static function extractAll(string $norm): array
    {
        $patterns = [
            // 角度
            '/×?\s*\d{1,3}\s+\d{1,2}\/\d{1,2}°/u', // 5 5/8°
            '/×?\s*(\d{1,3}°\d{1,2}\/\d{1,2})/u',  // 22°1/2
            '/×?\s*(\d{1,3}\s+\d{1,2}\/\d{1,2}°)/u',// 22 1/2°
            '/×?\s*(\d{1,3}°)/u',// 90°

            // 規格・圧力
            '/×\s*K\d+/iu',
            '/×\s*\d+K/u',
            '/×\s*G[F]?\d+(\.\d+)?K?/iu',
            '/×\s*RF\d+(\.\d+)?K?/iu',
            '/×\s*R\d+(\.\d+)?/iu',
            '/×\s*7\.5/iu',
        ];

        $removedWords = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $norm, $mm)) {
                foreach ($mm[0] as $match) {
                    $removedWords[] = trim(str_replace('×', '', $match));
                }
            }
            $norm = preg_replace($pattern, '', $norm);
        }

        return [$norm, $removedWords];
    }

    // --------------------------
    // 呼び径1~3抽出（×も含めて削除）
    // --------------------------
    public static function extractCallSizes(string $norm): array
    {
        $sizes = [];

        $patterns = [
            // M20×110
            [
                'regex' => '/M\d{1,4}×\d{1,4}/u',
                'callback' => function ($raw) {
                    return explode('×', $raw);
                }
            ],

            // 100×H300
            [
                'regex' => '/\d{1,4}×[LH]\d{1,4}/u',
                'callback' => function ($raw) {
                    return array_map(fn($v) => preg_replace('/[LH]/i', '', $v), explode('×', $raw));
                }
            ],

            // 100×300H
            [
                'regex' => '/\d{1,4}×\d{1,4}[LH]/u',
                'callback' => function ($raw) {
                    return array_map(fn($v) => preg_replace('/[LH]/i', '', $v), explode('×', $raw));
                }
            ],

            // 100×RF10K
            [
                'regex' => '/\d{1,4}(?=×(?:RF|GF?|R|K)\d)/u',
                'callback' => function ($raw) {
                    return [$raw];
                }
            ],

            // 50×40×30
            [
                'regex' => '/\d{1,4}×\d{1,4}(?:×\d{1,4})?/u',
                'callback' => function ($raw) {
                    return explode('×', $raw);
                }
            ],

            // 単独数値
            [
                'regex' => '/\d{2,4}\b/u',
                'callback' => function ($raw) {
                    return [$raw];
                }
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['regex'], $norm, $m)) {
                $raw = $m[0];

                // 抽出
                $sizes = $p['callback']($raw);

                // 削除
                if (str_contains($raw, '×')) {
                    $norm = str_replace($raw, '', $norm);
                } else {
                    $norm = str_replace($raw, '', $norm);
                }

                break; // ← 1パターンだけ取るなら
            }
        }

        return [$norm, $sizes];
    }

    // --------------------------
    // 先頭の 0 を除去（ただし "0" 単体にはならないよう注意）
    // --------------------------
    public static function cleanCallSizes(array $cleanedCallSizes): array
    {
        // 先頭3件までに制限
        $cleanedCallSizes = array_slice($cleanedCallSizes, 0, 3);

        // 数字だけの値は先頭0を削除
        return array_map(function ($v) {
            if (preg_match('/^[0-9]+$/', $v)) {
                return ltrim($v, '0') ?: '0'; // "000" → "0"
            }
            return $v; // M20 や H300 などはそのまま
        }, $cleanedCallSizes);
    }

    // --------------------------
    // 商品名の共通正規化
    // --------------------------
    public static function normalizeProductName(string $name, bool $stripEnds = true): string
    {
        //確認用
        // $log = Log::channel('orders_daily');//ログ設定
        if (empty($name)) return '';

        $openBrackets  = ['〔','【','［','〈','《','＜', '（'];
        $closeBrackets = ['〕','】','］','〉','》','＞', '）'];
        $name = str_replace($openBrackets,  '(', $name);//カッコ調整
        $name = str_replace($closeBrackets, ')', $name);//カッコ調整

        $name = str_replace('G×', 'GX', $name);// G× → GX 調整
        // ★ 追加：先頭の行番号を除去（例: "1 DCフランジ短管", "3GX形曲管"）
        // $name = preg_replace('/^\d+\s+/u', '', $name);
        $name = preg_replace('/^\d+/u', '', $name);

        // 先頭 I or |、語尾 1/I/| を除外
        if ($stripEnds) {
            $name = preg_replace(['/^[\sI\|]+/u', '/[\s1I\|]+$/u'], '', $name);
        }

        /* 商品名調整　**************************************************************/
        // 受挿L → 受挿しに置換
        $name = str_replace('內', '内', $name);
        $name = str_replace('受挿L', '受挿し', $name);
        $name = str_replace('内税', '内粉', $name);
        $name = str_replace('K形 輪', 'K形継輪', $name);
        $name = str_replace('函形', 'K形', $name);
        $name = str_replace('ブランジ', 'フランジ', $name);
        $name  = str_replace('补', 'セット', $name);

        // 「受曲管」が含まれていて「両受曲管」ではない場合のみ置換
        if (preg_match('/受曲管/u', $name) && !preg_match('/両受曲管/u', $name)) {
            $name = preg_replace('/受曲管/u', '両受曲管', $name);
        }
        /**************************************************************************/

        return trim($name);
    }




}
