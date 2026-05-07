<script setup>
import { ref,onMounted,watch } from 'vue';

const filterText = ref('');
const filterName = ref('');
const filtrItemName = ref('');
const yobikei1 = ref('');
const yobikei2 = ref('');
const yobikei3 = ref('');
const radioOption = ref('1');
const isDetailsOpen = ref(true);
const isLoading = ref(false);
const emit = defineEmits(['search']); // イベント名を 'search' に変更
const savedFilters = JSON.parse(sessionStorage.getItem('stockFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'stockFilters') {
        sessionStorage.removeItem(key);
    }
});
onMounted(() => {
  if (savedFilters.filter) filterText.value = savedFilters.filter;
  if (savedFilters.name) filterName.value = savedFilters.name;
  if (savedFilters.itemname) filtrItemName.value = savedFilters.itemname;
  if (savedFilters.yobikei1) yobikei1.value = savedFilters.yobikei1;
  if (savedFilters.yobikei2) yobikei2.value = savedFilters.yobikei2;
  if (savedFilters.yobikei3) yobikei3.value = savedFilters.yobikei3;
  if (savedFilters.radioOption) radioOption.value = savedFilters.radioOption;
  
  // Set icon state for details open/close
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});


watch(filterName, (newVal) => {
  // 新しい値を大文字に変換し、再度フィルタ名に設定
  filterName.value = newVal.toUpperCase();
});

// 検索処理
const searchStaff = () => {
  // 検索条件を作成
  const params = {
    filter: filterText.value || '',
    name: filterName.value || '',
    itemname: filtrItemName.value || '',
    yobikei1: yobikei1.value || '',
    yobikei2: yobikei2.value || '',
    yobikei3: yobikei3.value || '',
    radioOption: radioOption.value || '',
  };

  sessionStorage.setItem('stockFilters', JSON.stringify(params));

  // 親コンポーネントに検索条件を送信
  emit('search', params);
};
const toggleIcon = (event) => {
  isDetailsOpen.value = event.target.open;
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
              <div class="col-lg-1 sto">
                <label for="" class="col-form-label">商品種別</label>                
              </div>
              <div class="col-lg-2 cate form_r">
                <select class="form-select normal" v-model="filterText">
                  <option style="color:#050B15;" value="">0:ALL</option>
                  <option style="color:#050B15;" value="1">1:異形管</option>
                  <option style="color:#050B15;" value="2">2:バルブ</option>
                  <option style="color:#050B15;" value="3">3:筺類</option>
                  <option style="color:#050B15;" value="4">4:接合部分</option>
                  <option style="color:#050B15;" value="5">5:その他</option>
                </select>                 
              </div>
              <div class="col-lg-1 sto">
                <label for="inputField" class="col-form-label">商品CD</label>                
              </div>
              <div class="col-lg-3 form_r ">
                <input type="text" class="form-control normal alpha-numeric-uppercase-input" id="inputField" v-model="filterName" required placeholder="半角大文字英数字記号">                 
              </div>
              <div class="col-lg-5 form_r radio_f">
                <fieldset class="radio_btn">
                  <label>
                    <input type="radio" name="radio_btn" value="1" v-model="radioOption" />
                    1:・・から始まる
                  </label>
                  <label>
                    <input type="radio" name="radio_btn" value="2" v-model="radioOption" />
                    2:・・を含む
                  </label>
                </fieldset>           
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="" class="col-form-label label_w">商品名</label>   
                <input type="text" class="form-control normal color_f" v-model="filtrItemName">           
              </div>
              <div class="col-lg-6 form_r flex kei align kake">
                <label for="" class="col-form-label label_wm">呼び径</label>                
                <input type="text" class="form-control normal alpha-numeric-symbol-input" id="inputField1" v-model="yobikei1" placeholder="半角英数字記号"> 
                <span>×</span>
                <input type="text" class="form-control normal alpha-numeric-symbol-input" id="inputField2" v-model="yobikei2" placeholder="半角英数字記号"> 
                <span>×</span>
                <input type="text" class="form-control normal alpha-numeric-symbol-input" id="inputField3" v-model="yobikei3" placeholder="半角英数字記号">            
              </div>
              <div class="col-lg-1 btn_center ma_btm_a center_a">
                <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
              </div>
            </div>          
          </div>
        </form>         
      </details>  
    </div>
  </div> 
</template>