<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\FormatSpaceService;

/* <作業状態> ********************
再集荷(集荷済)&中断 :7
再集荷(集荷済) :6
キャンセル (集荷済&キャンセル未処理) :5
キャンセル（未集荷) :4
キャンセル（処理済み）:3
集荷処理済み :2
中断 :1
未集荷 :0 
*********************************/


class PickUpController extends Controller
{

    // ======================
    // 集荷一覧
    // ======================
    public function list(Request $request)
    {
        \DB::enableQueryLog();        

        $queryParams = $request->all();//入力値
        $isSearch = $request->has('search'); //検索フラグ

        if ($isSearch) {
            $logkind = 2;
            $do = '集荷期間検索';
        } else {
            $logkind = 1;
            $do = '集荷一覧表示';
        }

        // ソート設定
        $sorts = $request->input('sorts', [
            ['column' => '作業状態', 'order' => 'desc'],
        ]);

        $syozokubumonCD = session('所属部門CD');//ログインユーザーのセッション:所属部門CD
        $jurisdictionCD  = $syozokubumonCD == 'HASDES' ?  1 : $syozokubumonCD;//HASDESの場合は10にする、それ以外は自身の所属部門CD

        // =====================================
        // メインクエリ
        // =====================================
        $query = DB::table('D出荷指示伝票 as ds')
            
            // ログ（現在の出荷指示NO）
            ->leftJoin('HD出荷指示伝票ログ as hs', function ($join) {
                $join->on('hs.管轄部門CD', '=', 'ds.管轄部門CD')
                    ->on('hs.受注_移動NO', '=', 'ds.受注_移動NO')
                    ->on('hs.出荷指示NO', '=', 'ds.出荷指示NO');
            })

            ->leftJoin('D出荷指示明細 as dd', function($join) { 
                $join->on('dd.管轄部門CD', '=', 'ds.管轄部門CD') 
                ->on('dd.出荷指示NO', '=', 'ds.出荷指示NO');
            })

            ->leftJoin('D受注伝票 as od', function($join) {
                $join->on('od.管轄部門CD', '=', 'ds.管轄部門CD')
                    ->on('od.受注No', '=', 'ds.受注_移動NO');
            })
            
            //納入先住所(D受注伝票-出荷先CD ⇒ M出荷先-住所1 + 住所2)
            ->leftJoin('M出荷先 as ms', function ($join) {
                $join->on('ms.出荷先_エンドユーザーCD', '=', 'ds.出荷先CD')
                    ->where('ms.得意先区分', 2)
                    ->where(function ($q) {
                        $q->whereColumn('ms.管轄部門CD', 'ds.受注_移動の管轄部門CD')
                        ->orWhere('ms.管轄部門CD', 0); //０も対象
                    });
            })

            ->leftJoin('M運送会社 as mc', 'mc.運送会社CD', '=', 'dd.運送会社CD')

            // キャンセル判定
            ->leftJoin('D受注キャンセル伝票 as dc', function ($join) {
                $join->on('dc.管轄部門CD', '=', 'ds.受注_移動の管轄部門CD')
                    ->on('dc.削除元受注NO', '=', 'ds.受注_移動NO')
                    ->where('ds.出荷予定区分', 1);
            })

            ->selectRaw('
                ds.出荷日,
                ds.出荷指示NO,
                ds.受注_移動NO,
                ds.出荷先CD,
                SUM(dd.出荷指示数量) AS 出荷指示数量合計,
                ds.重量合計,
                dd.出荷形態,
                dd.運送会社CD,
                ms.略名,
                ms.住所1,
                ms.住所2,
                mc.運送会社名,
                CASE
                    /* ===== 再集荷 ＋ 中断中 → 7 ===== */
                    WHEN (
                        -- ① HD出荷指示伝票ログに、同一の管轄部門CD + 受注_移動NO & 異なる出荷指示NOが存在 & 処理済み
                        EXISTS (
                            SELECT 1
                            FROM HD出荷指示伝票ログ h2
                            WHERE h2.管轄部門CD = ds.管轄部門CD
                            AND h2.受注_移動NO = ds.受注_移動NO
                            AND h2.出荷指示NO <> ds.出荷指示NO
                            AND h2.処理状態 = 2
                            AND h2.再出荷済フラグ = 0
                        )
                        -- ② 集荷完了に同じ出荷指示NOが存在しない
                        AND NOT EXISTS (
                            SELECT 1
                            FROM D集荷完了 de
                            WHERE de.管轄部門CD = ds.管轄部門CD
                            AND de.出荷指示NO = ds.出荷指示NO
                        )
                        -- ③ HD出荷指示伝票ログに、同一の管轄部門 & 出荷指示NOが存在 & 中断
                        AND EXISTS (
                            SELECT 1
                            FROM HD出荷指示伝票ログ h3
                            WHERE h3.管轄部門CD = ds.管轄部門CD
                            AND h3.出荷指示NO = ds.出荷指示NO
                            AND h3.処理状態 = 1
                        )
                    ) THEN 7
                    /* ===== 再集荷（6） ===== */
                    WHEN (
                        -- ① 同一の管轄部門CD + 受注_移動NO & 異なる出荷指示NOが存在 & 処理済み
                        EXISTS (
                            SELECT 1
                            FROM HD出荷指示伝票ログ h2
                            WHERE h2.管轄部門CD = ds.管轄部門CD
                            AND h2.受注_移動NO = ds.受注_移動NO
                            AND h2.出荷指示NO <> ds.出荷指示NO
                            AND h2.処理状態 = 2
                            AND h2.再出荷済フラグ = 0
                        )
                        AND
                        -- ② 集荷完了に同じ出荷指示NOが存在しない
                        NOT EXISTS (
                            SELECT 1
                            FROM D集荷完了 de
                            WHERE de.管轄部門CD = ds.管轄部門CD
                            AND de.出荷指示NO = ds.出荷指示NO
                        )
                    ) THEN 6                    
                    /* ===== キャンセル系 ===== */
                    WHEN hs.処理状態 = 3 THEN 3
                    WHEN hs.処理状態 IS NULL AND dc.削除元受注NO IS NOT NULL THEN 4
                    WHEN hs.処理状態 = 1 AND dc.削除元受注NO IS NOT NULL THEN 4
                    WHEN dc.削除元受注NO IS NOT NULL THEN 5
                    /* ===== 通常状態 ===== */
                    WHEN hs.処理状態 = 1 THEN 1
                    WHEN hs.処理状態 = 2 THEN 2
                    ELSE 0
                END AS 作業状態
            ')
            ->where('ds.管轄部門CD', $jurisdictionCD)
            // 再出荷済フラグ1(再出荷した以前のデータ)の場合は非表示
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('HD出荷指示伝票ログ as hx')
                    ->whereColumn('hx.管轄部門CD', 'ds.管轄部門CD')
                    ->whereColumn('hx.出荷指示NO', 'ds.出荷指示NO')
                    ->where('hx.再出荷済フラグ', 1);
            })
            ->groupBy(
                'ds.管轄部門CD',
                'ds.受注_移動NO',
                'ds.出荷指示NO',
                'ds.出荷日',
                'ds.出荷先CD',
                'ds.重量合計',
                'dd.出荷形態',
                'dd.運送会社CD',
                'ms.略名',
                'ms.住所1',
                'ms.住所2',
                'mc.運送会社名',
                'hs.処理状態',
                'dc.削除元受注NO'
            )
            ->havingRaw('SUM(CASE WHEN dd.出荷確定済 = 1 THEN 1 ELSE 0 END) = 0'); //グループ内に“出荷確定済 = 1”の明細が1件もないものだけを残す

        // =====================================
        // 日付条件
        // =====================================
        if (!empty($queryParams['start_date'])) {
            $query->where('ds.出荷日', '>=', str_replace('-', '', $queryParams['start_date']));
        } else {
            $query->where('ds.出荷日', '>=', now()->format('Ymd'));
        }
        if (!empty($queryParams['end_date'])) {
            $query->where('ds.出荷日', '<=', str_replace('-', '', $queryParams['end_date']));
        }

        // =====================================
        // 並び順
        // =====================================
        foreach ($sorts as $sort) {

            $column = $sort['column'];
            $order  = $sort['order'];

            if ($column === '作業状態') {
                $query->orderByRaw("
                    CASE 作業状態
                        WHEN 7 THEN 6
                        WHEN 5 THEN 6
                        WHEN 1 THEN 5
                        WHEN 6 THEN 4
                        WHEN 0 THEN 4
                        WHEN 2 THEN 3
                        WHEN 3 THEN 2
                        WHEN 4 THEN 2
                        ELSE 1
                    END {$order}
                ");
            }
            elseif ($column === '出荷形態') {
                $query->orderBy('dd.出荷形態', $order);
            }
            elseif ($column === '運送会社CD') {
                $query->orderBy('dd.運送会社CD', $order);
            }
        }

        $result = ['data' => $query->get()];

        // \Log::info('一覧', $result['data']->toArray()); //確認用

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




    // ======================
    // 出荷指示書（詳細ページ）
    // ======================
    public function detail(Request $request)
    {
// \Log::info('start');
// $start = microtime(true);

        DB::flushQueryLog();//クエリログ削除
        \DB::enableQueryLog();
        try {

            $pickupId = $request->query('key');//出荷指示NO
            $originNo = $request->query('key2');//集荷済みの元出荷指示NO （再集荷）

            if (empty($pickupId) || !is_numeric($pickupId)) {
                return response()->json(['message' => '無効なメッセージIDです。'], 400);
            }

            $syozokubumonCD = session('所属部門CD');
            $jurisdictionCD  = $syozokubumonCD == 'HASDES' ? 1 : $syozokubumonCD;


            //-----------------------------------------
            if(empty($originNo)) {

                //HD出荷指示伝票ログに保存されているか確認
                $originNo = DB::table('HD出荷指示伝票ログ')
                    ->where('管轄部門CD', $jurisdictionCD)
                    ->where('出荷指示NO', $pickupId)
                    ->whereIn('処理状態', [1, 2])
                    ->value('元出荷指示NO');          
            }
            //------------------------------------------


            // ヘッダ取得：最新(ds) ＋ 保存版(hs)
            $pickups = DB::table('D出荷指示伝票 as ds')
                // ->leftJoin('HD出荷指示伝票ログ as hs', function ($join) use ($jurisdictionCD, $originNo) {
                //     $join->on('hs.管轄部門CD', '=', 'ds.管轄部門CD')
                //         ->on('hs.受注_移動NO', '=', 'ds.受注_移動NO')
                //         ->where('hs.管轄部門CD', $jurisdictionCD)
                //         ->where('hs.出荷指示NO', '=', $originNo); //集荷済みの元出荷指示NO
                // })
                ->leftJoin('HD出荷指示伝票ログ as hs', function ($join) use ($jurisdictionCD, $originNo) {
                    $join->on('hs.管轄部門CD', '=', 'ds.管轄部門CD')
                        ->on('hs.受注_移動NO', '=', 'ds.受注_移動NO')
                        ->where('hs.管轄部門CD', $jurisdictionCD);

                    // ★ NULLのときはJOIN条件を絶対マッチしない条件にする
                    if (!is_null($originNo)) {
                        $join->where('hs.出荷指示NO', '=', $originNo);
                    } else {
                        $join->whereRaw('1 = 0'); // NULLなら何もJOINしない
                    }
                })
                ->leftJoin('D出荷指示明細 as dd', function($join) {
                    $join->on('dd.管轄部門CD', '=', 'ds.管轄部門CD')
                        ->on('dd.出荷指示NO', '=', 'ds.出荷指示NO')
                        ->on('dd.受注_移動No', '=', 'ds.受注_移動NO');
                })
                // 出荷予定区分 = 1 → [D受注伝票]の備考を見る
                // ※紐づけはD出荷指示伝票-管轄部門CD、受注移動Noと D受注伝票-管轄部門CD&受注No
                ->leftJoin('D受注伝票 as od', function($join) {
                    $join->on('od.管轄部門CD', '=', 'ds.管轄部門CD') //1
                        ->on('od.受注No', '=', 'ds.受注_移動NO') //25007771
                        ->where('ds.出荷予定区分', '=', 1);
                })
                // 出荷予定区分 = 2 → [D移動伝票]の備考を見る
                // ※紐づけはD出荷指示伝票-管轄部門CD、受注移動Noと D移動伝票-管轄部門CD&移動No
                ->leftJoin('D移動伝票 as md', function($join) {
                    $join->on('md.管轄部門CD', '=', 'ds.管轄部門CD')
                        ->on('md.移動NO', '=', 'ds.受注_移動NO')
                        ->where('ds.出荷予定区分', '=', 2);
                })
                // //納入先住所(D受注伝票-出荷先CD ⇒ M出荷先-住所1 + 住所2)
                ->leftJoin('M出荷先 as ms', function ($join) {
                    $join->on('ms.出荷先_エンドユーザーCD', '=', 'ds.出荷先CD')
                        ->where('ms.得意先区分', 2)
                        ->where(function ($q) {
                            $q->whereColumn('ms.管轄部門CD', 'ds.受注_移動の管轄部門CD')
                            ->orWhere('ms.管轄部門CD', 0);
                        });
                })
                ->leftJoin('M運送会社 as mc', 'mc.運送会社CD', '=', 'dd.運送会社CD')
                //キャンセルか確認
                //D出荷指示伝票の「管轄部門CD」＆「受注_移動NO」＆「D出荷指示伝票-出荷予定区分 = 1」にて紐づけ
                ->leftJoin('D受注キャンセル伝票 as dc', function($join) {
                    $join->on('dc.管轄部門CD', '=', 'ds.受注_移動の管轄部門CD')
                        ->on('dc.削除元受注NO', '=', 'ds.受注_移動NO')
                        ->where('ds.出荷予定区分', 1);  //D出荷指示伝票-出荷予定区分 = 1
                })
                ->selectRaw('
                    MIN(ds.出荷日) AS 出荷日,
                    MIN(hs.出荷日) AS hs_出荷日,
                    MIN(ds.出荷指示NO) AS 出荷指示NO,
                    MIN(hs.出荷指示NO) AS hs_出荷指示NO,
                    MIN(ds.受注_移動NO) AS 受注_移動NO,
                    MIN(hs.受注_移動NO) AS hs_受注_移動NO,
                    MIN(ds.出荷先CD) AS 出荷先CD,
                    MIN(hs.出荷先CD) AS hs_出荷先CD,
                    ms.略名,
                    ms.郵便番号,
                    ms.住所1,
                    ms.住所2,
                    mc.運送会社名,
                    MIN(dd.出荷形態) AS 出荷形態,
                    MIN(hs.出荷形態) AS hs_出荷形態,
                    MIN(dd.運送会社CD) AS 運送会社CD,
                    MIN(hs.運送会社CD) AS hs_運送会社CD,
                    SUM(dd.出荷指示数量) AS 出荷指示数量合計,
                    ROUND(SUM(重量), 2) AS 重量合計,
                    SUM(dd.出荷指示梱数) AS 出荷指示梱数合計,
                    MIN(ds.相手先注文NO_得意先) AS 相手先注文NO_得意先,
                    MIN(hs.貴注番) AS hs_相手先注文NO_得意先,
                    MIN(ds.出荷予定区分) AS 出荷予定区分,
                    MIN(ds.工場CD) AS 工場CD,
                    CASE 
                        WHEN MIN(ds.出荷予定区分) = 1 THEN MAX(od.備考)
                        WHEN MIN(ds.出荷予定区分) = 2 THEN MAX(md.備考)
                        ELSE NULL
                    END AS 備考,
                    CASE
                    /* ===== 再集荷 + 中断（7） ===== */
                        WHEN
                            EXISTS (
                                SELECT 1
                                FROM HD出荷指示伝票ログ h2
                                WHERE h2.管轄部門CD = MIN(ds.管轄部門CD)
                                AND h2.受注_移動NO = MIN(ds.受注_移動NO)
                                AND h2.処理状態 = 2
                                AND h2.再出荷済フラグ = 0
                                AND h2.出荷指示NO <> MIN(ds.出荷指示NO)
                            )
                            AND NOT EXISTS (
                                SELECT 1
                                FROM D集荷完了 de
                                WHERE de.管轄部門CD = MIN(ds.管轄部門CD)
                                AND de.出荷指示NO = MIN(ds.出荷指示NO)
                            )
                            AND EXISTS (
                                SELECT 1
                                FROM HD出荷指示伝票ログ h3
                                WHERE h3.管轄部門CD = MIN(ds.管轄部門CD)
                                AND h3.受注_移動NO = MIN(ds.受注_移動NO)
                                AND h3.出荷指示NO = MIN(ds.出荷指示NO)
                                AND h3.処理状態 = 1
                            )
                        THEN 7
                    /* ===== 再集荷（6） ===== */
                        WHEN
                            EXISTS (
                                SELECT 1
                                FROM HD出荷指示伝票ログ h2
                                WHERE h2.管轄部門CD = MIN(ds.管轄部門CD)
                                AND h2.受注_移動NO = MIN(ds.受注_移動NO)
                                AND h2.処理状態 = 2
                                AND h2.再出荷済フラグ = 0
                                AND h2.出荷指示NO <> MIN(ds.出荷指示NO)
                            )
                            AND NOT EXISTS (
                                SELECT 1
                                FROM D集荷完了 de
                                WHERE de.管轄部門CD = MIN(ds.管轄部門CD)
                                AND de.出荷指示NO = MIN(ds.出荷指示NO)
                            )
                        THEN 6                        
                        /* ===== キャンセル系 ===== */
                        WHEN MIN(hs.処理状態) = 3 THEN 3
                        WHEN MIN(hs.処理状態) IS NULL AND MIN(dc.削除元受注NO) IS NOT NULL THEN 4
                        WHEN MIN(hs.処理状態) = 1 AND MIN(dc.削除元受注NO) IS NOT NULL THEN 4
                        WHEN MIN(dc.削除元受注NO) IS NOT NULL THEN 5
                        /* ===== 通常状態 ===== */
                        WHEN MIN(hs.処理状態) = 1 THEN 1
                        WHEN MIN(hs.処理状態) = 2 THEN 2
                        ELSE 0
                    END AS 作業状態
                ')
                ->where('ds.出荷指示NO', $pickupId)
                ->where('ds.管轄部門CD', $jurisdictionCD)
                ->groupBy(
                    'ds.出荷日','hs.出荷日',
                    'ds.出荷指示NO','hs.出荷指示NO',
                    'ds.受注_移動NO','hs.受注_移動NO',
                    'ds.相手先注文NO_得意先','hs.貴注番',
                    'ms.略名','hs.出荷先CD',
                    'ms.郵便番号',
                    'ms.住所1',
                    'ms.住所2',
                    'mc.運送会社名','hs.運送会社CD',
                    'hs.出荷形態'
                    // 'od.備考','md.備考'
                )
                ->havingRaw('SUM(CASE WHEN dd.出荷確定済 = 1 THEN 1 ELSE 0 END) = 0') //グループ内に“出荷確定済 = 1”の明細が1件もないものだけを残す
                ->first();




            if (!$pickups) {
                return response()->json(['message' => '伝票データが見つかりません。'], 404);
            }

            // ===== HD出荷指示伝票が存在しない&作業状態が未集荷、中断の場合は差分なし =====
            $hasHS = !is_null($pickups->hs_出荷日) && !in_array($pickups->作業状態, [0,1], true);

            // 出荷日
            $pickups->diff_ship_date =
                $hasHS && ($pickups->出荷日 !== $pickups->hs_出荷日);

            // 出荷指示NO
            $pickups->diff_ship_no =
                $hasHS && ($pickups->出荷指示NO !== $pickups->hs_出荷指示NO);

            // 貴注番
            $pickups->diff_order_no =
                $hasHS && ($pickups->相手先注文NO_得意先 !== $pickups->hs_相手先注文NO_得意先);

            // 納入先
            $pickups->diff_customer_name =
                $hasHS && ($pickups->出荷先CD !== $pickups->hs_出荷先CD);

            // 運送会社
            $pickups->diff_carrier =
                $hasHS && ($pickups->運送会社CD !== $pickups->hs_運送会社CD);

            // 出荷形態
            $pickups->diff_shipping_type =
                $hasHS && ($pickups->出荷形態 !== $pickups->hs_出荷形態);

            // \Log::info('HS確認', [
            //     '$originNo' => $originNo,
            //     'hs_no' => $pickups->hs_出荷指示NO,
            //     '$pickups->hs_出荷日' => $pickups->hs_出荷日,
            //     'hs_status' => $pickups->作業状態,
            //     'pickupss' => $pickups,
            // ]);

            // 明細取得 ********************************************************************

            $factoryCD = $pickups->工場CD; //工場CD

            //① D出荷指示明細（集約済）
            $ddAgg = DB::table('D出荷指示明細')
            ->selectRaw('
                管轄部門CD,
                TRIM(商品CD) 商品CD,
                TRIM(呼び径1) 呼び径1,
                TRIM(呼び径2) 呼び径2,
                TRIM(呼び径3) 呼び径3,
                年号
            ')
            ->where('管轄部門CD',$jurisdictionCD)
            ->where('出荷指示NO',$pickupId)
            ->where('出荷確定済','!=',1)
            ->groupBy(
                '管轄部門CD',
                '商品CD',
                '呼び径1',
                '呼び径2',
                '呼び径3',
                '年号'
            );
            // \Log::info('$ddAgg：', $ddAgg->get()->toArray()); //確認用

            //② HD出荷指示明細ログ集約（保存履歴）
            $hdTarget = DB::table('HD出荷指示明細ログ')
                ->where('管轄部門CD', $jurisdictionCD)
                ->where('出荷指示NO', $pickupId)
                ->whereNull('消去日時')
                ->exists() ? $pickupId : $originNo;

            $hdAgg = DB::table('HD出荷指示明細ログ')
                ->selectRaw('
                    管轄部門CD,
                    TRIM(商品CD) 商品CD,
                    TRIM(呼び径1) 呼び径1,
                    TRIM(呼び径2) 呼び径2,
                    TRIM(呼び径3) 呼び径3,
                    年号,
                    確定年号,
                    SUM(出荷指示数量) 保存数量
                ')
                ->where('管轄部門CD', $jurisdictionCD)
                ->where('出荷指示NO', $hdTarget)
                ->whereNull('消去日時')
                ->groupBy(
                    '管轄部門CD',
                    '商品CD',
                    '呼び径1',
                    '呼び径2',
                    '呼び径3',
                    '年号',
                    '確定年号'
                );
            // \Log::info('$hdAgg：', $hdAgg->get()->toArray()); //確認用

            //③ DD + HD
            $ddBase = DB::query()
            ->fromSub($ddAgg,'dd')
            ->leftJoinSub($hdAgg,'hd',function($join){
                $join->on('hd.管轄部門CD','=','dd.管轄部門CD')
                    ->on('hd.商品CD','=','dd.商品CD')
                    ->on('hd.呼び径1','=','dd.呼び径1')
                    ->on('hd.呼び径2','=','dd.呼び径2')
                    ->on('hd.呼び径3','=','dd.呼び径3')
                    ->on('hd.年号','=','dd.年号');
            })
            ->selectRaw('
                NULL 出荷指示行NO,
                dd.商品CD,
                dd.呼び径1,
                dd.呼び径2,
                dd.呼び径3,
                dd.年号 指示年号,
                hd.確定年号,
                hd.保存数量,
                0 hd_only
            ');
            // \Log::info('ddBase：', $ddBase->get()->toArray()); //確認用


            //④ HD_ONLY （HDにしかないものを取得）減量かつ指示数量0
            $hdOnly = DB::query()
            ->fromSub($hdAgg,'hd')
            ->whereNotExists(function($q) use ($jurisdictionCD,$pickupId) {

            $q->from('D出荷指示明細 as dd')
            ->whereRaw('TRIM(dd.商品CD) = hd.商品CD')
            ->whereRaw('TRIM(dd.呼び径1) = hd.呼び径1')
            ->whereRaw('TRIM(dd.呼び径2) = hd.呼び径2')
            ->whereRaw('TRIM(dd.呼び径3) = hd.呼び径3')
            ->whereRaw('dd.年号 = hd.年号')
            ->where('dd.管轄部門CD',$jurisdictionCD)
            ->where('dd.出荷指示NO',$pickupId)
            ->where('dd.出荷確定済','!=',1);

            })
            ->selectRaw('
                NULL 出荷指示行NO,
                hd.商品CD,
                hd.呼び径1,
                hd.呼び径2,
                hd.呼び径3,
                hd.年号 指示年号,
                hd.確定年号,
                hd.保存数量,
                0 hd_only
            ');
            // \Log::info('$hdOnly：', $hdOnly->get()->toArray()); //確認用

            //⑤ UNION（調合）　$ddBase+$hdOnly
            $rows = $ddBase
            ->unionAll($hdOnly)
            ->get();
            // \Log::info('$rows：', $rows->toArray());


            // 元行一覧用のDD明細(集約なし)
            $ddRaw = DB::table('D出荷指示明細')
            ->selectRaw('
                出荷指示行NO,
                TRIM(商品CD) 商品CD,
                TRIM(呼び径1) 呼び径1,
                TRIM(呼び径2) 呼び径2,
                TRIM(呼び径3) 呼び径3,
                年号 指示年号,
                出荷指示数量
            ')
            ->where('管轄部門CD',$jurisdictionCD)
            ->where('出荷指示NO',$pickupId)
            ->where('出荷確定済','!=',1)
            ->orderBy('出荷指示行NO')
            ->get();

            // 生産管理NO取得
            $seisanTarget = DB::table('HD生産管理NOログ')
                ->where('管轄部門CD', $jurisdictionCD)
                ->where('出荷指示NO', $pickupId)
                ->whereNull('消去日時')
                ->exists() ? $pickupId : $originNo;

            $seisanNos = DB::table('HD生産管理NOログ')
                ->where('管轄部門CD', $jurisdictionCD)
                ->where('出荷指示NO', $seisanTarget)
                ->whereNull('消去日時')
                ->select([
                    DB::raw('TRIM(商品CD) as 商品CD'),
                    DB::raw('TRIM(呼び径1) as 呼び径1'),
                    DB::raw('TRIM(呼び径2) as 呼び径2'),
                    DB::raw('TRIM(呼び径3) as 呼び径3'),
                    '確定年号',
                    '生産管理NO',
                    '数量'
                ])
                ->get();
            // \Log::info('$seisanNos', $seisanNos->toArray()); //表示用明細中身確認


            // $rows から商品キー一覧を作成
            $rowKeys = $rows->map(fn($r) => [
                '商品CD'  => $r->商品CD,
                '呼び径1' => $r->呼び径1,
                '呼び径2' => $r->呼び径2,
                '呼び径3' => $r->呼び径3,
                '指示年号' => $r->指示年号,
            ])->unique(fn($r) => $r['商品CD'].'_'.$r['呼び径1'].'_'.$r['呼び径2'].'_'.$r['呼び径3']);

            // D出荷指示明細の数量系（pickupId のみ）
            $ddSums = DB::table('D出荷指示明細 as dd')
                ->selectRaw('
                    TRIM(dd.商品CD) as 商品CD,
                    TRIM(dd.呼び径1) as 呼び径1,
                    TRIM(dd.呼び径2) as 呼び径2,
                    TRIM(dd.呼び径3) as 呼び径3,
                    dd.年号 as 指示年号,
                    SUM(dd.出荷指示数量) as 出荷指示数量,
                    SUM(dd.重量) as 重量,
                    SUM(dd.出荷指示梱数) as 出荷指示梱数
                ')
                ->where('dd.管轄部門CD', $jurisdictionCD)
                ->where('dd.出荷指示NO', $pickupId)
                ->where('dd.出荷確定済', '!=', 1)
                ->groupBy('dd.商品CD', 'dd.呼び径1', 'dd.呼び径2', 'dd.呼び径3', 'dd.年号')
                ->get()
                ->keyBy(fn($r) => $r->商品CD.'_'.$r->呼び径1.'_'.$r->呼び径2.'_'.$r->呼び径3.'_'.$r->指示年号);

            // $rows の商品キーに対してマスタ情報を取得
            // ※商品CD・呼び径はキーが同じなら年号によらず同じ商品なので年号除いてunique済み
            // $ddWithMaster = collect();
            // foreach ($rowKeys as $rk) {
            //     $masterRow = DB::table('M商品 as mp')
            //         ->leftJoin('F在庫場所ﾌｧｲﾙ as mf', function($join) use ($jurisdictionCD) {
            //             $join->on('mf.事業所CD', '=', DB::raw("'$jurisdictionCD'"))
            //                 ->on(DB::raw('TRIM(mf.商品CD)'), '=', DB::raw('TRIM(mp.商品CD)'))
            //                 ->on(DB::raw('TRIM(mf.呼び径1)'), '=', DB::raw('TRIM(mp.呼び径1)'))
            //                 ->on(DB::raw('TRIM(mf.呼び径2)'), '=', DB::raw('TRIM(mp.呼び径2)'))
            //                 ->on(DB::raw('TRIM(mf.呼び径3)'), '=', DB::raw('TRIM(mp.呼び径3)'))
            //                 ->where('mf.場所優先ﾌﾗｸﾞ', 1);
            //         })
            //         ->leftJoin('M保管場所 as ms', function($join) use ($factoryCD) {
            //             $join->on('ms.保管場所CD', '=', 'mf.在庫場所番号')
            //                 ->where('ms.倉庫部門CD', $factoryCD);
            //         })
            //         ->selectRaw("
            //             TRIM(mp.商品CD) as 商品CD,
            //             TRIM(mp.呼び径1) as 呼び径1,
            //             TRIM(mp.呼び径2) as 呼び径2,
            //             TRIM(mp.呼び径3) as 呼び径3,
            //             mp.商品名_社内用 as 商品名,
            //             mp.単位 as 単位,
            //             ms.保管場所名 as 保管場所名
            //         ")
            //         ->whereRaw('TRIM(mp.商品CD) = ?', [$rk['商品CD']])
            //         ->whereRaw('TRIM(mp.呼び径1) = ?', [$rk['呼び径1']])
            //         ->whereRaw('TRIM(mp.呼び径2) = ?', [$rk['呼び径2']])
            //         ->whereRaw('TRIM(mp.呼び径3) = ?', [$rk['呼び径3']])
            //         ->first();

            //     if ($masterRow) {
            //         $ddWithMaster->put(
            //             $masterRow->商品CD.'_'.$masterRow->呼び径1.'_'.$masterRow->呼び径2.'_'.$masterRow->呼び径3,
            //             $masterRow
            //         );
            //     }
            // }

//修正
// $rows の商品キーに対してマスタ情報を取得
// ※商品CD・呼び径はキーが同じなら年号によらず同じ商品なので年号除いてunique済み
$ddWithMaster = DB::table('M商品 as mp')
    ->leftJoin('F在庫場所ﾌｧｲﾙ as mf', function($join) use ($jurisdictionCD) {
        $join->on('mf.事業所CD', '=', DB::raw("'$jurisdictionCD'"))
            ->on(DB::raw('TRIM(mf.商品CD)'), '=', DB::raw('TRIM(mp.商品CD)'))
            ->on(DB::raw('TRIM(mf.呼び径1)'), '=', DB::raw('TRIM(mp.呼び径1)'))
            ->on(DB::raw('TRIM(mf.呼び径2)'), '=', DB::raw('TRIM(mp.呼び径2)'))
            ->on(DB::raw('TRIM(mf.呼び径3)'), '=', DB::raw('TRIM(mp.呼び径3)'))
            ->where('mf.場所優先ﾌﾗｸﾞ', 1);
    })
    ->leftJoin('M保管場所 as ms', function($join) use ($factoryCD) {
        $join->on('ms.保管場所CD', '=', 'mf.在庫場所番号')
            ->where('ms.倉庫部門CD', $factoryCD);
    })
    ->selectRaw("
        TRIM(mp.商品CD) as 商品CD,
        TRIM(mp.呼び径1) as 呼び径1,
        TRIM(mp.呼び径2) as 呼び径2,
        TRIM(mp.呼び径3) as 呼び径3,
        mp.商品名_社内用 as 商品名,
        mp.単位 as 単位,
        ms.保管場所名 as 保管場所名
    ")
    ->where(function($q) use ($rowKeys) {
        foreach ($rowKeys as $rk) {
            $q->orWhere(function($sub) use ($rk) {
                $sub->whereRaw('TRIM(mp.商品CD) = ?', [$rk['商品CD']])
                    ->whereRaw('TRIM(mp.呼び径1) = ?', [$rk['呼び径1']])
                    ->whereRaw('TRIM(mp.呼び径2) = ?', [$rk['呼び径2']])
                    ->whereRaw('TRIM(mp.呼び径3) = ?', [$rk['呼び径3']]);
            });
        }
    })
    ->get()
    ->keyBy(fn($r) => $r->商品CD.'_'.$r->呼び径1.'_'.$r->呼び径2.'_'.$r->呼び径3);




    
                //-------------------------------------------------------
                // rawDetails（保存）用に行NO単位で集約（重複潰し）
                    // DD（行NO付き）
                    $rawDetails = $ddRaw
                    ->map(function($row){
                        return (object)[
                            '出荷指示行NO' => $row->出荷指示行NO,
                            '商品CD'       => $row->商品CD,
                            '呼び径1'      => $row->呼び径1,
                            '呼び径2'      => $row->呼び径2,
                            '呼び径3'      => $row->呼び径3,
                            '出荷指示数量' => (int)$row->出荷指示数量,
                            '指示年号'     => $row->指示年号,
                        ];
                    });
                    $maxRowNo = $rawDetails->max('出荷指示行NO') ?? 0;
                    $hdRows = $hdOnly->get();//HDにしかないデータ
                    //HDにしかないデータを追加
                    $hdRows = collect($hdRows)->map(function($row) use (&$maxRowNo){
                        $maxRowNo++;
                        return (object)[
                            '出荷指示行NO' => $maxRowNo,
                            '商品CD'       => $row->商品CD,
                            '呼び径1'      => $row->呼び径1,
                            '呼び径2'      => $row->呼び径2,
                            '呼び径3'      => $row->呼び径3,
                            '出荷指示数量' => 0,
                            '指示年号'     => $row->指示年号,
                        ];
                    });

                    $rawDetails = $rawDetails
                    ->merge($hdRows)
                    ->values();

                    // \Log::info('$rawDetails', $rawDetails->toArray()); //表示用明細中身確認
                //-------------------------------------------------------

                $ddLineMap = $ddRaw
                    ->merge($hdRows)
                    ->groupBy(fn($r) =>
                        $r->商品CD.'_'.
                        $r->呼び径1.'_'.
                        $r->呼び径2.'_'.
                        $r->呼び径3.'_'.
                        $r->指示年号
                    );

                // viewDetails(表示用明細) 作成
                $viewDetails = $rows
                    ->groupBy(fn($r) => $r->商品CD.'_'.$r->呼び径1.'_'.$r->呼び径2.'_'.$r->呼び径3.'_'.$r->指示年号)
                    ->map(function($rows) use ($ddSums, $ddWithMaster, $ddLineMap) {
                        $r0  = $rows[0];
                        $key = $r0->商品CD.'_'.$r0->呼び径1.'_'.$r0->呼び径2.'_'.$r0->呼び径3.'_'.$r0->指示年号;
                        // 商品マスタキー（年号なし）
                        $masterKey = $r0->商品CD.'_'.$r0->呼び径1.'_'.$r0->呼び径2.'_'.$r0->呼び径3;
                        $dd     = $ddSums[$key] ?? null;
                        $master = $ddWithMaster[$masterKey] ?? null;
                        $before = $rows->sum('保存数量');
                        $after  = $dd->出荷指示数量  ?? 0;
                        $weight = $dd->重量          ?? 0;
                        $pack   = $dd->出荷指示梱数  ?? 0;
                        $lines = ($ddLineMap[$key] ?? collect())->pluck('出荷指示行NO')->unique()->values();
                        //数量変化区分
                        $diffType =
                            $before == 0 && $after > 0 ? '新規' :
                            ($after > $before ? '増量' :
                            ($after < $before ? '減量' : '変更なし'));

                        return [
                            '商品CD'        => $r0->商品CD,
                            '呼び径1'       => $r0->呼び径1,
                            '呼び径2'       => $r0->呼び径2,
                            '呼び径3'       => $r0->呼び径3,
                            '指示年号'      => $r0->指示年号,
                            '出荷指示数量'  => $after,
                            '重量'          => round($weight, 2),
                            '出荷指示梱数'  => $pack,
                            '数量変化区分'  => $diffType,
                            'yearCounts'    => $rows->whereNotNull('確定年号')->map(fn($r) => [
                                '確定年号' => $r->確定年号,
                                '保存数量' => $r->保存数量,
                            ]),
                            '元行一覧'      => $lines,
                            '商品名'        => $master->商品名     ?? null,
                            '単位'          => $master->単位       ?? null,
                            '保管場所名'    => $master->保管場所名  ?? null,
                        ];
                    })
                    ->values();

                // \Log::info('$viewDetails', $viewDetails->toArray()); //表示用明細中身確認

                // ****************************************************************************

                // クエリログの取得
                $queries = \DB::getQueryLog();
                $sqlAll = '';
                foreach ($queries as $q) {
                    $sql = $q['query'];
                    $bindings = $q['bindings'];
                    // ? をバインド値に置換
                    foreach ($bindings as $binding) {
                        $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    }
                    $sqlAll .= $sql . ";\n\n";
                }
                if ($sqlAll) {
                    HDLog::create([
                        '担当者CD' => session('担当者CD'),
                        'ログ種別' => 1,
                        '実行内容' => '集荷指示書表示',
                        'SQL種別' => 1,
                        'エラー' => 0,
                        'SQL文' => $sqlAll
                    ]);
                } else {
                    \Log::error('SQLクエリが取得できませんでした。');
                }



// \Log::info('time: '.(microtime(true) - $start));
// \Log::info(\DB::getQueryLog());

            return response()->json([
                '元出荷指示NO' => $originNo, //元出荷指示NO
                '出荷指示伝票' => $pickups,
                '画面用表示明細' => $viewDetails,   // 画面用（集約）
                '保存用明細'  => $rawDetails,        // 保存用（非集約）
                '生産管理NOs'  => $seisanNos,        // 生産管理NO一覧
            ]);

        } catch (\Exception $e) {
            \Log::error('detail() ERROR: '.$e->getMessage());
            return response()->json(['message' => 'サーバーエラーが発生しました。'], 500);
        }
    }




    // ==========================
    // 集荷中断（新規登録or更新）
    // ==========================
    public function suspend(Request $request,  FormatSpaceService $space)
    {
        //バリデーションチェック
        $request->validate(['出荷指示NO' => 'required|integer']);

        $pickupNo = $request->input('出荷指示NO'); //出荷指示NO
        $originNo = $request->input('元出荷指示NO') ?? $pickupNo; //元出荷指示NO
        $details  = $request->input('明細', []); //明細
        $status   = $request->input('作業状態');//作業状態
        $productionNos   = $request->input('生産管理NOs');//作業状態

        //確認用
        // \Log::info('suspend START', [
        //     'pickupNo' => $pickupNo,
        //     'originNO' => $originNo,
        //     'details_count' => is_array($details) ? count($details) : null,
        //     'details' => $details, // ★ 受信データを確認
        // ]);

        // 保存できない条件
        if (empty($pickupNo)) {
                Log::info('$pickupNo', ['pickupNo'=>$pickupNo]);            
                \Log::warning('集荷中断スキップ:suspend SKIPPED', [
                'pickupNo_empty' => empty($pickupNo),
            ]);
            throw new \Exception('中断エラー');
        }

        $syozokubumonCD = session('所属部門CD');
        $jurisdictionCD = $syozokubumonCD === 'HASDES' ? 1 : (int)$syozokubumonCD;
        $today = Carbon::now()->format('Ymd');
        $his   = Carbon::now()->format('His'); //更新日時

        DB::beginTransaction();

        try {
            // SQLログ開始
            DB::flushQueryLog();
            DB::enableQueryLog();

            /* ---------------------------
            * 出荷指示ヘッダ取得
            /* -------------------------*/
            $header = DB::table('D出荷指示伝票 as ds')
                ->leftJoin('D出荷指示明細 as dd', function($join) {
                    $join->on('dd.出荷指示NO', '=', 'ds.出荷指示NO')
                        ->on('dd.管轄部門CD', '=', 'ds.管轄部門CD');
                })
                ->leftJoin('M運送会社 as mc', 'mc.運送会社CD', '=', 'dd.運送会社CD')
                ->selectRaw('
                    MIN(ds.出荷日) AS 出荷日,
                    MIN(ds.受注_移動NO) AS 受注_移動NO,
                    MIN(ds.出荷先CD) AS 出荷先CD,
                    MIN(dd.出荷形態) AS 出荷形態,
                    MIN(dd.運送会社CD) AS 運送会社CD,
                    MIN(ds.相手先注文NO_得意先) AS 相手先注文NO_得意先
                ')
                ->where('ds.管轄部門CD', $jurisdictionCD)
                ->where('ds.出荷指示NO', $pickupNo)
                ->first();

            //エラー
            if (!$header) {
                \Log::warning('出荷指示が存在しません');
                throw new \Exception('出荷指示が存在しません');
            }

            //未集荷の場合だけ登録
            if (in_array($status, [0,6], true)) { //0:未集荷 //6:再集荷

                /* ---------------------------
                *  HD出荷指示伝票ログ（新規 or 更新）
                /* -------------------------*/
                // 処理状態は「空 or 1」のときだけ更新
                if (empty($status) || $status == 6) {
                    $status = 1; // 1=中断
                }

                // \Log::info('HD HEADER INSERT', [
                //     'pickupNo' => $pickupNo,
                //     'status'   => $status,
                // ]);

                DB::table('HD出荷指示伝票ログ')
                    ->insert([
                        '管轄部門CD' => $jurisdictionCD,
                        '出荷指示NO' => $pickupNo,
                        '元出荷指示NO' => $originNo,
                        '出荷日'       => $header->出荷日,
                        '受注_移動NO'  => $header->受注_移動NO,
                        '出荷先CD'     => $header->出荷先CD,
                        '貴注番'       => $header->相手先注文NO_得意先,
                        '運送会社CD'   => $header->運送会社CD,
                        '出荷形態'     => $header->出荷形態,
                        '処理状態'     => $status,
                        '更新日時'     => now()
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

                    HDLog::create([
                        '担当者CD' => session('担当者CD'),
                        'ログ種別' => 4,
                        '実行内容' => '集荷指示書中断：出荷指示伝票ログ登録',
                        'SQL種別' => 1,
                        'エラー' => 0,
                        'SQL文' => $sqlquery, // 生成されたSQL文を保存
                    ]);
                } else {
                    \Log::error('SQLクエリが取得できませんでした。');
                }
            }

            // //前回のデータを削除
            // DB::table('HD出荷指示明細ログ')
            //     ->where('管轄部門CD', $jurisdictionCD)
            //     ->where('出荷指示NO', $pickupNo)
            //     ->delete();

            // //前回のデータを削除
            // DB::table('HD生産管理NOログ')
            //     ->where('管轄部門CD', $jurisdictionCD)
            //     ->where('出荷指示NO', $pickupNo)
            //     ->delete();


            /* ---------------------------
            * HD出荷指示明細ログ
            /* -------------------------*/
            // ★ フロントエンドから受け取ったdetailsをそのまま使う
            // ★ 西暦が古い順に並べ替え（必要に応じて）
            usort($details, function ($a, $b) {
                // 行番号優先、その次に年号
                $lineCompare = (int)$a['出荷指示行NO'] <=> (int)$b['出荷指示行NO'];
                if ($lineCompare !== 0) return $lineCompare;
                
                return (int)($a['確定年号'] ?? 0) <=> (int)($b['確定年号'] ?? 0);
            });

$existingRows = DB::table('HD出荷指示明細ログ')
    ->where('管轄部門CD', $jurisdictionCD)
    ->where('出荷指示NO', $pickupNo)
    ->get();

$existingMap = [];

foreach ($existingRows as $row) {
    $key = $row->出荷指示行NO . '-' . $row->出荷指示行NO枝番;
    $existingMap[$key] = $row;
}
$newMap = [];




            //枝番管理用
            $branchMap = [];
            $insertRows = []; //明細配列

            // SQLログ開始
            DB::flushQueryLog();//クエリログ削除
            DB::enableQueryLog();//クエリ結果取得
            


foreach ($details as $row) {

    $baseLineNo = (int)$row['出荷指示行NO'];

    if (!isset($branchMap[$baseLineNo])) {
        $branchMap[$baseLineNo] = 1;
    }

    $branchNo = $branchMap[$baseLineNo]++;

    $key = $baseLineNo . '-' . $branchNo;

    $newMap[$key] = [
        '管轄部門CD'       => $jurisdictionCD,
        '出荷指示NO'       => $pickupNo,
        '出荷指示行NO'     => $baseLineNo,
        '出荷指示行NO枝番' => $branchNo,
        '受注_移動NO'      => $header->受注_移動NO,
        '年号'             => $row['年号'],
        '確定年号'         => $this->normalizeYear($row['確定年号']),
        '商品CD'           => $space->rightSpace($row['商品CD'], 13),
        '呼び径1'          => $space->leftSpace($row['呼び径1'], 4),
        '呼び径2'          => $space->leftSpace($row['呼び径2'], 4),
        '呼び径3'          => $space->leftSpace($row['呼び径3'], 3),
        '出荷指示数量'     => (int)$row['数量'],
        '消去日時'       => null,
    ];
}

$insertRows = [];
$updateRows = [];

/* UPDATE / INSERT */
foreach ($newMap as $key => $newData) {

    if (isset($existingMap[$key])) {

        $old = $existingMap[$key];

        $updateRows[] = [
            'where' => [
                '管轄部門CD'       => $old->管轄部門CD,
                '出荷指示NO'       => $old->出荷指示NO,
                '出荷指示行NO'     => $old->出荷指示行NO,
                '出荷指示行NO枝番' => $old->出荷指示行NO枝番,
            ],
            'data' => $newData,
        ];

    } else {
        $insertRows[] = $newData;
    }
}

//新規登録
if (!empty($insertRows)) {
    DB::table('HD出荷指示明細ログ')->insert($insertRows);
}
//更新
foreach ($updateRows as $row) {
    DB::table('HD出荷指示明細ログ')
        ->where($row['where'])
        ->update($row['data']);
}
//余剰文は論理削除
foreach ($existingMap as $key => $old) {
    if (!isset($newMap[$key])) {
        DB::table('HD出荷指示明細ログ')
            ->where([
                '管轄部門CD'       => $old->管轄部門CD,
                '出荷指示NO'       => $old->出荷指示NO,
                '出荷指示行NO'     => $old->出荷指示行NO,
                '出荷指示行NO枝番' => $old->出荷指示行NO枝番,
            ])
            ->update(['消去日時' => now()]);
    }
}

//             foreach ($details as $row) {
//                 // \Log::info('DETAIL ROW', $row);
//                 $normalizedYear = $this->normalizeYear($row['確定年号']);

//                 // 未スキャン（null）はそのまま通す
//                 if ($row['確定年号'] !== null && $normalizedYear === null) {
//                     \Log::warning('年号正規化失敗', ['row' => $row]);
//                     continue;
//                 }

//                 $baseLineNo = (int)$row['出荷指示行NO'];

//                 // ★ 枝番を初期化または取得
//                 if (!isset($branchMap[$baseLineNo])) {
//                     $branchMap[$baseLineNo] = 1;
//                 }

//                 $branchNo = $branchMap[$baseLineNo];
//                 $branchMap[$baseLineNo]++; // 次回用にインクリメント

//                 //確認用
//                 // \Log::info('HD出荷指示明細ログ INSERT', [
//                 //     '行NO' => $baseLineNo,
//                 //     '枝番' => $branchNo,
//                 //     '年号' => $row['年号'],
//                 //     '確定年号' => $normalizedYear,
//                 //     '数量' => (int)$row['数量'],
//                 //     '生産管理NO'     => $row['生産管理NO'],
//                 // ]);

//                 $productCD = $space->rightSpace($row['商品CD'], 13);
//                 $y1 = $space->leftSpace($row['呼び径1'], 4);
//                 $y2 = $space->leftSpace($row['呼び径2'], 4);
//                 $y3 = $space->leftSpace($row['呼び径3'], 3);
//                 $qty = (int)$row['数量'];   

//                 // // ★ フロントエンドから受け取った数量をそのまま使う
//                 // $insertRows[] = [
//                 //     '管轄部門CD'       => $jurisdictionCD,
//                 //     '出荷指示NO'       => $pickupNo,
//                 //     '出荷指示行NO'     => $baseLineNo,
//                 //     '出荷指示行NO枝番' => $branchNo,
//                 //     '受注_移動NO'      => $header->受注_移動NO,
//                 //     '年号'             => $row['年号'],
//                 //     '確定年号'         => $normalizedYear,
//                 //     '商品CD'           => $productCD,
//                 //     '呼び径1'          => $y1,
//                 //     '呼び径2'          => $y2,
//                 //     '呼び径3'          => $y3,
//                 //     '出荷指示数量'     => $qty,
//                 // ];



            // //生産管理NO登録
            // $insertRows2 = []; //生産管理NO配列

            // foreach ($productionNos as $row) {

            //     if (($row['数量'] ?? 0) <= 0) {
            //         continue; // スキップ
            //     }

            //     $productCD = $space->rightSpace($row['商品CD'], 13);
            //     $y1 = $space->leftSpace($row['呼び径1'], 4);
            //     $y2 = $space->leftSpace($row['呼び径2'], 4);
            //     $y3 = $space->leftSpace($row['呼び径3'], 3);
            //     $insertRows2[] = [
            //         '管轄部門CD' => $jurisdictionCD,
            //         '出荷指示NO' => $pickupNo,
            //         '確定年号'   => $row['確定年号'],
            //         '商品CD'     => $productCD,
            //         '呼び径1'    => $y1,
            //         '呼び径2'    => $y2,
            //         '呼び径3'    => $y3,
            //         '生産管理NO'=> $row['生産管理NO'],
            //         '数量'=> $row['数量'],
            //     ];
            // }

            // //INSERT
            // if (!empty($insertRows)) {
            //     DB::table('HD出荷指示明細ログ')->insert($insertRows);
            // }
            // if (!empty($insertRows2)) {
            //     DB::table('HD生産管理NOログ')->insert($insertRows2);
            // }




/* ---------------------------
 * HD生産管理NOログ
 * -------------------------*/
$existingRows2 = DB::table('HD生産管理NOログ')
    ->where('管轄部門CD', $jurisdictionCD)
    ->where('出荷指示NO', $pickupNo)
    ->get();

$existingMap2 = [];

foreach ($existingRows2 as $row) {

    $key = $row->商品CD . '|'
         . $row->呼び径1 . '|'
         . $row->呼び径2 . '|'
         . $row->呼び径3 . '|'
         . $row->生産管理NO;

    $existingMap2[$key] = $row;
}

$newMap2 = [];
$updateRows2 = [];

foreach ($productionNos as $row) {

    if (($row['数量'] ?? 0) <= 0) continue;

    $productCD = $space->rightSpace($row['商品CD'], 13);
    $y1 = $space->leftSpace($row['呼び径1'], 4);
    $y2 = $space->leftSpace($row['呼び径2'], 4);
    $y3 = $space->leftSpace($row['呼び径3'], 3);

    $key = $productCD . '|'
         . $y1 . '|'
         . $y2 . '|'
         . $y3 . '|'
         . $row['生産管理NO'];

    $newMap2[$key] = [
        '管轄部門CD' => $jurisdictionCD,
        '出荷指示NO' => $pickupNo,
        '確定年号'   => $row['確定年号'],
        '商品CD'     => $productCD,
        '呼び径1'    => $y1,
        '呼び径2'    => $y2,
        '呼び径3'    => $y3,
        '生産管理NO' => $row['生産管理NO'],
        '数量'       => $row['数量'],
        '消去日時' => null,
    ];
}

foreach ($newMap2 as $key => $newData2) {

    $keepKeys[$key] = true;

    if (isset($existingMap2[$key])) {

        $old = $existingMap2[$key];

        $updateRows2[] = [
            'where' => [
                '管轄部門CD' => $old->管轄部門CD,
                '出荷指示NO' => $old->出荷指示NO,
                '商品CD'     => $old->商品CD,
                '呼び径1'    => $old->呼び径1,
                '呼び径2'    => $old->呼び径2,
                '呼び径3'    => $old->呼び径3,
                '生産管理NO' => $old->生産管理NO,
            ],
            'data' => $newData2,
        ];

    } else {
        $insertRows2[] = $newData2;
    }
}


/* ---------------------------
 * INSERT
 * -------------------------*/
if (!empty($insertRows2)) {
    DB::table('HD生産管理NOログ')->insert($insertRows2);
}

/* ---------------------------
 * UPDATE（必ず=null）
 * -------------------------*/
foreach ($updateRows2 as $upRow) {
    DB::table('HD生産管理NOログ')
        ->where($upRow['where'])
        ->update(array_merge($upRow['data'], [
            '消去日時' => null
        ]));
}

/* ---------------------------
 * 余剰行（消去日時=now()）
 * -------------------------*/
foreach ($existingMap2 as $key => $old) {

    if (!isset($keepKeys[$key])) {

        DB::table('HD生産管理NOログ')
            ->where([
                '管轄部門CD' => $old->管轄部門CD,
                '出荷指示NO' => $old->出荷指示NO,
                '商品CD'     => $old->商品CD,
                '呼び径1'    => $old->呼び径1,
                '呼び径2'    => $old->呼び径2,
                '呼び径3'    => $old->呼び径3,
                '生産管理NO' => $old->生産管理NO,
            ])
            ->update([
                '消去日時' => now(),
                '数量' => 0,
            ]);
    }
}






            /* ---------------------------
            * クエリログ取得 & ログ保存
            * -------------------------*/
            $queries = DB::getQueryLog();
            $sqlAll = '';
            $hasUpdate  = false; // ★ UPDATE検出フラグ

            foreach ($queries as $query) {
                $sql = $query['query'];
                $bindings = $query['bindings'];
                // ? をバインド値に置換
                foreach ($bindings as $binding) {
                    $value = is_numeric($binding) ? $binding : "'".$binding."'";
                    $sql = preg_replace('/\?/', $value, $sql, 1);
                }
                $sqlAll .= $sql . ";\n\n";

                    // ★ UPDATEが含まれるか確認
    if (stripos($query['query'], 'update') === 0) {
        $hasUpdate = true;
    }
            }


// ★ INSERT のみ → ログ種別4、UPDATE含む → ログ種別3
$logkind = $hasUpdate ? 3 : 4;
            // 実行内容判定
            $do = $hasUpdate ? '出荷指示明細ログ、生産管理NOログ更新' : '出荷指示明細ログ、生産管理NOログ登録';
            // 1回だけログ保存
            HDLog::create([
                '担当者CD' => session('担当者CD'),
                'ログ種別' => $logkind,
                '実行内容' => '集荷指示書中断：'.$do,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => $sqlAll,
            ]);     

            DB::commit();

            return response()->json([
                'message' => '集荷を中断しました',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('集荷中断エラー', [
                'pickupNo' => $pickupNo,
                'originNo' => $originNo,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }






    // ==========================
    // 集荷、処理完了（新規登録or更新）
    // ==========================
    public function complete(Request $request,  FormatSpaceService $space)
    {
        //バリデーションチェック
        $request->validate([
            '出荷指示NO' => 'required|integer', //整数
        ]);

        $pickupNo = $request->input('出荷指示NO'); //出荷指示NO
        $originNo = $request->input('元出荷指示NO') ?? $pickupNo; //元出荷指示NO
        $details  = $request->input('明細', []); //明細
        $status   = $request->input('作業状態');//作業状態
        $productionNos   = $request->input('生産管理NOs');//作業状態

        // 保存できない条件
        if (empty($pickupNo) || (empty($details) && $status != 5)) {
            \Log::warning('集荷中断スキップ:suspend SKIPPED', [
                'pickupNo_empty' => empty($pickupNo),
                'details_empty'  => empty($details),
            ]);
            throw new \Exception('登録エラー');
        }

        $syozokubumonCD = session('所属部門CD');
        $jurisdictionCD = $syozokubumonCD === 'HASDES' ? 1 : (int)$syozokubumonCD;
        $today = Carbon::now()->format('Ymd');
        $his   = Carbon::now()->format('His'); //更新日時

        DB::beginTransaction();

        try {
            // 処理状態が[中断]と[変更]のときだけ更新 (0:未集荷　1:中断  5:未処理のキャンセル　6:再集荷  7:再集荷中断)
            if (empty($status) || in_array($status, [1, 5, 6, 7], true)) {

                if(empty($status) || in_array($status, [1, 6, 7], true)) {
                    // ★ 重複確認
                    $exists = DB::table('D集荷完了')
                        ->where('管轄部門CD', $jurisdictionCD)
                        ->where('出荷指示NO', $pickupNo)
                        ->exists();
                    if ($exists) {
                        throw new \Exception('すでに登録されています');
                    }
                }

                /* ---------------------------
                * 出荷指示ヘッダ取得
                /* -------------------------*/
                $header = DB::table('D出荷指示伝票 as ds')
                    ->leftJoin('D出荷指示明細 as dd', function($join) {
                        $join->on('dd.出荷指示NO', '=', 'ds.出荷指示NO')
                            ->on('dd.管轄部門CD', '=', 'ds.管轄部門CD');
                    })
                    ->leftJoin('M運送会社 as mc', 'mc.運送会社CD', '=', 'dd.運送会社CD')
                    ->selectRaw('
                        MIN(ds.出荷日) AS 出荷日,
                        MIN(ds.受注_移動NO) AS 受注_移動NO,
                        MIN(ds.出荷先CD) AS 出荷先CD,
                        MIN(dd.出荷形態) AS 出荷形態,
                        MIN(dd.運送会社CD) AS 運送会社CD,
                        MIN(ds.相手先注文NO_得意先) AS 相手先注文NO_得意先
                    ')
                    ->where('ds.管轄部門CD', $jurisdictionCD)
                    ->where('ds.出荷指示NO', $pickupNo)
                    ->first();

                if (!$header) {
                    \Log::warning('出荷指示が存在しません');
                    throw new \Exception('出荷指示が存在しません');
                }

                /* ---------------------------
                * HD出荷指示伝票ログ（新規 or 更新）
                /* -------------------------*/
                //キャンセル完了
                if($status== 5){
                    $updateStatus = 3; // キャンセル（処理済み）:3
                //集荷完了
                }else{
                    $updateStatus = 2; // 処理済み :2
                }

                //再出荷の場合、再出荷前のデータのフラグを1にする（一覧から非表示にさせるため）
                if(in_array($status, [6, 7], true) && ($pickupNo !== $originNo)){
                        //再出荷済更新フラグ
                        DB::table('HD出荷指示伝票ログ')
                            ->where('管轄部門CD', $jurisdictionCD)
                            ->where('出荷指示NO', $originNo) // 元の出荷指示NO
                            ->where('処理状態', 2)
                            ->update(['再出荷済フラグ' => 1]);
                }

                //HD出荷指示伝票ログが存在するか
                $exists = DB::table('HD出荷指示伝票ログ')
                        ->where('管轄部門CD', $jurisdictionCD)
                        ->where('出荷指示NO', $pickupNo)
                        ->exists();

                // SQLログ開始
                DB::enableQueryLog();

                DB::table('HD出荷指示伝票ログ')
                    ->updateOrInsert(
                        [
                            '管轄部門CD' => $jurisdictionCD,
                            '出荷指示NO' => $pickupNo,
                        ],
                        [
                            '元出荷指示NO' => $originNo,
                            '出荷日'       => $header->出荷日,
                            '受注_移動NO'  => $header->受注_移動NO,
                            '出荷先CD'     => $header->出荷先CD,
                            '貴注番'       => $header->相手先注文NO_得意先,
                            '運送会社CD'   => $header->運送会社CD,
                            '出荷形態'     => $header->出荷形態,
                            '処理状態'     => $updateStatus,
                            '更新日時'     => now(),
                        ]
                    );

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
                    $do = $exists ? '更新' : '登録';
                    $logkind =  $exists ? 3 : 4;

                    HDLog::create([
                        '担当者CD' => session('担当者CD'),
                        'ログ種別' => $logkind,
                        '実行内容' => '集荷指示書集荷完了：出荷指示伝票ログ'.$do,
                        'SQL種別' => 1,
                        'エラー' => 0,
                        'SQL文' => $sqlquery, // 生成されたSQL文を保存
                    ]);
                } else {
                    \Log::error('SQLクエリが取得できませんでした。');
                }

                // //前回のデータを削除
                // DB::table('HD出荷指示明細ログ')
                //     ->where('管轄部門CD', $jurisdictionCD)
                //     ->where('出荷指示NO', $pickupNo)
                //     ->delete();

                // //前回のデータを削除
                // DB::table('HD生産管理NOログ')
                //     ->where('管轄部門CD', $jurisdictionCD)
                //     ->where('出荷指示NO', $pickupNo)
                //     ->delete();



                /* ---------------------------
                * HD出荷指示明細ログ、D集荷完了
                /* -------------------------*/
                // ★ フロントエンドから受け取ったdetailsをそのまま使う
                // ★ 西暦が古い順に並べ替え（必要に応じて）
                usort($details, function ($a, $b) {
                    // 行番号優先、その次に年号
                    $lineCompare = (int)$a['出荷指示行NO'] <=> (int)$b['出荷指示行NO'];
                    if ($lineCompare !== 0) return $lineCompare;
                    
                    return (int)($a['確定年号'] ?? 0) <=> (int)($b['確定年号'] ?? 0);
                });


$existingRows = DB::table('HD出荷指示明細ログ')
    ->where('管轄部門CD', $jurisdictionCD)
    ->where('出荷指示NO', $pickupNo)
    ->get();

$existingMap = [];

foreach ($existingRows as $row) {
    $key = $row->出荷指示行NO . '-' . $row->出荷指示行NO枝番;
    $existingMap[$key] = $row;
}
$newMap = [];

                //枝番管理用
                $branchMap = [];
                $insertRows = []; //明細配列

                // SQLログ開始
                DB::flushQueryLog();//クエリログ削除
                DB::enableQueryLog();//クエリ結果取得

                // foreach ($details as $row) {
                //     $normalizedYear = $this->normalizeYear($row['確定年号']);

                //     // 未スキャン（null）はそのまま通す
                //     if ($row['確定年号'] !== null && $normalizedYear === null) {
                //         \Log::warning('年号正規化失敗', ['row' => $row]);
                //         continue;
                //     }

                //     $baseLineNo = (int)$row['出荷指示行NO'];

                //     // ★ 枝番を初期化または取得
                //     if (!isset($branchMap[$baseLineNo])) {
                //         $branchMap[$baseLineNo] = 1;
                //     }

                //     $branchNo = $branchMap[$baseLineNo];
                //     $branchMap[$baseLineNo]++; // 次回用にインクリメント


                //     $productCD = $space->rightSpace($row['商品CD'], 13);
                //     $y1 = $space->leftSpace($row['呼び径1'], 4);
                //     $y2 = $space->leftSpace($row['呼び径2'], 4);
                //     $y3 = $space->leftSpace($row['呼び径3'], 3);
                //     $qty = (int)$row['数量'];   

                //     // ★ フロントエンドから受け取った数量をそのまま使う
                //     $insertRows[] = [
                //         '管轄部門CD'       => $jurisdictionCD,
                //         '出荷指示NO'       => $pickupNo,
                //         '出荷指示行NO'     => $baseLineNo,
                //         '出荷指示行NO枝番' => $branchNo,
                //         '受注_移動NO'      => $header->受注_移動NO,
                //         '年号'             => $row['年号'],
                //         '確定年号'         => $normalizedYear,
                //         '商品CD'           => $productCD,
                //         '呼び径1'          => $y1,
                //         '呼び径2'          => $y2,
                //         '呼び径3'          => $y3,
                //         '出荷指示数量'     => $qty,
                //     ];
                //     //処理済みの場合だけ　数量0は除外
                //     if($updateStatus == 2 && $qty > 0) {
                //         $insertRows2[] = [
                //             '管轄部門CD'   => $jurisdictionCD,
                //             '出荷指示NO'   => $pickupNo,
                //             '出荷指示行NO' => $baseLineNo,
                //             '出荷指示行NO枝番' => $branchNo,
                //             '年号'         => $normalizedYear,
                //             '商品CD'   => $productCD,
                //             '呼び径1'  => $y1,
                //             '呼び径2'  => $y2,
                //             '呼び径3'  => $y3,
                //             '数量'     => $qty,
                //             '登録日'   => $today,
                //             '登録時刻' => $his,
                //             '予備文字項目1' => '',
                //             '予備文字項目2' => '',
                //         ];
                //     }
                // }

                // //INSERT
                // if (!empty($insertRows)) {
                //     DB::table('HD出荷指示明細ログ')->insert($insertRows);
                // }
                // if (!empty($insertRows2)) {
                //     DB::table('D集荷完了')->insert($insertRows2);
                // }



foreach ($details as $row) {

    $normalizedYear = $this->normalizeYear($row['確定年号']);

    // 未スキャン（null）はそのまま通す
    if ($row['確定年号'] !== null && $normalizedYear === null) {
        \Log::warning('年号正規化失敗', ['row' => $row]);
        continue;
    }


    $baseLineNo = (int)$row['出荷指示行NO'];

    if (!isset($branchMap[$baseLineNo])) {
        $branchMap[$baseLineNo] = 1;
    }

    $branchNo = $branchMap[$baseLineNo]++;

    $key = $baseLineNo . '-' . $branchNo;
    $productCD = $space->rightSpace($row['商品CD'], 13);
    $y1 = $space->leftSpace($row['呼び径1'], 4);
    $y2 = $space->leftSpace($row['呼び径2'], 4);
    $y3 = $space->leftSpace($row['呼び径3'], 3);
    $qty = (int)$row['数量'];   


    $newMap[$key] = [
        '管轄部門CD'       => $jurisdictionCD,
        '出荷指示NO'       => $pickupNo,
        '出荷指示行NO'     => $baseLineNo,
        '出荷指示行NO枝番' => $branchNo,
        '受注_移動NO'      => $header->受注_移動NO,
        '年号'             => $row['年号'],
        '確定年号'         => $normalizedYear,
        '商品CD'   => $productCD,
        '呼び径1'  => $y1,
        '呼び径2'  => $y2,
        '呼び径3'  => $y3,
        '出荷指示数量'     => $qty,
        '消去日時'       => null,
    ];

    //処理済みの場合だけ　数量0は除外
    if($updateStatus == 2 && $qty > 0) {
        $insertRows2[] = [
            '管轄部門CD'   => $jurisdictionCD,
            '出荷指示NO'   => $pickupNo,
            '出荷指示行NO' => $baseLineNo,
            '出荷指示行NO枝番' => $branchNo,
            '年号'         => $normalizedYear,
            '商品CD'   => $productCD,
            '呼び径1'  => $y1,
            '呼び径2'  => $y2,
            '呼び径3'  => $y3,
            '数量'     => $qty,
            '登録日'   => $today,
            '登録時刻' => $his,
            '予備文字項目1' => '',
            '予備文字項目2' => '',
        ];
    }

}



$insertRows = [];
$updateRows = [];

/* HD明細ログ　UPDATE / INSERT */
foreach ($newMap as $key => $newData) {

    if (isset($existingMap[$key])) {

        $old = $existingMap[$key];

        $updateRows[] = [
            'where' => [
                '管轄部門CD'       => $old->管轄部門CD,
                '出荷指示NO'       => $old->出荷指示NO,
                '出荷指示行NO'     => $old->出荷指示行NO,
                '出荷指示行NO枝番' => $old->出荷指示行NO枝番,
            ],
            'data' => $newData,
        ];

    } else {
        $insertRows[] = $newData;
    }
}

if (!empty($insertRows)) {
    DB::table('HD出荷指示明細ログ')->insert($insertRows);
}

foreach ($updateRows as $row) {
    DB::table('HD出荷指示明細ログ')
        ->where($row['where'])
        ->update($row['data']);
}

foreach ($existingMap as $key => $old) {
    if (!isset($newMap[$key])) {
        DB::table('HD出荷指示明細ログ')
            ->where([
                '管轄部門CD'       => $old->管轄部門CD,
                '出荷指示NO'       => $old->出荷指示NO,
                '出荷指示行NO'     => $old->出荷指示行NO,
                '出荷指示行NO枝番' => $old->出荷指示行NO枝番,
            ])
            ->update(['消去日時' => now()]);
    }
}

//集荷完了登録
if (!empty($insertRows2)) {
    DB::table('D集荷完了')->insert($insertRows2);
}


                // //生産管理NO登録
                // $insertRows3 = []; //生産管理NO配列

                // foreach ($productionNos as $row) {

                //     if (($row['数量'] ?? 0) <= 0) {
                //         continue; // スキップ
                //     }

                //     $productCD = $space->rightSpace($row['商品CD'], 13);
                //     $y1 = $space->leftSpace($row['呼び径1'], 4);
                //     $y2 = $space->leftSpace($row['呼び径2'], 4);
                //     $y3 = $space->leftSpace($row['呼び径3'], 3);

                //     $insertRows3[] = [
                //         '管轄部門CD' => $jurisdictionCD,
                //         '出荷指示NO' => $pickupNo,
                //         '確定年号'   => $row['確定年号'],
                //         '商品CD'     => $productCD,
                //         '呼び径1'    => $y1,
                //         '呼び径2'    => $y2,
                //         '呼び径3'    => $y3,
                //         '生産管理NO'=> $row['生産管理NO'],
                //         '数量'=> $row['数量'],
                //     ];
                // }

                // if (!empty($insertRows3)) {
                //     DB::table('HD生産管理NOログ')->insert($insertRows3);
                // }


/* ---------------------------
 * HD生産管理NOログ
 * -------------------------*/
$existingRows2 = DB::table('HD生産管理NOログ')
    ->where('管轄部門CD', $jurisdictionCD)
    ->where('出荷指示NO', $pickupNo)
    ->get();

$existingMap2 = [];

foreach ($existingRows2 as $row) {

    $key = $row->商品CD . '|'
         . $row->呼び径1 . '|'
         . $row->呼び径2 . '|'
         . $row->呼び径3 . '|'
         . $row->生産管理NO;

    $existingMap2[$key] = $row;
}

$newMap2 = [];
$updateRows2 = [];

foreach ($productionNos as $row) {

    if (($row['数量'] ?? 0) <= 0) continue;

    $productCD = $space->rightSpace($row['商品CD'], 13);
    $y1 = $space->leftSpace($row['呼び径1'], 4);
    $y2 = $space->leftSpace($row['呼び径2'], 4);
    $y3 = $space->leftSpace($row['呼び径3'], 3);

    $key = $productCD . '|'
         . $y1 . '|'
         . $y2 . '|'
         . $y3 . '|'
         . $row['生産管理NO'];

    $newMap2[$key] = [
        '管轄部門CD' => $jurisdictionCD,
        '出荷指示NO' => $pickupNo,
        '確定年号'   => $row['確定年号'],
        '商品CD'     => $productCD,
        '呼び径1'    => $y1,
        '呼び径2'    => $y2,
        '呼び径3'    => $y3,
        '生産管理NO' => $row['生産管理NO'],
        '数量'       => $row['数量'],
        '消去日時' => null,
    ];
}

foreach ($newMap2 as $key => $newData2) {

    $keepKeys[$key] = true;

    if (isset($existingMap2[$key])) {

        $old = $existingMap2[$key];

        $updateRows2[] = [
            'where' => [
                '管轄部門CD' => $old->管轄部門CD,
                '出荷指示NO' => $old->出荷指示NO,
                '商品CD'     => $old->商品CD,
                '呼び径1'    => $old->呼び径1,
                '呼び径2'    => $old->呼び径2,
                '呼び径3'    => $old->呼び径3,
                '生産管理NO' => $old->生産管理NO,
            ],
            'data' => $newData2,
        ];

    } else {
        $insertRows3[] = $newData2;
    }
}


/* ---------------------------
 * INSERT
 * -------------------------*/
if (!empty($insertRows3)) {
    DB::table('HD生産管理NOログ')->insert($insertRows3);
}

/* ---------------------------
 * UPDATE（必ず消去日時=null）
 * -------------------------*/
foreach ($updateRows2 as $upRow) {
    DB::table('HD生産管理NOログ')
        ->where($upRow['where'])
        ->update(array_merge($upRow['data'], [
            '消去日時' => null
        ]));
}

/* ---------------------------
 * 余剰行（消去日時 = now()）
 * -------------------------*/
foreach ($existingMap2 as $key => $old) {

    if (!isset($keepKeys[$key])) {

        DB::table('HD生産管理NOログ')
            ->where([
                '管轄部門CD' => $old->管轄部門CD,
                '出荷指示NO' => $old->出荷指示NO,
                '商品CD'     => $old->商品CD,
                '呼び径1'    => $old->呼び径1,
                '呼び径2'    => $old->呼び径2,
                '呼び径3'    => $old->呼び径3,
                '生産管理NO' => $old->生産管理NO,
            ])
            ->update([
                '消去日時' => now(),
                '数量' => 0,
            ]);
    }
}


            /* ---------------------------
            * クエリログ取得 & ログ保存
            * -------------------------*/
                $queries = DB::getQueryLog();
                $sqlAll = '';
                $hasUpdate  = false; // ★ UPDATE検出フラグ


                foreach ($queries as $query) {
                    $sql = $query['query'];
                    $bindings = $query['bindings'];
                    // ? をバインド値に置換
                    foreach ($bindings as $binding) {
                        $value = is_numeric($binding) ? $binding : "'".$binding."'";
                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    }
                    $sqlAll .= $sql . ";\n\n";
    // ★ UPDATEが含まれるか確認
    if (stripos($query['query'], 'update') === 0) {
        $hasUpdate = true;
    }

                }

                // 実行内容判定
                $do = '出荷指示明細ログ、D集荷完了、生産管理NOログ登録';

                // 1回だけログ保存
                HDLog::create([
                    '担当者CD' => session('担当者CD'),
                    'ログ種別' => 4,
                    '実行内容' => '集荷指示書集荷完了：'.$do,
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlAll,
                ]);     

                DB::commit();
            }

            return response()->json([
                'message' => '処理完了しました',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('処理完了エラー', [
                'pickupNo' => $pickupNo,
                'originNo' => $originNo,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }




    // ==========================
    // 再集荷検索
    // ==========================
    public function search(Request $request)
    {
        \DB::enableQueryLog();

        try {

            $sijiNo = $request->query('出荷指示NO');
            $idoNO = $request->query('受注_移動NO');
            
            if (empty($sijiNo) || !is_numeric($sijiNo)) {
                return response()->json(['message' => '無効な出荷指示NOです。'], 400);
            }
            if (empty($idoNO) || !is_numeric($idoNO)) {
                return response()->json(['message' => '無効な受注_移動NOです。'], 400);
            }

            $syozokubumonCD = session('所属部門CD');
            $jurisdictionCD  = $syozokubumonCD == 'HASDES' ? 1 : $syozokubumonCD;

            // ① すでにHDログに存在するか？
            $pickup = DB::table('HD出荷指示伝票ログ as hd')
                ->where('hd.管轄部門CD', $jurisdictionCD)
                ->where('hd.出荷指示NO', $sijiNo)
                ->whereIn('hd.処理状態', [1, 2])
                ->first();                    
        
            //　② 再集荷候補
            // $result = DB::table('D出荷指示伝票 as ds')
            //     ->join('HD出荷指示伝票ログ as hd', function ($join) use ($jurisdictionCD, $idoNO) {
            //         $join->on('hd.出荷指示NO', '=', 'ds.出荷指示NO')
            //             ->on('hd.管轄部門CD', '=', 'ds.管轄部門CD')
            //             ->where('hd.管轄部門CD', $jurisdictionCD)
            //             ->where('hd.受注_移動NO', $idoNO)
            //             ->where('hd.処理状態', 2)
            //             ->where('hd.再出荷済フラグ', 0);
            //     })
            // ->select('ds.出荷指示NO')
            // ->where('ds.管轄部門CD', $jurisdictionCD)
            // ->where('ds.出荷指示NO', '!=',  $sijiNo)
            // ->where('ds.受注_移動NO', $idoNO)
            // ->get();
            $result = DB::table('HD出荷指示伝票ログ')
                ->select('出荷指示NO')
                ->where('管轄部門CD', $jurisdictionCD)
                ->where('受注_移動NO', $idoNO)
                ->where('処理状態', 2)
                ->where('再出荷済フラグ', 0)
                ->where('出荷指示NO', '!=',  $sijiNo)
                ->get();

            return response()->json([
                'pickup' => $pickup,
                'result' => $result, 
            ]);

        } catch (\Throwable $e) {

            \Log::error('再集荷表示エラー', [
                '出荷指示NO' => $sijiNo ?? null,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    // ==========================
    // 年号正規化
    // ==========================
    private function normalizeYear($year): ?int
    {
        if (is_int($year)) {
            return $year;
        }

        if (is_string($year)) {
            // "~2023" → "2023"
            $year = ltrim($year, '~');

            if (ctype_digit($year)) {
                return (int)$year;
            }
        }
        return null; // 不正値
    }





}






