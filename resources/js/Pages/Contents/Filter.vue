<script setup>
import { onMounted,ref } from 'vue';

const emit = defineEmits(['search']);

// 検索条件
const startdate = ref('');
const enddate = ref('');
const jdouga = ref('');
const jshiryo = ref('');
const jtext = ref('');
const titele = ref('');
const hinmeicd = ref('');
const isDetailsOpen = ref(true);
const isLoading = ref(false);

const contentssavedFilters = JSON.parse(sessionStorage.getItem('contentsFilters') || '{}');
// 現在の sessionStorage のすべてのキーを取得
const keys = Object.keys(sessionStorage);

// messageFilters 以外のキーを削除
keys.forEach(key => {
    if (key !== 'contentsFilters') {
        sessionStorage.removeItem(key);
    }
});
onMounted(() => {
  // セッションストレージから保存された値をロード
  if (contentssavedFilters.startdate) {
    startdate.value = contentssavedFilters.startdate;
  }
  if (contentssavedFilters.enddate) {
    enddate.value = contentssavedFilters.enddate;
  }
  if (contentssavedFilters.jdouga) {
    jdouga.value = contentssavedFilters.jdouga;
  }
  if (contentssavedFilters.jshiryo) {
    jshiryo.value = contentssavedFilters.jshiryo;
  }
  if (contentssavedFilters.jtext) {
    jtext.value = contentssavedFilters.jtext;
  }
  if (contentssavedFilters.titele) {
    titele.value = contentssavedFilters.titele;
  }
  if (contentssavedFilters.hinmeicd) {
    hinmeicd.value = contentssavedFilters.hinmeicd;
  }

  // 初期状態でdetailsが開いているか確認し、アイコン状態を設定
  const detailsElement = document.querySelector('details.contents_head');
  isDetailsOpen.value = detailsElement.hasAttribute('open');
});

const searchcontents = () => {
  const params = {
    startdate: startdate.value,
    enddate: enddate.value,
    jdouga: jdouga.value ? jdouga.value : null,
    jshiryo: jshiryo.value ? jshiryo.value : null,
    jtext: jtext.value ? jtext.value : null,
    titele: titele.value,
    hinmeicd:hinmeicd.value,
  };
  sessionStorage.setItem('contentsFilters', JSON.stringify(params));
  emit('search', params);
};

// detailsの開閉状態に応じてアイコンを切り替える
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
              <div class="col-lg-3 form_r flex date kei align kake time">
                <label for="inputEmail" class="col-form-label label_wm">投稿日</label>                
                <input type="date" class="form-control normal t_input custom-width" v-model="startdate">  
                <span>〜</span>  
                <input type="date" class="form-control normal t_input custom-width" v-model="enddate">                    
              </div>
              <div class="col-lg-3 form_r flex jnl center_a">
                <label for="inputEmail" class="col-form-label label_s">ジャンル</label>                
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="jdouga">
                  <label class="form-check-label">
                    動画
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="jshiryo">
                  <label class="form-check-label">
                    資料
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="jtext">
                  <label class="form-check-label">
                    テキスト
                  </label>
                </div>
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">タイトル</label>                
                <input type="text" class="form-control normal" v-model="titele">                    
              </div>
              <div class="col-lg-3 form_r flex">
                <label for="inputEmail" class="col-form-label label_wm">品名CD</label>                
                <input type="text" class="form-control normal" v-model="hinmeicd">                    
              </div>
              <div class="col-lg-1 btn_center center_a">
                <button type="submit" class="button_r none search"@click="searchcontents" :disabled="isLoading">検索</button>        
              </div>
            </div>          
          </div>
        </form>        
      </details>  
    </div>
  </div> 
</template>
