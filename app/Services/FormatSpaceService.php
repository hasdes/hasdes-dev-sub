<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;//ログ出力

//===============================
// スペース作成
//===============================

class FormatSpaceService
{

    /**
     * 呼び径を右詰めで整形する。
     * 空の場合は指定桁数分の半角スペースを返す。
     */
    public function leftSpace(?string $value, int $len): string
    {

        try{
            // null or '' → 指定文字数ぶんの半角スペースを返す
            if ($value === null || $value === '') {
                return str_repeat(' ', $len);
            }

            // 半角に統一
            $value = mb_convert_kana($value, 'n');

            // 右詰め（左側にスペースを補充）
            return str_pad($value, $len, ' ', STR_PAD_LEFT);

            
        } catch (\Exception $e) {
            Log::error('FormatSpace ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

    }


    /**
     * 呼び径を左詰めで整形する。
     * 空の場合は指定桁数分の半角スペースを返す。
     */
    public function rightSpace(?string $value, int $len): string
    {

        try{
            // null or '' → 指定文字数ぶんの半角スペースを返す
            if ($value === null || $value === '') {
                return str_repeat(' ', $len);
            }

            // 半角に統一
            $value = mb_convert_kana($value, 'n');

            // 左詰め（右側にスペースを補充）
            return str_pad($value, $len, ' ', STR_PAD_RIGHT);

            
        } catch (\Exception $e) {
            Log::error('FormatSpace ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

    }


}