<script setup>
import { ref, onMounted } from 'vue';

const filterText = ref('');
const filterName = ref('');
const department = ref('');
const section = ref('');
const departments = ref([]);
const sections = ref([]);
const userType = ref('');
const isDetailsOpen = ref(true);
const isLoading = ref(false);
const emit = defineEmits(['search']);
const savedFilters = JSON.parse(sessionStorage.getItem('staffFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'staffFilters') {
        sessionStorage.removeItem(key);
    }
});
const userTypes = ref({
  0: 'システム管理',
  1: '従業員',
  2: '退職者',
  3: 'その他'
});

onMounted(() => {
  // セッションストレージから保存された値をロード
  if (savedFilters.filter) {
    filterText.value = savedFilters.filter;
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
  if (savedFilters.userType) {
    userType.value = savedFilters.userType;
  }

  // 初期状態でdetailsが開いているか確認し、アイコン状態を設定
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});

// 検索処理
const searchStaff = () => {
  // 検索条件を作成
  const params = {
    filter: filterText.value || '',
    name: filterName.value || '',
    department: department.value || '',
    section: section.value || '',
    userType: userType.value !== '' ? userType.value : '',
  };

  console.log('検索条件:', params);

  // 検索条件をセッションストレージに保存
  sessionStorage.setItem('staffFilters', JSON.stringify(params));

  // 親コンポーネントに検索条件を送信
  emit('search', params);
};

const fetchDepartments = async () => {
  try {
    // const response = await axios.get('/api/departments');
    const response = await axios.get('/api/departments/getDepartments');

    departments.value = response.data;
  } catch (error) {
    console.error('Error fetching departments:', error);
  }
};
const fetchSections = async () => {
  try {
    const response = await axios.get('/api/sections');
    sections.value = response.data;
  } catch (error) {
    console.error('Error fetching sections:', error);
  }
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
        <form action="">
          <div class="search_send bo_none">
            <div class="row space align-center masse space_c">
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">担当者CD</label>
                <input type="text" v-model="filterText" class="form-control normal alpha-numeric-symbol-input" id="inputField" placeholder="半角英数字記号">
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">担当者名</label>
                <input type="text" v-model="filterName" class="form-control normal">
              </div>
              <div class="col-lg-2 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">所属部門</label>
                <select v-model="department" class="form-select normal">
                  <option style="color:#B1B1B1;" value="">選択</option>
                  <option v-for="dept in departments" :key="dept.部門CD" :value="dept.部門CD" style="color:#050B15;">
                    {{ dept.部門CD }}:{{ dept.部門略称名 }}
                  </option>
                </select>
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_w">所属</label>
                <select v-model="section" class="form-select normal">
                  <option style="color:#B1B1B1;" value="">選択</option>
                  <option v-for="secs in sections" :key="secs.所属CD" :value="secs.所属CD" style="color:#050B15;">
                    {{ secs.所属CD }}:{{ secs.所属名_社内用 }}
                  </option>
                </select>
              </div>
              <div class="col-lg-3 form_r flex">
                  <label for="userType" class="col-form-label label_wm">ユーザー種別</label>
                  <select v-model="userType" class="form-select normal">
                    <option style="color:#B1B1B1;" value="">選択</option>
                    <option v-for="(value, key) in userTypes" :key="key" :value="key" style="color:#050B15;">
                      {{ key }}:{{ value }}
                    </option>
                  </select>
                </div>

              <div class="col-lg-1 btn_center line_up ma_btm_a center_a">
                <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
              </div>
            </div>
          </div>
        </form>
      </details>
    </div>
  </div>
</template>
