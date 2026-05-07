<?php
namespace App\Http\Controllers;
use App\Models\Item;
use App\Models\ConverProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;


class ConverProductController extends Controller
{

    //商品一覧
    public function productlist(Request $request)
    {

        // 'filter' パラメータを取得し、期待する型かどうかをチェック
        $filter = $request->input('filter');
        $coname = $request->input('coname');

        \DB::enableQueryLog();
         // 'page' パラメータを除外し、'filter' パラメータを個別に処理
        $queryParams = $request->except('page', 'filter', 'sortColumn', 'sortOrder');

        // $query = Item::query()
        //     ->select(
        //         '商品CD',
        //         \DB::raw('MIN(M商品_ID) as M商品_ID'),
        //         \DB::raw('MIN(商品名_社内用) as 商品名_社内用')
        //     )
        //     ->groupBy('商品CD');

        //優先商品名配列（随時追加）
        $priorityNames = [
            'FFCER000     ' => 'F  ﾌﾗﾝｼﾞふた RF     ',
        ];

        $caseSql = collect($priorityNames)->map(function ($name, $cd) {
            return "WHEN 商品CD = '{$cd}' AND TRIM(商品名_社内用) = '{$name}' THEN 商品名_社内用";
        })->implode(' ');

        $query = Item::query()
            ->select(
                '商品CD',
                \DB::raw('MIN(M商品_ID) as M商品_ID'),
                DB::raw("
                    COALESCE(
                        MIN(CASE {$caseSql} END),
                        MIN(商品名_社内用)
                    ) as 商品名_社内用
                ")
            )
            ->groupBy('商品CD');

        $isSearch = !empty($request->input('filter')) ||
        $request->filled('coname');

        if ($isSearch) {
            $logkind = 2;
            $do = '商品検索';
        } else {
            $logkind = 1;
            $do = '商品表示';
        }

        // filter がスカラー値の場合のみ検索に使用
        if (is_scalar($filter) && $filter !== '') {
            $query->where('商品CD', 'LIKE', $filter . '%');
        }

        if (!empty($coname)) {
            $query->where('商品名_社内用', 'LIKE', '%' . $coname . '%');
        }

        $sortColumn = $request->input('sortColumn', '商品CD'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        $query->orderBy($sortColumn, $sortOrder);
        
        // ページネーション
        if ($request->has('page')) {
            $result = $query->paginate(17);
        } else {
            $result = ['data' => $query->get()];  
        }

        $sql = $query->toSql(); // ? がプレースホルダー
        $bindings = $query->getBindings(); // バインド値

        foreach ($bindings as $binding) {
            $sql = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
        }

        $queries = \DB::getQueryLog();
        $lastQuery = end($queries); // 最後に実行されたクエリを取得

        // 取得したクエリのSQL文とパラメータを変数に格納
        $sqlquery = $lastQuery && isset($lastQuery['query']) ? $lastQuery['query'] : null;
        $bindings = $lastQuery && isset($lastQuery['bindings']) ? $lastQuery['bindings'] : [];

        if ($sqlquery) {
            // バインド変数をSQL文に埋め込む処理
            foreach ($bindings as $binding) {
                $sqlquery = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sqlquery, 1);
            }
            
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => $logkind,
                '実行内容' => $do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery, // 生成されたSQL文を保存
            ]);
        } else {
            \Log::error('SQLクエリが取得できませんでした。');
        }
        return $result;
    }



    //変換一覧
    public function list(Request $request)
    {
        \DB::enableQueryLog();
        
        $ProductCD = $request->query('key');//商品CD
        if (empty($ProductCD)) {
            return response()->json(['message' => '無効な商品CDです。'], 400);
        }

        $sortColumn = $request->input('sortColumn', 'HD変換商品_ID');
        $sortOrder  = $request->input('sortOrder', 'desc');

        $query = ConverProduct::query()
            ->select('HD変換商品_ID', '商品CD', '変換名')
            ->where('商品CD', $ProductCD)
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
                '実行内容' => '商品変換データ詳細表示',
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlquery,
            ]);
        }

        return response()->json($result);
    }

    //選択した商品変換名
        public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から商品変換IDを取得
            $ProductId = $request->query('key');

            // $ProductIdのバリデーション
            if (empty($ProductId) || !is_numeric($ProductId)) {
                return response()->json(['message' => '無効な商品変換IDです。'], 400);
            }

            // 'M商品_ID' に一致する顧客情報を取得
            $Product = ConverProduct::where('HD変換商品_ID', $ProductId)->first();

            // 顧客情報が見つからない場合は404を返す
            if (!$Product) {
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
                $do = '商品変更_商品名表示';
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
            return response()->json(['data' => $Product]);

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

            // リクエストで送信されたHD商品_IDを取得
            $hdID = $request->input('HD変換商品_ID');
            $productCD = $request->input('商品CD');

            // HD変換商品_IDに対応するコンテンツを取得
            $contents = ConverProduct::where('HD変換商品_ID', $hdID)->first();
    
            if (!$contents) {
                return response()->json(['message' => '該当するデータが見つかりません'], 404);
            }
        
            $newName = $request->input('変換名') ?? $contents->変換名;

            // 空欄チェック
            if (empty($newName) || trim($newName) === '') {
                return response()->json(['error' => '変換名を入力してください。'], 422);
            }

            // 重複チェック（選択中のHD変換出荷先_IDは除外）
            $duplicate = ConverProduct::where('変換名', $newName)
                ->where('HD変換商品_ID', '!=', $hdID)
                ->where('商品CD', $productCD)
                ->whereNull('消去日時')
                ->exists();
            if ($duplicate) {
                return response()->json(['error' => 'こちらの商品CDで入力された変換名はすでに登録されています。'], 409);
            }

            // データ更新
            $contents->update(['変換名' => $newName]);

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
    
                // ログを作成
                $do = '商品変換データ編集';
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
    
            return response()->json(['message' => '更新成功しました'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    //新規追加
    public function create(Request $request)
    {
        \DB::enableQueryLog();
                    

        // 商品IDがM商品テーブルに存在するか確認
        $productCD = $request->input('商品CD');
        $converName = $request->input('変換名');

        $productCD_confirm = trim($productCD);//確認用

        // 一致レコードを取得
        $product = Item::whereRaw("TRIM(商品CD) = ?", [$productCD_confirm])->first();

        // 存在しない場合 400 返す
        if (!$product) {
            return response()->json(['error' => '指定されたデータは存在しません。'], 400);
        }

        // 一致したCD（DB基準の正しい値）
        $realProductCD = $product->商品CD;

        // 変換名の重複チェック
        $duplicate = ConverProduct::where('変換名', $converName)
            ->where('商品CD', $realProductCD)
            ->whereNull('消去日時')
            ->exists();
        if ($duplicate) {
            return response()->json(['error' => 'こちらの商品CDで入力された変換名はすでに登録されています。'], 409);
        }

        try {
            $content = ConverProduct::create([
                '商品CD' => $realProductCD,
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
            $do = '新規商品変換名追加';
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => 4,//新規
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
        
        // リクエストで送信されたHD変換商品_IDを取得
        $dID = $request->input('id');

        // HD変換商品_IDに対応するコンテンツを取得
        $contents = ConverProduct::where('HD変換商品_ID', $dID)->first();

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

                $do = '商品変換データ消去';
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
            \Log::warning("HD変換商品_ID {$ContentsCD} が見つかりませんでした。");
            return response()->json(['success' => false, 'message' => 'データが見つかりませんでした。']);
        }
    }

}

