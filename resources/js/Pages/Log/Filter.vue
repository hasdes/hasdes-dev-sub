<script setup>
import { ref,onMounted } from 'vue';

const startdate = ref('');
const enddate = ref('');
const staffcd = ref('');
const staffname = ref('');
const logkind = ref('');
const execution = ref('');
const sqlkind = ref('');
const erorr = ref('');
const isDetailsOpen = ref(true);
const isLoading = ref(false);
const emit = defineEmits(['search']); // イベント名を 'search' に変更
const savedFilters = JSON.parse(sessionStorage.getItem('logFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'logFilters') {
        sessionStorage.removeItem(key);
    }
});
onMounted(() => {
  if (savedFilters.startdate !== undefined && savedFilters.startdate !== null) {
    startdate.value = savedFilters.startdate;
  }
  if (savedFilters.enddate !== undefined && savedFilters.enddate !== null) {
    enddate.value = savedFilters.enddate;
  }
  if (savedFilters.staffcd !== undefined && savedFilters.staffcd !== null) {
    staffcd.value = savedFilters.staffcd;
  }
  if (savedFilters.staffname !== undefined && savedFilters.staffname !== null) {
    staffname.value = savedFilters.staffname;
  }
  if (savedFilters.logkind !== undefined && savedFilters.logkind !== null) {
    logkind.value = savedFilters.logkind;
  }
  if (savedFilters.execution !== undefined && savedFilters.execution !== null) {
    execution.value = savedFilters.execution;
  }
  if (savedFilters.sqlkind !== undefined && savedFilters.sqlkind !== null) {
    sqlkind.value = savedFilters.sqlkind;
  }
  if (savedFilters.erorr !== undefined && savedFilters.erorr !== null) {
    erorr.value = savedFilters.erorr;
  }

  // Set icon state for details open/close
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});


// 検索処理
const searchStaff = () => {
  // 検索条件を作成
  const params = {
    startdate: startdate.value,
    enddate: enddate.value,
    staffcd: staffcd.value,
    staffname: staffname.value,
    logkind: logkind.value !== "" ? Number(logkind.value) : null,
    execution: execution.value,
    sqlkind: sqlkind.value !== "" ? Number(sqlkind.value) : null,
    erorr: erorr.value !== "" ? Number(erorr.value) : null,
  };

  console.log('検索条件:', params);
  sessionStorage.setItem('logFilters', JSON.stringify(params));
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
              <form @submit.prevent="searchStaff">
                <div class="search_send bo_none">
                  <div class="row align-center two space_c">
                    <div class="col-lg-4 form_r flex date kei align kake time"> 
                      <label for="" class="col-form-label label_w">日付</label>                
                      <input type="date" class="form-control normal d_input custom-width"  v-model="startdate"> 
                      <span>〜</span>  
                      <input type="date" class="form-control normal t_input custom-width" v-model="enddate">                     
                    </div>
                    <div class="col-lg-3 form_r flex">
                    <label for="" class="col-form-label label_wm">担当者CD</label>               
                    <input type="text" class="form-control normal alpha-numeric-symbol-input" id="inputField" required placeholder="半角英数字記号"v-model="staffcd">                  
                    </div>
                    <div class="col-lg-3 form_r flex">
                    <label for="" class="col-form-label label_w">担当者名</label>                
                      <input type="text" class="form-control normal"v-model="staffname">                    
                    </div>
                    <div class="col-lg-3 form_r flex">
                      <label for="" class="col-form-label label_wm">ログ種別</label>                
                      <select class="form-select normal"v-model="logkind">
                        <option style="color:#B1B1B1;" value="">選択</option>
                        <option style="color:#050B15;" value="">全て</option>
                        <option style="color:#050B15;" value="0">認証ログ</option>
                        <option style="color:#050B15;" value="1">イベントログ</option>
                        <option style="color:#050B15;" value="2">操作ログ</option>
                        <option style="color:#050B15;" value="3">変更・更新ログ</option>
                        <option style="color:#050B15;" value="4">新規追加</option>
                      </select>                 
                    </div>
                    <div class="col-lg-3 form_r flex">
                    <label for="" class="col-form-label label_w">実行内容</label>                
                      <input type="text" class="form-control normal"v-model="execution">                    
                    </div>
                    <div class="col-lg-3 form_r flex">
                      <label for="" class="col-form-label label_wm">SQL発行</label>                
                      <select class="form-select normal"v-model="sqlkind">
                        <option style="color:#B1B1B1;" value="">選択</option>
                        <option style="color:#050B15;" value="0">なし</option>
                        <option style="color:#050B15;" value="1">有り</option>
                      </select>                 
                    </div>
                    <div class="col-lg-3 form_r flex">
                      <label for="" class="col-form-label label_wm">エラー</label>                
                      <select class="form-select normal"v-model="erorr">
                        <option style="color:#B1B1B1;" value="">選択</option>
                        <option style="color:#050B15;" value="">全て</option>
                        <option style="color:#050B15;" value="0">なし</option>
                        <option style="color:#050B15;" value="1">あり</option>
                      </select>                 
                    </div>
                    <div class="col-lg-2 btn_center center_a line_up ma_btm_a">
                      <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
                    </div>
                  </div>           
                </div>
              </form>        
          </details>  
          </div>
        </div>
</template>
