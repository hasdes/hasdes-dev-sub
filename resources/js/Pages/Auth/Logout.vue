<script setup>
import { onMounted } from 'vue'
import axios from 'axios'
import { useLoginState } from '../../assets/LoginState.js'
import { useRouter } from 'vue-router'

const st = useLoginState();
const router = useRouter();

const logout = async () => {
    try {
        // CSRFトークンを取得
        await axios.get('/sanctum/csrf-cookie');
        
        const res = await axios.post('/logout');
        console.log('logout response:', res.data.message);
        
        // sessionStorage をクリア
        sessionStorage.clear();

        //追加5/30(Dispatch/List.vueの月のローカル保持削除)
        localStorage.removeItem('dispatchSelectedMonth');

        // 必要に応じて localStorage もクリア
        localStorage.clear();

        // ログイン状態をリセット
        st.setLogout();

        // ログインページにリダイレクト
        router.push({ name: 'auth.elogin' });
    } catch (err) {
        console.log('logout error:', err);
    }
}

onMounted(() => {
    logout();
});
</script>

<template></template>
