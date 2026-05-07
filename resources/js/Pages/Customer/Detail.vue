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
    axios.get('/api/customer/detail', {
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
          <li><router-link to="/customer">顧客企業マスタメンテ</router-link></li>
          <li>顧客企業情報詳細</li>
        </ol>
        <div class="col-lg-12">
          <div class="card ma_btm_bm">
              <div class="contents_head">
                <h5 class="card-title">顧客企業情報詳細</h5>
              </div>
                <div class="row space align-center justify-content-between page c space_d">    
                  <div class="col-md-6">
                    <label class="col-form-label">官庁区分</label>   
                    <span>{{ item?.官庁区分 !== undefined ? item.官庁区分 : '' }}</span>                 
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書パターン</label>   
                    <span>{{ item?.納品書パターン	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">得意先CD</label>   
                    <span>{{ item?.得意先CD || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">締日</label>   
                    <span>{{ item?.締日 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">得意先名(カナ)</label>   
                    <span>{{ item?.カナ || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">支払月</label>   
                    <span>{{ item?.支払月 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">得意先名</label>   
                    <span>{{ item?.得意先名 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                  <label class="col-form-label">支払日</label>   
                  <span>{{ item?.支払日 || '' }}</span>                     
                </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">得意先支店名</label>   
                    <span>{{ item?.得意先支店名 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">HASグループCD</label>   
                    <span>{{ item?.グループCD || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">得意先名(略)</label>   
                    <span>{{ item?.得意先略名	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">HASグループ名</label>   
                    <span>{{ item?.グループ名 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">郵便番号</label>   
                    <span>{{ item?.郵便番号 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">グループ都道府県</label>   
                    <span>{{ item?.グループ_都道府県 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">住所１</label>   
                    <span>{{ item?.住所1 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">請求書 郵便番号</label>   
                    <span>{{ item?.請求書_郵便番号	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">住所２</label>   
                    <span>{{ item?.住所２ || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">請求書 住所１</label>   
                    <span>{{ item?.請求書_住所1	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">電話番号</label>   
                    <span>{{ item?.電話番号	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">請求書 住所２</label>   
                    <span>{{ item?.請求書_住所2 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">FAX番号</label>   
                    <span>{{ item?.FAX番号 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">請求書 名称</label>   
                    <span>{{ item?.請求書_名称 || '' }}</span> 	                   
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書並べ替え順</label>   
                    <span>{{ item?.納品書並べ替え順 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">請求書 支店名</label>   
                    <span>{{ item?.請求書_支店名 || '' }}</span>                     
                  </div>                 
                  <div class="col-md-6">
                    <label class="col-form-label">出荷案内所並べ替え順</label>   
                    <span>{{ item?.出荷案内所並べ替え順 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書送 郵便番号</label>   
                    <span>{{ item?.納品書送り先_郵便番号 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">URL</label>   
                    <span>{{ item?.URL || '' }}</span>    
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書送 住所１</label>   
                    <span>{{ item?.納品書送り先_住所1	 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">営業担当者CD</label>   
                    <span>{{ item?.営業担当者CD || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書送 住所２</label>   
                    <span>{{ item?.納品書送り先_住所12 || '' }}</span>                     
                  </div>
                  <div class="col-md-6">
                    <label class="col-form-label">請求書先区分</label>   
                    <span>{{ item?.請求先区分 || '' }}</span>
                  </div>
                  <div class="col-md-6">
                    <label class="col-form-label">納品書送 名称</label>   
                    <span>{{ item?.納品書送り先_名称 || '' }}</span>                     
                  </div>
                  <div class="col-md-6">
                    <label class="col-form-label">請求書先CD</label>   
                    <span>{{ item?.請求書先CD || '' }}</span>                     
                  </div>
                  <div class="col-md-6">
                    <label class="col-form-label">納品書送 支店名</label>   
                    <span>{{ item?.納品書送り先_支店名 || '' }}</span>                     
                  </div>
                  <div class="col-md-6">
                    <label class="col-form-label">入金先区分</label>   
                    <span>{{ item?.入金先区分 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書 電話番号</label>   
                    <span>{{ item?.納品書送り先_電話番号 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">入金先CD</label>   
                    <span>{{ item?.入金先CD || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">納品書 FAX番号</label>   
                    <span>{{ item?.納品書送り先_FAX番号 || '' }}</span>
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">価格設定重量</label>   
                    <span>{{ item?.価格設定重量 || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">増値GF設定A</label>   
                    <span>{{ item?.増値_GF設定A || '' }}</span>                     
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">増値RF設定A</label>   
                    <span>{{ item?.増値_RF設定A || '' }}</span>                
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">増値GF設定B</label>   
                    <span>{{ item?.増値_GF設定B || '' }}</span>                
                  </div> 
                  <div class="col-md-6">
                    <label class="col-form-label">増値RF設定B</label>   
                    <span>{{ item?.増値_RF設定B || '' }}</span>                 
                  </div> 
                  <div class="col-md-12">
                    <label class="col-form-label">送り状通知メールアドレス</label>   
                    <span>{{ item?.カナ || '' }}</span>                     
                  </div> 
                  <div class="col-md-12">
                    <label class="col-form-label">受検証明書メールアドレス</label>   
                    <span>{{ item?.カナ || '' }}</span>                     
                  </div> 
                  <div class="col-md-12">
                    <label class="col-form-label">下水検査証メールアドレス</label>   
                    <span>{{ item?.カナ || '' }}</span>                     
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
