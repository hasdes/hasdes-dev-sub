<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    private AuthManager $auth; // プロパティを正しく定義

    /**
     * @param AuthManager $auth
     */
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth; // コンストラクタでプロパティに代入
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        // ログアウト処理
        $this->auth->guard()->logout();

        // 現在のセッションを無効化
        $request->session()->invalidate();

        // セッションデータの完全削除（キャッシュストアからも削除）
        $request->session()->flush();

        // セッションIDを再生成（CSRFトークンも含む）
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
