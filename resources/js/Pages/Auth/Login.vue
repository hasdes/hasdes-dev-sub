<script setup>
// import { ref } from 'vue';
// import axios from 'axios';
// import { useRouter } from 'vue-router';
// import { useLoginState } from '../../assets/LoginState.js';
// import { ElNotification } from 'element-plus';

// // 担当者CD と PASSWORD 用の変数を宣言
// const 担当者CD = ref('');
// const PASSWORD = ref('');
// const st = useLoginState();
// const router = useRouter();
// const dialogVisible = ref(true);

// const login = async () => {
//     try {
//         await axios.get('/sanctum/csrf-cookie');
//         // 担当者CD と PASSWORD を送信
//         const res = await axios.post('/login', {
//             担当者CD: 担当者CD.value,
//             PASSWORD: PASSWORD.value,
//         });

//         console.log('login response:', res.data.message);
//         console.log('login status:', res.data.status);

//         if (res.data.status === 200) {
//             st.setLogin();
//             router.push('/home');
//             dialogVisible.value = false;
//         } else {
//             ElNotification({
//                 title: 'Error',
//                 message: 'ログイン失敗しました',
//                 type: 'error',
//             });
//         }
//     } catch (error) {
//         console.log('login error:', error);
//         ElNotification({
//             title: 'Error',
//             message: 'ログイン中にエラーが発生しました。後でもう一度お試しください。',
//             type: 'error',
//         });
//     }
// };
</script>

<template>
  <main>
    <div class="content log_back_sp">
      <section class="register over_h">
          <div class="row">
            <div class="col-lg-6 log_back column_log frame_p sp_lg log_text">
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
                    <h3 class="text-center log_caption">顧客従業員ログイン</h3>
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
                      <button type="submit" class="button_r submit" @click.prevent="">ログイン</button>
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
