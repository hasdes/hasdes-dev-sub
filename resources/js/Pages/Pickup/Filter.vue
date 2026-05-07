<script setup>
import { ref, onMounted, watch } from 'vue';

const filterStart_date = ref('');
const filterEnd_date = ref('');
const isDetailsOpen = ref(true);
const isLoading = ref(false);
const emit = defineEmits(['search']);

// 検索履歴セッション
const saved = JSON.parse(sessionStorage.getItem('pickupFilters') || '{}');

// 日本時間の今日を YYYY-MM-DD 形式で作成
const getToday = () => {
  const today = new Date();
  const y = today.getFullYear();
  const m = String(today.getMonth() + 1).padStart(2, '0');
  const d = String(today.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

onMounted(() => {
  // saved があれば優先。ない場合は今日をセット
  filterStart_date.value = saved.start_date || getToday();
  filterEnd_date.value   = saved.end_date;

  const detailsElement = document.querySelector('details.contents_head');
  if (detailsElement) {
    isDetailsOpen.value = detailsElement.hasAttribute('open');
  }
});


// Save filters
watch([filterStart_date, filterEnd_date], () => {
  const filters = {
    start_date: filterStart_date.value,
    end_date: filterEnd_date.value
  };
  sessionStorage.setItem('pickupFilters', JSON.stringify(filters));
});

// Run search
const searchPickUp = () => {
  emit('search', {
    start_date: filterStart_date.value,
    end_date: filterEnd_date.value,
  });
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
        <form>
          <div class="search_send bo_none">
            <div class="row space align-center masse space_c">

              <div class="col-lg-3 form_r flex">
                <label class="col-form-label label_wm">開始</label>
                <input type="date" class="form-control normal" v-model="filterStart_date">
              </div>

              <div class="col-lg-3 form_r flex">
                <label class="col-form-label label_wm">終了</label>
                <input type="date" class="form-control normal" v-model="filterEnd_date">
              </div>

              <div class="col-lg-1 btn_center ma_btm_a center_a">
                <button type="button" @click="searchPickUp" class="button_r none search">
                  検索
                </button>
              </div>

            </div>
          </div>
        </form>         
      </details>
    </div>
  </div>
</template>
