<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'; // ← 追加
const router = useRouter(); // ← ここで router を取得


defineProps({
  authItems: Array
})

const loadingActive = ref(false); // ローディング状態を管理する変数

const goToList = () => {
  router.push({ path: '/leadtime/'})
}

const goToAdd = () => {
  router.push({ path: '/leadtime/add'})
}
</script>


<template>
<section class="section dashboard">
  <!-- ローディング画面 -->
  <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div>
  <div class="d-flex justify-content-between align-items-center">
    <ol class="breadcrumb mb-0">
      <li v-if="authItems?.[0]?.ホーム == 0">
        <router-link to="/home">ホーム</router-link>
      </li>
      <li>情報表示</li>
      <li>
        <router-link to="/leadtime">目安納期</router-link>
      </li>
      <li>工程日数一括変更</li>
    </ol>
    <button type="submit" class="button_r back none od_b" @click.prevent="goToList()">
      閉じる
    </button>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">工程日数一括変更</h5>
      </div>          
      <table class="table_w tablesorter none sp_w detail_table process"> 
        <thead>
          <tr class="head">
            <th class="narrow_d">工程名</th>
            <th class="narrow_b">現在設定日数</th>
            <th class="narrow_b">変更後日数</th>                      
          </tr>
        </thead>
        <tbody>
          <tr>
            <th class="item_f">GS造形</th>
            <td data-label="現在設定日数">15</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">NB造形</th>
            <td data-label="現在設定日数">20</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">LG造形</th>
            <td data-label="現在設定日数">20</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">外注造形</th>
            <td data-label="現在設定日数">20</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">ショット＆研掃</th>
            <td data-label="現在設定日数">2</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">素管検査</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">修正</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">水圧</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">加工1</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">加工2</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">グリッド原管</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">溶射</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">内外塗装</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">組付完成検査</th>
            <td data-label="現在設定日数">1</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">移送 本社-九州工場</th>
            <td data-label="現在設定日数">7</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">移送 本社-東北工場</th>
            <td data-label="現在設定日数">8</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">移送 九州工場-東北工場</th>
            <td data-label="現在設定日数">8</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">ｾﾞﾛ素材納期　繁忙加算</th>
            <td data-label="現在設定日数">10</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">予備項目1</th>
            <td data-label="現在設定日数">0</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">予備項目2</th>
            <td data-label="現在設定日数">0</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
          <tr>
            <th class="item_f">予備項目3</th>
            <td data-label="現在設定日数">0</td>
            <td data-label="変更後日数"><input type="text" class="form-control normal"></td>                                    
          </tr>
        </tbody>                             
      </table>
      <div class="col-sp-12 btn_center ma_top_a line_up flex-end space_m">
        <button type="submit" class="button_r none search od_a to max">変更</button>    
      </div>     
    </div>
  </div>

  <div class="col-sp-12 btn_center ma_top_a line_up flex-start">
      <button type="submit" class="button_r none search cont max" @click.prevent="goToAdd()">インポート</button>    
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
/* .close-btn2 {
  position: absolute;
  top: -50px;
  right:5px;
} */
.flex-end {
  justify-content: flex-end;
}
.flex-start {
  justify-content: flex-start;
}
@media (max-width: 768px) {
  .item_f {
      display: flex;
      align-items: center;
  }
  .button_r.max {
      width: 100%;
  }
  .btn_center {
    flex-direction: unset;
  }
}

/* テーブル */
.process tbody tr {
  border-bottom: 1px solid #C9D4E6;
}
.process tbody th {
  font-weight: bold;
}
.process tbody td input {
  margin: 3px 0;  
  width:50%;
}
</style>
