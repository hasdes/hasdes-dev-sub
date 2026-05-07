<?php
// namespace App\Services;
namespace App\Services\Ocr;

// Google Document AI を使ってPDFをOCR解析するサービスクラス
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\Document;
use Google\ApiCore\ApiException;//Google API呼び出し時の例外クラス。
use Illuminate\Support\Facades\Log;//ログ出力


// Google DocumentAIのAPIと連結する関数
class DocumentAiService {

    //*************************************************************** */
    // Google Document AIの情報
    //*************************************************************** */
    public function processLocalPdf(string $filePath): ?Document {

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . base_path('project-0cb604f8-c9fa-4c68-b65-e898dd049948.json'));//HAS用本番

        //プロジェクトID
        $projectId = 'project-0cb604f8-c9fa-4c68-b65';//HAS用本番
        

        //言語
        $location = 'us';

        //プロセッサーID
        $processorId = 'da6555cf7b2af162';// Form_Parser

        $client = new \Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient();

        //プロセッサー名
        $processorName = sprintf(
            'projects/%s/locations/%s/processors/%s',
            $projectId,
            $location,
            $processorId
        );

        $pdfContent = file_get_contents($filePath);
        if ($pdfContent === false || !is_string($pdfContent)) {
            Log::error("PDFの読み込みに失敗: {$filePath}");
            return null;
        }

        $rawDocument = new \Google\Cloud\DocumentAI\V1\RawDocument([
            'content' => $pdfContent,
            'mime_type' => 'application/pdf',
        ]);

        $request = new \Google\Cloud\DocumentAI\V1\ProcessRequest([
            'name' => $processorName,
            'raw_document' => $rawDocument,
        ]);

        // リクエストを構築
        $request = new ProcessRequest([
            'name' => $processorName,
            'raw_document' => $rawDocument,
            // 'process_options' => $processOptions
        ]);

        try {
            //API呼び出し
            $response = $client->processDocument($request);
            return $response->getDocument();

        } 

        catch (ApiException $e) {
            Log::error('Document AI 呼び出し失敗', [
                'status' => $e->getStatus(),         // 例: PERMISSION_DENIED, INVALID_ARGUMENT, RESOURCE_EXHAUSTED
                'message' => $e->getMessage(),
                'basicMessage' => $e->getBasicMessage(),
            ]);

            $message = $e->getMessage();

            if (str_contains($message, 'BILLING_DISABLED')) {
                throw new \Exception('Google Cloudの課金設定が無効です。管理者にお問い合わせください。');
            }

            if (str_contains($message, 'PERMISSION_DENIED')) {
                throw new \Exception('Document AI の権限が不足しています。管理者にお問い合わせください。');
            }

            throw new \Exception('Document AIの処理に失敗しました。');
        }

    }
}
