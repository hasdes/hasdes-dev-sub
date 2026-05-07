<script setup>
import { ref } from 'vue';
import axios from 'axios';
// import { useRouter } from 'vue-router';
import { useRouter, useRoute } from 'vue-router'; // ← 追加
import { useLoginState } from '../../assets/LoginState.js';
import { ElNotification } from 'element-plus';

// 担当者CD と PASSWORD 用の変数を宣言
const 担当者CD = ref('');
const PASSWORD = ref('');
const st = useLoginState();
const router = useRouter();
const route = useRoute(); // ← 追加
const dialogVisible = ref(true);

const validateForm = () => {
    if (!担当者CD.value) {
        ElNotification({
            title: 'Validation Error',
            message: 'ログイン IDを入力してください。',
            type: 'warning',
        });
        return false;
    } else if (!PASSWORD.value) {
        ElNotification({
            title: 'Validation Error',
            message: 'パスワードを入力してください。',
            type: 'warning',
        });
        return false;
    }
    return true;
};

const login = async () => {
    if (!validateForm()) return;

    try {
        // ★ CSRF発行
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

        // await axios.get('/sanctum/csrf-cookie');
        const res = await axios.post('/login', {
            担当者CD: 担当者CD.value,
            PASSWORD: PASSWORD.value,
        });

        if (res.data.status === 200) {

          //ホーム権限
          const res2 = await axios.post('api/auth/getHomePermission', {
              担当者CD: 担当者CD.value
          });

            st.setLogin();

            //2026/03 修正　ホームの権限に応じてリダイレクト先を変更
            const hasHomePermission = res2.data.ホーム === 0;
            const initialPage = hasHomePermission ? '/home' : '/pickup';

            // router.push('/home');//非表示
            //以下に差し替え
            // const redirectTo = route.query.redirect || '/home';
            const redirectTo = route.query.redirect || initialPage; //リダイレクトがあればそのページへ、なければ指定デフォルトページへ
            router.push(redirectTo);//対象ページにリダイレクト

            dialogVisible.value = false;
        } else if (res.data.status === 403) {
            ElNotification({
                title: 'Account Locked',
                message: 'アカウントがロックされています。',
                type: 'error',
            });
        } else if (res.data.status === 443) {
            ElNotification({
                title: 'Account Locked',
                message: 'アカウントがロックされました。',
                type: 'error',
            });
        } else {
            ElNotification({
                title: 'Error',
                message: 'ログイン失敗しました',
                type: 'error',
            });
        }
    } catch (error) {
        console.error('login error:', error);
        if (error.response && error.response.data && error.response.data.message) {
            ElNotification({
                title: 'Error',
                message: error.response.data.message,
                type: 'error',
            });
        } else {
            ElNotification({
                title: 'Error',
                message: 'ログイン中にエラーが発生しました。後でもう一度お試しください。',
                type: 'error',
            });
        }
    }
};
</script>


<template>
  <main>
    <div class="content log_back_sp e">
      <section class="register over_h">
          <div class="row">
            <div class="col-lg-6 log_back e column_log frame_p sp_lg log_text">
              <div class="login_title">
                <a href="" class="logo log">
                  <img src="https://hasdes.com/img/logo_w.png" alt="HASDES">
                </a>
                <h1>HAS Data Enhancement System</h1>
              </div>
            </div>
            <div class="col-lg-6 logform">
              <div class="pc_lg">
                <div class="login_title">
                  <a href="" class="logo log">
                    <img src="https://hasdes.com/img/logo_w.png" alt="HASDES">
                  </a>
                  <h1>HAS Data Enhancement System</h1>
                </div>
              </div>
              <div class="re">
                <div class="card-body form_w">
                  <div class="pt-4 pb-2">
                    <h3 class="text-center log_caption">従業員ログイン</h3>
                  </div>

                  <form class="row g-3 needs-validation log" action="">
                    <div class="col-12">
                      <p for="email" class="form-label">ログインID</p>
                      <div class="input-group">
                        <input type="text" class="form-control form_log" v-model="担当者CD" required/>
                      </div>
                    </div>
                    <div class="col-12">
                      <p for="password" class="form-label">パスワード</p>
                      <input type="password" class="form-control form_log" v-model="PASSWORD" />
                      <div class="invalid-feedback">パスワードを入力してください</div>
                    </div>
                    <div class="col-12">
                      <button type="submit" class="button_r submit" @click.prevent="login">ログイン</button>
                    </div>                  
                  </form>

                </div>
              </div>
            </div>
          </div>
      </section>

    </div>
  </main>
</template>
