<?php
// namespace App\Services;
namespace App\Services\Ocr\Customers;
use App\Services\Ocr\DocumentAiHelperService;
use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

//========================
// ヤマトガワ
//========================
class YamatogawaService
{
    // ===== 各フォーマットのキーワード配列（整理・統合）=====
    // 西東京支店（共通）: format1Keywords に format1Sub を統合
    private static array $format1Keywords = [
        'ヤマトガワリ西東京支店',
        'ヤマﾄガﾜ株式会社 西東京支店',
        '納入埸所', '客先名', '提出書類', '検 査', '見積依頼日',
        '先行手配日', '客先/現場', '仕切金額', '手配依頼'
    ];

    // 関東支店・三重支店・足立支店（ヤマトガワ③⑦⑨）
    private static array $format3Keywords = [
        '注文No', 'ヤマトガワ（株）関東支店', 'ヤマトガワ (株)関東支店', 'ヤマトガワ足立支店', 'ヤマトガワ株式会社足立支店',
        '<納期', '<お届先', '<件名', '定価 (単価)', '(単位:円)',
        'ヤマトガワ株式会社 三重支店', '御連絡事項', '仕入先注文No', '<EU>',
        '承認図面', '注文・出荷依頼書', '本注文No', 'EU 名称', '工事名称',
        '納入会社', '納入担当者', '(注文書)', '別紙送信枚数',
        '定価・仕切・納期を折返しFAXお願い致します', '納品書に必ず注文番号を記載のこと'
    ];

    // 宮崎支店・熊本支店（ヤマトガワ④⑧）
    private static array $format4Keywords = [
        'ヤマトガワ（株）宮崎支店', 'メーカー整通NO', '役所名', 'ヤマトガワ（株）熊本支店',
        '工事店', '希望納期', '設計金額', '設計単価', 'NET単価', 'NET金額', '※宜しく御願い致します'
    ];

    // 九州支店・山口支店（ヤマトガワ⑤⑥）
    private static array $format5Keywords = [
        'エンドユーザー', 'ソヤマトガワ株式会社 九州支店', '直送先',
        'ヤマトガワ株式会社 山口支店', "ヤマトガワ株式会社\n山口支店",
        '引取先', 'ヤマトガワ 山口支店', '希望価格', '仕切単価',
        '納期・引取日', '名称/規格,形状,寸法',
    ];

    // ========= ヘルパー =========
    private static function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($text, $keyword)) return true;
        }
        return false;
    }

    // フォーマット自動判定（統一窓口）
    private static function detectFormat(string $text): string
    {
        if (self::containsAny($text, self::$format1Keywords)
            && (preg_match('/注番\s*[:|｜]?\s*[A-Z0-9\-]+/u', $text) || self::containsAny($text, ['客先名','提出書類','検 査']))) {
            return 'format1';
        }
        if (self::containsAny($text, ['見積依頼日', '先行手配日', '客先/現場', '仕切金額', '手配依頼'])) {
            return 'format2';
        }
        if (self::containsAny($text, self::$format3Keywords)) return 'format3';
        if (self::containsAny($text, self::$format4Keywords)) return 'format4';
        if (preg_match('/ヤマトガワ\s*(株式会社)?\s*九州支店/u', $text) || self::containsAny($text, self::$format5Keywords)) {
            return 'format5';
        }
        return 'unknown';
    }



    // =====================
    // 伝票取得
    // =====================
    public static function YamatogawaOrderInfo(string $text, Document $document): array
    {
        $log = Log::channel('orders_daily');

        try {

            // 初期化
            $orderNumber = $customerName = $shippingName = $dueDate = $shipDate = $saturday = $shippingNumber = $siteName = null;

            // 得意先名（表記ゆれ対応）
            if (
                // 「ヤマトガワ株式会社 ◯◯支店」「ヤマ卜ガワ株式会社 ◯◯支店」など
                preg_match('/ヤマ[\p{Katakana}ﾞﾟト卜]*[ガｶ][ワﾜ]株式会社\s*([^\n\r]+支店)/u', $text, $m)

            ) {
                $customerName = 'ヤマトガワ株式会社 ' . trim($m[1]);
            } elseif (
                // 「ヤマトガワ(株)◯◯支店」「ヤマ卜ガワ(株)◯◯支店」「トガワ(株)◯◯支店」など
                preg_match('/(ヤマ[\p{Katakana}ﾞﾟト卜]*[ガｶ][ワﾜ]|トガワ)\s*\(株\)\s*([^\n\r]+支店)/u', $text, $m)  || //ヤマトガワ（株）
                preg_match('/中ト\s*\(株\)\s*([^\n\r]+支店)/u', $text, $m) || //中ト株
                preg_match('/ヤ.{0,3}ガ.{1}\s*\(株\)\s*([^\n\r]+支店)/u', $text, $m) || // ヤ小ガワ / ヤ卜ガワ / ヤマガワ
                preg_match('/マ.{0,3}ガ.{1}\s*\(株\)\s*([^\n\r]+支店)/u', $text, $m) // マ卜ガフ
            ) {
                $customerName = 'ヤマトガワ(株) ' . trim($m[1]);
            } elseif (preg_match('/ヤ.{0,2}ガワ\s*\(株\)\s*([^\n\r]+支店)/u', $text, $m)) {
                $customerName = 'ヤマトガワ株式会社 ' . trim($m[1]);
            }


            // フォーマット判定（統一）
            $fmt = self::detectFormat($text);
            if ($fmt === 'unknown') {
                throw new \Exception('フォーマット判定不能のため終了');
            }

            // 各フォーマット別処理
            switch ($fmt) {
                case 'format1': { // 西東京①
                    $log->info("フォーマット１");

                    // 発注番号
                    if (preg_match('/注番\s*\|*\s*([A-Z0-9\-]+)/u', $text, $m)) {
                        $orderNumber = trim($m[1]);
                    }

                    // 出荷先（納入埸所の3行）
                    if (preg_match('/納入埸所\s*\|*\s*(.+)\n(.+)\n\s*\|*\s*(.+支店)/u', $text, $m)) {
                        $shippingName = trim($m[1]) . ' ' . trim($m[2]) . ' ' . trim($m[3]);
                    }

                    // 納期（テーブル）
                    foreach ($document->getPages() as $page) {
                        foreach ($page->getTables() as $table) {
                            $rows = $table->getBodyRows();
                            foreach ($rows as $row) {
                                $cells = $row->getCells();
                                $cellTexts = [];
                                foreach ($cells as $cell) {
                                    $cellTexts[] = DocumentAiHelperService::extractTextFromAnchor($text, $cell->getLayout()->getTextAnchor());
                                }
                                for ($i = 0; $i < count($cellTexts) - 1; $i++) {
                                    $label = mb_ereg_replace('\s+', '', $cellTexts[$i] . $cellTexts[$i + 1]);
                                    if (preg_match('/納\s*期/u', $label) || preg_match('/納期/u', $label)) {
                                        $valueIndex = isset($cellTexts[$i + 2]) ? $i + 2 : $i + 1;
                                        if (isset($cellTexts[$valueIndex])) {
                                            $rawDue  = trim($cellTexts[$valueIndex]);
                                            $dueDate = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                                            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                        // 出荷先用
                        if (preg_match('/客先名\s*.*\n.*\|(.+?)(?:\n|$)/u', $text, $m)) {
                            $shippingNumber = trim($m[1]);
                        }
                        // 営業用備考
                        if (preg_match('/件名\s*[|:：]?\s*(.+)/u', $text, $m)) {
                            $siteName = trim($m[1]);
                            $siteName = str_replace(['单契'], '単契', $siteName);                        
                        }
                    }
                    break;
                }

                case 'format2': { // 西東京②
                    $log->info("フォーマット２");

                    // 発注番号 / 出荷先用 / 営業用備考（客先/現場）
                    if (preg_match('/客先\/現場\s*(.+?)\/(.+)/u', $text, $m)) {
                        $orderNumber    = trim($m[1]) . '/' . trim($m[2]);
                        $shippingNumber = trim($m[1]);
                        $siteName       = trim($m[2]);
                    }

                    // 出荷先（納入場所：納期の前まで3行想定）
                    if (preg_match('/納入場所\s*(.+?)\n(.+?)\n(.+)/u', $text, $m)) {
                        $lines = [trim($m[1]), trim($m[2]), trim($m[3])];
                        $cleaned = [];
                        foreach ($lines as $line) {
                            if (mb_strpos($line, '納期') !== false) break;
                            $cleaned[] = $line;
                        }
                        $shippingName = implode(' ', $cleaned);
                    }

                    // 納期
                    if (preg_match('/納期\s*[：:\|]?\s*(.+)/u', $text, $m)) {
                        $rawDue  = trim($m[1]);
                        $dueDate = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                        $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;
                    }
                    break;
                }

                case 'format3': { // 関東・三重・足立・京都 南大阪
                    $log->info("フォーマット３");

                    // -- 発注番号（「本注文No」は除外）--
                    if (preg_match('/(?<!本)(?:発注番号|注文\s*No|注文NO|注文番号)\s*[.:：\s]*([^\r\n]+)/u', $text, $m)) {
                        $orderNumber = trim($m[1]);
                        // Log::info("発注番号: " . $orderNumber); //確認用
                    }

                    // -- <納期>: or 納入日：の場合 --
                    if (
                        preg_match('/[<〈く]\s*納\s*期\s*[>〉]\s*[:：]?\s*(.*)/u', $text, $m)
                        || preg_match('/[<〈く]\s*納\s*[\r\n]*\s*期\s*[>〉]\s*[:：]?\s*(.*)/u', $text, $m)
                        || preg_match('/納\s*入\s*日\s*[:：]?\s*(.*)/u', $text, $m)
                    ) 
                    {
                        $rawDue  = trim($m[1]);
                        $dueDate = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                        $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;

                    // <納　の場合
                    } elseif (preg_match('/[<〈く]\s*納\s*(?:\R\s*)?([^\r\n]+)/u', $text, $m)) { 
                        $rawDue  = trim($m[1]);
                        $dueDate = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                        $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;
                    }

                    // -- 出荷先名 --
                    $recipient = '';

                    // パターンA：<お届先>: 足立支店倉庫
                    if (preg_match('/お届先\s*[>〉》＞]?\s*[:：;]?\s*([^\r\n]+)/u', $text, $m1)) {
                        $recipient = trim($m1[1]);
                    // パターンB：<お届先>\n 足立支店倉庫
                    } elseif (preg_match('/[<〈《＜]?\s*お届先\s*[>〉》＞]?\s*\R\s*([^\r\n]+)/u', $text, $m2)) {
                        $recipient = trim($m2[1]);
                    }

                    $address = '';
                    if (preg_match('/住所[:：]?\s*((?:[^\n]*\n?){1,4})/u', $text, $m2)) {
                        $rawAddressBlock = $m2[1];

                        // 不要語が出たらそこで打ち切る
                        $rawAddressBlock = preg_split(
                            '/(送り状|FAXください)/u',
                            $rawAddressBlock
                        )[0] ?? '';

                        $cleanAddress = preg_split('/\s*TEL[:：]|FAX[:：]/u', $rawAddressBlock)[0] ?? ''; // TEL / FAX 行も除外
                        $address = preg_replace('/\s+/u', ' ', trim($cleanAddress));// 整形
                    }
                    if ($recipient || $address) {
                        $shippingName = trim($recipient . ' ' . $address);
                    }
                    // 納入会社がある場合はそれを出荷先に
                    if (empty($shippingName) && preg_match('/納入会社[:：]?\s*([^\n]+)/u', $text, $m1)) {
                        $shippingName = trim($m1[1]);
                    }
                    // -- 出荷先用 / 備考（件名／工事名称）--
                    if (preg_match('/[<〈]?\s*件名\s*[>〉]?[;：:\s]*([^\r\n]+)/u', $text, $m)) {
                        $siteName = trim($m[1]);
                        $shippingNumber = $siteName;
                    } elseif (preg_match('/[<〈]工事名称[〉>][:：\s]*([\s\S]*?)(?=[<〈]納)/u', $text, $m)) {
                        $siteName = trim(preg_replace('/\s+/u', ' ', $m[1]));
                        $shippingNumber = $siteName;
                    } elseif (preg_match('/[<〈]工事名称[〉>][:：\s]*([^\n]+)/u', $text, $m)) {
                        $siteName = trim($m[1]);
                        $shippingNumber = $siteName;
                    }
                    //工事整形
                    if (!empty($shippingNumber)) {
                        // 「エ事」「エ 事」「工 事」など → 「工事」
                        $siteName = $shippingNumber = preg_replace('/[エ工]\s*事/u', '工事', $shippingNumber);
                    }

                    break;
                }

                case 'format4': { // 宮崎・熊本
                    $log->info("フォーマット４");

                    // 発注番号
                    if (preg_match('/注文書NO[.:：,]?\s*([A-Z0-9\-–—]+)/u', $text, $m)
                        || preg_match('/注文書\s*NO[.:：,]?\s*([A-Z0-9\-–—]+)/u', $text, $m)
                        || preg_match('/EXNO[.,]?\s*([A-Z0-9\-–—]+)/u', $text, $m)) {
                        $orderNumber = trim($m[1]);
                    }

                    // 出荷先名（住所〜会社名）
                    if (preg_match('/住所\s*([\s\S]+?)会社名\s*([^\n\r]+)/u', $text, $m)) {
                        $address = trim(preg_replace('/\s+/', ' ', $m[1]));
                        $company = trim(preg_replace('/\s+/', ' ', $m[2]));
                        $shippingName = trim($address . ' ' . $company);
                    }

                    // 納期
                    if (preg_match('/希望納期\s*(.+)/u', $text, $m2)) {
                        $rawDue = trim($m2[1]);

                        // ★ 住所・郵便番号系は無効化
                        if (
                            preg_match('/送り先|送付先|\(送\)先/u', $rawDue) || //送り先
                            preg_match('/住所/u', $rawDue) ||                          // 住所
                            preg_match('/^[〒T]?\d{3}-?\d{4}$/u', trim($rawDue))       // 郵便番号
                        ) {
                            Log::info("{$rawDue} → 住所系のため「○/○出荷」処理スキップ");
                            $rawDue = null;
                        }

                        // --- 「○/○出荷」パターンの特別処理 ---
                        if (preg_match('/(\d{1,2})[\/／\-](\d{1,2})\s*出荷/u', $rawDue, $dm)) {
                            
                            // 月日取得
                            $month = (int)$dm[1];
                            $day   = (int)$dm[2];
                            $year  = ($month < (int)date('n')) ? (int)date('Y') + 1 : (int)date('Y');

                            // 出荷日＝指定日
                            $shipDate = sprintf('%04d%02d%02d', $year, $month, $day);

                            // 納期＝出荷日の翌営業日
                            $dueDate = DocumentAiHelperService::getNextBusinessDay($shipDate);

                            Log::info("{$rawDue} → 出荷日={$shipDate}, 納期={$dueDate}（希望納期欄・出荷記載）");

                        }
                        // --- 通常処理 ---
                        else {
                            $dueDate  = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;
                        }
                    }

                    // 出荷先用 / 営業用備考（工事名／工事店を統一処理）
                    if (preg_match('/工事(名|店)\s*[:：]?\s*(.+)/u', $text, $mBase)) {

                        $candidate = trim($mBase[2]);

                        // 熊本 → 工事名 優先
                        if (!empty($customerName) && mb_strpos($customerName, '熊本') !== false) {
                            if (preg_match('/工事名\s*[:：]?\s*(.+)/u', $text, $mKM)) {
                                $candidate = trim($mKM[1]);
                            }
                        }

                        // 宮崎 → 工事店 優先
                        if (!empty($customerName) && mb_strpos($customerName, '宮崎') !== false) {
                            if (preg_match('/工事店\s*[:：]?\s*(.+)/u', $text, $mMZ)) {
                                $candidate = trim($mMZ[1]);
                            }
                        }

                        // 無効値除外（空/工事名/工事店/電話番号、郵便番号）
                        if (
                            empty($candidate) ||
                            $candidate === '工事名' ||
                            $candidate === '工事店' ||
                            $candidate === '希望納期' ||
                            preg_match('/^[メ一]力一整通NO/u', $candidate) || // 一力一整通NO
                            preg_match('/^整通NO/u', $candidate) || // 整通NO
                            preg_match('/^\+?\d{1,4}[-\s]?\d{2,4}[-\s]?\d{4}$/u', $candidate) || //電話番号
                            preg_match('/^[〒T]?\d{3}-?\d{4}$/u', $candidate) // 郵便番号（追加）//郵便番号
                        ) {
                            $candidate = null;
                        }

                        // 最終採用
                        if ($candidate !== null) {
                            $shippingNumber = $siteName = $candidate;
                        }
                    }

                    break;
                }

                case 'format5': { // 九州・山口
                    $log->info("フォーマット5");

                    $text = str_replace(['▾'], '', $text); // ノイズ記号除去

                    // 発注番号（空白を除去）
                    if (preg_match('/注文\s*NO\s*[:：]?\s*([0-9\-. ]+)/u', $text, $m)) {
                        $orderNumber = preg_replace('/\s+/u', '', $m[1]);
                    }

                    // 出荷先名
                    if (preg_match('/引\s*取\s*先\s*([^\n\r]+)/u', $text, $m)) {
                        $shippingName = trim(preg_replace('/\s+/', ' ', $m[1]));
                    }
                    elseif (preg_match('/直\s*送\s*先\s*([^\n\r]+)/u', $text, $m)) {
                        $shippingName = trim(preg_replace('/\s+/', ' ', $m[1]));
                    }elseif (preg_match('/弊社店[入人]|弊社/u', $text)) {
                        $shippingName = $customerName;
                    }

                    // 納期（納期・引取日）- 複数行対応
                    if (preg_match('/(?:納期[・･.\s]*)?引取日[\s　▾▽▼▶►\n\r]*((?:.*?\n)*?)(?=(?:「?\s*(?:直送先|引取先))|$)/u' ,$text, $m)) {

                        // マッチした複数行から最初の有効な日付を取得
                        $lines = preg_split('/\r\n|\r|\n/', $m[1]);
                        $rawDue = null;
                        
                        foreach ($lines as $line) {
                            $line = trim($line);

                            // 空行、「.」のみ、矢印記号のみの行をスキップ
                            if (empty($line) || 
                                // $line === '.' || 
                                preg_match('/^[\.\・\-\=~＿—–－]+$/u', $line) ||
                                preg_match('/^[⇒→➡︎\s]+$/', $line))
                            {
                                continue;
                            }
                            if (preg_match('/^直送先/u', $line)) break; // foreach だけを抜ける

                            // 最初の有効な文字列を採用（「即日」「8/19着」など）
                            $rawDue = $line;
                            break; // foreach だけを抜ける
                        }
                        
                        $dueDate = !empty($rawDue) ? DocumentAiHelperService::formatDateToNumber($rawDue, $saturday) : null;
                        $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;
                    }

                    // 出荷先用 / 営業用備考
                    if (preg_match('/工事件名\s*([^\n\r]+)/u', $text, $m)) {
                        //直送先が含まれていたらnull
                       if (preg_match('/直送先/u', $m[1])){
                         $shippingNumber = $siteName = null;
                       }else{
                         $shippingNumber = $siteName = trim($m[1]);
                       }
                    } elseif (preg_match('/【件名】\s*([^\n\r]+)/u', $text, $m)) {
                         // 「納期 引取日」が含まれていたらスキップ
                        if (preg_match('/(納期|引取日)/u', $m[1])) {
                            // 何も代入せず次へ
                        } else {
                            $shippingNumber = $siteName = trim($m[1]);
                        }
                    }
                    break;
                }

                default:
                    // 判定不能時は無処理（既存仕様を踏襲）
                    break;
            }

            // 共通：出荷先に「当社/弊社」が含まれていたら得意先名に置換
            if (!empty($customerName) && !empty($shippingName)) {
                $shippingName = DocumentAiHelperService::formatshippingName($customerName, $shippingName);
            } else {
                $log->warning("formatshippingName スキップ: customerName または shippingName が空", [
                    'customerName' => $customerName,
                    'shippingName' => $shippingName
                ]);
            }

            return [
                'orderNumber'    => $orderNumber,
                'customerName'   => $customerName,
                'shippingName'   => $shippingName,
                'shipDate'       => $shipDate,
                'dueDate'        => $dueDate,
                'saturday'       => $saturday,
                'shippingNumber' => $shippingNumber,
                'salesBikou'     => $siteName,
            ];


        } catch (\Throwable $e) {
            // エラー内容をログ出力して終了
            $log->error('YamatogawaOrderInfo 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());

            // 上位（コマンド側）でDB保存・PDF移動を継続させるため再スロー
            throw $e;
        }
    }




    // ------------------
    // 商品明細抽出（エントリポイント）
    // ------------------
    public static function YamatogawaOrderItems(string $text, Document $document): array
    {
        $log = Log::channel('orders_daily');

        try {
            // 九州支店（フォーマット5）は「品名＋規格」同列
            if (preg_match('/ヤマトガワ\s*(株式会社)?\s*九州支店/u', $text) || self::containsAny($text, self::$format5Keywords)) {
                $log->info('YamatogawaOrderItems: フォーマット5検出');
                return self::extractFormat5Items($text, $document);
            }

            $log->info('YamatogawaOrderItems: デフォルトフォーマット適用');
            return self::extractDefaultYamatogawaItems($text, $document);

        } catch (\Throwable $e) {
            // ===== エラー時はログを出して強制終了 =====
            $log->error('YamatogawaOrderItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());

            // 上位（コマンド）側で DB保存・PDF移動を続けるため再スロー
            throw $e;
        }
    }



    //===========================================
    // 商品明細　デフォルト（関東/西東京/宮崎/熊本/など）
    //===========================================
    public static function extractDefaultYamatogawaItems(string $text, Document $document): array
    {
        $registeredItems = [];
        $log = Log::channel('orders_daily');

        try {
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {

                    try {
                        // --- ヘッダ取得（無ければボディ先頭を擬似ヘッダ）---
                        $headerTexts = [];
                        $headerRows  = $table->getHeaderRows();
                        $bodyRows    = [];
                        $rawBodyRows = $table->getBodyRows();

                        // ------------------------------
                        // ① DocumentAI の header がある場合
                        // ------------------------------
                        if (count($headerRows) > 0) {

                            // まず headerRows[0] をそのまま使う
                            foreach ($headerRows[0]->getCells() as $i => $cell) {
                                $headerTexts[$i] = mb_ereg_replace(
                                    '\\s+',
                                    '',
                                    DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    )
                                );
                            }

                            // ヘッダーとして妥当か? → 名称/品名 + 数量 がなければ BodyRows から探す
                            $joined = implode('|', $headerTexts);
                            $hasName = preg_match('/(品名|名称)/u', $joined);
                            $hasQty  = preg_match('/(数量|数)/u', $joined);

                            if ($hasName && $hasQty) {
                                // 使えるヘッダー
                                $bodyRows = $rawBodyRows;
                            } else {
                                // ↓↓↓ フォールバック（BodyRows 1〜3 から探す）
                                $headerTexts = self::detectHeaderFromBody($rawBodyRows, $text);
                                $bodyRows    = self::sliceBodyAfterHeader($rawBodyRows, $headerTexts);


                            }

                        // ------------------------------
                        // ② headerRows が 0 → BodyRows から探す
                        // ------------------------------
                        } else {
                            $headerTexts = self::detectHeaderFromBody($rawBodyRows, $text);
                            $bodyRows = self::sliceBodyAfterHeader($rawBodyRows, $headerTexts);
                        }

                        // ★★★ null なら空配列に補正（重要） ★★★
                        if ($headerTexts === null) {
                            $headerTexts = [];
                        }
                        if ($bodyRows === null) {
                            $bodyRows = [];
                        }

                        // --- 列インデックス特定 ---
                        $orderNoCol = $productCol = $qtyCol = $specCol = $bikouCol = null;
                        $combinedNoAndName = false;

                        foreach ($headerTexts as $i => $label) {

                            //商品名
                            if (preg_match('/No\.?/iu', $label) && preg_match('/(名称|品名)/u', $label)) {
                                $productCol = $i;
                                $combinedNoAndName = true;
                                continue;
                            }
                            //発注No
                            if (preg_match('/^No\.?$/iu', $label)) {
                                $orderNoCol = $i;
                            //商品名
                            } elseif (preg_match('/品名|名称|名\s*称|品\s*名/u', $label)) {
                                $productCol = $i;
                            //呼び径
                            } elseif (preg_match('/寸法|規格/u', $label)) {
                                $specCol = $i;
                            //数量
                            } elseif (preg_match('/数量|数/u', $label) && !preg_match('/枚数/u', $label)) {
                                $qtyCol = $i;
                            //備考欄
                            } elseif (preg_match('/備考/u', $label)) {
                                $bikouCol = $i;
                            }
                        }


                        // --- No 列の信頼性チェック ---
                        $isNumericNoCol = false;
                        $isSequentialNoCol = false;
                        $isAdjacentToName = false;

                        if ($orderNoCol !== null) {
                            $rowsArray  = is_array($bodyRows) ? $bodyRows : iterator_to_array($bodyRows);
                            $sampleRows = array_slice($rowsArray, 0, 2);
                            $samples = [];
                            foreach ($sampleRows as $row) {
                                $cells = $row->getCells();
                                if ($orderNoCol < count($cells)) {
                                    $val = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$orderNoCol]->getLayout()->getTextAnchor()
                                    ));
                                    if ($val !== '') $samples[] = $val;
                                }
                            }
                            $allInts = [];
                            foreach ($samples as $s) {
                                $n = self::toIntLike($s);
                                if ($n === null) { $allInts = []; break; }
                                $allInts[] = $n;
                            }
                            $isNumericNoCol = !empty($allInts) && count($allInts) === count($samples);
                            $isSequentialNoCol = self::isSequentialNumbers($samples);
                            if ($productCol !== null) {
                                $isAdjacentToName = (abs($orderNoCol - $productCol) <= 1);
                            }
                        }

                        $isSingleNoOne = false;
                        if ($orderNoCol !== null && count($bodyRows) === 1) {
                            $cells = $bodyRows[0]->getCells();
                            if ($orderNoCol < count($cells)) {
                                $noText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                    $text, $cells[$orderNoCol]->getLayout()->getTextAnchor()
                                ));
                                $isSingleNoOne = (self::toIntLike($noText) === 1);
                            }
                        }

                        $enableNoStrip =
                            (($orderNoCol !== null) && $isNumericNoCol && $isSequentialNoCol && $isAdjacentToName)
                            || $combinedNoAndName
                            || $isSingleNoOne;

                        // --- 本文ループ ---
                        foreach ($bodyRows as $row) {
                            try {
                                $cells = $row->getCells();

                                // --- 商品名-----
                                $productName = null;
                                if ($productCol !== null && $productCol < count($cells)) {
                                    $productName = DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$productCol]->getLayout()->getTextAnchor()
                                    );
                                    // NG行スキップ（例：年製にて）
                                    if (preg_match('/年製にて/u', $productName)) continue;

                                    $productName = DocumentAiHelperService::normalizeProductName($productName, true);//商品名を整形
                                }

                                // 先頭 No の剥がし
                                if ($enableNoStrip && $productName !== '') {
                                    if ($orderNoCol !== null && $orderNoCol < count($cells) && $orderNoCol !== $productCol) {
                                        $noText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                            $text, $cells[$orderNoCol]->getLayout()->getTextAnchor()
                                        ));
                                        if ($noText !== '' && preg_match('/^\d{1,2}$/u', $noText)) {
                                            $productName = preg_replace('/^' . preg_quote($noText, '/') . '\s+/u', '', $productName);
                                            $productName = preg_replace('/^' . preg_quote($noText, '/') . '(?=[A-ZＡ-Ｚ])/u', '', $productName);
                                        }
                                    } elseif ($combinedNoAndName) {
                                        $productName = preg_replace('/^(?<=^)\d{1,2}(?=\s|[A-ZＡ-Ｚ])/u', '', $productName);
                                        $productName = ltrim($productName);
                                    }
                                }

                                // （関東⑨等）備考列を商品名後方に結合
                                if ((str_contains($text, 'EU 名称') || str_contains($text, '納品書に必ず注文番号を記載のこと。'))
                                    && $bikouCol !== null && $bikouCol < count($cells)) {
                                    $bikouName = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$bikouCol]->getLayout()->getTextAnchor()
                                    ));
                                    if ($bikouName !== '') $productName .= ' ' . $bikouName;
                                }

                                // --- 数量 ---
                                $quantity = null;
                                if ($qtyCol !== null && $qtyCol < count($cells)) {
                                    $rawQty = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$qtyCol]->getLayout()->getTextAnchor());
                                    $rawQty = mb_convert_kana((string)$rawQty, 'n');
                                    $rawQty = preg_replace('/[−―ー‐–－]/u', '-', $rawQty);
                                    $rawQty = str_replace(' ', '', $rawQty);
                                    if (preg_match('/-?\d+/', $rawQty, $m)) {
                                        $quantity = (string)abs((int)$m[0]);
                                    }
                                }
                                if ($quantity === '' && preg_match('/(\d+)\s*個/u', $productName, $m)) {
                                    $quantity = $m[1];
                                }
                                $quantity = mb_convert_kana($quantity, 'n');
                                if (in_array($quantity, ['Ⅰ','Ｉ','ｌ','l','|','$','＄'], true)) $quantity = '1';//1に置換


                                // --- 呼び径（規格）---
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
                                    if (!empty($size['size1'])) {
                                        $str = (string)$size['size1'];
                                        if ($str[0] === '4' || (int)$str > 450) {
                                            $size['size1'] = (int)substr($str, 1);
                                        }
                                    }
                                }

                                // 無効行スキップ（商品名＆数量 or 数量＆呼び径 どちらも空）
                                if (empty($productName) && empty($quantity)) continue;
                                if (empty($size['size1']) && empty($quantity)) continue;

                                //確認用
                                // if (empty($productName) && empty($quantity)) {
                                //     $log->warning("Skip row: productName & quantity empty");
                                //     continue;
                                // }
                                // if (empty($size['size1']) && empty($quantity)) {
                                //     $log->warning("Skip row: callSize & quantity empty");
                                //     continue;
                                // }

                                $registeredItems[] = [
                                    '商品名'  => trim($productName),
                                    '呼び径1' => $size['size1'] ?? null,
                                    '呼び径2' => $size['size2'] ?? null,
                                    '呼び径3' => $size['size3'] ?? null,
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

        return $registeredItems;


        } catch (\Throwable $e) {
            $log->error('extractDefaultYamatogawaItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e; // 上位 (YamatogawaOrderItems) に伝播
        }
    }




    //==================================
    // 九州・山口（フォーマット5）用　商品明細
    //==================================
    private static function extractFormat5Items(string $text, Document $document): array
    {
        $items = [];
        $log = Log::channel('orders_daily');

        try {
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {

                    try {

                        // 1. HeaderRows / BodyRows 取得 ===============
                        $headerRows = $table->getHeaderRows();//ヘッダー
                        $bodyRows   = $table->getBodyRows();

                        // 2. 通常ヘッダー取得（あれば） ===============
                        $headerTexts = [];
                        if (!empty($headerRows)) {
                            foreach ($headerRows[0]->getCells() as $i => $cell) {
                                $headerTexts[$i] = preg_replace(
                                    '/\s+/u',
                                    '',
                                    DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    )
                                );
                            }
                        }

                        // 3. BodyRows から擬似ヘッダー探索） ===============
                        $pseudoHeaderIndex = null;
                        $pseudoHeaderTexts = [];

                        foreach ($bodyRows as $rowIndex => $row) {
                            $texts = [];

                            foreach ($row->getCells() as $i => $cell) {
                                $texts[$i] = preg_replace(
                                    '/\s+/u',
                                    '',
                                    DocumentAiHelperService::extractTextFromAnchor(
                                        $text,
                                        $cell->getLayout()->getTextAnchor()
                                    )
                                );
                            }

                            $joined = implode('|', $texts);

                            if (
                                preg_match('/名称/u', $joined) &&
                                preg_match('/規格/u', $joined) &&
                                preg_match('/数量|数/u', $joined) &&
                                preg_match('/単位|单位/u', $joined)
                            ) {
                                $pseudoHeaderIndex = $rowIndex;
                                $pseudoHeaderTexts = $texts;
                                break;
                            }
                        }

                        // 4. ヘッダー確定（通常 → 擬似）） ===============
                        $headerForMapping = null;

                        if (!empty($headerTexts)) {
                            $joined = implode('|', $headerTexts);
                            if (
                                preg_match('/名称/u', $joined) &&
                                preg_match('/規格/u', $joined) &&
                                preg_match('/単位|单位/u', $joined)
                            ) {
                                $headerForMapping = $headerTexts;
                            }
                        }

                        if ($headerForMapping === null && !empty($pseudoHeaderTexts)) {
                            $headerForMapping = $pseudoHeaderTexts;
                        }

                        // 商品テーブルでなければスキップ
                        if ($headerForMapping === null) {
                            continue;
                        }

                        // 5. 列マッピング -----------------------------------------------------------------------------------------------------------------------------------------
                        $nameCol=$qtyCol=$unitCol = null;
                        $qtyflg = false;

                        foreach ($headerForMapping as $i => $h) {
                            if ($nameCol === null && $qtyCol === null && preg_match('/名称\/?規格[,、]?\s*形状[,、]?\s*寸法[\s\S]*?(数量|数)/u', $h)) $nameCol = $qtyCol  = $i;//商品と数量のヘッダが同じ
                            if ($nameCol === null && preg_match('/名称\/?規格,?形状,?寸法/u', $h)) $nameCol = $i;//商品名
                            if ($nameCol === null && preg_match('/名称\/?規格/u', $h)) $nameCol = $i;//商品名
                            if ($qtyCol  === null && preg_match('/^(数量|数)$/u', $h)) $qtyCol  = $i;//数量
                            if ($unitCol === null && preg_match('/^(単位|单位)$/u', $h)) $unitCol = $i;//単位                            
                            if (preg_match('/(規格|形状|寸法)/u', $h) && preg_match('/数量|数/u', $h)) $qtyflg = true;//数量と呼び径が一緒だった場合のフラグ
                        }
                        if ($nameCol === null) $nameCol = 0;
                        if ($qtyCol === null) {
                            $qtyCol = 1; // format5 は基本これ
                        }
                        // ----------------------------------------------------------------------------------------------------------------------------------------------------

                        // 6. 商品行開始位置（擬似ヘッダーの次）
                        $startRowIndex = ($pseudoHeaderIndex !== null) ? $pseudoHeaderIndex + 1 : 0;

                        // 行パース
                        for ($r = $startRowIndex; $r < count($bodyRows); $r++) {

                            try {
                                $row = $bodyRows[$r];
                                $cells = $row->getCells();
                                if (count($cells) === 0) continue;//セルがなければスキップ

                                // ======                         
                                // 商品名
                                // ======     
                                    //商品名と数量のヘッダが一緒の場合                    
                                    if($nameCol == $qtyCol) {
                                        $startCol = $endCol = $nameCol;

                                    // 品名+規格セルを連結（nameCol-1 ~ nameCol+2、数量/単位は除外）
                                    }else{
                                        $startCol = max(0, $nameCol - 1);
                                        $endCol   = $nameCol + 2;
                                    }
                                                                
                                    // ----------
                                    // for ループ前
                                    // ----------
                                    $skipQty = !($nameCol == $qtyCol);
                                    $skipUnit = !($nameCol == $unitCol);

                                    // ----------
                                    // セル結合
                                    // ----------
                                    $fullParts = [];
                                    for ($i = $startCol; $i <= $endCol; $i++) {

                                        //スキップ
                                        if (!isset($cells[$i])) continue;//存在しなければスキップ
                                        if ($skipQty && $i === $qtyCol) continue;//数量スキップ（商品名に数量がなければ）
                                        if ($skipUnit && $i === $unitCol) continue;//単位スキップ（商品名に単位がなければ）

                                        $textFragment = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$i]->getLayout()->getTextAnchor(), true);
                                        $fullParts[] = $textFragment;
                                    }

                                    //調整
                                    $full = implode(' ', array_unique(array_filter(array_map('trim', $fullParts))));//array_map('trim', $fullParts)→セル文字列の 前後のスペースを除去、　array_filter()→空文字 "" を削除する  例:["K形 輪", "", "(内粉)"]→ ["K形 輪", "(内粉)"]、　array_unique()→同じ文字列を重複して連結しないようにする。 implode(' ', ...) → 残った文字列を 半角スペースで結合 する。
                                    $full = str_replace(["\r", "\n"], ' ', (string)$full);//改行コード（\r, \n）が混ざっていたら全部スペースに変える。
                                    $full = preg_replace('/\s+/u', ' ', trim($full));//余計な連続スペースを全部1つのスペースに変換する。
                                    $full = ltrim($full, '| ');

                                    // 除外したいワード一覧（正規表現のままでもOK、ただし扱いに注意）
                                    $headerWords = [
                                        '名称','規格','形状','寸法','数量','単位','单位',
                                        '現場',
                                    ];

                                    // 1つでも含まれていたらスキップ
                                    foreach ($headerWords as $word) {

                                        // 正規表現として安全に扱うために preg_quote
                                        $pattern = '/' . preg_quote($word, '/') . '/u';

                                        if (preg_match($pattern, $full)) {
                                            // $log->info("Skip header by text: {$full} == hit [$word]");
                                            continue 2; // 行スキップ
                                        }
                                    }

                                    // セル内が空 or ラベル行スキップ
                                    if ($full === '' || preg_match('/^(エンドユーザー|工事件名|直送先|住所|電話番号|FAX番号|備\s*考|上記|願|直送)$/u', $full)) continue;

                                // ======                         
                                // 数量
                                // ======                         
                                    $qty = '';
                                    if ($qtyCol < count($cells)) {

                                        // セル内の文字列を取得して全角→半角に統一
                                        $qtyRaw = DocumentAiHelperService::extractTextFromAnchor($text, $cells[$qtyCol]->getLayout()->getTextAnchor());
                                        $qtyRaw = trim(mb_convert_kana((string)$qtyRaw, 'n')); // 全角数字を半角へ

                                        if(!empty($qtyRaw)) {

                                            //商品名と数量のヘッダが異なる場合
                                            if($skipQty){

                                                //
                                                if($qtyflg == true) {
                                                    $tmp = trim($qtyRaw);

                                                    // -------------------------------
                                                    // ① 改行がある場合 → そのまま分割
                                                    // -------------------------------
                                                    if (preg_match('/\R/u', $tmp)) {

                                                        // $log->info("① 改行がある場合 → そのまま分割");

                                                        $parts = preg_split('/\R+/u', $tmp);
                                                        $parts = array_values(array_filter(array_map('trim', $parts)));

                                                        // $log->info("parts:" . print_r($parts, true));

                                                        // 数量は最後の行
                                                        $foundQty = end($parts);

                                                        // 呼び径は最後以外の行すべてを結合（通常 1 行）
                                                        $callSizeQty = null;
                                                        if (count($parts) >= 2) {
                                                            $callSizeQty = trim(implode(' ', array_slice($parts, 0, -1)));
                                                        }

                                                        // 数量セット
                                                        $qty = $foundQty;

                                                        // 呼び径を商品名に統合
                                                        if (!empty($callSizeQty)) {
                                                            $full = trim($full . ' ' . $callSizeQty);
                                                        }

                                                    }
                                                    else {
                                                        // $log->info("② 改行がない場合");
                                                        // -------------------------------
                                                        // ② 改行がない場合
                                                        //    語尾に「空白＋1〜2桁の数字」があるかを検出
                                                        //    例: "～ GF7.5K 1" or "75 1"
                                                        // -------------------------------
                                                        if (preg_match('/(.+?)\s+(\d{1,2})$/u', $tmp, $m)) {
                                                            $callSizeQty = trim($m[1]);   // 呼び径
                                                            $foundQty = trim($m[2]);   // 末尾の数量

                                                            // 数量セット
                                                            $qty = $foundQty;

                                                            // 呼び径を商品名側へ統合
                                                            if ($callSizeQty !== '') {
                                                                $full = trim($full . ' ' . $callSizeQty);
                                                            }

                                                        }
                                                    }

                                                }else {
                                                    $qty = $qtyRaw;

                                                }
                                            //商品名と数量のヘッダが一緒　→ 商品名から数量削除
                                            }else{
                                                // $log->info("商品名と数量のヘッダが一緒　→ 商品名から数量削除");

                                                // まずは元の文字列を作業用にコピー
                                                $work = $qtyRaw;

                                                // --- 呼び径・角度・規格っぽい数字を全部削る ---
                                                $patterns = [
                                                    '/\d+\s*[×x]\s*\d+(?:\s*[×x]\s*\d+)*/u',  // 75×22, 75x22
                                                    '/\d+\s*\/\s*\d+\s*°?/u',                 // 1/2°
                                                    '/\d+\s+\d+\s*\/\s*\d+\s*°?/u',
                                                    '/\d+°/u',                                 // 45°
                                                    '/\b[HL]\d+\b/u',                          // H300, L300
                                                    '/\bM\d+\b/u',                             // M20
                                                    '/\b\d+K\b/u',                             // 10K, 16K
                                                    '/\b[GR]\d+(?:\.\d+)?[A-Z]*\b/u',          // G7.5, GF7.5K
                                                    '/\b\d{3,}\b/u',                           // 3桁以上の数字を除去（100, 200, 300…）
                                                ];
                                                $work = preg_replace($patterns, ' ', $work);
                                                $work = preg_replace('/\s+/u', ' ', trim($work));

                                                // --- 残った中から「単独の1〜2桁の数字」だけ取得 ---
                                                $matches = [];
                                                if (preg_match_all('/\b\d{1,2}\b/u', $work, $matches) && !empty($matches[0])) {
                                                    // 最後に出てきた数字を数量として採用
                                                    $qty = end($matches[0]);
                                                }
                                                
                                                // --- 商品名 $full から 数量を削除 ---
                                                // 語尾に 1〜2桁の数字があるか？
                                                if (preg_match('/(\d{1,2})$/u', $full, $m)) {

                                                    $tail = $m[1];  // 語尾の数字

                                                    // ★ 語尾の数字と $qty が一致したら削除
                                                    if ($qty !== '' && $tail === $qty) {

                                                        // 語尾の数字だけ削除
                                                        $full = preg_replace('/\d{1,2}$/u', '', $full);
                                                        $full = rtrim($full);
                                                    }
                                                //語尾にない場合
                                                } else {
                                                    $q = preg_quote($qty, '/');
                                                    // 数字の前後が「数字ではない」ことだけを条件にする
                                                    $full = preg_replace(
                                                        '/(?<!\d)' . $q . '(?!\d)/u',
                                                        '',
                                                        $full
                                                    );
                                                    // スペース整理
                                                    $full = trim(preg_replace('/\s+/u', ' ', $full));
                                                }

                                                // 必要ならログ確認
                                                // $log->info('qtyRaw: ' . $qtyRaw . ' / work: ' . $work . ' / qty: ' . $qty);
                                            }
                                        }

                                    }

                                    // ======                         
                                    // 呼び径
                                    // ======                         
                                    $size = self::extractCallSizeFromText($full) ?? [
                                        'productName' => '',
                                        'size1'        => '',
                                        'size2'        => '',
                                        'size3'        => '',
                                        'removedWords' => [],
                                    ];

                                // 商品名の最終整形（呼び径除去後の残り）
                                // $productName = self::normalizeProductName($size['productName'], false);
                                $productName = DocumentAiHelperService::normalizeProductName($size['productName'], false);//商品名を整形


                                //商品、数量なければスキップ
                                if ($productName === '' && $qty === '') {
                                    // $log->warning("Skip row: productName & quantity empty");
                                    continue;
                                }

                                $items[] = [
                                    '商品名'  => $productName,
                                    '呼び径1' => $size['size1'],
                                    '呼び径2' => $size['size2'],
                                    '呼び径3' => $size['size3'],
                                    '数量'    => $qty,
                                    '備考'    => null,
                                ];

                            } catch (\Throwable $e) {
                                $log->warning('行スキップ（format5）: ' . $e->getMessage());
                                continue;
                            }
                        }

                    } catch (\Throwable $e) {
                        $log->error('テーブル解析失敗（format5）: ' . $e->getMessage());
                        throw $e; // 関数強制終了
                    }
                }
            }

        return $items;

        } catch (\Throwable $e) {
            $log->error('extractFormat5Items 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e; // 上位 (YamatogawaOrderItems) に伝播
        }
    }





    // ========= ヘッダー行を BodyRows から探す関数　=====================
        private static function detectHeaderFromBody($rawBodyRows, string $text): ?array
    {
        if (count($rawBodyRows) === 0) return null;

        // BodyRows の 0〜3行を候補にする
        $limit = min(3, count($rawBodyRows) - 1);

        for ($r = 0; $r <= $limit; $r++) {

            $tmp = [];
            foreach ($rawBodyRows[$r]->getCells() as $i => $cell) {
                $tmp[$i] = mb_ereg_replace(
                    '\\s+',
                    '',
                    DocumentAiHelperService::extractTextFromAnchor(
                        $text,
                        $cell->getLayout()->getTextAnchor()
                    )
                );
            }

            if (empty($tmp)) continue;

            $joined = implode('|', $tmp);
            $hasName = preg_match('/(品名|名称)/u', $joined);
            $hasQty  = preg_match('/(数量|数)/u', $joined);

            if ($hasName && $hasQty) {
                return $tmp; // ← 正しいヘッダー発見！
            }
        }

        return null;
    }
    // ========= ヘッダー行を使った後の bodyRows 調整　=====================
    private static function sliceBodyAfterHeader($rawBodyRows, ?array $headerTexts): array
    {
        // RepeatedField → array
        if ($rawBodyRows instanceof \Google\Protobuf\Internal\RepeatedField) {
            $rawBodyRows = iterator_to_array($rawBodyRows);
        }

        // ヘッダーが null → そのまま返す
        if ($headerTexts === null) {
            return $rawBodyRows;
        }

        // ヘッダーあり → 1行目を飛ばす
        return array_slice($rawBodyRows, 1);
    }

    // ========= 文字列を整数に正規化（OCR補正付き） ======================
    private static function toIntLike(?string $s): ?int
    {
        if ($s === null) return null;
        $s = trim(mb_convert_kana($s, 'n'));
        $s = strtr($s, ['Ⅰ'=>'1', 'Ｉ'=>'1', 'l'=>'1', 'ｌ'=>'1', '|'=>'1', '$'=>'1', '＄'=>'1']);
        if (preg_match('/^\d{1,3}/u', $s, $m)) return (int)$m[0];
        return null;
    }

    // ========= 配列が +1 連番か ======================================
    private static function isSequentialNumbers(array $values): bool
    {
        $ints = [];
        foreach ($values as $v) {
            $n = self::toIntLike($v);
            if ($n === null) return false;
            $ints[] = $n;
        }
        if (count($ints) < 2) return false;
        for ($i = 0; $i < count($ints) - 1; $i++) {
            if ($ints[$i + 1] - $ints[$i] !== 1) return false;
        }
        return true;
    }




    // ========= 呼び径抽出（冗長置換を整理しつつ既存仕様を維持）=========
    private static function extractCallSizeFromText(string $text): array
    {
        $log = Log::channel('orders_daily');

        try {

            // 置換・除去
            $norm = DocumentAiHelperService::normalizeText($text, $flg1 = false);

            // 「22 1/2」「221/2」などの角度表記を統一（°を付与）
            $norm = DocumentAiHelperService::normalizeHalfDegree($norm);

            $cleanedCallSizes = [];

            // 規格・圧力コード（×ごと除去し removedWords へ）
            [$norm, $removedWords] = DocumentAiHelperService::extractAll($norm);


            // 呼び径抽出
            [$norm, $cleanedCallSizes] = DocumentAiHelperService::extractCallSizes($norm);

            // 商品名（呼び径除去後に残った本文）
            $productName = trim($norm);

            // 規格・圧力コードを商品名末尾へ追記
            if (!empty($removedWords)) {
                $productName = trim($productName . ' ' . implode(' ', $removedWords));
            }

            // 先頭の 0 を除去（ただし "0" 単体にはならないよう注意）
            $cleanedCallSizes = DocumentAiHelperService::cleanCallSizes($cleanedCallSizes);


            return [
                'productName' => $productName,
                'size1'       => $cleanedCallSizes[0] ?? null,
                'size2'       => $cleanedCallSizes[1] ?? null,
                'size3'       => $cleanedCallSizes[2] ?? null,
                'removedWords'=> $removedWords,
            ];

        } catch (\Throwable $e) {
            $log->error('extractCallSizeFromText エラー: ' . $e->getMessage());
            return [
                'productName'   => trim($text),
                'size1'         => null,
                'size2'         => null,
                'size3'         => null,
                'removedWords'  => [],
            ];
        }

    }


}
