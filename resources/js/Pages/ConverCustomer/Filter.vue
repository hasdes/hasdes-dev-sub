<script setup>
import { ref, onMounted } from 'vue';

const filterText = ref('');
const filterCoName = ref('');
const isLoading = ref(false);
const isDetailsOpen = ref(true); // アイコンの状態管理用
const emit = defineEmits(['search']);

// 現在のフィルタ情報をセッションストレージから取得
const savedFilters = JSON.parse(sessionStorage.getItem('convercustomerFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'convercustomerFilters') {
        sessionStorage.removeItem(key);
    }
});

// 初回ロード時にフィルター情報を入力フィールドに反映
onMounted(() => {
  if (savedFilters.filter) {
    filterText.value = savedFilters.filter;
  }
  if (savedFilters.coname) {
    filterCoName.value = savedFilters.coname;
  }
  // 初期状態でdetailsが開いているか確認し、アイコン状態を設定
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});

// 検索処理
const searchStaff = () => {
  const params = {
    filter: filterText.value || '',
    coname: filterCoName.value || '',
  };

  emit('search', params);
};

// detailsの開閉状態に応じてアイコンを切り替える
const toggleIcon = (event) => {
  isDetailsOpen.value = event.target.open;
};
</script>

<!-- <template>
<div class="col-lg-12">
  <div class="card">
    <details class="contents_head" open>
      <summary class="send_f">             
        <h5 class="card-title">検索条件</h5>
        <i class="bi bi-arrow-up"></i>
      </summary>
      <form action="">
        <div class="search_send bo_none">
          <div class="row align-center two space_c">
            <div class="col-lg-3 form_r flex">
            <label for="inputEmail" class="col-form-label label_wm">得意先CD</label>                
            <input type="text" class="form-control normal alpha-numeric-symbol-input" id="inputField" required placeholder="半角英数字記号">                 
            </div>
            <div class="col-lg-3 form_r flex">
              <label for="inputEmail" class="col-form-label label_wm">得意先名</label>                
              <input type="text" class="form-control normal">                    
            </div>                   
            <div class="col-lg-1 btn_center center_a">
              <button type="submit" class="button_r none search">検索</button>        
            </div>
          </div>             
        </div>
      </form>        
  </details>  
  </div>
</div>
</template> -->

<template>
  <div class="col-lg-12">
    <div class="card">
      <details class="contents_head" open @toggle="toggleIcon">
        <summary class="send_f">             
          <h5 class="card-title">検索条件</h5>
          <i :class="isDetailsOpen ? 'bi bi-arrow-up' : 'bi bi-arrow-down'"></i>
        </summary>
        <form action="">
          <div class="search_send bo_none">
            <div class="row align-center two space_c">
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">得意先CD</label>                
                <input type="text" v-model="filterText" class="form-control normal" placeholder="半角英数字記号">   
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">得意先名(略)</label>                
                <input type="text" v-model="filterCoName" class="form-control normal">   
              </div>
              <div class="col-lg-1 btn_center center_a">
                <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
              </div>
            </div>             
          </div>
        </form>        
      </details>  
    </div>
  </div>
</template>
