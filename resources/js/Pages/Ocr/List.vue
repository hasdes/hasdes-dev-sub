<script setup>
import { onMounted, ref, computed, onUnmounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router'; // Vue Routerのインポート
import Filter from '@/Pages/Ocr/Filter.vue';//検索
import dayjs from 'dayjs';//日付フォーマット

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得

/** 一覧データ */
const items = ref([]);
const itemsTotal = ref(0);

/** ページング */
const savedPage = sessionStorage.getItem('orderPage')
const page = ref(savedPage ? Number(savedPage) : 1)
const pageSize = ref(17); // 1ページあたりの表示件数

/** ローディング */
const loadingActive = ref(false);

/** 検索条件 */
const currentFilters = ref({});
const savedFilters = sessionStorage.getItem('orderFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

/** 警告ポップアップ */
const showBackModal = ref(false);

/** 日付 */
const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD H:mm');
};

/** ソートに関する状態 */
const sortColumn = ref('登録日時'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順
const sort = (column) => {
  if (sortColumn.value === column) {
    // 同じカラムが再度クリックされたらソート順を切り替え
    sortOrder.value = sortOrder.value === 'desc' ? 'asc' : 'desc';
  } else {
    // 新しいカラムがクリックされたらそのカラムで昇順ソート
    sortColumn.value = column;
    sortOrder.value = 'desc';
  }
  reLoadItems();
};

/** ====== 追加：一括変更用 ====== */
/** チェックされたID */
const selectedIds = ref([])
/** 移動モーダル */
const showMoveModal = ref(false)
const moveBumonCd = ref('')
/** 削除モーダル */
const showDeleteModal = ref(false)
/** 営業所一覧 */
const sections = ref([])
/** 未確認検索中かどうか */
const isUnconfirmed = computed(() => {
  return Number(currentFilters.value.status ?? 0) === 0
})
/** 一括操作メニュー表示 */
const showBulkMenu = ref(false)
/** メニューの開閉 */
const toggleBulkMenu = () => {
  if (selectedIds.value.length === 0) return
  showBulkMenu.value = !showBulkMenu.value
}
const bulkMenuArea = ref(null)
//=================================


// データを再読み込みする関数
const reLoadItems = () => {
  loadingActive.value = true; // ローディング状態を有効に

  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    page: page.value,
    pageSize: pageSize.value, // ページ数
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/orderslip-ocr/list', { params })
    .then((res) => {
      // Laravelから返されたデータをVueに反映
      items.value = res.data.result.data; // paginateされたデータは .data の中
      itemsTotal.value = res.data.result.total;
      selectedIds.value = [] // 再取得時はチェック解除
    })
    .catch((error) => {
      console.error('データ取得中にエラーが発生しました:', error);
    })
    .finally(() => {
      loadingActive.value = false; // ローディング状態を解除
    });
};


// 子コンポーネント(Filter.vue)から検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('orderFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  sessionStorage.setItem('orderPage', 1)
  reLoadItems();
};



// データチェックボタン押下（重複有無確認） -----------------
const targetOcrId = ref(null)
const search = (HD受注伝票_OCR_ID, 管轄部門CD, 相手先注文NO_得意先, ) => {
  axios.get('/api/orderslip-ocr/search', {
    params: { HD受注伝票_OCR_ID, 管轄部門CD, 相手先注文NO_得意先 }
  })
  .then(res => {
    const count = res.data.count;
    targetOcrId.value = HD受注伝票_OCR_ID

    if (count > 0) {
      showBackModal.value = true; // 重複あり → 警告ポップアップ
    } else {
      router.push({ path: '/ocr/edit', query: { key: HD受注伝票_OCR_ID } }); // 重複なし → 詳細ページ
    }
  })
  .catch(err => console.error(err));
};

//モーダル「はい」
const handleBackConfirm = () => {
  showBackModal.value = false;
  router.push({ path: '/ocr/edit', query: { key: targetOcrId.value } });
};
//モーダル「いいえ」
const closeBackModal = () => showBackModal.value = false;
// ---------------------------------------------------



/** 営業所一覧 */// ---------------------------------------------------
const fetchDepartments = async () => {
  const res = await axios.get('/api/departments/getSalesOffice')
  sections.value = res.data
}

/** チェック */
const toggleCheck = (item) => {
  if (item.OCR受注伝票NO !== null) return
  const id = item.HD受注伝票_OCR_ID
  const idx = selectedIds.value.indexOf(id)
  idx === -1 ? selectedIds.value.push(id) : selectedIds.value.splice(idx, 1)
}

/** 移動実行 */
const executeMove = async () => {
  if (!moveBumonCd.value || selectedIds.value.length === 0) return

  try {
    await axios.post('/api/orderslip-ocr/edit', {
      ids: selectedIds.value,
      管轄部門CD: moveBumonCd.value
    })
    alert(`${selectedIds.value.length} 件を移動しました`)
    showMoveModal.value = false
    moveBumonCd.value = ''
    selectedIds.value = []
    reLoadItems()
  } catch {
    alert('移動に失敗しました')
  }
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー

/** 削除 */// ---------------------------------------------------
const executeDelete = async () => {
  if (selectedIds.value.length === 0) return

  try {
    await axios.post('/api/orderslip-ocr/delete', {
      ids: selectedIds.value,
    })
    alert(`${selectedIds.value.length} 件を削除しました`)
    showDeleteModal.value = false
    selectedIds.value = []
    reLoadItems()
  } catch {
    alert('削除に失敗しました')
  }
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー

// 外クリック検知 */// ---------------------------------------------------
const handleClickOutside = (e) => {
  if (!showBulkMenu.value) return

  if (bulkMenuArea.value && !bulkMenuArea.value.contains(e.target)) {
    showBulkMenu.value = false
  }
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー

// ページ変更時の処理
const setPage = (val) => {
  page.value = val;
  sessionStorage.setItem('orderPage', val)
  reLoadItems();
};

onMounted(async () => {
  document.addEventListener('click', handleClickOutside)
  await fetchDepartments()
  await reLoadItems()
})

//操作メニュー解除
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

</script>

<template>
<section class="section dashboard">
  <div class="row">
    <ol class="breadcrumb">
      <!-- <li><router-link to="/home">ホーム</router-link></li> -->
      <li v-if="authItems?.[0]?.ホーム == 0">
        <router-link to="/home">ホーム</router-link>
      </li>
      <li>販売管理</li>
      <li>受注入力_OCR</li>
    </ol>

    <!-- ローディング画面 -->
    <div v-if="loadingActive" class="loading-wrap">
      <span>読み込み中...</span>
    </div>
    
    <!-- 検索条件 -->
    <Filter :parentLoading="loadingActive" @search="handleSearch"/>

    <div class="col-lg-12" v-show="!loadingActive">
      <div class="card">
          <div class="contents_head">
            <h5 class="card-title">注文書データ一覧</h5>
          </div>         

          <div class="scroll-box_y c">
              <table class="table_w tablesorter alter" id="table_sort">   
                <thead>
                  <tr class="head">
                    <!-- 操作ボタン -->
                    <th class="narrow_i bulk-th" ref="bulkMenuArea">
                      <button class="bulk-menu-btn"
                        :disabled="selectedIds.length === 0 || !isUnconfirmed"
                        @click.stop="toggleBulkMenu">▼</button>
                      <!-- 一括操作メニュー -->
                      <div v-if="showBulkMenu" class="bulk-menu">
                        <div class="bulk-menu-item" @click="showBulkMenu = false; showMoveModal = true">
                          他営業所へ移動
                        </div>
                        <div class="bulk-menu-item" @click="showBulkMenu = false; showDeleteModal = true">
                          削除
                        </div>
                      </div>
                    </th>
                    <th class="narrow_c" @click="sort('登録日時')">読み取り日時
                      <span v-if="sortColumn === '登録日時' && sortOrder === 'desc'">▲</span>
                      <span v-if="sortColumn === '登録日時' && sortOrder === 'asc'">▼</span>
                    </th>
                    <th class="narrow_f">得意先名</th>
                    <th class="narrow_f">出荷先名</th>
                    <th class="narrow_f">相手先注文No</th>
                    <th class="sm"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in items" :key="index">
                    <td>
                      <input type="checkbox"
                        :checked="selectedIds.includes(item.HD受注伝票_OCR_ID)"
                        :disabled="item.OCR受注伝票NO !== null"
                        @change="toggleCheck(item)"
                      />
                    </td>                  
                    <td data-label="登録日時">{{ formatDate(item.登録日時) }}</td>
                    <td data-label="得意先">{{ item.得意先名 }}</td>
                    <td data-label="出荷先">{{ item.出荷先名 }}</td>
                    <td data-label="相手先注文No">{{ item.相手先注文NO_得意先 }}</td>      
                    <td class="sp_btn"><button @click.prevent="search(item.HD受注伝票_OCR_ID, item.管轄部門CD, item.相手先注文NO_得意先)" class="bo_btn d">データチェック</button></td>
                  </tr>
                </tbody>
              </table>
          </div>
      </div>
    </div>
    <div class="table_fot">
      <span v-if="itemsTotal === 0">
        全 0 件
      </span>
      <span v-else>
        全 {{ itemsTotal }} 件中
        {{ (page - 1) * pageSize + 1 }} 件 〜
        {{ Math.min(page * pageSize, itemsTotal) }} 件を表示
      </span>
 
      <el-pagination
        layout="prev, pager, next"
        :total="itemsTotal"
        :page-size="pageSize"
        :current-page.sync="page"
        @current-change="setPage"
      ></el-pagination>

    </div>
  </div>

    <!-- 警告ポップアップ -->
    <div v-if="showBackModal" class="modal_wrap">
      <div class="modal_inner s return_modal_inner">
        <div class="signup_form">
          <h5>客先の注文Noが既に読取済みです。<br>詳細ページを開きますか？</h5>
        </div>
        <div class="btn_space_modal">
          <div class="submit_btn_no close_icon" @click="closeBackModal">いいえ</div>
          <div class="submit_btn_yes id_btn_yes" @click="handleBackConfirm">はい</div>
        </div>
      </div>
    </div>


     <!-- 営業所移動確認モーダル -->
    <div v-if="showMoveModal" class="modal_wrap">
      <div class="modal_inner s return_modal_inner">
        <div class="signup_form">
          <h5>
            <!-- {{ selectedIds.length }} 件の注文書を -->
            選択した注文書を他営業所へ移動します。<br>
          </h5>
        </div>
        <select v-model="moveBumonCd" class="form-select bumon-select">
          <option value="">移動先営業所を選択</option>
          <option v-for="sec in sections" :key="sec.部門CD" :value="sec.部門CD">
            {{ sec.部門CD }} : {{ sec.部門名 }}
          </option>
        </select>

        <div class="btn_space_modal">
          <div class="submit_btn_no" @click="showMoveModal = false">いいえ</div>
          <div class="submit_btn_yes" :class="{ disabled: !moveBumonCd }" @click="executeMove">はい</div>
        </div>
      </div>
    </div>

    <!-- 削除ポップアップ -->
    <div v-if="showDeleteModal" class="modal_wrap">
      <div class="modal_inner s return_modal_inner">
        <div class="signup_form">
          <h5>選択したデータを削除しますか？</h5>
        </div>
        <div class="btn_space_modal">
          <div class="submit_btn_no close_icon" @click="showDeleteModal = false">いいえ</div>
          <div class="submit_btn_yes id_btn_yes" @click="executeDelete">はい</div>
        </div>
      </div>
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
.bulk-th,
tbody tr > td:first-child {
  width: 40px;
  text-align: center;
}
</style>
