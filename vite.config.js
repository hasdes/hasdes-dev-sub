import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'; //追加

export default defineConfig({
    plugins: [
        vue(),  //既に追加済み
        laravel({
            input: ['resources/css/bootstrap.min.css','resources/css/style.css','resources/js/app.js'],
            refresh: true,
        }),
    ],
    define: {
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false, // これを追加
    },css: {
        devSourcemap: false, // ソースマップの無効化を追加
    },

});
