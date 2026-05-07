<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\FruitsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ContentsController;
use App\Http\Controllers\SystemMessageController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\SyozokubumonController;
use App\Http\Controllers\SyozokuController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HDLogController;
use App\Http\Controllers\HinmeiController;
use App\Http\Controllers\DispatchController;//配車計画
use App\Http\Controllers\DispatchUnavailableController;//配車計画　配車
use App\Http\Controllers\ShippingController; // 出荷先
use App\Http\Controllers\ConverCustomerController;//D変換得意先
use App\Http\Controllers\ConverShippingController;//D変換出荷先
use App\Http\Controllers\ConverProductController;//D変換商品
use App\Http\Controllers\OrderSlipOCRController;//HD受注伝票_OCR
use App\Http\Controllers\OrderDetailOCRController;//HD受注伝票_OCR
use App\Http\Controllers\ConverOrderOCRController;//D受注伝票_OCR,D受注明細_OCR
// use App\Http\Controllers\OcrManualController;//OCR手動
use App\Http\Controllers\PickUpController;
use App\Http\Controllers\QRCodeReadingController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    if (!$request->session()->has('担当者CD')) {
        // return redirect('/elogin'); // セッションがなければログインページへリダイレクト
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    return $request->user();
});

//  セッション確認（後で非表示）
Route::get('/session-check', function (Request $request) {
    // \Log::info('session all:', $request->session()->all());
    return response()->json([
        'authenticated' => auth()->check(),
        // 'has_tanto_cd' => $request->session()->has('担当者CD'),
        // 'user' => $request->user(),
        // 'dispatch_display' => $request->session()->get('配車表示', null),
        '担当者CD' => $request->session()->get('担当者CD'),
    ]);
});

//セッション取得 （所属部門CD / 担当者CD を返す）
Route::get('/auth/userinfo', function (Request $request) {
    return response()->json([
        '担当者CD'   => $request->session()->get('担当者CD'),
        '所属部門CD' => $request->session()->get('所属部門CD'),
    ]);
});



//ログアウト
Route::post('/logout', function (Request $request) {
    Auth::logout(); // 認証状態を削除
    $request->session()->invalidate(); // セッションを破棄
    $request->session()->regenerateToken(); // CSRFトークンをリセット

    return response()->json(['message' => 'logged out']);
});



Route::group(['middleware' => ['auth:sanctum']], function () {
    
    Route::get('/fruits/list',[FruitsController::class,'list']);
    Route::put('/fruits/update/{fruit}',[FruitsController::class,'update']);
    Route::post('/fruits/create',[FruitsController::class,'create']);
    Route::delete('/fruits/delete/{fruit}',[FruitsController::class,'delete']);

    Route::get('/auth/list',[UsersController::class,'list']);
    Route::put('/auth/update',[UsersController::class,'update']);
    Route::get('/auth/getUserId',[UsersController::class,'getUserId']);
    Route::get('/auth/listcount',[UsersController::class,'listcount']);
    Route::get('/auth/listtime',[UsersController::class,'listtime']);
    Route::post('/auth/getHomePermission',[UsersController::class,'getHomePermission']);//ホームの権限取得

    Route::get('/hinmei/search', [HinmeiController::class, 'search']);

    Route::get('/item/list', [ItemController::class, 'list']);
    Route::get('/item/detail', [ItemController::class, 'detail']);

    Route::get('/staff/list', [StaffController::class, 'list']);
    Route::post('/staff/update', [StaffController::class, 'updatePermissions']);
    Route::post('/staff/initializePassword', [StaffController::class, 'initializePassword']);
    Route::get('/staff/permissions/{staffCD}', [StaffController::class, 'getPermissions']);
    Route::get('/staff/getjyugyoinkubun/{staffCD}', [StaffController::class, 'getjyugyoinkubun']);
    Route::post('/staff/updatejyugyoinkubun', [StaffController::class, 'updatejyugyoinkubun']);
    Route::post('/staff/rockstatuschenge', [StaffController::class, 'rockstatuschenge']);
    Route::get('/staff/listformess', [StaffController::class, 'listformess']);
    
    
    Route::get('/stock/list', [StockController::class, 'list']);
    Route::get('/stock/stocklist', [StockController::class, 'stocklist']);

    Route::post('/contents/create',[ContentsController::class,'create']);
    Route::get('/contents/list', [ContentsController::class, 'list']);
    Route::get('/contents/detail', [ContentsController::class, 'detail']);
    Route::post('/contents/update', [ContentsController::class, 'update']);
    Route::post('/contents/edit', [ContentsController::class, 'edit']);


    Route::get('/message/list', [MessageController::class, 'list']);
    Route::get('/message/detail', [MessageController::class, 'detail']);
    Route::post('/message/create', [MessageController::class, 'create']);
    Route::post('/message/newcreate', [MessageController::class, 'newcreate']);
    
    Route::get('/systemmessage/list', [SystemMessageController::class, 'list']); 
    Route::get('/systemmessage/detail', [SystemMessageController::class, 'detail']);    

    Route::get('/customer/list', [CustomerController::class, 'list']);
    Route::get('/customer/detail', [CustomerController::class, 'detail']);
    // Route::get('/customer/ocr_list', [CustomerController::class, 'ocr_list']);
    // Route::get('/customer/ocr_search', [CustomerController::class, 'ocr_search']);


    // Route::get('/departments', [SyozokubumonController::class, 'getDepartments']);
    Route::get('/departments/getDepartments', [SyozokubumonController::class, 'getDepartments']);//部門全て
    Route::get('/departments/getSalesOffice', [SyozokubumonController::class, 'getSalesOffice']);//営業所のみ

    Route::get('/sections', [SyozokuController::class, 'getSections']);

    Route::get('/home/Messagelist', [HomeController::class, 'Messagelist']);
    Route::get('/home/Contentslist', [HomeController::class, 'Contentslist']);

    Route::get('/HDLog/list', [HDLogController::class, 'list']);
    Route::get('/HDLog/detail', [HDLogController::class, 'detail']);
    Route::get('/HDLog/exportCsv', [HDLogController::class, 'exportCsv']);
    Route::post('/HDLog/create', [HDLogController::class, 'create']);
    
    //配車計画
    Route::get('/dispatch/list', [DispatchController::class, 'list']);
    Route::put('/dispatch/edit', [DispatchController::class, 'edit']);    
    Route::put('/dispatch-unavailable/add', [DispatchUnavailableController::class, 'add']);    
    Route::put('/dispatch-unavailable/delete', [DispatchUnavailableController::class, 'delete']);    
    Route::get('/dispatch-unavailable/list', [DispatchUnavailableController::class, 'list']);    
    Route::get('/dispatch/filter', [DispatchController::class, 'getBase']);

    //拠点セッション保存 Filter.vue
    Route::post('/set-department-cd', function (Request $request) {
        $request->validate([
            'department_cd' => 'required|string',
        ]);
        session(['部門CD' => $request->input('department_cd')]);
        return response()->json(['message' => 'Saved']);
    });

    //拠点セッション取得
    Route::get('/get-department-cd', function () {
    return response()->json([
        '部門CD' => session('部門CD')
    ]);
    });

    //社内ヘルプデスク
    Route::post('/log/helpdesk', [HDLogController::class, 'logHelpdesk']);

    //受注入力データ
    Route::get('/orderslip-ocr/list', [OrderSlipOCRController::class, 'list']);//伝票一覧
    Route::post('/orderslip-ocr/edit', [OrderSlipOCRController::class, 'edit']);//営業所変更
    Route::get('/orderslip-ocr/detail', [OrderSlipOCRController::class, 'detail']);//伝票詳細
    Route::post('/orderslip-ocr/edit', [OrderSlipOCRController::class, 'edit']);//伝票更新
    Route::post('/orderslip-ocr/delete', [OrderSlipOCRController::class, 'delete']);//削除
    Route::get('/orderslip-ocr/search', [OrderSlipOCRController::class, 'search']);//伝票詳細重複チェック
    Route::get('/orderdetail-ocr/list', [OrderDetailOCRController::class, 'list']);//明細（商品）一覧

    Route::post('/converorder-ocr/create', [ConverOrderOCRController::class, 'create']);//受注入力データ変換(新規)

    Route::get('/shipping/list', [ShippingController::class, 'list']);//出荷先一覧
    Route::get('/shipping/detail', [ShippingController::class, 'detail']);//出荷先詳細

    //得意先変換
    Route::get('/convercustomer/list', [ConverCustomerController::class, 'list']);//一覧
    Route::get('/convercustomer/detail', [ConverCustomerController::class, 'detail']);//詳細
    Route::put('/convercustomer/edit', [ConverCustomerController::class, 'edit']);//更新
    Route::post('/convercustomer/create', [ConverCustomerController::class, 'create']);//新規
    Route::put('/convercustomer/update', [ConverCustomerController::class, 'update']);//削除

    //出荷先変換
    Route::get('/convershipping/list', [ConverShippingController::class, 'list']);//一覧
    Route::get('/convershipping/detail', [ConverShippingController::class, 'detail']);//詳細
    Route::put('/convershipping/edit', [ConverShippingController::class, 'edit']);//更新
    Route::post('/convershipping/create', [ConverShippingController::class, 'create']);//新規
    Route::put('/convershipping/update', [ConverShippingController::class, 'update']);//削除

    //商品変換
    Route::get('/converproduct/productlist', [ConverProductController::class, 'productlist']);//一覧
    Route::get('/converproduct/list', [ConverProductController::class, 'list']);//一覧
    Route::get('/converproduct/detail', [ConverProductController::class, 'detail']);//詳細
    Route::put('/converproduct/edit', [ConverProductController::class, 'edit']);//更新
    Route::post('/converproduct/create', [ConverProductController::class, 'create']);//新規
    Route::put('/converproduct/update', [ConverProductController::class, 'update']);//削除

    //OCR読み取り手動
    // Route::post('/orderslip-ocr/manual', [OcrManualController::class, 'manual']);

    //PDFアップロード
    Route::post('/orderslip-ocr/upload', [OrderSlipOcrController::class, 'upload']);

    //集荷
    Route::get('/pickup/list', [PickUpController::class, 'list']);//一覧
    Route::get('/pickup/search', [PickUpController::class, 'search']);//再集荷一覧
    Route::get('/pickup/detail', [PickUpController::class, 'detail']);//指示書
    Route::post('/qrcodereading/check', [QRCodeReadingController::class, 'check']);//スキャン
    Route::post('/pickup/suspend', [PickUpController::class, 'suspend']);//作業中断
    Route::post('/pickup/complete', [PickUpController::class, 'complete']);//集荷完了

});
