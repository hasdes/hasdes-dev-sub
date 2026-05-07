<?php
//未使用

// //（Artisanコマンド）
// namespace App\Console\Commands;
// use Illuminate\Console\Command;

// use Illuminate\Support\Facades\Log;//ログ出力
// use Illuminate\Support\Facades\DB; //DB接続
// use Illuminate\Support\Facades\Storage;// ファイル保存
// use Carbon\Carbon;

// //DocumentAI
// use Google\Cloud\DocumentAI\V1\Document;

// //Services
// use App\Services\DocumentAiService;//DocumentAI呼び出し
// use App\Services\DocumentAiHelper;//共通
// use App\Services\WPService;//渡辺パイプ
// use App\Services\FujiService;//冨士機材
// use App\Services\FusouService;//フソウ
// use App\Services\YamatogawaService;//ヤマトガワ

// //Models
// use App\Models\OrderSlipOCR;//HD受注伝票_OCR
// use App\Models\OrderDetailOCR;//HD受注明細_OCR
// use App\Models\Customer;//得意先
// use App\Models\Syozokubumon; //所属部門


// //OCR読み取り
// class ProcessOrderPDFs extends Command
// {
//     /** PDF読み込み先フォルダ */
//     private string $externalFolder;

//     protected $signature = 'order:process-pdfs'; // Artisanコマンド名

//     public function __construct()
//     {
//         parent::__construct();

//         // フォルダを一元管理
//         $this->externalFolder = storage_path('app/public/faxs');
//     }



//     /**
//      * 自動OCR（全営業所）
//      */
//     public function handle(DocumentAiService $docService)
//     {
//         $log = Log::channel('orders_daily');//ログ

//         try {
//             // $pdfFiles = $this->getPdfFiles();
//             $pdfFiles = $this->getPdfFilesRecursively($this->externalFolder);

//             if (empty($pdfFiles)) {
//                 // $log->info('PDFが見つかりませんでした。', ['dir' => $this->externalFolder]);
//                 $log->info('PDFが見つかりませんでした。');
//                 return Command::SUCCESS;
//             }
//             $log->info('----- 自動OCR開始 ---------------------------------------------------------------------------');

//             // 保存先（publicディスク）を準備　 storage/app/public/ocr_orders
//             Storage::disk('public')->makeDirectory('ocr_orders');


//         // ===============================
//         // DBから営業所マッピングを動的取得
//         // ===============================
//             // 部門CDと営業所名のマップ
//             $officeMap = Syozokubumon::whereIn('部門CD', [10,20,30,40,50,60,70])
//                 ->pluck('部門名', '部門CD')
//                 ->toArray();



//             // 各PDFファイルを順に処理
//             foreach ($pdfFiles as $filePath) {
//                 $fileName = basename($filePath);
//                 $folderName = basename(dirname($filePath)); // フォルダ名取得（a,b,cなど）

//                 // ===============================
//                 // 営業所コード・営業所名の判定
//                 // ===============================
//                 $officeCd = null;
//                 $officeName = null;

//                 //フォルダ名から営業所名判定
//                 if (preg_match('/^(\d{2})/', $folderName, $m)) {
//                     // フィルダ名に "10_" のようなパターンがあればそれを採用
//                     $officeCd = $m[1];
//                     $officeName = $officeMap[$officeCd] ?? '不明営業所';
//                 } else {
//                     // 未判定 → スキップ
//                     $log->warning("営業所コード未判定のためスキップ: {$folderName}");
//                     continue;                
//                 }
                
//                 $log->info('********************************************************');
//                 $log->info("処理対象フォルダ: {$folderName} 処理対象ファイル: {$fileName}（営業所CD: {$officeCd}／営業所名: {$officeName}）");

//                 try {
//                     // 営業所情報を引数に渡す
//                     // $this->processSingleFile($filePath, $docService, $officeCd, $officeName);
//                     $this->processSingleFile($filePath, $docService, $officeCd);
//                 } catch (\Throwable $e) {
//                     $log->error('ファイル処理中に致命的エラー', [
//                         'file' => $fileName,
//                         'officeCd' => $officeCd,
//                         'officeName' => $officeName,
//                         'error' => $e->getMessage(),
//                         'trace' => $e->getTraceAsString()
//                     ]);
//                     continue; // 次のファイルへ
//                 }
//             }     
//             $log->info('----- 自動OCR終了 ---------------------------------------------------------------------------');

//         } catch (\Throwable $e) {
//             $log->critical('バッチ全体でエラー発生', ['error' => $e->getMessage()]);
//         }

//     }


//     /**
//      * 手動OCR（指定営業所）
//      */
//     public function handleWithOffice(string $officeCd, string $officeName)
//     {
//         $log = Log::channel('orders_daily');//ログ
//         $errorFiles = []; // ← 追加

//         $log->info("----- 手動OCR開始: {$officeName}（CD:{$officeCd}） ----------------------------------------------");

//         if($officeCd == 10) {
//             $folderName = '10_tokyo';
//         }
//         elseif($officeCd == 20) {
//             $folderName = '20_osaka';
//         }
//         elseif($officeCd == 30) {
//             $folderName = '30_nagoya';
//         }
//         elseif($officeCd == 40) {
//             $folderName = '40_kyusyu';
//         }
//         elseif($officeCd == 50) {
//             $folderName = '50_sapporo';
//         }
//         elseif($officeCd == 60) {
//             $folderName = '60_hiroshima';
//         }
//         elseif($officeCd == 70) {
//             $folderName = '70_tohoku';
//         }

//         $targetFolder = "{$this->externalFolder}/{$folderName}";

//         if (!is_dir($targetFolder)) {
//             $log->warning("指定フォルダが存在しません: {$targetFolder}");
//             return ['found' => false, 'processed' => 0];
//         }

//         $pdfFiles = $this->getPdfFilesRecursively($targetFolder);//PDFフォルダを取得
//         if (empty($pdfFiles)) {
//             $log->warning("PDFファイルが存在しません（フォルダ内が空）:{$targetFolder}}", [
//                 'dir' => $this->externalFolder
//             ]);
//             return;
//         }

//         $processed = 0;

//         // 2. 各PDFファイルを順に処理
//         foreach ($pdfFiles as $filePath) {
//             $fileName = basename($filePath);
//             $log->info("*************************************");
//             $log->info("OCR対象: {$fileName}");

//             try {
//                  $result = $this->processSingleFile($filePath, app(DocumentAiService::class), $officeCd);

//                 if (is_array($result)) {

//                     // パスワード・破損・非PDF
//                     if (in_array(($result['status'] ?? ''), ['not_pdf', 'password_pdf', 'broken_pdf'], true)) {
//                         $errorFiles[] = $result['message'];
//                         continue;
//                     }

//                     // 既存PDFスキップ
//                     if (($result['status'] ?? '') === 'skipped') {
//                         continue;
//                     }
//                 }

//                 $processed++; // ← 処理件数　※実際に登録できたときだけ

//             } catch (\Throwable $e) {
//                 $log->error('手動処理エラー', [
//                     'file' => $fileName,
//                     'error' => $e->getMessage(),
//                 ]);
//             }
//         }

//         $log->info("----- 手動OCR終了: {$officeName}（処理件数: {$processed}）------------------------------------------");

//         // return にエラー一覧を追加
//         return [
//             'processed' => $processed,
//             'errors' => $errorFiles,
//         ];

//     }

//     /**
//      * サブフォルダも含めてPDFを再帰的に取得
//      */
//     private function getPdfFilesRecursively(string $baseDir): array
//     {
//         $pdfFiles = [];
//         $log = Log::channel('orders_daily');

//         $iterator = new \RecursiveIteratorIterator(
//             new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
//         );

//         foreach ($iterator as $file) {

//             // --- (1) .pdf の場合は PDF 本体かチェック ---
//             if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {

//                 // ファイル名に ":" が入っている場合 → Zone.Identifier の可能性
//                 if (strpos($file->getFilename(), ':') !== false) {
//                     $log->warning("偽PDFファイルを削除しました: {$file->getPathname()}");
//                     @unlink($file->getPathname());
//                     continue;
//                 }

//                 // 通常PDF → 正常リストへ
//                 $pdfFiles[] = $file->getPathname();
//                 continue;
//             }

//             // --- (2) PDF以外 → 削除 ---
//             if ($file->isFile()) {
//                 $log->warning("PDF以外の不要ファイルを削除しました: {$file->getPathname()}");
//                 @unlink($file->getPathname());
//             }
//         }

//         return $pdfFiles;
//     }



//     /**
//      * PDF一覧を取得（共通化）
//      */
//     private function getPdfFiles(): array
//     {
//         return glob($this->externalFolder . '/*.pdf');
//     }



//     /**
//      * 各PDFファイルをOCR解析・保存（共通化）
//      */
//     private function processSingleFile(string $filePath, DocumentAiService $docService, string $officeCd)
//     {
//         $log = Log::channel('orders_daily');
//         DB::reconnect();//ここで毎回DBを接続させる（長時間実行するバッチ処理やスケジュールジョブで、DB接続が切断されるリスクがある場合」に、再接続を強制するための命令）


//         $fileName = basename($filePath);
//         $lockPath = $filePath . '.lock'; // ← ロックファイルのパス

//         // ======== ロック確認 ========
//         if (file_exists($lockPath)) {
//             $log->info("スキップ: 他プロセスで処理中のため {$fileName}");
//             return; // 別プロセスが処理中
//         }


//         // ======== ロック作成 ========
//         try {
//             touch($lockPath); // 空の.lockファイルを作成
//         } catch (\Throwable $e) {
//             $log->warning("ロックファイル作成失敗: {$fileName} ({$e->getMessage()})");
//         }

//         try {

//             $log->info('処理開始');

//             // ===================================================
//             // ★ officeCd 未設定はここへ（finally を必ず通すため）
//             // ===================================================
//             if (empty($officeCd)) {
//                 $log->error("officeCd が未設定のため処理中断: {$fileName}");
//                 return;
//             }

//             // ===================================================
//             // ★ 読み取り専用 PDF 判定（削除より前）
//             // ===================================================            
//             // -----------------------
//             // ① PDFヘッダ確認 (%PDF)
//             // -----------------------
//             $fp = @fopen($filePath, 'rb');
//             if (!$fp) {
//                 return [
//                     'status'  => 'not_pdf',
//                     'file'    => $fileName,
//                     'message' => 'PDFを開けません'
//                 ];
//             }

//             $header = fread($fp, 2048);
//             fclose($fp);

//             if (strpos($header, '%PDF') !== 0) {
//                 return [
//                     'status'  => 'not_pdf',
//                     'file'    => $fileName,
//                     'message' => 'PDFではないか破損しています'
//                 ];
//             }

//             // -----------------------
//             // ② パスワード付きPDF確認 (/Encrypt)
//             // -----------------------
//             if (strpos($header, '/Encrypt') !== false) {
//                 return [
//                     'status'  => 'password_pdf',
//                     'file'    => $fileName,
//                     'message' => 'パスワード付きPDFのため処理できません'
//                 ];
//             }

//             // ===================================================
//             // 既にDBに登録済み → スキップ & 削除
//             // ===================================================            
//             $exists = OrderSlipOCR::where('PDF', $fileName)
//                 ->where('管轄部門CD', $officeCd)
//                 ->exists();

//             if ($exists && file_exists($filePath)) {

//                 @unlink($filePath); // 元ファイル削除

//                 $log->info("既に登録済みのためスキップ&ファイル削除");

//                 return [
//                     'status' => 'skipped',
//                     'file' => $fileName,
//                     'message' => "既存PDFのためスキップ: {$fileName}"
//                 ];
//             }

//             // ===========================
//             // Document AI OCR 処理
//             // ===========================

//             try {

//             $document = $docService->processLocalPdf($filePath);

//             } catch (\Throwable $e) {

//                 $msg = $e->getMessage();

//                 // パスワードPDF判定
//                 if (stripos($msg, 'password') !== false ||
//                     stripos($msg, 'encrypt') !== false) {

//                     $log->warning("パスワードPDFのため退避: {$fileName} ({$msg})");

//                     return [
//                         'status'  => 'password_pdf',
//                         'file'    => $fileName,
//                         'message' => "PDFがパスワード保護されているため処理できません: {$fileName}"
//                     ];
//                 }

//                 // 壊れてるPDF判定
//                 $log->warning("破損PDFのため退避: {$fileName} ({$msg})");

//                 return [
//                     'status'  => 'broken_pdf',
//                     'file'    => $fileName,
//                     'message' => "PDFが破損しているため処理できません: {$fileName}"
//                 ];
//             }

//             //読み込み失敗
//             if (!$document) {
//                 $log->error('OCR取得失敗', ['file' => $fileName]);
//                 return;
//             }

//             //OCR全てのテキスト
//             $text = $document->getText();

//             // =======================
//             // OCR全体テキスト 確認用 ログ
//             // =======================
//             $log->info('========テキスト一覧==========');
//             $log->info($text);
//             $log->info('========================');
//             $lines = preg_split("/\r\n|\n|\r/", $text);

//             // =======================
//             // Form Parser 確認用 ログ
//             // =======================
//             // --- Tables ---
//             $log->info("=== Tables ===");

//             foreach ($document->getPages() as $pageIndex => $page) {
//                 foreach ($page->getTables() as $tableIndex => $table) {
//                     $log->info("Page {$pageIndex} - Table {$tableIndex}");
//                     foreach ($table->getHeaderRows() as $rowIndex => $row) {
//                         $headers = [];
//                         foreach ($row->getCells() as $cell) {
//                             $headers[] = DocumentAiHelper::layoutText($document, $cell->getLayout()->getTextAnchor());
//                         }
//                         $log->info("Header Row {$rowIndex}: " . implode(' | ', $headers));
//                     }
//                     foreach ($table->getBodyRows() as $rowIndex => $row) {
//                         $values = [];
//                         foreach ($row->getCells() as $cell) {
//                             $values[] = DocumentAiHelper::layoutText($document, $cell->getLayout()->getTextAnchor());
//                         }
//                         $log->info("Row {$rowIndex}: " . implode(' | ', $values));
//                     }
//                 }
//             }
//             $log->info("=================");

//             // =====================================
//             // ファイル名から会社判定（最優先）
//             // =====================================
//             $matchedCompany = null;

//             if (preg_match('/冨士機材/u', $fileName)) {
//                 $matchedCompany = '冨士機材';
//             } elseif (preg_match('/渡辺パイプ|WP|渡パイ|渡辺ﾊﾟｲﾌﾟ/u', $fileName)) {
//                 $matchedCompany = '渡辺パイプ';
//             } elseif (preg_match('/ﾔﾏﾄｶﾞﾜ|ヤマトガワ/u', $fileName)) {
//                 $matchedCompany = 'ヤマトガワ';
//             } elseif (preg_match('/フソウ|ﾌｿｳ/u', $fileName)) {
//                 $matchedCompany = 'フソウ1';
//             } elseif (preg_match('/共立株式会社/u', $fileName)) {
//                 $matchedCompany = '共立株式会社';
//             }


//             // =====================================
//             // 会社ごとの共通定義
//             // =====================================        
//             $companies = [
//                 '冨士機材' => ['match' => '冨士機材'],
//                 '富士機材' => ['match' => '富士機材'],
//                 '共立株式会社' => ['match' => '共立株式会社'],
//                  'ヤマトガワ' => [
//                     'match' => [
//                         'ヤマトガワ', 'ﾔﾏﾄｶﾞﾜ', 'ヤマトガワ', 'ﾔﾏﾄｶﾞﾜ', 'ヤ小ガワ', 'マ卜ガフ(株)' // 正規化揺れ全部
//                     ]
//                 ],
//                 '中ト(株)' => ['match' => '中ト(株)'],
//                 'フソウ1' => ['match' => '株式会社フソウ'],
//                 'フソウ2' => ['match' => '株式会社 フソウ'],
//                 '渡辺パイプ' => ['match' => '渡辺パイプ']
//             ];

//             //====================================
//             // 共通
//             //====================================
//             $fileNameNorm = \Normalizer::normalize($fileName, \Normalizer::FORM_KC);
//             $textNorm = \Normalizer::normalize($text, \Normalizer::FORM_KC);

//             foreach ($companies as $name => $config) {

//                 if (!$matchedCompany) {
//                     // --- 会社判定 ---
//                     foreach ($companies as $name => $conf) {

//                         $patterns = (array)$conf['match'];

//                         foreach ($patterns as $p) {
//                             if (mb_strpos($textNorm, $p) !== false || mb_strpos($fileNameNorm, $p) !== false) {
//                                 $matchedCompany = $name;
//                                 $log->info("会社判定成功: {$name}");
//                                 break 2;
//                             }
//                         }
//                     } 
//                 } else {
//                     $log->info("会社判定成功（ファイル名から）: {$matchedCompany}");
//                 }
                
//                 // ===========================
//                 // 各社ごとのOCR解析呼び出し
//                 // ===========================
//                 // 会社名が見つからなくてもスキップせず続行
//                 if (!$matchedCompany) {
//                     $log->warning('会社判定できませんでした（会社不明として登録します）', ['file' => $fileName]);
//                     $info = [];
//                     $items = [];
//                 } else {
//                     // 各社のOCR処理
//                     try {
//                         switch ($matchedCompany) {
//                             case '渡辺パイプ':
//                                 $info  = WPService::wpOrderInfo($text, $document);
//                                 $items = WPService::wpOrderItems($text, $document);
//                                 break;

//                             case '冨士機材':
//                             case '富士機材':
//                             case '共立株式会社':
//                                 $info  = FujiService::FujiOrderInfo($text, $document);
//                                 $items = FujiService::FujiOrderItems($text, $document);
//                                 break;

//                             case 'フソウ1':
//                             case 'フソウ2':
//                                 $info  = FusouService::FusouOrderInfo($text, $document);
//                                 $items = FusouService::FusouOrderItems($text, $document);
//                                 break;

//                             case 'ヤマトガワ':
//                             case '中ト(株)':
//                                 $info  = YamatogawaService::YamatogawaOrderInfo($text, $document);
//                                 $items = YamatogawaService::YamatogawaOrderItems($text, $document);
//                                 break;

//                             default:
//                                 $info = [];
//                                 $items = [];
//                                 $log->warning("未対応会社: {$matchedCompany}");
//                         }
//                     } catch (\Throwable $e) {
//                         $log->error("{$matchedCompany} のOCR解析中にエラー", [
//                             'file' => $fileName,
//                             'error' => $e->getMessage()
//                         ]);
//                         // 失敗してもinfo/itemsを空にして続行
//                         $info = [];
//                         $items = [];
//                     }
//                 }
//                     // $log->info(print_r($info, true)); // ← ここで $info の中身をログ出力
//                     // exit;

                
//                 //====================================
//                 // DB保存
//                 //====================================
//                 $jurisdictionCD = $officeCd ?? 10; //管轄部門CD 仮
//                 $order = null;

//                 try {
//                     DB::beginTransaction();

//                     // 必要フィールドをinfoから受け取る（無ければnull）
//                         $order = OrderSlipOCR::create([
//                             '管轄部門CD' => $jurisdictionCD,
//                             'ヘッダ希望納期' => $info['shipDate'] ?? null,
//                             '得意先名' => $info['customerName'] ?? null,
//                             '出荷先名' => $info['shippingName'] ?? null,
//                             '送り状印字内容' => $info['dueDate'] ?? null,
//                             '土日着日指定' => $info['saturday'] ?? null,
//                             '相手先注文NO_得意先' => $info['orderNumber'] ?? null,
//                             '相手先注文NO_出荷先' => $info['shippingNumber'] ?? null,
//                             '営業用備考' => $info['salesBikou'] ?? null,
//                             'PDF' => $fileName,
//                         ]);

//                         // // 6. テーブル情報（商品明細）も保存
//                         $orderId = $order?->getKey();

//                         foreach ($items as $i) {
//                             OrderDetailOCR::create([
//                                 'HD受注伝票_OCR_ID' => $orderId,
//                                 '商品名' => $i['商品名'] ?? null,
//                                 '呼び径1' => $i['呼び径1'] ?? null,
//                                 '呼び径2' => $i['呼び径2'] ?? null,
//                                 '呼び径3' => $i['呼び径3'] ?? null,
//                                 '数量' => $i['数量'] ?? null,
//                                 '備考' => $i['備考'] ?? null,
//                             ]);
//                         }

//                     // ここまでOKなら commit
//                     DB::commit();

//                     $log->info('DB登録完了');

//                 } catch (\Throwable $e) {
//                     DB::rollBack();
//                     $log->error('DB保存に失敗', ['file' => $fileName, 'error' => $e->getMessage()]);// 失敗ファイルの取り扱い（必要なら failed/ へ）
//                     return; //次のPDFを読み込み
//                 }

//             //====================================
//             // PDFを移動させる
//             //====================================
                
//                 try {

//                     Storage::disk('public')->putFileAs(
//                         'ocr_orders',
//                         new \Illuminate\Http\File($filePath),
//                         $fileName
//                     );
//                     @unlink($filePath); // 元ファイル削除

//                     $log->info('PDF移動完了', [
//                         'from' => $filePath,
//                         'to' => "ocr_orders/{$fileName}"
//                     ]);

//                 } catch (\Throwable $e) {
//                     $log->error('PDF移動に失敗（DBは保存済）', ['file' => $fileName, 'error' => $e->getMessage()]);        // ここでDBの状態は既にcommit済。ファイル移動だけ後でリトライできるようログに残す。
//                 }

//                 break;  // 最初にヒットした会社だけ処理

//             }



//         } catch (\Throwable $e) {
//             $log->error('processSingleFile 致命的エラー', ['file' => $fileName, 'error' => $e->getMessage()]);
//         } finally {
//             // メモリ・接続解放
//             unset($document);
//             gc_collect_cycles();

//             $log->info("処理完了");

//             // ======== ロック解除 ========
//             if (file_exists($lockPath)) {
//                 @unlink($lockPath);
//                 $log->info("ロック解除: {$fileName}");
//             }
//         }
//     }

// }
