<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Add from '@/Pages/Message/Add.vue';


const filterName = ref('');
const section = ref('');

const isDetailsOpen = ref(true);
const sections = ref([]);
const similarStaffList = ref([]); // 類似する担当者のリスト
const isLoading = ref(false);

const emit = defineEmits(['search']);
const savedFilters = JSON.parse(sessionStorage.getItem('messageFilters') || '{}');

// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'messageFilters') {
        sessionStorage.removeItem(key);
    }
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

  // 初期状態でdetailsが開いているか確認し、アイコン状態を設定
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});

// 検索処理
const searchStaff = () => {
  const params = {
    name: filterName.value || '',
    section: section.value || '',
  };
  console.log('検索条件:', params);
  sessionStorage.setItem('messageFilters', JSON.stringify(params));
  emit('search', params);
};

// 所属データをAPIから取得
const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/sections');
    sections.value = response.data;
  } catch (error) {
    console.error('Error fetching sections:', error);
  }
};

// 担当者名を使って類似する担当者を検索
const fetchSimilarStaff = async () => {
  try {
    const response = await axios.get('/api/staff/listformess', {
      params: {
        name: filterName.value,
        section: section.value,
      }
    });
    similarStaffList.value = response.data.data || []; // 類似する担当者リストをセット
  } catch (error) {
    console.error('Error fetching similar staff:', error);
  }
};


const addRef = ref(null);

// 追加ボタンのクリック処理
const handleAddClick = async () => {
  if (filterName.value) {
    await fetchSimilarStaff();  // 担当者名がある場合、類似する担当者を検索
  }
  if (section.value) {
    await fetchSimilarStaff();  // 担当者名がある場合、類似する担当者を検索
  }

  if (addRef.value) {
    addRef.value.open(similarStaffList.value);  // 検索結果をAdd.vueに渡す
  } else {
    console.error('addRef is not available.');
  }
};

onMounted(() => {
  fetchDepartments();
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
            <div class="row align-center two masse space_c">
              <div class="col-lg-3 form_r small flex">
                <label for="" class="col-form-label label_w">担当者名</label>                
                <input type="text" v-model="filterName" class="form-control normal">                
              </div>
              <div class="col-lg-3 form_r small flex">
                <label for="" class="col-form-label label_wm">所属</label>                
                <select v-model="section" class="form-select normal">
                  <option style="color:#B1B1B1;" value="">選択</option>
                  <option v-for="sec in sections" :key="sec.所属CD" :value="sec.所属CD" >
                    {{ sec.所属CD }}:{{ sec.所属名_社内用 }}
                  </option>
                </select>                 
              </div>              
              <div class="col-lg-2 btn_center line_up ma_btm_a center_a">
                <button type="button" @click="handleAddClick" class="button_r none search" :disabled="isLoading">追加</button>
                <button type="button" @click="searchStaff" class="button_r none search" :disabled="isLoading">検索</button>
              </div>
            </div>       
          </div>
        </form>        
      </details>  
    </div>
  </div>
  <Add ref="addRef"></Add>
</template>
