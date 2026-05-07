<script setup>
import axios from 'axios';
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { ElNotification } from 'element-plus';
import Add from '@/Pages/Ocr/Add.vue';//新規登録

// 親から
const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (val) => ['customer', 'shipping', 'product'].includes(val)
  },
  // 編集画面から渡される「元の文字列」（変換名の初期値に使う）
  conversionText: {
   type: String,
   default: ''
  }
})

// Emits
const emit = defineEmits(['close', 'selected'])

// リアクティブデータ
const searchResults = ref([])
const showSearchResults = ref(false)
const conversionList = ref([])
const showConversionList = ref(false)
const selectedItem = ref(null)

// ◆ 新規変換名
const targetCd = ref('');
const localConversionText = ref(props.conversionText);

const syozokubumonCD = ref(null)

const loadingActive = ref(false)  // 初期は読み込み中


// 検索タイトル
const modalTitle = computed(() => {
  const titles = {
    customer: '得意先',
    shipping: '出荷先',
    product: '商品'
  }
  return titles[props.type] || '検索'
})
//placeholder 
const placeholder1 = computed(() => {
  const placeholders = {
    customer: '得意先CD',
    shipping: '出荷先CD',
    product: '商品CD'
  }
  return placeholders[props.type] || '検索語を入力'
})
const placeholder2 = computed(() => {
  const placeholders = {
    customer: '得意先略名',
    shipping: '出荷先略名',
    product: '商品名'
  }
  return placeholders[props.type] || '検索語を入力'
})

//項目名
const tableHeaders = computed(() => {
  const baseHeaders = {
    customer: { codeLabel: '得意先CD', codeKey: '得意先CD', nameLabel: '得意先略名', nameKey: '得意先略名' },
    shipping: { codeLabel: '出荷先CD', codeKey: '出荷先_エンドユーザーCD', nameLabel: '出荷先略名', nameKey: '略名' },
    product:  { codeLabel: '商品CD', codeKey: '商品CD', nameLabel: '商品名', nameKey: '商品名_社内用' }
  }
  return baseHeaders[props.type]
})

// キャンセルボタン
function close() {
  emit('close')
}


//ポップアップ ドラッグ機能 ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
const dragTarget = ref(null)
const dragHandle = ref(null)

let isDragging = false
let offsetX = 0
let offsetY = 0

function onMouseDown(e) {
  if (!dragTarget.value || !dragHandle.value) return
  isDragging = true
  const rect = dragTarget.value.getBoundingClientRect()
  offsetX = e.clientX - rect.left
  offsetY = e.clientY - rect.top

  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e) {
  if (!isDragging) return
  dragTarget.value.style.left = `${e.clientX - offsetX}px`
  dragTarget.value.style.top = `${e.clientY - offsetY}px`
  dragTarget.value.style.transform = 'none' // 中央固定解除
}

function onMouseUp() {
  isDragging = false
  document.removeEventListener('mousemove', onMouseMove)
  document.removeEventListener('mouseup', onMouseUp)
}

onMounted(() => {
  dragHandle.value?.addEventListener('mousedown', onMouseDown)
})

onBeforeUnmount(() => {
  dragHandle.value?.removeEventListener('mousedown', onMouseDown)
})
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー


// 簡易新規登録API ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
//バリデーション
const validate = () => {
  if (!localConversionText.value?.trim()) {
      ElNotification({ title: 'Error', message: '変換名を入力してください。', type: 'error' });
      return false; // エラーがあればここで終了
  }

  if (!targetCd.value?.trim()) {
   ElNotification({
     title: 'Error',
     message: `${placeholder1.value} を入力してください。`,
     type: 'error'
   });
   return false;
 }

  // すべての条件が満たされた場合
  return true;
};

const registerConversion = async () => {
  if (!validate()) return;

  // 各タイプ別のURLとラベル
  let label = '';
  let createUrl = '';

  if (props.type === 'customer') {
    label = '得意先CD';
    createUrl = '/api/convercustomer/create';
  } else if (props.type === 'shipping') {
    label = '出荷先_エンドユーザーCD';
    createUrl = '/api/convershipping/create';
  } else if (props.type === 'product') {
    label = '商品CD';
    createUrl = '/api/converproduct/create';
  }

  // FormData 作成
  const formData = new FormData();
  formData.append(label, targetCd.value); // CD
  formData.append('管轄部門CD', syozokubumonCD.value);
  formData.append('変換名', localConversionText.value);
  
  axios.post(createUrl, formData)
  .then(() => {
    ElNotification({
      title: 'Success',
      message: '登録成功しました',
      type: 'success',
    });
    targetCd.value = '';//入力値を空にする

  })
  .catch((error) => {
    let errorMessage = '登録に失敗しました';
    if (error.response) {
      if (error.response.status === 400) {
        errorMessage = `指定された${label}は存在しません。`;
      } else if (error.response.status === 409) {
        errorMessage = '入力された変換名はすでに登録されています。';
      }
    }
    ElNotification({ title: 'Error', message: errorMessage, type: 'error' });
  });

};

//　変換名選択
function selectConversion(selectedName) {
  if (selectedItem.value) {
    emit('selected', { 
      // code: selectedItem.value.code, 
      cd: selectedItem.value.code, 
      name: selectedName 
    })
  }
}

// 変換名変数
const displayConversionText = computed(() => {
  const text = localConversionText.value || '';
  return text.length > 50 ? text.substring(0, 50) + '…' : text;
});

watch(
  () => props.conversionText,
  (val) => {
   // props が null/空文字なら無視（検索結果再取得時など）
   if (!val) return;

    localConversionText.value = val;
  }
);
onMounted(() => {
  // 初期値だけ props からコピー
  localConversionText.value = props.conversionText || '';
});
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー


// 得意先・出荷先・商品検索　ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
const filter = ref('');
const coname = ref('');

// function searchEntry() {
async function searchEntry() {
  loadingActive.value = true; // ← 検索開始

  const params = {};
  //入力値
  params.filter = filter.value;
  params.coname = coname.value;
  // params.syozokubumon = props.syozokubumon;//所属部門（管轄部門）

  //各URL　（得意先、出荷先、商品名）
  const urlMap = {
    customer: '/api/customer/list',
    shipping: '/api/shipping/list',
    product: '/api/converproduct/productlist'
  }
  const url = urlMap[props.type] || ''

  //検索
  try {
    const res = await axios.get(url, { params });

    searchResults.value = res.data.data || [];
    showSearchResults.value = true;
    showConversionList.value = false;
    selectedItem.value = null;

  } catch (error) {
    console.error('データ取得中にエラーが発生しました:', error);
    searchResults.value = [];
  } finally {
    loadingActive.value = false; // ← ここ重要！（成功でも失敗でも確実にOFF）
  }
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー


//ソート機能 ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
const sortColumn = ref(null); // 初期表示時のデフォルトソートカラム 得意先CD/出荷先_エンドユーザーCD/商品CDいずれか
const sortOrder = ref('asc'); // 初期表示時のデフォルトソート順

onMounted(async () => {
  sortColumn.value = tableHeaders.value.codeKey;

  try {
   const res = await axios.get('/api/auth/userinfo')

    syozokubumonCD.value = res.data.所属部門CD

    // console.log("Filter 所属部門CD:", syozokubumonCD.value)

  } catch (error) {
    console.error("Filter ユーザー情報取得エラー", error)
  }
});

// ソート切り替え関数
function sort(column) {
  if (sortColumn.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }

  // ★選択されてないなら中断
  if (!selectedItem.value) return;

  fetchConversionList(
    selectedItem.value.code,
    props.type === 'shipping' ? selectedItem.value.管轄部門CD : null
  );
}
const sortedSearchResults = computed(() => {
  if (!searchResults.value) return [];

  const key = sortColumn.value;
  const order = sortOrder.value;

  return [...searchResults.value].sort((a, b) => {
    const av = a[key] ?? '';
    const bv = b[key] ?? '';

    if (order === 'asc') {
      return av > bv ? 1 : -1;
    } else {
      return av < bv ? 1 : -1;
    }
  });
});
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー


// 得意先・出荷先・商品一覧 ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
function selectEntry(item) {
  const codeKey = tableHeaders.value.codeKey;
  const nameKey = tableHeaders.value.nameKey;

  if (props.type === 'shipping') {
    selectedItem.value = {
      code: item[codeKey],
      name: item[nameKey],
      管轄部門CD: item.管轄部門CD ?? null
    }
    // console.log('選択した shipping の管轄部門CD:', selectedItem.value.管轄部門CD);
  } else {
    selectedItem.value = {
      code: item[codeKey],
      name: item[nameKey]
    } 
  }

  // テーブルを1行に絞る
  if (props.type === 'customer') {
    searchResults.value = [{ 得意先CD: selectedItem.value.code, 得意先略名: selectedItem.value.name }]

  } else if (props.type === 'shipping') {
    searchResults.value = [{
      出荷先_エンドユーザーCD: selectedItem.value.code,
      略名: selectedItem.value.name,
      管轄部門CD: selectedItem.value.管轄部門CD
    }]

  } else if (props.type === 'product') {
    searchResults.value = [{
      商品CD: selectedItem.value.code,
      商品名_社内用: selectedItem.value.name,
    }]
  }

  fetchConversionList(
    selectedItem.value.code, //CD
    props.type === 'shipping' ? selectedItem.value.管轄部門CD : null //管轄部門(出荷先の場合)
  )
  showConversionList.value = true
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー


// APIから変換名一覧を取得する関数 ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
function fetchConversionList(cd,jurisdiction) {
  //各URL　（変換名一覧）
  let url2 = ''
  if (props.type === 'customer') {
    url2 = '/api/convercustomer/list'
  } else if (props.type === 'shipping') {
    url2 = '/api/convershipping/list'
  } else if (props.type === 'product') {
    url2 = '/api/converproduct/list'
  }

  axios.get(url2, {
    params: { 
      key: cd, 
      key2: jurisdiction,
      sortColumn: sortColumn.value, // ソート対象カラム
      sortOrder: sortOrder.value // ソート順
    }  // LaravelのAPIではkeyパラメータに得意先CDを渡す想定
  })
  .then(res => {
    if (res.data && res.data.data) {
      // 変換名リストを配列で取得してVueの配列にセット
      conversionList.value = res.data.data.map(item => item.変換名)
    } else {
      conversionList.value = []
    }
  })
  .catch(err => {
    console.error('変換名一覧取得APIエラー:', err)
    conversionList.value = []
  })
}
//ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー
</script>

<template>
  <div class="modal_wrap search-ocr">
    <div class="modal_inner pop_width" ref="dragTarget">
      <button class="close-btn close_icon" @click="close">×</button>
      <div class="modal-draggable" ref="dragHandle">
        <p class="pop_ttl txt_blue">{{ modalTitle }}検索</p>
      </div>
      
      <!-- 検索用のform -->
      <form @submit.prevent="searchEntry">
        <div class="d-flex gap-2 mb-3">
          <input type="text" class="form-control normal w-25" :placeholder="placeholder1" v-model="filter"/><!-- CD -->
          <input type="text" class="form-control normal w-75" :placeholder="placeholder2" v-model="coname"/><!-- 名前 -->
          <button type="submit" class="button_r btn btn-primary w-25">検索</button>
        </div>
      </form>

      <!-- 簡易登録 -->
      <div class="quick-register text-center" v-if="!showConversionList">
        <p class="qr-text">
          「{{ displayConversionText }}」を変換名として登録します
        </p>
        <div class="d-flex justify-content-end align-items-center gap-1 mb-2 kani">
          <input type="text" class="form-control normal" :placeholder="placeholder1" v-model="targetCd"/>
          <button type="submit" class="button_r btn btn-primary add_btn" @click="registerConversion">追加</button>
        </div>
      </div>

      <div class="table-wrap" :class="{ loading: loadingActive, 'need-min-height': !showSearchResults || loadingActive }">
          <!-- ローディング画面 -->
          <div v-if="loadingActive" class="table-loading">
            <span>読み込み中...</span>
          </div>

        <!-- 検索結果テーブル --> 
        <div v-if="showSearchResults && searchResults.length > 0" class="table-wrapper scrollable-table">
          <table class="table_w pop_table hover-highlight">
            <thead>
              <tr class="head">
                <th @click="sort(tableHeaders.codeKey)">
                  {{ tableHeaders.codeLabel }}
                  <span v-if="sortColumn === tableHeaders.codeKey && sortOrder === 'asc'">▲</span>
                  <span v-if="sortColumn === tableHeaders.codeKey && sortOrder === 'desc'">▼</span>
                </th><!-- CD -->
                <th>{{ tableHeaders.nameLabel }}</th><!-- 名前 -->
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in sortedSearchResults" :key="index" @dblclick="selectEntry(item)" class="selectable-row">
              <td :data-label="tableHeaders.codeLabel" class="keep-space">{{ item[tableHeaders.codeKey].trim()}}</td>
              <td :data-label="tableHeaders.nameLabel" class="white-space">{{ item[tableHeaders.nameKey] }}</td>
            </tr>
            </tbody>
          </table>
        </div>

        <!-- 検索結果なしメッセージ -->
        <div v-else-if="showSearchResults && searchResults.length === 0" class="text-center py-4">
          <p class="text-muted">該当する項目が見つかりませんでした。</p>
        </div>
      </div>

      <!-- 変換名一覧 -->
      <div v-if="showConversionList" class="conversion-box card shadow-sm p-3 mb-4 mt-4">
        <p class="fw-bold txt_blue text-center border-bottom pb-2 mb-3">変換名一覧</p>
        <div class="scroll-box_y mb-3 scroll-limit">
          <ul class="list-group list-group-flush hover-highlight">
            <li
              v-for="(name, index) in conversionList"
              :key="index"
              class="list-group-item selectable-row"
              @dblclick="selectConversion(name)"
            >
              {{ name }}
            </li>
          </ul>
        </div>
        
        <!-- Add.vue　新規登録 -->
        <Add 
          v-if="selectedItem?.code" 
          :cd="selectedItem.code" 
          :jurisdiction="selectedItem.管轄部門CD" 
          :type = props.type
          @reLoad="fetchConversionList(selectedItem.code,selectedItem.管轄部門CD)" 
        />
      </div>

    </div>
  </div>
</template>

<style scoped>
.modal_inner {
  box-shadow: 0 10px 30px rgba(0,0,0,0.35);
  border: 2px solid #dcdcdc;
}
@media screen and (max-width: 590px) {
  /* 各行の最後のセルに下線を追加 */
  .table_w tbody tr {
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 10px;
  }
}
/* ポップアップ大きさ */
@media (min-width: 1024px) {
  .modal_inner.pop_width {
    width: 1000px;
  }
}
</style>
