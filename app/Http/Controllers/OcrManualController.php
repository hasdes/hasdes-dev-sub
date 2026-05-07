<?php
//未使用
// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Console\Commands\ProcessOrderPDFs;


// // 手動OCR読み取り (Ocr/Filter.vue)
// class OcrManualController extends Controller
// {
//     public function manual(Request $request)
//     {
//         $officeCd = $request->input('sales_office_cd');//部門CD（営業所CD）
//         $officeName = $request->input('sales_office_name');//部門名（営業所名）

//         // if (!$officeCd || !$officeName) {
//         if ($officeCd === null || $officeCd === '' || $officeName === null || $officeName === '') {
//             \Log::info("営業所情報が不足しています(エラー400)：", ['部門CD' => $officeCd,'部門名' => $officeName]);
//             return response()->json(['message' => '営業所情報が不足しています'], 400);
//         }

//         // \Log::info("手動OCR開始", ['営業所CD' => $officeCd,'営業所名' => $officeName]);
        
//         try {
//             // DI推奨：app() で解決（newしない）
//             $command = app(ProcessOrderPDFs::class);
//             $result = $command->handleWithOffice($officeCd, $officeName); // ['found'=>bool, 'processed'=>int]

// // ★★★ 手動OCR：読み取り専用エラーの返却処理 ここを追加！ ★★★
// if (!empty($result['errors'])) {
//     return response()->json([
//         'status' => 'error',
//         'errors' => $result['errors'], // ← Vueにそのままアラートで使える
//     ], 200);
// }

//             // return response()->json([
//             //     'message'         => "営業所 {$officeName}（CD:{$officeCd}）のOCR処理を開始しました",
//             //     // 'found'           => (bool)($result['found'] ?? false),
//             //     'processed_count' => (int)($result['processed'] ?? 0),
//             // ]);

// // 通常成功
// return response()->json([
//     'status'          => 'success',
//     'message'         => "営業所 {$officeName}（CD:{$officeCd}）のOCR処理を開始しました",
//     'processed_count' => (int)($result['processed'] ?? 0),
// ]);

//         } catch (\Exception $e) {
//             \Log::error('OCR処理中にエラーが発生', ['error' => $e->getMessage()]);

//             // 500エラーとして返す
//             return response()->json([
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }
// }
