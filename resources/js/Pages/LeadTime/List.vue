<script setup>
import { onMounted, ref } from 'vue';

defineProps({
  authItems: Array
})

const loadingActive = ref(false); // ローディング状態を管理する変数
const showResult = ref(false);
const searchParams = ref({});

const handleSearch = (params) => {
  searchParams.value = params; // 条件受け取る
  showResult.value = true;     // 結果表示
};


import Filter from '@/Pages/LeadTime/Filter.vue';
</script>


<template>
<section class="section dashboard">
  <!-- ローディング画面 -->
  <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div>
  <ol class="breadcrumb">
    <li v-if="authItems?.[0]?.ホーム == 0">
      <router-link to="/home">ホーム</router-link>
    </li>
    <li>情報表示</li>
    <li>目安納期</li>
  </ol>
  <!-- 検索 -->
  <Filter @search="handleSearch"></Filter>

  <!-- 目安納期 -->
  <div class="col-sp-12 btn_center ma_top_a clear l_space">
    <button type="button" class="l_btn button_r back none od_b" @click="showResult = false" v-if="showResult">結果をクリア</button>  
  </div>   

  <!-- 抽出結果 -->
  <div class="col-lg-12">
    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">抽出結果</h5>
      </div>
      <div class="scroll-box s scroll-box_y c">
        <table class="table_w tablesorter alter res" id="table_sort_a">
          <thead>
            <tr class="head">
              <th class="narrow_j">商品CD</th>
              <th class="narrow_j">呼び径1</th>
              <th class="narrow_j">呼び径2</th>
              <th class="narrow_j">呼び径3</th>
              <th class="narrow_j">必要数量</th>
              <th class="narrow_j">目安納期</th>
            </tr>
          </thead>
          <tbody v-if="!showResult">
            <tr>
              <td colspan="6">データがありません。</td>
            </tr>
          </tbody>
          <tbody v-if="showResult">
            <tr class="space align-center page group contents space_d">
              <td data-label="商品CD">
                <input class="form-control normal item_cd" value="GGTE 000" disabled>                  
              </td>
              <td data-label="呼び径1">                
                <input class="form-control normal yobi" value="100" disabled>                  
              </td>
              <td data-label="呼び径2">
                <input class="form-control normal yobi" value="75" disabled>                  
              </td>
              <td data-label="呼び径3">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td data-label="必要数量">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td></td>
            </tr>
            <tr class="space align-center page group contents space_d">
              <td data-label="商品CD">
                <input class="form-control normal item_cd" value="GGTE 000" disabled>                  
              </td>
              <td data-label="呼び径1">                
                <input class="form-control normal yobi" value="100" disabled>                  
              </td>
              <td data-label="呼び径2">
                <input class="form-control normal yobi" value="75" disabled>                  
              </td>
              <td data-label="呼び径3">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td data-label="必要数量">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td></td>
            </tr>
            <tr class="space align-center page group contents space_d">
              <td data-label="商品CD">
                <input class="form-control normal item_cd" value="GGTE 000" disabled>                  
              </td>
              <td data-label="呼び径1">                
                <input class="form-control normal yobi" value="100" disabled>                  
              </td>
              <td data-label="呼び径2">
                <input class="form-control normal yobi" value="75" disabled>                  
              </td>
              <td data-label="呼び径3">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td data-label="必要数量">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td></td>
            </tr>
            <tr class="space align-center page group contents space_d">
              <td data-label="商品CD">
                <input class="form-control normal item_cd" value="GGTE 000" disabled>                  
              </td>
              <td data-label="呼び径1">                
                <input class="form-control normal yobi" value="100" disabled>                  
              </td>
              <td data-label="呼び径2">
                <input class="form-control normal yobi" value="75" disabled>                  
              </td>
              <td data-label="呼び径3">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td data-label="必要数量">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td></td>
            </tr>
            <tr class="space align-center page group contents space_d">
              <td data-label="商品CD">
                <input class="form-control normal item_cd" value="GGTE 000" disabled>                  
              </td>
              <td data-label="呼び径1">                
                <input class="form-control normal yobi" value="100" disabled>                  
              </td>
              <td data-label="呼び径2">
                <input class="form-control normal yobi" value="75" disabled>                  
              </td>
              <td data-label="呼び径3">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td data-label="必要数量">
                <input class="form-control normal yobi" value="" disabled>                  
              </td>
              <td></td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  
</section>
</template>

<style scoped>
.loading-wrap {
  position: fixed;
  top: 50%;
  left: 50%;
  width: 15vw;
  height: 15vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255, 255, 255, 0.5);
  z-index: 2;
  font-size: 1.5em;
  transform: translate(-50%, -50%);
}

/* 結果クリアボタン */
.btn_center.clear {
  justify-content: flex-end; 
  margin:50px 0 10px 0;
}
/* 抽出結果 */
.res th {
  height: 26.8px;
}
.res td {
  height: 43.5px;
  padding: 0 12px;
}
.item_cd, .yobi {
  background-color: unset;
}
.form-control.item_cd {
  width:8.5rem; 
  margin: 3px 0;
}
.form-control.yobi {
  width:3.5rem; 
  margin: 3px 0;
}
/* 結果クリアボタン */
@media (min-width: 768px) {
  .l_space {
    flex-wrap: wrap;
    justify-content: space-between;
  }
  .l_btn {
    flex: 0 1 30%;  /* 3つ並び */
    max-width: 15%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
@media (max-width: 767px) {
  .l_btn {
    width: auto; 
  }
}
</style>

