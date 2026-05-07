<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useRouter } from 'vue-router';
import { ElNotification } from 'element-plus';
import axios from 'axios';

defineProps({
  authItems: Array
})

const item = ref({変換名: ''});
// const loadingActive = ref(false); // ローディング状態を管理する変数
const route = useRoute();
const mKey = route.query.key;//M出荷先_ID
const dKey = route.query.d_key;//HD変換出荷先_ID
const router = useRouter(); // Vue Router を使って遷移を管理

// console.log('Dキー：', dKey);
// console.log('Mキー：', mKey);

// 出荷先名を取得する関数
const reLoadItem = () => {
  // loadingActive.value = true;

  if (dKey) {
    axios.get('/api/convershipping/detail', {
        params: { key: dKey }  // 取得したkeyをAPIに渡す
      })
      .then((res) => {
        // console.log('APIレスポンス:', res);
        item.value = res.data.data || null; // データが無い場合はnull
          // console.log('itemの中身：',item.value);
      })
      .catch((error) => {
        console.error(error);
      })
      // .finally(() => {
        // loadingActive.value = false; // ローディング状態を解除
      // });
  } else {
    console.error('URLにkeyパラメータがありません。');
  }
};

// 初回読み込み
onMounted(() => {
  reLoadItem();
});

//更新
const changeshipping = () => {
    if (!item.value.変換名 || item.value.変換名.trim() === '') {
      ElNotification({
        title: 'Error',
        message: '変換名を入力してください。',
        type: 'error',
      });
      return; // 更新処理中断
    }
    
    axios.put('/api/convershipping/edit', {
      'HD変換出荷先_ID': dKey,
      '変換名': item.value.変換名
    }).then((res) => {
        // console.log('convershipping change success');
        ElNotification({
            title: 'Success',
            message: '更新に成功しました',
            type: 'success',
        });

      setTimeout(() => {
        router.push({ 
          path: '/convershipping/detail', 
          query: { 
            key: mKey,
          }
        });
      }, 1000);

    }).
    catch((error) => {
      let errorMessage = '更新に失敗しました';

      if (error.response && error.response.data) {
        if (error.response.status === 409) {
          // 重複エラーはコンソールに出さない
          errorMessage = error.response.data.error;
        } else {
          // それ以外はコンソールに出す
          console.error('更新エラー:', error);
          errorMessage = error.response.data.error || error.response.data.message || errorMessage;
        }
      }

      ElNotification({
        title: 'Error',
        message: errorMessage,
        type: 'error',
      });
  });
};
</script>

<template>
  <section class="section dashboard">
    <!-- ローディング画面 -->
    <!-- <div v-if="loadingActive" class="loading-wrap">
      <span>読み込み中...</span>
    </div> -->
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>販売管理</li>
        <li><router-link to="/conver">OCR変換マスタ</router-link></li>
        <li><router-link to="/convershipping/">出荷先編集</router-link></li>
        <li><router-link :to="{ path: '/convershipping/detail', query: { key: mKey } }">出荷先変換</router-link></li>
        <li>出荷先変換</li>
        <li>出荷先変換名変更</li>
      </ol>

      <!-- 変換データ -->
      <div class="col-lg-12">
        <div class="card ma_btm_bm">
            <div class="contents_head">
              <h5 class="card-title">出荷先変換名変更</h5>
            </div>
            <form v-if="item">
              <div class="row space align-center justify-content-between page group contents space_d">                  
                <div class="col-lg-12">
                  <label class="col-form-label">出荷先変換名</label>   
                  <input type="text" class="form-control normal" v-model="item.変換名">                  
                </div>
                <div class="col-sp-12 btn_center ma_top_a line_up center">
                  <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a>
                  <button type="submit" @click.prevent="changeshipping" class="button_r none search od_a to">保存</button>        
                </div>
              </div>              
            </form>           
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
</style>