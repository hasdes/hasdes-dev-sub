<script setup>
import { reactive, ref, onMounted } from 'vue'
import axios from 'axios';
import { ElNotification } from 'element-plus';

defineProps({
  authItems: Array
})

const form = reactive({
    id: null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const password = ref('');
const password_confirmation = ref('');
const password_current = ref('');

onMounted(() => {
  const logData = { 
    '実行内容': 'パスワード変更画面表示',
  };

  axios.post('/api/HDLog/create', logData)
    .then(() => {
      console.log('ログが正常に保存されました');
    })
    .catch((error) => {
      console.error('ログ保存中にエラーが発生しました', error);
    });
});

const changePassword = () => {
    axios.put('/api/auth/update', {
        'current_password': password_current.value,
        'password': password.value,
        'password_confirmation': password_confirmation.value
    }).then((res) => {
        console.log('password change success');
        ElNotification({
            title: 'Success',
            message: 'パスワードの更新に成功しました',
            type: 'success',
        });
    }).catch((error) => {
        console.log('error ' + error);
        ElNotification({
            title: 'Error',
            message: 'パスワードの更新に失敗しました',
            type: 'error',
        });
    });
};


</script>

<template>
  <section class="section dashboard">
    <form>
      <div class="row">
        <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
          <li><router-link to="/auth/list">ユーザー設定</router-link></li>
          <li>パスワード変更</li>
        </ol>
        <div class="col-lg-12">
          <div class="card">
            <div class="contents_head">
              <h5 class="card-title">パスワード変更</h5>
            </div>
            <div class="row space align-center justify-content-between page group">
              <div class="col-md-7">
                <label class="col-form-label">現在のパスワード</label>   
                <input type="password" class="form-control normal" v-model="password_current">                     
              </div> 
              <div class="col-md-7">
                <label class="col-form-label">新しいパスワード</label>   
                <input type="password" class="form-control normal" v-model="password">                    
              </div>    
              <div class="col-md-7">
                <label class="col-form-label">新しいパスワード(確認)</label>   
                <input type="password" class="form-control normal" v-model="password_confirmation">                     
              </div> 
              <div class="col-md-7">
                *  英数字(a-z,A-Z,0-9)を含む12文字以上、16文字以下で設定してください。              
              </div>                                                                            
              <div class="col-lg-7 ma_top_a line_up flex_end btn_center center_a">
                <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
                <button @click.prevent="changePassword" class="button_r none search od_a syoki pass_btn" type="button">変更</button>
              </div>
            </div>              
          </div>
        </div>
      </div>
    </form>
  </section>
  </template>
  