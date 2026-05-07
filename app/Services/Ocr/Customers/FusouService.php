<?php
// namespace App\Services;
namespace App\Services\Ocr\Customers;
use App\Services\Ocr\DocumentAiHelperService;
use Google\Cloud\DocumentAI\V1\Document;
use Illuminate\Support\Facades\Log;


//========================
//フソウ
//========================
class FusouService
{
    // ------------------
    // 注文情報抽出
    // ------------------

    public static function FusouOrderInfo(string $text, Document $document): array
    {

        $log = Log::channel('orders_daily');//ログ

        try {

            $orderNumber = $customerName = $shippingName = $dueDate = $saturday = $shippingNumber = $siteName = null;

            // 1. 発注番号（元コードをそのまま活かす）
            if (preg_match('/注文(番号|ナンバー)[：:]\s*([A-Z0-9]+)/u', $text, $m1)) {
                $orderNumber = trim(mb_convert_kana($m1[2], 'a'));
            }

            // 2. 得意先　◯◯支店 or ◯◯営業所  ◯◯支社　に対応
            if (preg_match_all('/株式会社\s*フソウ\s*[^\n\r]{0,20}(支店|支社|営業所|本店|本社|営業部)/u', str_replace("\n", ' ', $text), $matches)) {
                
                foreach ($matches[0] as $match) { // ← array_reverseしない！
                    // ハズの営業所はスキップ
                    if (!preg_match('/ハズ/u', $match)) {
                        $customerName = trim(preg_replace('/\s+/u', ' ', $match));
                        break;
                    }
                }
            }

            // 3. 出荷先（納所・納入先）
            if (preg_match('/(納所|【納入先】)[：:\s]*((?:.*\n?){1,3})/u', $text, $m3)) {

                $rawShipping = $m3[2];//３行取得

                // TELやFAXなどで終端を強制的に切る
                $rawShipping = preg_replace('/(TEL|FAX|電話)[^\\n]*/u', '', $rawShipping);

                $rawShipping = str_replace(['|'], '', $rawShipping); // 記号除去

                // 改行・余分な空白を正規化
                $shippingName = preg_replace('/\s+/u', ' ', trim($rawShipping));
                
                // 末尾の数字ノイズ除去（例: 13 32）
                $shippingName = preg_replace('/\s{0,2}\d{1,3}\s*\d{1,3}$/u', '', $shippingName);
            }

            // 4. 納期（希望納期 or ◯/◯着 両方対応）
            if (preg_match('/希望納期\s?(.*?)(?:\n|$)/u', $text, $m4)) {
                $rawArrivalDate = trim($m4[1]);
            } elseif (preg_match('/納期[:：]?\s*(\d{1,2}[\/／]\d{1,2})\s*着/u', $text, $m5)) {
                $rawArrivalDate = trim($m5[1]);
            }

            //　納期取得
            $dueDate = !empty($rawArrivalDate) ? DocumentAiHelperService::formatDateToNumber($rawArrivalDate, $saturday) : null;
            // 出荷日
            $shipDate = !empty($dueDate) ? DocumentAiHelperService::getDay($dueDate) : null;

            // 6.出荷先用
            // 「案件名：」~「下記の通り御注文申し上げます」直前まで取得
            if (preg_match('/案件名\s*[:：]?\s*([\s\S]*?)(?=下記の通り御注文申し上げます|下記の通り|御注文|申し上げます)/u',$text,$m)) {
                $shippingNumber = trim(preg_replace('/\s+/u', ' ', $m[1]));
            }
            elseif (preg_match('/(案件名|物件名\d?)\s*[:：]?\s*(.+)/u', $text, $m7)) {
                $shippingNumber = trim($m7[2]);
            }

            // [共通]　出荷先に「当社」または「弊社」が含まれていたら得意先名に置き換え
            $shippingName = DocumentAiHelperService::formatshippingName($customerName ?? '', $shippingName ?? '');



            // ------ 最終レコード作成 ------
            return [
                'orderNumber'    => $orderNumber,//注文番号
                'customerName'   => $customerName,//得意先
                'shippingName'   => $shippingName,//出荷先
                'shipDate'       => $shipDate,//出荷日
                'dueDate'        => $dueDate,//納期
                'saturday'        => $saturday,//納期
                'shippingNumber' => $shippingNumber,//出荷先用
                'salesBikou'     => $siteName,//備考 (フソウは無し)
            ];

        } catch (\Throwable $e) {
            // エラー内容をログ出力して終了
            $log->error('FusouOrderInfo 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            // 上位（コマンド側）でDB保存・PDF移動を継続させるため再スロー
            throw $e;
        }

    }



    // ------------------
    // 商品明細抽出
    // ------------------

    public static function FusouOrderItems(string $text, Document $document): array
    {
        $log = Log::channel('orders_daily');//ログ
        $items = [];

        try {
            foreach ($document->getPages() as $page) {
                foreach ($page->getTables() as $table) {

                    try {

                        // ヘッダー処理
                        $headerRows = $table->getHeaderRows();
                        $bodyRows = [];
                        $headerTexts = [];

                        // ===== ヘッダー処理（なければ1行目を疑似ヘッダーに） =====
                        list($headerTexts, $bodyRows) = DocumentAiHelperService::extractTableHeaderAndBodyRows($table, $headerRows, $text);

                        // ===== ヘッダーから列インデックス（商品名・数量）を特定 =====   
                        $nameCol = $shapeCol = $qtyCol = $specCol = null;

                        foreach ($headerTexts as $i => $label) {
                            $label = preg_replace('/\s+/u', '', $label);
                            if (preg_match('/(商品名|品名)/u', $label))  $nameCol  = $i;          // 品名 or 商品名
                            if (preg_match('/形状.?寸法/u', $label))      $shapeCol = $i;          // 形状･寸法
                            if (preg_match('/数量/u', $label))            $qtyCol   = $i;
                            if (preg_match('/規格/u', $label))            $specCol  = $i;          // 旧パターン用の後方互換
                        }

                        //テーブルの中身
                        foreach ($bodyRows as $row) {
                            try {

                                $cells = $row->getCells();
                                $productName = null;//商品名
                                // $callSize = null; 
                                $size1 = $size2 = $size3 = null;//呼び径

                                // ------ 品名（商品名）は品名/商品名カラムだけを使う ------
                                $name = '';
                                if ($nameCol !== null && $nameCol < count($cells)) {
                                    $name = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$nameCol]->getLayout()->getTextAnchor()
                                    ));

                                    $productName = DocumentAiHelperService::normalizeProductName($name, true);
                                }

                                // ------ 数量 ------
                                $rawQty = ($qtyCol !== null && $qtyCol < count($cells))
                                    ? trim(DocumentAiHelperService::extractTextFromAnchor($text, $cells[$qtyCol]->getLayout()->getTextAnchor()))
                                    : '';
                                preg_match('/\d+/', $rawQty, $mQty);
                                $quantity = isset($mQty[0]) ? mb_convert_kana($mQty[0], 'n') : '';

                                // ------ 非商品（ノイズ）行フィルタ ------
                                // 商品列に管理情報が混入するので弾く
                                $nonItemPattern = '/(注文(番号|ナンバー)|納期|納所|発行日|発注|TEL|FAX|住所|株式会社|支店|総金額|合計|T価|備考|メーカー)/u';
                                if ($productName !== '' && preg_match($nonItemPattern, $productName)) {
                                    continue;
                                }

                                // 「品名も数量も空」ならスキップ
                                if (empty($productName) && empty($quantity)) continue;

                                // ------ 呼び径 ------
                                // 形状・寸法項目がある場合（商品名と呼び径が別）
                                $hasCallSize = false;

                                if ($shapeCol !== null && $shapeCol < count($cells)) {
                                    $shapeText = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$shapeCol]->getLayout()->getTextAnchor()
                                    ));
                                    $size = self::extractCallSizeFromText($shapeText);
                                    if (!empty($size['size1'])) {
                                        $hasCallSize = true;
                                        $size1 = $size['size1']; $size2 = $size['size2']; $size3 = $size['size3'];
                                        if (!empty($size['removedWords'])) {
                                            $productName .= ' ' . implode(' ', $size['removedWords']);
                                        }
                                    }
                                }

                                //形状・寸法項目がない場合（商品名の中に呼び径がある）
                                // shape で取れなかった場合のみ specCol で補完
                                if (!$hasCallSize && $specCol !== null && $specCol < count($cells)) {
                                    $spec = trim(DocumentAiHelperService::extractTextFromAnchor(
                                        $text, $cells[$specCol]->getLayout()->getTextAnchor()
                                    ));
                                    $size = self::extractCallSizeFromText($spec);
                                    if (!empty($size['size1'])) {
                                        $size1 = $size['size1']; $size2 = $size['size2']; $size3 = $size['size3'];
                                        $productName = DocumentAiHelperService::normalizeProductName($size['productName'], false);
                                    }
                                }

                                // ------ 最終レコード作成 ------
                                $items[] = [
                                    '商品名'  => $productName,
                                    '呼び径1' => $size1,
                                    '呼び径2' => $size2,
                                    '呼び径3' => $size3,
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
            $log->error('FusouOrderItems 強制終了: ' . $e->getMessage());
            $log->error($e->getTraceAsString());
            throw $e; // 上位 (YamatogawaOrderItems) に伝播
        }

    }


    //フソウ用
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

            // === 呼び径抽出の前に 角度、規格・圧力コード を抽出する（×も含めて削除） ===
            [$norm, $removedWords] = DocumentAiHelperService::extractAll($norm);

            //　呼び径抽出
            [$norm, $cleanedCallSizes] = DocumentAiHelperService::extractCallSizes($norm);

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
                'size1' => $cleanedCallSizes[0] ?? '',//呼び径1
                'size2' => $cleanedCallSizes[1] ?? '',//呼び径2
                'size3' => $cleanedCallSizes[2] ?? '',//呼び径3
                'removedWords' => $removedWords,//除去ワード（商品名に加える）
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