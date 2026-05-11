<script setup>
// import { ref,onMounted,watch } from 'vue';
import { useRouter } from 'vue-router'; // ← 追加
const router = useRouter(); // ← ここで router を取得




const emit = defineEmits(['search']);

const search = () => {
  const params = {
    factory: '',   // 必要なら値入れる
    year: '',
  };

  sessionStorage.setItem('itemFilters', JSON.stringify(params));

  emit('search', params);
};

// 工程日数マスタ
const goToEdit = () => {
  router.push({ path: '/leadtime/edit' });
};

// 工程日数一括変更
const goToUpdate = () => {
  router.push({ path: '/leadtime/update' });
};

</script>

<template>
  <div class="col-lg-12">
    <div class="card">
      <details class="contents_head" open @toggle="toggleIcon">
        <summary class="send_f">             
          <h5 class="card-title">検索条件</h5>
          <i :class="isDetailsOpen ? 'bi bi-arrow-up' : 'bi bi-arrow-down'"></i>
        </summary>
        <form action="">
          <div class="search_send send_f bo_none">
            <div class="row align-center two space_b space_d">
              <div class="col-lg-1 cd form_r">
                <label for="" class="col-form-label">担当工場</label>                
              </div>
              <div class="col-lg-1 cate form_r">
                <select class="form-select normal">
                  <option style="color:#050B15;" value="">1:本社</option>
                  <option style="color:#050B15;" value="1">2:九工</option>
                  <option style="color:#050B15;" value="2">3:東工</option>
                </select>                 
              </div>
              <div class="col-lg-1 sto">
                <label for="inputField" class="col-form-label">対象年</label>                
              </div>
              <div class="col-lg-3 form_r d-flex align-items-center">
                  <label>2024~2026</label>
              </div>
            </div>  
            
            
          </div>
        </form> 
        
        <div class="search_send le">
          <!-- <table class="table_w tablesorter alter leadtime" id="leadtime"> -->
          <table class="table_w tablesorter leadtime" id="leadtime">
            <thead>
              <tr class="head">
                <th class="narrow_b">商品CD</th>
                <!-- <th class="narrow_j">呼び径1</th>
                <th class="narrow_j">呼び径2</th>
                <th class="narrow_j">呼び径3</th>
                <th class="narrow_j">必要数量</th> -->
                <th class="input_yobi">呼び径1</th>
                <th class="input_yobi">呼び径2</th>
                <th class="input_yobi">呼び径3</th>
                <th class="input_yobi">必要数量</th>
                <!-- <th class="narrow_m" style=""></th> -->
                <th class="input_num" style=""></th>
              </tr>
            </thead> 
            <tbody>
              <tr class="space align-center page group contents space_d">
                <td data-label="商品CD">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item item_cd" value="GGTE 000">   
                  </div>
                </td>
                <td data-label="呼び径1">  
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="100"><span>×</span>                  
                  </div>              
                </td>
                <td data-label="呼び径2">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="75"> <span>×</span>                 
                  </div>   
                </td>
                <td data-label="呼び径3">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value=""> <span></span>                 
                  </div>   
                </td>
                <td data-label="必要数量">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="1000">   
                  </div>              
                </td>
                <td></td>
              </tr>
              <tr class="space align-center page group contents space_d">
                <td data-label="商品CD">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item item_cd" value="GGTE 000">   
                  </div>
                </td>
                <td data-label="呼び径1">  
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="100"><span>×</span>                  
                  </div>              
                </td>
                <td data-label="呼び径2">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="75"> <span>×</span>                 
                  </div>   
                </td>
                <td data-label="呼び径3">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value=""> <span></span>                 
                  </div>   
                </td>
                <td data-label="必要数量">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="1000">   
                  </div>              
                </td>
                <td></td>
              </tr>
              <tr class="space align-center page group contents space_d">
                <td data-label="商品CD">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item item_cd" value="GGTE 000">   
                  </div>
                </td>
                <td data-label="呼び径1">  
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="100"><span>×</span>                  
                  </div>              
                </td>
                <td data-label="呼び径2">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="75"> <span>×</span>                 
                  </div>   
                </td>
                <td data-label="呼び径3">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value=""> <span></span>                 
                  </div>   
                </td>
                <td data-label="必要数量">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="1000">   
                  </div>              
                </td>
                <td></td>
              </tr>
              <tr class="space align-center page group contents space_d">
                <td data-label="商品CD">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item item_cd" value="GGTE 000">   
                  </div>
                </td>
                <td data-label="呼び径1">  
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="100"><span>×</span>                  
                  </div>              
                </td>
                <td data-label="呼び径2">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="75"> <span>×</span>                 
                  </div>   
                </td>
                <td data-label="呼び径3">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value=""> <span></span>                 
                  </div>   
                </td>
                <td data-label="必要数量">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="1000">   
                  </div>              
                </td>
                <td></td>
              </tr>
              <tr class="space align-center page group contents space_d">
                <td data-label="商品CD">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item item_cd" value="GGTE 000">   
                  </div>
                </td>
                <td data-label="呼び径1">  
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="100"><span>×</span>                  
                  </div>              
                </td>
                <td data-label="呼び径2">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="75"> <span>×</span>                 
                  </div>   
                </td>
                <td data-label="呼び径3">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value=""> <span></span>                 
                  </div>   
                </td>
                <td data-label="必要数量">
                  <div class="yobi_wrap">
                    <input type="text" class="form-control normal input_item yobi" value="1000">   
                  </div>              
                </td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>

      <!-- ボタン -->
        <div class="search_send">
          <div class="col-sp-12 btn_center ma_top_a center l_space">
            <button type="button" class="l_btn button_r none search" @click="search">抽出</button>  
            <button type="button" class="l_btn button_r none search cont m" @click.prevent="goToEdit()">工程日数マスター</button>  
            <button type="button" class="l_btn button_r none search preview_btn c" @click.prevent="goToUpdate()">工程日数一括変更</button>  
          </div>   
        </div>

      </details>  

    </div>
  </div> 
</template>

<style scoped>
  /* テーブル全体 */
  .leadtime {
    border-collapse: collapse;
    /* width: 100%; */
    /* width: 40%; */
    border: 1px solid #C9D4E6;
  }
  /* ヘッダー行 */
  /* .btn_center thead tr.head th {
    letter-spacing: 0.03em;
  } */
  /* セルのパディング統一 */
  .leadtime tbody td {
    vertical-align: middle;
  }
  /* 入力欄をすっきりさせる */
  .leadtime .input_item:focus {
    border-color: #2D4A8A;
    outline: none;
    box-shadow: 0 0 0 2px rgba(45, 74, 138, 0.15);
  }
  .leadtime td {
    padding: 0 12px;
  }
  .form-control.item_cd {
    /* width:8.5rem;  */
    margin: 3px 0;
  }
  .form-control.yobi {
    /* width:3.5rem;  */
    margin: 3px 0;
  }
  .leadtime tbody tr {
    border-bottom: 1px solid #C9D4E6; 
    /* background:#fff; */
  }
  .search_send.le {
    border-bottom: unset;
  }
  .center.l_space {
    margin: 20px 30px;
  }

  @media (max-width: 767px) {
    .l_btn.button_r.cont {
      margin-bottom: 0!important;
      margin: 0 0px;
    }
    .l_btn {
      width: auto; 
    }
    .l_btn.preview_btn {
      margin-bottom: 0 !important;
    }
    .center.l_space {
      flex-direction: column; /* ←縦並び強制 */
      gap: 12px;
    }
  }
  @media (min-width: 768px) {
    .l_space {
      flex-wrap: wrap;
      justify-content: space-between;
    }
    .l_btn {
      flex: 0 1 30%;  /* 3つ並び */
      max-width: 20%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }
@media (min-width:1024px) {
  .l_btn {
    max-width: 15%;
  }
}

  .yobi_wrap {
    display: flex;
    align-items: center;
    /* gap: 6px; */
    gap: 16px;
  }

  .input_num {
    width: 350px;
  }
  .input_yobi {
    width: 100px;
  }
  /* .input_cd {
    width: 120px;
  } */
  @media (max-width: 1200px) {
    .input_num {
      width: 50px;
    }
    .input_yobi {
      width: 50px;
    }
    /* .input_cd {
      width: 60px;
    } */

  }

  @media (max-width: 768px) {
    .yobi_wrap span {
      display: none;
    }

  }

  
</style>

