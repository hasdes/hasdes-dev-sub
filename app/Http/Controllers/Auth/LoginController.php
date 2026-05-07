<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\HDLog;

final class LoginController extends Controller
{
    /**
     * @param AuthManager $auth
     */
    public function __construct(
        private readonly AuthManager $auth,
    ) {
    }

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws AuthenticationException
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        DB::enableQueryLog();
        $credentials = $request->only(['担当者CD', 'PASSWORD']);
    
        // ユーザーを担当者CDで取得
        $user = $this->auth->guard()->getProvider()->retrieveByCredentials(['担当者CD' => $credentials['担当者CD']]);
        
        if ($user && $user->ロックカウント >= 5) {
            HDLog::create([
                '担当者CD' => $credentials['担当者CD'],
                'ログ種別' => 0,
                '実行内容' => 'アカウントロック',
                'SQL種別' => 0,
                'エラー' => 0,
            ]);
            return new JsonResponse([
                'message' => 'Account locked.',
                'status' => 403,
            ]);
        }

        if ($user && !in_array($user->従業員区分, [0, 1])) {
            HDLog::create([
                '担当者CD' => $credentials['担当者CD'],
                'ログ種別' => 0,
                '実行内容' => '従業員外ログイン',
                'SQL種別' => 0,
                'エラー' => 0,
            ]);
            return new JsonResponse([
                'message' => 'Unauthorized employee category.',
                'status' => 400,
            ]);
        }
    
        if ($this->auth->guard()->attempt(['担当者CD' => $credentials['担当者CD'], 'password' => $credentials['PASSWORD']])) {
            $request->session()->regenerate();
            $userId = $this->auth->guard()->user()->担当者CD; 
            $request->session()->put('担当者CD', $userId);

            // 20251202追加：User モデルから所属部門CDを取得ーーーーーーーーーーーーーーーーーーーーーーーーー
            $userFlg = $this->auth->guard()->user(); 
            $user = \App\Models\User::where('担当者CD', $userFlg->担当者CD)->first();
            if (!$user) {
                HDLog::create([
                    '担当者CD' => $credentials['担当者CD'],
                    'ログ種別' => 0,
                    '実行内容' => '所属部門取得失敗',
                    'SQL種別' => 0,
                    'エラー' => 0,
                ]);

                return new JsonResponse([
                    'message' => '所属部門CDの取得に失敗しました。',
                    'status' => 400,
                ]);
            }
            $request->session()->put('所属部門CD', $user->所属部門CD);

            // Log::info('ログイン時:所属部門CD: '.session('所属部門CD'));//確認用
            //ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
    
            // ロックカウントを0にリセットして保存
            $user = $this->auth->guard()->user();
            $user->ロックカウント = 0;
            $user->save();

            $queries = DB::getQueryLog();
            $lastQuery = end($queries);
        
            $sqlquery = $lastQuery['query'] ?? null;
            $bindings = $lastQuery['bindings'] ?? [];
        
            if ($sqlquery) {
                foreach ($bindings as $binding) {
                    $sqlquery = preg_replace(
                        '/\?/',
                        is_numeric($binding) ? (string) $binding : "'" . $binding . "'",
                        $sqlquery,
                        1
                    );
                    
                }
                
                HDLog::create([
                    '担当者CD' => $userId,
                    'ログ種別' => 0,
                    '実行内容' => 'ログイン成功',
                    'SQL種別' => 1,
                    'エラー' => 0,
                    'SQL文' => $sqlquery,
                ]);
            }
    
            return new JsonResponse([
                'message' => 'Authenticated.',
                'status' => 200,
                '担当者CD' => $userId,
            ]);
        } elseif ($user) {
            // パスワードが不一致の場合にロックカウントをインクリメント
            $user->increment('ロックカウント');
            
            $logMessage = $user->ロックカウント >= 5 ? 'アカウントロック' : 'ログイン失敗';

            HDLog::create([
                '担当者CD' => $user->担当者CD,
                'ログ種別' => 0,
                '実行内容' => $logMessage,
                'SQL種別' => 1,
                'エラー' => 0,
                'SQL文' => 'N/A',
            ]);

            // ロックカウントが4の場合に443エラーを返す
            if ($user->ロックカウント === 5) {
                return new JsonResponse([
                    'message' => 'Warning: Account lock imminent.',
                    'status' => 443,
                ]);
            }

            return new JsonResponse([
                'message' => 'Password incorrect. Lock count increased.',
                'status' => 400,
            ]);
        }
    
        return new JsonResponse([
            'message' => 'Unauthenticated.',
            'status' => 400,
        ]);
    }
}
