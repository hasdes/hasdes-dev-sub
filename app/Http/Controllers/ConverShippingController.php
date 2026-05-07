<?php
namespace App\Http\Controllers;
use App\Models\Shipping;
use App\Models\ConverShipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;

class ConverShippingController extends Controller
{

    //変換一覧
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        
        $shippingCD = $request->query('key');//出荷先_エンドユーザーCD
        // $jurisdictionCD = $request->query('key2');//M出荷先の管轄部門CD
        $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD

        $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

        if ($shippingCD === null || $shippingCD === '' || !is_numeric($shippingCD)) {
            return response()->json(['message' => '無効な出荷先_エンドユーザーCDです。'], 400);
        }

        $sortColumn = $request->input('sortColumn', 'HD変換出荷先_ID');
        $sortOrder  = $request->input('sortOrder', 'desc');

        $query = ConverShipping::query()
            ->select('HD変換出荷先_ID', '出荷先_エンドユーザーCD', '変換名')
            ->where('出荷先_エンドユーザーCD', $shippingCD)
            ->where('管轄部門CD', $jurisdictionCD) //ユーザーの所属部門
            ->whereNull('消去日時')
            ->orderBy($sortColumn, $sortOrder);

        // ページネーション
        if ($request->has('page')) {
            $result = $query->paginate(17);
        } else {
            $result = ['data' => $query->get()];    
        }
        
        // クエリログ保存
        $queries = \DB::getQueryLog();
        $lastQuery = end($queries);
        if ($lastQuery && isset($lastQuery['query'])) {
            $sqlquery = $lastQuery['query'];
            $bindings = $lastQuery['bindings'];
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'$binding'", $sqlquery, 1);
            }

            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 1,
                '実行内容' => '出荷先変換データ詳細表示',
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery,
            ]);
        }

        return response()->json($result);
    }



    //選択した出荷先変換名
    public function detail(Request $request) {

        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から出荷先変換IDを取得
            $ShippingId = $request->query('key');
            $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD

            // $ShippingIdのバリデーション
            if (empty($ShippingId) || !is_numeric($ShippingId)) {
                return response()->json(['message' => '無効な出荷先変換IDです。'], 400);
            }

            $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD


            // 'M出荷先_ID' に一致する顧客情報を取得
            // $Shipping = ConverShipping::where('HD変換出荷先_ID', $ShippingId)->first();
            $Shipping = ConverShipping::where('HD変換出荷先_ID', $ShippingId)
                        ->where('管轄部門CD', $jurisdictionCD)
                // ->where(function($q) use ($jurisdictionCD) {
                //     $q->where('管轄部門CD', $jurisdictionCD)
                //     ->orWhere('管轄部門CD', 0);
                // })
                ->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$Shipping) {
                return response()->json(['message' => 'データが見つかりません。'], 404);
            }

            $queries = \DB::getQueryLog();
            $lastQuery = end($queries); // 最後に実行されたクエリを取得
        
            // 取得したクエリのSQL文とパラメータを変数に格納
            $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
            $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];
        
            // SQL文が取得できた場合のみログに保存
            if ($sqlquery) {
                // バインド変数をSQL文に埋め込む処理
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }
                $do = '出荷先変更_出荷先名表示';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            // 顧客情報を適切な構造で返す
            return response()->json(['data' => $Shipping]);

        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }

    //変更
    public function edit(Request $request)
    {
        try {
            \DB::enableQueryLog();

            // リクエストで送信されたD変換出荷先_IDを取得
            $hdID = $request->input('HD変換出荷先_ID');

            // 該当データ取得
            $contents = ConverShipping::where('HD変換出荷先_ID', $hdID)->first();
            if (!$contents) {
                return response()->json(['message' => '該当するデータが見つかりません'], 404);
            }
    
            $newName = $request->input('変換名') ?? $contents->変換名;

            // 空欄チェック
            if (empty($newName) || trim($newName) === '') {
                return response()->json(['error' => '変換名を入力してください。'], 422);
            }

            $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD
            $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD
    

            // 重複チェック（選択中のHD変換出荷先_IDは除外）
            $duplicate = ConverShipping::where('変換名', $newName)
                ->where('HD変換出荷先_ID', '!=', $hdID)
                ->where('管轄部門CD', $jurisdictionCD) //ログインユーザー所属部門CD
                ->whereNull('消去日時')
                ->exists();
            if ($duplicate) {
                return response()->json(['error' => '入力された変換名はすでに登録されています。'], 409);
            }
    
            // 更新
            $contents->update(['変換名' => $newName]);

             // SQLログ保存処理
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries); // 最後に実行されたクエリを取得
            $sqlquery = $lastQuery['query'] ?? null;
            $bindings = $lastQuery['bindings'] ?? [];
        
            // SQL文が取得できた場合のみログに保存
            if ($sqlquery) {
                // バインド変数をSQL文に埋め込む処理
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }
    
                // ログを作成
                $do = '出荷先変換データ編集';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,//更新
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }
    
            return response()->json(['message' => '更新成功しました'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    //新規追加
    public function create(Request $request)
    {
        \DB::enableQueryLog();
        
        $shippingCD = $request->input('出荷先_エンドユーザーCD');
        $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD

        // $jurisdictionCD = $request->input('管轄部門CD');

        $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  10 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

        $converName = $request->input('変換名');

        $shippingCD_confirm = trim($shippingCD);//確認用

        // 一致レコードを取得（exists()→first() に変更）
        $shipping = Shipping::whereRaw("TRIM(出荷先_エンドユーザーCD) = ?", [$shippingCD_confirm])
            // ->where('管轄部門CD', $jurisdictionCD) //ログインユーザー所属部門CD
            ->where(function($q) use ($jurisdictionCD) {
                $q->where('管轄部門CD', $jurisdictionCD)
                ->orWhere('管轄部門CD', 0);
            }) //M出荷先の管轄部門は、0とユーザーの所属部門が一致したもの
            ->first();

        // 存在しない場合 400 返す
        if (!$shipping) {
            return response()->json(['error' => '指定されたデータは存在しません。'], 400);
        }

        // 一致したCD（DB基準の正しい値）
        $realShippingCD = $shipping->出荷先_エンドユーザーCD;
        $shipping_jurisdictionCD = $shipping->管轄部門CD;
        
            // 変換名の重複チェック
            $duplicate = ConverShipping::where('変換名', $converName)
                ->where('管轄部門CD', $jurisdictionCD) //ログインユーザー所属部門CD
                ->whereNull('消去日時')
                ->exists();
            if ($duplicate) {
                return response()->json(['error' => '入力された変換名はすでに登録されています。'], 409);
            }

        try {
            // Log::info('$shipping_jurisdictionCD '.$shipping_jurisdictionCD );

            // データベースへの保存
            $content = ConverShipping::create([
                '管轄部門CD' => $jurisdictionCD,
                'M出荷先_管轄部門CD' => $shipping_jurisdictionCD,
                '出荷先_エンドユーザーCD' => $realShippingCD,
                '変換名' => $converName,
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }

        // クエリログの取得
        $queries = \DB::getQueryLog();
        $lastQuery = end($queries); // 最後に実行されたクエリを取得
    
        // 取得したクエリのSQL文とパラメータを変数に格納
        $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
        $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];
    
        // SQL文が取得できた場合のみログに保存
        if ($sqlquery) {
            // バインド変数をSQL文に埋め込む処理
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
            }
            $do = '新規出荷先変換名追加';
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 4,
                '実行内容' => $do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery, // 生成されたSQL文を保存
            ]);
        } else {
            \Log::error('SQLクエリが取得できませんでした。');
        }

        return response()->json($content, 201);
    }

    //削除
    public function update(Request $request) {

        \DB::enableQueryLog();
        
        // リクエストで送信されたD変換出荷先_IDを取得
        $dID = $request->input('id');

        // D変換出荷先_IDに対応するコンテンツを取得
        $contents = ConverShipping::where('HD変換出荷先_ID', $dID)->first();

        if ($contents) {
            // 現在の日時を消去日時に設定
            $contents->消去日時 = now();
            $contents->save();

            // クエリログの取得
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries); // 最後に実行されたクエリを取得
            
            // 取得したクエリのSQL文とパラメータを変数に格納
            $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
            $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];
            
            // SQL文が取得できた場合のみログに保存
            if ($sqlquery) {
                // バインド変数をSQL文に埋め込む処理
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }

                $do = '出荷先変換データ消去';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            return response()->json(['success' => true]);
        } else {
            \Log::warning("HD変換出荷先_ID {$dID} が見つかりませんでした。");
            return response()->json(['success' => false, 'message' => 'データが見つかりませんでした。']);
        }
    }

}

