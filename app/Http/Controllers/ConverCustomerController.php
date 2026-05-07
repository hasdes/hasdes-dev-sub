<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use App\Models\ConverCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

class ConverCustomerController extends Controller
{

    public function list(Request $request)
    {
        \DB::enableQueryLog();
        
        $customerCD = $request->query('key');//得意先CD
        if (empty($customerCD) || !is_numeric($customerCD)) {
            return response()->json(['message' => '無効な得意先CDです。'], 400);
        }

        $sortColumn = $request->input('sortColumn', 'HD変換得意先_ID');
        $sortOrder  = $request->input('sortOrder', 'desc');

        $query = ConverCustomer::query()
            ->select('HD変換得意先_ID', '得意先CD', '変換名')
            ->where('得意先CD', $customerCD)
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
                'ログ種別' => 1, //イベントログ（表示、取得）
                '実行内容' => '得意先変換データ詳細表示',
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery,
            ]);
        }

        return response()->json($result);
    }



    //選択した得意先変換名
        public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から得意先変換IDを取得
            $customerId = $request->query('key');

            // $customerIdのバリデーション
            if (empty($customerId) || !is_numeric($customerId)) {
                return response()->json(['message' => '無効な得意先変換IDです。'], 400);
            }

            // 'M得意先_ID' に一致する顧客情報を取得
            $customer = ConverCustomer::where('HD変換得意先_ID', $customerId)->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$customer) {
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

                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1, //イベントログ（表示、取得）
                    '実行内容' => '得意先変更_得意先名表示',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }

            // 顧客情報を適切な構造で返す
            return response()->json(['data' => $customer]);

        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }

    //更新
    public function edit(Request $request) {
        try {
            \DB::enableQueryLog();

            // ID取得
            $dID = $request->input('HD変換得意先_ID');

            // 該当データ取得
            $contents = ConverCustomer::where('HD変換得意先_ID', $dID)->first();
            if (!$contents) {
                return response()->json(['message' => '該当するデータが見つかりません'], 404);
            }

            $newName = $request->input('変換名') ?? $contents->変換名;
            $customerCD = $contents->得意先CD;

            // 空欄チェック
            if (empty($newName) || trim($newName) === '') {
                return response()->json(['error' => '変換名を入力してください。'], 422);
            }

            // // 重複チェック（選択中のHD変換得意先_IDは除外）
            // $duplicate = ConverCustomer::where('変換名', $newName)
            //     ->where('HD変換得意先_ID', '!=', $dID)
            //     ->whereNull('消去日時')
            //     ->exists();
            // 重複チェック（選択中のHD変換得意先_IDは除外）
            $duplicate = ConverCustomer::where('変換名', $newName)
                ->where('HD変換得意先_ID', '!=', $dID)
                ->where('得意先CD', $customerCD)
                ->whereNull('消去日時')
                ->exists();
            if ($duplicate) {
                return response()->json(['error' => '入力された変換名はすでに登録されています。'], 409);
            }

            // 更新
            $contents->update(['変換名' => $newName]);

            // SQLログ保存処理
            $queries = \DB::getQueryLog();
            $lastQuery = end($queries);
            $sqlquery = $lastQuery['query'] ?? null;
            $bindings = $lastQuery['bindings'] ?? [];

            if ($sqlquery) {
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
                }
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,//変更、更新ログ
                    '実行内容' => '得意先変換データ編集',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
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
        // 得意先CDが品名テーブルに存在するか確認
        $customerCD = $request->input('得意先CD');
        $converName = $request->input('変換名');

        $customerCD_confirm = trim($customerCD);//確認用

        // 一致レコードを取得（exists()→first() に変更）
        $customer = Customer::whereRaw("TRIM(得意先CD) = ?", [$customerCD_confirm])->first();

        // 存在しない場合 400 返す
        if (!$customer) {
            return response()->json(['error' => '指定されたデータは存在しません。'], 400);
        }

        // 一致したCD
        $realCustomerCD = $customer->得意先CD;

         // 変換名の重複チェック
        // $duplicate = ConverCustomer::where('変換名', $converName)
        //     ->whereNull('消去日時')
        //     ->exists();
        $duplicate = ConverCustomer::where('変換名', $converName)
            ->where('得意先CD', $customerCD)
            ->whereNull('消去日時')
            ->exists();
        if ($duplicate) {
            return response()->json(['error' => '入力された変換名はすでに登録されています。'], 409);
        }

        try {
            // データベースへの保存
            $content = ConverCustomer::create([
                '得意先CD' => $realCustomerCD,
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
            $do = '新規得意先変換追加';
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 4,//新規追加
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
        
        // リクエストで送信されたHD変換得意先_IDを取得
        $hdID = $request->input('id');

        // D変換得意先_IDに対応するコンテンツを取得
        $contents = ConverCustomer::where('HD変換得意先_ID', $hdID)->first();

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

                $do = '得意先変換データ消去';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 3,//変更・更新ログ
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
            \Log::warning("HD変換得意先_ID {$hdID} が見つかりませんでした。");
            return response()->json(['success' => false, 'message' => 'データが見つかりませんでした。']);
        }
    }

}

