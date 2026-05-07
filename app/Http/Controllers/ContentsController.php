<?php

namespace App\Http\Controllers;
use App\Models\Contents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\HDLog;
use App\Models\Item;


class ContentsController extends Controller
{

    public function list(Request $request)
{
    \DB::enableQueryLog();
    $query = Contents::query()->whereNull('消去日時');

    $filter = $request->input('filter');
    if (is_scalar($filter)) {
        $queryParams['filter'] = $filter;
    }

    $isSearch = !empty($request->input('filter')) ||
    $request->filled('startdate') ||
    $request->filled('enddate') ||
    $request->filled('jdouga') ||
    $request->filled('jshiryo') ||
    $request->filled('jtext') ||
    $request->filled('hinmeicd') ||
    $request->filled('titele');

    if ($isSearch) {
    $logkind = 2;
    $do = 'コンテンツデータ検索';
    } else {
    $logkind = 1;
    $do = 'コンテンツデータ表示';
    }

    // 投稿日フィルタ
    
    if ($request->filled('startdate') && $request->filled('enddate')) {
        // startdateとenddateの両方がある場合
        $query->whereBetween('更新日時', [
            $request->input('startdate'),
            Carbon::parse($request->input('enddate'))->endOfDay()
        ]);
    } elseif ($request->filled('startdate')) {
        // startdateのみがある場合
        $query->where('更新日時', '>=', $request->input('startdate'));
    } elseif ($request->filled('enddate')) {
        // enddateのみがある場合
        $query->where('更新日時', '<=', $request->input('enddate'));
    }

    // ジャンルフィルタ
    if ($request->filled('jdouga') || $request->filled('jshiryo') || $request->filled('jtext')) {
        $genres = [];
        if ($request->filled('jdouga')) $genres[] = '動画';
        if ($request->filled('jshiryo')) $genres[] = '資料';
        if ($request->filled('jtext')) $genres[] = 'テキスト';
        $query->where(function ($q) use ($genres) {
            foreach ($genres as $genre) {
                $q->orWhere('ジャンル', 'like', '%' . $genre . '%');
            }
        });
    }

    // タイトルフィルタ
    if ($request->filled('titele')) {
        $query->where('タイトル', 'like', '%' . $request->input('titele') . '%');
    }
    if ($request->filled('hinmeicd')) {
        $query->where('品名CD', '=', $request->input('hinmeicd'));
    }

        $sortColumn = $request->input('sortColumn', '更新日時'); // 初期表示時のデフォルトソートカラム
        $sortOrder = $request->input('sortOrder', 'asc'); // 初期表示時のデフォルトソート順

        // 担当者CDを数値としてキャストしてソートする
        if ($sortColumn === '更新日時') {
            $query->orderByRaw('CAST(更新日時 AS UNSIGNED) ' . $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
            $do .= '昇順・降順';
        }
        $result = $query->paginate(17);

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




    public function create(Request $request)
    {

        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
    
        // ログに記録
        Log::info('PHP設定値 - upload_max_filesize: ' . $uploadMaxFilesize);
        Log::info('PHP設定値 - post_max_size: ' . $postMaxSize);
        \DB::enableQueryLog();
        // ファイルのアップロード処理
        // 品名CDが品名テーブルに存在するか確認
        $ItemCD = $request->input('品名CD');
        $Exists = Item::where('品名CD', $ItemCD)->exists();
        
        if (!$Exists) {
            return response()->json(['error' => '指定された品名CDは存在しません。'], 400);
        }

        $Exists = Contents::where('品名CD', $ItemCD)
        ->whereNull('消去日時')
                  ->exists();
        
        if ($Exists) {
            return response()->json(['error' => '指定された品名CDはすでにコンテンツ登録されております。'], 401);
        }




        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                // ファイルが有効かどうかを確認
                if ($file->isValid()) {
                    // オリジナルのファイル名を取得
                    $originalName = $file->getClientOriginalName();
                    // ファイルをstorage/app/public/uploadsフォルダにオリジナル名で保存
                    $path = $file->storeAs('public/uploads', $originalName); 
                    $uploadedFiles[] = $originalName; // ファイル名のみを配列に保存
                } else {
                    return response()->json(['error' => '無効なファイルがアップロードされました。'], 400);
                }
            }
        }
    
        // ジャンルの処理
        $genres = $request->input('ジャンル');
        if (is_array($genres)) {
            $genres = implode(',', $genres); // 配列をカンマ区切りの文字列に変換
        }
    
        // YouTube動画リンクの処理（配列として受け取り、JSONとして保存）
        $youtubeLinks = $request->input('YouTube動画リンク');
        if (is_array($youtubeLinks)) {
            $youtubeLinks = json_encode($youtubeLinks); // JSON形式に変換
        } else {
            // $youtubeLinks = json_encode([]); // データがない場合に空の配列をJSONに変換
            $youtubeLinks = null; // データがない場合null
        }
    
        // データベースへの保存
        $content = Contents::create([
            '品名CD' => $request->input('品名CD'),
            'ジャンル' => $genres, // ジャンルはカンマ区切りで保存
            'タイトル' => $request->input('タイトル'),
            '詳細' => $request->input('詳細'),
            'YouTube動画リンク' => $youtubeLinks, // YouTubeリンクをJSONとして保存
            'files' => json_encode($uploadedFiles), // アップロードされたファイルのファイル名をJSONとして保存
        ]);
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
            $do = '新規コンテンツ追加';
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
    

    public function detail(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // URLのクエリパラメータ 'key' から顧客IDを取得
            $hinmeicd = $request->query('key');
        
            // $customerIdのバリデーション
            if (empty($hinmeicd) || !preg_match('/^[a-zA-Z0-9]+$/', $hinmeicd)) {
                return response()->json(['message' => '無効な顧客IDです。'], 400);
            }
        
            // '品名CD' に一致する顧客情報を取得
            $contents = Contents::where('品名CD', $hinmeicd)
            ->whereNull('消去日時')
            ->first();
        
            // 顧客情報が見つからない場合は404を返す
            if (!$contents) {
                return response()->json(['message' => '顧客情報が見つかりません。'], 404);
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
                $do = 'コンテンツデータ詳細表示';
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 1,
                    '実行内容' => $do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery, // 生成されたSQL文を保存
                ]);
            } else {
                \Log::error('SQLクエリが取得できませんでした。');
            }
        
            // 顧客情報を適切な構造で返す
            return response()->json(['data' => $contents]);
        
        } catch (\Exception $e) {
            // 予期せぬエラーが発生した場合に500エラーを返す
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
        
    }

    public function update(Request $request)
{
    \DB::enableQueryLog();
    
    // リクエストで送信されたMコンテンツ_IDを取得
    $ContentsCD = $request->input('ContentsCD');

    // Mコンテンツ_IDに対応するコンテンツを取得
    $contents = Contents::where('Mコンテンツ_ID', $ContentsCD)->first();

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

            $do = 'コンテンツデータ消去';
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
        \Log::warning("Mコンテンツ_ID {$ContentsCD} が見つかりませんでした。");
        return response()->json(['success' => false, 'message' => 'コンテンツが見つかりませんでした。']);
    }
}


    

    public function edit(Request $request)
    {
        try {
            \DB::enableQueryLog();
            // $contents = Contents::where('品名CD', $request->input('品名CD')
            // )->first();

            //修正 20250823
            $contents = Contents::where('品名CD', $request->input('品名CD'))
            ->whereNull('消去日時') // 消去日が null のものだけ
            ->first();
    
            if (!$contents) {
                return response()->json(['message' => '該当するコンテンツが見つかりません'], 404);
            }
    
            // 既存ファイルのデコード
            $uploadedFiles = $contents->files ? json_decode($contents->files, true) : [];
    
            // 新しいファイルの追加
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    if ($file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $file->storeAs('public/uploads', $originalName);
                        $uploadedFiles[] = $originalName;
                    } else {
                        return response()->json(['error' => '無効なファイルがアップロードされました。'], 400);
                    }
                }
            }
    
            //20250825　非表示
            // // 削除対象ファイルを取得して除外
            // $removedFiles = json_decode($request->input('removedFiles', '[]'), true);
            // $uploadedFiles = array_values(array_diff($uploadedFiles, $removedFiles));
    
            // // アップロードされたファイルがない場合は、既存のファイルリストを空にする
            // if (empty($uploadedFiles)) {
            //     $uploadedFiles = null;
            // }

            //20250825　追加
            $removedFileIndexes = json_decode($request->input('removedFiles', '[]'), true);

            if (is_array($removedFileIndexes) && !empty($removedFileIndexes)) {
                foreach ($removedFileIndexes as $index) {
                    if (isset($uploadedFiles[$index])) {
                        unset($uploadedFiles[$index]);
                    }
                }
                $uploadedFiles = array_values($uploadedFiles); // インデックスを詰める
            }


            // アップロードされたファイルがない場合は、既存のファイルリストを空にする
            if (empty($uploadedFiles)) {
                // $uploadedFiles = null;
                $uploadedFiles = []; // 配列のまま
            }
    
            // ジャンルの処理
            $genres = $request->input('ジャンル');
            if (is_array($genres)) {
                $genres = implode(',', $genres);
            }
    
            // 既存のYouTube動画リンクをリセットしてリクエストのリンクで上書きする
            $youtubeLinks = $request->input('YouTube動画リンク') ?? [];

            //20250825　非表示
            // // 削除されたリンクをリクエストから取得して除外
            // $removedYouTubeLinks = json_decode($request->input('removedYouTubeLinks', '[]'), true);
            // $youtubeLinks = array_values(array_diff($youtubeLinks, $removedYouTubeLinks));
    
            // // 新しいリンクがない場合は、既存のリンクを空にする
            // if (empty($youtubeLinks)) {
            //     $youtubeLinks = null;
            // }


            // 更新データの準備
            $updateData = [
                'ジャンル' => $genres,
                'タイトル' => $request->input('タイトル') ?? $contents->タイトル,
                '詳細' => $request->input('詳細') ?? $contents->詳細,
                'YouTube動画リンク' => $youtubeLinks ? json_encode($youtubeLinks) : null,
                'files' => $uploadedFiles ? json_encode($uploadedFiles) : null,
            ];
    
            // データ更新
            $contents->update($updateData);

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
                $do = 'コンテンツデータ編集';
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
    
    

    
    
    
 

}
