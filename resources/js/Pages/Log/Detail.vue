<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

defineProps({
  authItems: Array
})

const item = ref(null); // 単一のデータを扱うため、変数名を `item` に変更

// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key'); // URLから 'key' パラメータを取得
};

// 顧客情報を取得する関数
const reLoadItem = () => {
  const key = getKeyFromUrl(); // URLから取得したkeyを使用
  if (key) {
    axios.get('/api/HDLog/detail', {
        params: { key: key }  // 取得したkeyをAPIに渡す
      })
      .then((res) => {
        item.value = res.data.data || null; // データが無い場合はnull
      })
      .catch((error) => {
        console.error(error);
      });
  } else {
    console.error('URLにkeyパラメータがありません。');
  }
};

onMounted(() => {
  reLoadItem();
});
</script>

<template>
   <section class="section dashboard">
      <div class="row">
        <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
          <li>システム管理</li>
          <li><router-link to="/log">ログ管理</router-link></li>
          <li>SQL全文表示</li>
        </ol>
        <div class="col-lg-12">
          <div class="card ma_btm_bm">
              <div class="contents_head">
                <h5 class="card-title">SQL全文表示</h5>
              </div>
                <div class="row space align-center justify-content-between page c space_d">    
                  <div class="col-md-12">
                    <span>{{ item?.SQL文 || 'データがありません' }}
                    </span>                     
                  </div>                 
                  <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                    <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
                  </div>
                </div>
          </div>
        </div>
      </div>
    </section>
</template>
