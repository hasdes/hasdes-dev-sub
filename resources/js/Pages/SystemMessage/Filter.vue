<script setup>
import { ref,onMounted } from 'vue';

const filterCD = ref('');
const filterName = ref('');
const department = ref('');
const section = ref('');
const isDetailsOpen = ref(true);
const isLoading = ref(false);
const emit = defineEmits(['search']); // イベント名を 'search' に変更
const savedFilters = JSON.parse(sessionStorage.getItem('systemmessageFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'systemmessageFilters') {
        sessionStorage.removeItem(key);
    }
});
onMounted(() => {
  if (savedFilters.filter) {
    filterCD.value = savedFilters.filter;
  }
  if (savedFilters.name) {
    filterName.value = savedFilters.name;
  }
  if (savedFilters.department) {
    department.value = savedFilters.department;
  }
  if (savedFilters.section) {
    section.value = savedFilters.section;
  }

  // Set icon state for details open/close
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});

const sections = ref([]);
const fetchSections = async () => {
  try {
    const response = await axios.get('/api/sections');
    sections.value = response.data;
  } catch (error) {
    console.error('Error fetching sections:', error);
  }
};

const departments = ref([]);
const fetchDepartments = async () => {
  try {
    // const response = await axios.get('/api/departments');
    const response = await axios.get('/api/departments/getDepartments');

    departments.value = response.data;
  } catch (error) {
    console.error('Error fetching departments:', error);
  }
};

// 検索処理
const searchStaff = () => {
  // 検索条件を作成
  const params = {
    filter: filterCD.value || '',
    name: filterName.value || '',
    department: department.value || '',
    section: section.value || '',
  };

  sessionStorage.setItem('systemmessageFilters', JSON.stringify(params));
  // 親コンポーネントに検索条件を送信
  emit('search', params);
};


onMounted(() => {
  fetchDepartments();
  fetchSections();
});
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
                  <div class="row space align-center two masse space_c">
                    <div class="col-lg-3 form_r flex">
                      <label for="inputEmail" class="col-form-label label_wm">担当者CD</label>                
                      <input type="text" v-model="filterCD" class="form-control normal alpha-numeric-symbol-input" id="inputField" required placeholder="半角英数字記号">                 
                    </div>
                    <div class="col-lg-3 form_r flex">
                      <label for="inputEmail" class="col-form-label label_wm">担当者名</label>                
                      <input type="text" v-model="filterName" class="form-control normal">                
                    </div>
                    <div class="col-lg-2 form_r flex">
                      <label for="inputEmail" class="col-form-label label_wm">所属部門</label>                
                      <select v-model="department" class="form-select normal">
                        <option style="color:#B1B1B1;" value="">選択</option>
                        <option v-for="dep in departments" :key="dep.部門CD" :value="dep.部門CD" style="color:#050B15;">
                        {{ dep.部門CD }}:{{ dep.部門略称名 }}
                      </option>
                      </select>                 
                    </div>        
                    <div class="col-lg-2 form_r flex">
                      <label for="inputEmail" class="col-form-label label_w">所属</label>                
                      <select v-model="section" class="form-select normal">
                        <option style="color:#B1B1B1;" value="">選択</option>
                        <option v-for="sec in sections" :key="sec.所属CD" :value="sec.所属CD" style="color:#050B15;">
                        {{ sec.所属CD }}:{{ sec.所属名_社内用 }}
                      </option>
                      </select>             
                    </div>        
                    <div class="col-lg-2 btn_center ma_btm_a center_a">
                      <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
                    </div>
                  </div>             
                </div>
              </form>        
          </details>  
          </div>
        </div>
</template>
