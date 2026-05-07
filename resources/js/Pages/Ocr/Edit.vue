<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import { useRouter } from 'vue-router'
import Search from './Search.vue'
import axios from 'axios'
import { ElNotification } from 'element-plus'

defineProps({
  authItems: Array
})

const router = useRouter() //ページ遷移
const loadingActive = ref(true)  // 初期は読み込み中

// モーダルの種類や開閉状態
const modalType = ref(null)   // 得意先、出荷先、商品
const showBackModal = ref(false) // 戻る
const showModal = ref(false) // 受注データ変換完了

// 編集関連
const currentlyEditing = ref(null) // 今どのフィールドを編集中か
const clickTimeout = ref(null)     // ダブルクリック判定用タイマー
const selectedFields = ref(new Set()) // 編集対象として選ばれたフィールド

//表示する情報
const item = ref(null); // 受注ヘッダ情報（1件分）
const items = ref([]);  // 商品明細の配列
const editableData = ref({})  // 編集用に加工したデータ
const originalData = ref({})  // 編集前の元データ
const products = ref([])
const targetProduct = ref(null)
const registeredOrderNo = ref(''); // 
const duplicateProductRowIndex = ref(null);// ポップアップ商品の行
//送信中フラグ
const isSubmitting = ref(false)

// 共通: URLパラメータからkey取得
const key = new URLSearchParams(window.location.search).get('key')

// 全角数字を半角数字に変換
const toHalfWidth = (str) => str.replace(/[０-９]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0));
// HTMLタグ除去
// const stripTags = (str) => str.replace(/<[^>]*>?/gm, '');
const stripTags = (str) => {
  if (str == null) return '';
  return String(str).replace(/<[^>]*>?/gm, '');
};

// --------------------
// バリデーション関数（トップレベル）
const validateField = (fieldKey, value) => {
  let isValid = true
  let errorMessage = ''

    // --- ここでタグを除去 ---
  let plainValue = stripTags(value ?? '');
  // 全角を半角に変換してからバリデーション
  const convertedValue = toHalfWidth(plainValue ?? '').trim();
  
  // デバッグ用ログ
  // console.log(`Validating ${fieldKey}: "${value}" -> "${convertedValue}"`);
  // console.log([...convertedValue].map(c => c.charCodeAt(0))); 

  if (fieldKey === 'shippingDate' && !/^\d{8}$/.test(convertedValue)) {
    isValid = false
    errorMessage = '希望出荷日は8桁の半角数字を入力してください'
  }
  if (fieldKey === 'orderNo' && convertedValue.length > 20) {
    isValid = false
    errorMessage = '相手先注文Noは20文字以内で入力してください'
  }
  if (fieldKey === 'shippingNote' && convertedValue.length > 100) {
    isValid = false
    errorMessage = '出荷先用は100文字以内で入力してください'
  }
  if (fieldKey === 'salesNote' && convertedValue.length > 100) {
    isValid = false
    errorMessage = '営業用備考は100文字以内で入力してください'
  }
  // if (fieldKey === 'deliveryDate' && convertedValue !== '' && !/^\d{8}$/.test(convertedValue)) {
  //   isValid = false
  //   errorMessage = '納期は8桁の半角数字で入力してください'
  // }
  if (fieldKey === 'deliveryDate' && plainValue.trim() === '') {
    isValid = false
    errorMessage = '納期を入力してください'
  }

  if (fieldKey === 'saturdayDelivery' && convertedValue !== '' && !/^\d{8}$/.test(convertedValue)) {
    isValid = false
    errorMessage = '土曜指定は8桁の半角数字で入力してください'
  }
  if (
    (fieldKey === 'diameter1' || fieldKey === 'diameter2') && convertedValue !== '' && !/^[0-9A-Z]{1,4}$/.test(convertedValue)) {
    isValid = false
    errorMessage = '呼び径1・2は半角数字またはアルファベット大文字を4桁以内で入力してください'
  }
  if (fieldKey === 'diameter3' && convertedValue !== '' && !/^[0-9A-Z]{1,3}$/.test(convertedValue)) {
    isValid = false
    errorMessage = '呼び径3は半角数字またはアルファベット大文字3桁以内で入力してください'
  }
  if (fieldKey === 'quantity' && convertedValue !== '' && !/^\d+$/.test(convertedValue)) {
    isValid = false
    errorMessage = '数量は半角数字で入力してください'
  }
  if (fieldKey === 'bikou' && convertedValue.length > 30) {
    isValid = false
    errorMessage = '備考は30文字以内で入力してください'
  }
  // console.log(`Validation result for ${fieldKey}: ${isValid ? 'PASS' : 'FAIL - ' + errorMessage}`);
  return { isValid, errorMessage }
}
// --------------------

// OCR読み取り内容取得　--------------------
//受注伝票取得
const reLoadItem = async () => {
  if (!key) return
  try {
    const { data } = await axios.get('/api/orderslip-ocr/detail', { params: { key } })
    item.value = data.data || null
    if (item.value) {
      const fields = {
        shippingDate: item.value['ヘッダ希望納期'] || '',
        customerName: item.value['得意先名'] || '',
        shippingName: item.value['出荷先名'] || '',
        deliveryDate: item.value['送り状印字内容'] || '',
        orderNo: item.value['相手先注文NO_得意先'] || '',
        saturdayDelivery: item.value['土日着日指定'] || '',
        shippingNote: item.value['相手先注文NO_出荷先'] || '',
        salesNote: item.value['営業用備考'] || '',
        customerCD: '',  // ← 追加
        shippingCD: '',  // ← 追加
      }
      editableData.value = { ...fields }
      originalData.value = { ...fields }
    }
  } catch (e) {
    console.error('ヘッダ取得エラー:', e)
  }
}

// 商品情報取得
const reLoadItem2 = async () => {
  if (!key) return
  try {
    const { data } = await axios.get('/api/orderdetail-ocr/list', { params: { key } })
    items.value = data.data || []

    products.value = items.value.map(p => {
      const productData = {
        productName: p['商品名'] || '',
        productCD: p['商品CD'] || '',
        diameter1: p['呼び径1'] || '',
        diameter2: p['呼び径2'] || '',
        diameter3: p['呼び径3'] || '',
        quantity: p['数量'] || '',
        bikou: p['備考'] || '',
      }
      return {
        ...productData,
        originalData: { ...productData },
        editingField: null,
        selectedFields: new Set()
      }
    })

    // DBに商品が1件もない場合は、空の1行を追加
    if (products.value.length === 0) {
      const emptyProduct = {
        productName: '',
        diameter1: '',
        diameter2: '',
        diameter3: '',
        quantity: '',
        bikou: '',
        originalData: { productName: '', diameter1: '', diameter2: '', diameter3: '', quantity: '', bikou: '' },
        editingField: null,
        selectedFields: new Set()
      }
      products.value.push(emptyProduct)
    }

  } catch (e) {
    console.error('商品取得エラー:', e)
  }
}
// --------------------

// 差分ハイライト
const highlightDiff = (oldText, newText) => {
  // 既存の span を除去
  const oldStr = stripTags(oldText)
  const newStr = stripTags(newText)

  if (oldStr === newStr) return newStr

  let start = 0, endOld = oldStr.length - 1, endNew = newStr.length - 1

  while (start < oldStr.length && start < newStr.length && oldStr[start] === newStr[start]) start++
  while (endOld >= start && endNew >= start && oldStr[endOld] === newStr[endNew]) { endOld--; endNew-- }

  return `${newStr.substring(0, start)}<span class="edited-text">${newStr.substring(start, endNew + 1)}</span>${newStr.substring(endNew + 1)}`
}

// --------------------
// 編集開始（共通）
const startEditing = (type, fieldKey, element, product = null) => {
  if (currentlyEditing.value) return
  currentlyEditing.value = { type, fieldKey, product }
  nextTick(() => {
    const input = element.querySelector('.edit-input')
    if (input) { input.focus(); input.select() }
  })
}

/**
 * 編集を終了し、入力値を反映する共通処理（ヘッダ／商品）
 */
const finishEditing = () => {

  // 現在、編集中の状態でなければ、何もせずに関数を終了
  if (!currentlyEditing.value) return

  // 分割代入で現在編集中の情報を取得
  // - type: 'header' か 'product'
  // - fieldKey: 編集しているフィールド名（例: shippingDate, productName, quantityなど）
  // - product: 商品行のオブジェクト（typeが'product'のときのみ）
  const { type, fieldKey, product } = currentlyEditing.value

  //現在表示されている編集中の input 要素を取得する処理
  const inputElement = document.querySelector('.edit-input')

  // 編集中の input に入力されている値を取得する処理
  let newValue = inputElement?.value || '' 

  // 全角→半角変換
  newValue = toHalfWidth(newValue)

  // === ヘッダ項目の編集処理 ===
  if (type === 'header') {
    // 元データ（編集前の値）を取得（未定義なら空文字）
    const originalText = originalData.value[fieldKey] || ''

    // 値に変更があれば差分ハイライト（赤文字にする）
    editableData.value[fieldKey] = newValue !== originalText ? highlightDiff(originalText, newValue) : newValue

    // 編集後に常に選択状態をONにする（背景色を青にする）
    selectedFields.value.add(fieldKey)

  // === 商品明細の編集処理 ===
  } else if (type === 'product' && product) {

    // 元データ（編集前の値）を取得（未定義なら空文字）
    const originalText = product.originalData?.[fieldKey] || ''

    // 値に変更があれば差分ハイライト（赤文字にする）
    product[fieldKey] = newValue !== originalText ? highlightDiff(originalText, newValue) : newValue

    // 編集モードを解除（編集対象フィールドをリセット）
    product.editingField = null

    // 編集後は青背景を維持するため、選択状態を強制ON
    product.selectedFields.add(fieldKey)
  }
  // 編集状態をリセット
  currentlyEditing.value = null
}

// --------------------
/**
 * ヘッダ項目をクリックしたときの処理
 * - シングルクリック：選択状態をトグル（青 ⇄ 解除）
 * - ダブルクリック：選択状態を強制ON＋編集モード開始
 */
function handleTextClick(event, fieldKey) {
  event.stopPropagation();// この要素内でクリックを完結させ、親要素やドキュメントへの伝播を防ぐ

  // クリックで選択ON⇄OFF（トグル）
  if (selectedFields.value.has(fieldKey)) {
    selectedFields.value.delete(fieldKey);
  } else {
    selectedFields.value.add(fieldKey);
  }

  // ダブルクリック検出：編集開始＋選択状態を強制ON
  if (clickTimeout.value) {
    clearTimeout(clickTimeout.value);// ダブルクリックでは必ずON（青）にする
    clickTimeout.value = null;
    selectedFields.value.add(fieldKey); // ←ダブルクリックでは必ず選択状態に
    startEditing('header', fieldKey, event.currentTarget);

  // シングルクリック遅延処理（ダブルクリック検出のため）
  } else {
    clickTimeout.value = setTimeout(() => {
      clickTimeout.value = null;
    }, 300);
  }
}

// --------------------
/**
 * 商品明細のセルをクリックしたときの処理
 * - シングルクリック：選択状態をトグル（青 ⇄ 解除）
 * - ダブルクリック：選択状態を強制ON＋編集モード開始
 */
function handleProductTextClick(event, product, fieldKey) {
  event.stopPropagation();// この要素内でクリックを完結させ、親要素やドキュメントへの伝播を防ぐ

   // クリックで選択ON⇄OFF（トグル）
  if (product.selectedFields.has(fieldKey)) {
    product.selectedFields.delete(fieldKey);
  } else {
    product.selectedFields.add(fieldKey);
  }

  if (clickTimeout.value) {
    clearTimeout(clickTimeout.value);// ダブルクリックでは必ずON（青）にする
    clickTimeout.value = null;
    product.selectedFields.add(fieldKey); // ←ダブルクリックでは必ず選択状態に
    startEditing('product', fieldKey, event.currentTarget, product);

  // シングルクリック遅延処理（ダブルクリック検出のため）
  } else {
    clickTimeout.value = setTimeout(() => {
      clickTimeout.value = null;
    }, 300);
  }
}

// --------------------
// キーダウン（ヘッダ・商品共通）
function handleKeydown(event) {
  if (event.key === 'Enter' || event.key === 'Escape') {
    finishEditing()
  }
}
// --------------------
// blur（ヘッダ・商品共通）
function handleInputBlur() {
  finishEditing()
}
// --------------------
// 入力処理（全角→半角変換を適用）
function handleInput(event) {
  const convertedValue = toHalfWidth(event.target.value);
  event.target.value = convertedValue;
}
// --------------------

// 商品追加
const addProduct = (index) => {
  const newProduct = { productName: '', diameter1: '', diameter2: '', diameter3: '', quantity: '', bikou: '' }

  products.value.splice(index + 1, 0, {
    ...newProduct,
    originalData: { ...newProduct },
    editingField: null,
    selectedFields: new Set()
  })
}

// 商品削除
const deleteProduct = index => products.value.splice(index, 1)


//*********************

// 検索モーダル
const openSearchModal = (type, product = null) => {
  modalType.value = type;
  targetProduct.value = product;

  // 現在編集中として扱う
  if (type === 'product' && product) {
    currentlyEditing.value = { type: 'product', fieldKey: 'productName', product };
  } else {
    const map = { customer: 'customerName', shipping: 'shippingName' };
    currentlyEditing.value = { type: 'header', fieldKey: map[type] };
  }
};

const closeSearchModal = () => modalType.value = null

// 戻るモーダル制御
const openBackModal = () => showBackModal.value = true
const closeBackModal = () => showBackModal.value = false
const handleBackConfirm = () => { showBackModal.value = false; router.push({ path: '/ocr' }) }

// 検索モーダルで得意先、出荷先、商品を選んだとき
const handleSearchSelect = ({ name, cd }) => {  // cd を受け取る
  //商品
  if (modalType.value === 'product' && targetProduct.value) {

    const p = targetProduct.value;
    // 元値を取得（ハイライト判定に必要）
    const originalText = p.originalData.productName || '';
    // 値をセット
    p.productName = name !== originalText ? highlightDiff(originalText, name) : name;
    p.productCD = cd;
    // ★追加：originalData を更新しないと差分判定できない
    p.originalData.productName = originalText;
    // 選択状態（青背景）
    p.selectedFields.add('productName');

  //得意先、出荷先
  } else {
     //モーダルの種類
    const fieldMap = { customer: 'customerName', shipping: 'shippingName' }
    const key = fieldMap[modalType.value]
    if (!key) return

    //CD
    if (modalType.value === 'customer') {
      editableData.value.customerCD = cd //得意先CD
    }
    if (modalType.value === 'shipping') {
      editableData.value.shippingCD = cd //出荷先CD
    }  
    //初期化
    if (!editableData.value[key]) editableData.value[key] = ''
    //元データ(OCRで最初に読み取った値)を取得
    const originalText = originalData.value[key] || ''
    //差分判定(赤文字)して反映
    editableData.value[key] = name !== originalText ? highlightDiff(originalText, name) : name
    //選択状態にする（青背景）
    selectedFields.value.add(key)
  }
  closeSearchModal() //モーダル閉じる
}


// 登録　受注入力データ変換　*****************************************************************************

// ====================
// 重複得意先/出荷先/商品モーダル関連
// ====================
const showChoiceModal = ref(false);
const duplicateChoices = ref([]);
const duplicateName = ref('');
const selectedCD = ref(null); //選択したCD
const selectedName = ref(''); //選択した名前
let duplicateProductTarget = null;
const duplicateType = ref('');

//重複データ
const cdKey = computed(() => duplicateType.value + 'CD')
const nameKey = computed(() => duplicateType.value + '名')

//選択した値
const handleSelect = (choice) => {
  // console.log('選択されたもの:', choice);
  selectedCD.value = choice[cdKey.value];
  selectedName.value = choice[nameKey.value];
  // console.log('selectedCD.value:', selectedCD.value);
};

//重複エラーモーダル表示
const handleChoiceConfirm = async () => {
  // console.log('確認時:', { target: duplicateProductTarget, selectedform: selectedCD.value });

  //エラー表示(得意先/出荷先/商品が選択されていない)
  if (duplicateType.value === '商品') {
    // console.log('duplicateProductTarget:',duplicateProductTarget, 'selectedCD.value:',selectedCD.value)
    if (!duplicateProductTarget || !selectedCD.value) {
      ElNotification({
        title: 'エラー',
        message: '商品を選択してください',
        type: 'warning'
      });
      return;
    }
  } else {
    if (!selectedCD.value) {
      // console.log('selectedCD.value:',selectedCD.value)
      ElNotification({
        title: 'エラー',
        message: nameKey.value + 'を選択してください',
        type: 'warning'
      });
      return;
    }
  }

  // ★ 得意先CDを確実に反映
  if (duplicateType.value === '得意先') {
    editableData.value.customerCD = selectedCD.value;
  }
  if (duplicateType.value === '出荷先') {
    editableData.value.shippingCD = selectedCD.value;
  }
  if (duplicateType.value === '商品') {
    duplicateProductTarget.productCD = selectedCD.value;
  }

  selectedCD.value = null
  showChoiceModal.value = false;//モーダル閉じる

  //商品が重複していた場合、詳細選択後の表示
  ElNotification({
   title: nameKey.value +'選択完了',
   message: '再度「受注入力データ変換」を実行してください',
   type: 'success'
 });
};

// ====================


// ====================
// 登録用データ生成
// ====================
const buildRequestPayload = () => {
  // ヘッダー部分
  const headerData = Object.fromEntries(
    Object.entries(editableData.value).map(([k, v]) => [
      k,
      toHalfWidth(stripTags(v ?? '')),
    ])
  )
  const clean = v => toHalfWidth(stripTags(v ?? ''))

  // console.log('customerCD:',editableData.value.customerCD);
  // console.log('shippingCD:',editableData.value.shippingCD);

  return {
    HD受注伝票_OCR_ID: item.value['HD受注伝票_OCR_ID'],
    管轄部門CD: item.value['管轄部門CD'],
    受注NO: '',
    customerName: clean(editableData.value.customerName),
    shippingName: clean(editableData.value.shippingName),
    customerCD: editableData.value.customerCD || '', //得意先CD
    shippingCD: editableData.value.shippingCD || '', //出荷先CD
    ...headerData,
    products: products.value.map(p => ({
      商品名: clean(p.productName),
      商品CD: p.productCD || '',
      呼び径1: clean(p.diameter1),
      呼び径2: clean(p.diameter2),
      呼び径3: clean(p.diameter3),
      数量: clean(p.quantity),
      備考: clean(p.bikou),
    })),
  }
}


// 登録処理　受注入力データ変換
const regist = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true

  try {
    // ヘッダバリデーション
    for (const [fieldKey, value] of Object.entries(editableData.value)) {
      const { isValid, errorMessage } = validateField(fieldKey, stripTags(value))
      if (!isValid) {
        ElNotification({ title: 'エラー', message: errorMessage, type: 'error' })
        return
      }
    }

    // 商品バリデーション
    for (const product of products.value) {
      for (const fieldKey of ['productName','diameter1','diameter2','diameter3','quantity','bikou']) {
        const { isValid, errorMessage } = validateField(fieldKey, (product[fieldKey] || '').toString())
        if (!isValid) {
          ElNotification({ title: 'エラー', message: errorMessage, type: 'error' })
          return
        }
      }
    }

    // ペイロード作成
    const payload = buildRequestPayload()
    if (!payload) throw new Error('伝票情報がありません')
    //D受注伝票OCR登録
    const res1 = await axios.post('/api/converorder-ocr/create', payload)
    registeredOrderNo.value = res1.data?.orderNo
    showModal.value = true

  } catch (error) {
    const data = error.response?.data
    // === 重複商品モーダル用 ===
    if (data?.reason === 'データ重複') {
      duplicateName.value = data.name
      duplicateChoices.value = data.choices
      duplicateType.value = data.type
      showChoiceModal.value = true
      //商品のみ対応
      if (duplicateType.value === '商品') {
        duplicateProductTarget = products.value[data.rowIndex]
        duplicateProductRowIndex.value = data.rowIndex
      }
      return
    }
    // 通常エラー
    const msg = data?.error || data?.message || '登録に失敗しました'
    ElNotification({ title: 'エラー', message: msg, type: 'error' })
    console.log('登録エラー:', msg)
  } finally {
    isSubmitting.value = false // 通常エラーでも戻す
  }
}
// ====================

//********************************************************************************************* */

//今どの項目を編集しているかで変換名を動的に取得
const currentConvertValue = computed(() => {
  if (currentlyEditing.value?.type === 'header') {
    const key = currentlyEditing.value.fieldKey;
    return stripTags(editableData.value[key] || '');
  }
  if (currentlyEditing.value?.type === 'product') {
    const p = currentlyEditing.value.product;
    return stripTags(p.productName || '');
  }
  return '';
});


//初期読み込み
const reLoadItems = async () => {
  loadingActive.value = true
  try {
    await Promise.all([reLoadItem(), reLoadItem2()])
  } catch (e) {
    console.error(e)
  } finally {
    loadingActive.value = false
  }
}

// 初期化(画面が表示されたらAPIからデータを取ってくる)
onMounted(() => {
  //編集
  document.addEventListener('click', e => {
    if (!e.target.closest('.editable-text') && !e.target.classList.contains('edit-input')) finishEditing()
  })

  //リサイズ
  const resizer = document.getElementById("resizer");
    const leftPanel = document.getElementById("ocr-left");
    const rightPanel = document.getElementById("ocr-right");
    const container = document.getElementById("ocr-container");

    let isResizing = false;
    let startX = 0;
    let startLeftWidth = 0;

    // リサイズ開始
    resizer?.addEventListener("mousedown", function(e) {
      e.preventDefault();
      isResizing = true;
      startX = e.clientX;
      startLeftWidth = leftPanel.offsetWidth;

      // リサイズ中のスタイルを適用
      document.body.classList.add("resizing");
      document.body.style.cursor = "col-resize";
      document.body.style.userSelect = "none";
    });

    // リサイズ中
    document.addEventListener("mousemove", function(e) {
      if (!isResizing) return;
      e.preventDefault();

      const containerRect = container.getBoundingClientRect();
      const containerWidth = containerRect.width;
      const resizerWidth = resizer.offsetWidth;

      // 新しい左パネルの幅を計算
      const deltaX = e.clientX - startX;
      let newLeftWidth = startLeftWidth + deltaX;

      // 最小・最大幅
      const minLeftWidth = 300; // 左パネルの最小幅
      const minRightWidth = 300; // 右パネルの最小幅
      const maxLeftWidth = containerWidth - minRightWidth - resizerWidth;

      newLeftWidth = Math.max(minLeftWidth, Math.min(newLeftWidth, maxLeftWidth));

      // 幅を適用
      leftPanel.style.flex = "none";
      leftPanel.style.width = newLeftWidth + "px";

      rightPanel.style.flex = "1";
      rightPanel.style.width = "auto";

      // console.log('Left width:', newLeftWidth, 'Container width:', containerWidth);
    });

    // リサイズ終了
    document.addEventListener("mouseup", function() {
      if (!isResizing) return;
      isResizing = false;

      document.body.classList.remove("resizing");
      document.body.style.cursor = "";
      document.body.style.userSelect = "";
    });

    // ウィンドウリサイズ時の対応
    window.addEventListener("resize", function() {
      if (leftPanel.style.width && leftPanel.style.width !== "auto") {
        const containerWidth = container.offsetWidth;
        const currentLeftWidth = parseInt(leftPanel.style.width);
        const resizerWidth = resizer.offsetWidth;
        const minRightWidth = 200;

        if (currentLeftWidth > containerWidth - minRightWidth - resizerWidth) {
          leftPanel.style.width = (containerWidth - minRightWidth - resizerWidth) + "px";
        }
      }
    });

    //伝票データ取得
    reLoadItems()
})
</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>販売管理</li>
        <li><router-link to="/ocr">受注入力_OCR</router-link></li>
        <li>OCR読み取り 確認画面</li>
      </ol>

      <!-- ローディング画面 -->
      <div v-if="loadingActive" class="loading-wrap">
        <span>読み込み中...</span>
      </div>

      <div v-show="!loadingActive">
        <form>
          <div class="contents_head">
            <h5 class="card-title">OCR読み取り 確認画面</h5>
          </div>

          <div class="ocr-layout" id="ocr-container">
            <div class="ocr-left" id="ocr-left">
               <embed
                v-if="item && item.PDF"
                :src="`/storage/ocr_orders/${item.PDF}`"
                type="application/pdf"
                width="100%"
                height="100%"
              />
            </div>

            <!-- リサイズバー -->
            <div class="resizer" id="resizer"></div>

            <div class="ocr-right page" id="ocr-right">
              <!-- 希望出荷日 -->
              <div class="col-lg-12">
                <div class="label-col">
                  <label class="col-form-label">希望出荷日</label>
                </div>
                <div
                  class="editable-text"
                  :class="{ editing: currentlyEditing?.fieldKey === 'shippingDate', selectedform: selectedFields.has('shippingDate') }"
                  @click="handleTextClick($event, 'shippingDate')"
                >
                  <input
                    v-if="currentlyEditing?.fieldKey === 'shippingDate'"
                    class="edit-input"
                    type="text"
                    :value="editableData.shippingDate.replace(/<[^>]*>/g, '')"
                    @keydown="handleKeydown"
                    @blur="handleInputBlur"
                    @input="handleInput"
                    @click.stop
                  />
                  <span v-else v-html="editableData.shippingDate"></span>
                </div>
              </div>

              <!-- 得意先名 -->
              <div class="col-lg-12">
                <div class="label-col">
                  <label class="col-form-label">得意先名</label>
                  <i
                    class="fa-solid fa-magnifying-glass pointer text-primary"
                    @click.stop="openSearchModal('customer')"
                    title="得意先選択"
                  ></i>
                </div>
                <div
                  class="editable-text"
                  :class="{ editing: currentlyEditing?.fieldKey === 'customerName', selectedform: selectedFields.has('customerName') }"
                  @click="handleTextClick($event, 'customerName')"
                >
                  <input
                    v-if="currentlyEditing?.fieldKey === 'customerName'"
                    class="edit-input"
                    type="text"
                    :value="editableData.customerName.replace(/<[^>]*>/g, '')"
                    maxlength="255"
                    @keydown="handleKeydown"
                    @blur="handleInputBlur"
                    @click.stop
                  />
                  <span v-else v-html="editableData.customerName"></span>
                </div>
              </div>

              <!-- 出荷先名 -->
              <div class="col-lg-12">
                <div class="label-col">
                  <label class="col-form-label mb-0">出荷先名</label>
                  <i
                    class="fa-solid fa-magnifying-glass pointer text-primary"
                    @click.stop="openSearchModal('shipping')" 
                    title="出荷先選択"
                  ></i>
                </div>
                <div
                  class="editable-text"
                  :class="{ editing: currentlyEditing?.fieldKey === 'shippingName', selectedform: selectedFields.has('shippingName') }"
                  @click="handleTextClick($event, 'shippingName')"
                >
                  <input
                    v-if="currentlyEditing?.fieldKey === 'shippingName'"
                    class="edit-input"
                    type="text"
                    :value="editableData.shippingName.replace(/<[^>]*>/g, '')"
                    maxlength="255"
                    @keydown="handleKeydown"
                    @blur="handleInputBlur"
                    @click.stop
                  />
                  <span v-else v-html="editableData.shippingName"></span>
                </div>
              </div>

              <!-- その他編集可能フィールド -->
              <div class="col-lg-12"
                v-for="(label, key) in {
                  deliveryDate: '送状印字（納期）',
                  orderNo: '相手先注文No',
                  saturdayDelivery: '土曜指定',
                  shippingNote: '出荷先用',
                  salesNote: '営業用備考'
                }"
                :key="key"
              >
              <div class="label-col">
                <label class="col-form-label">{{ label }}</label>
              </div>
                <div
                  class="editable-text"
                  :class="{ editing: currentlyEditing?.fieldKey === key, selectedform: selectedFields.has(key) }"
                  @click="handleTextClick($event, key)"
                >
                  <input
                    v-if="currentlyEditing?.fieldKey === key"
                    class="edit-input"
                    type="text"
                    :value="editableData[key].replace(/<[^>]*>/g, '')"
                    @keydown="handleKeydown"
                    @blur="handleInputBlur"
                    @input="handleInput"
                    @click.stop
                  />
                  <span v-else v-html="editableData[key]"></span>
                </div>
              </div>

              <!--
              *******************
                  商品一覧表示  
              *******************
              *-->

              <!-- 商品テーブル -->
              <div class="col-lg-12 product-info-block">
                <table class="product-table">
                  <thead>
                    <tr class="product-header">
                      <th rowspan="2"></th>
                      <th rowspan="2" colspan="2">商品名</th>
                      <th colspan="3">呼び径</th>
                      <th rowspan="2">数量</th>
                      <th rowspan="2">備考</th>
                      <th rowspan="2">操作</th>
                    </tr>
                    <tr class="product-header">
                      <th>1</th>
                      <th>2</th>
                      <th>3</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="(product, index) in products" :key="`product-${index}`">
                      <!-- 行番号 -->
                      <td>{{ index + 1 }}</td>

                      <!-- 商品名 -->
                      <td class="product-name-cell">
                        <div
                          class="editable-text"
                          :class="{ editing: currentlyEditing?.fieldKey === 'productName' && currentlyEditing?.product === product, selectedform: product.selectedFields.has('productName') }"
                          @click="handleProductTextClick($event, product, 'productName')"
                        >
                        <input
                          v-if="currentlyEditing?.fieldKey === 'productName' && currentlyEditing?.product === product"
                          class="edit-input"
                          :value="stripTags(product.productName)"
                          @keydown="handleKeydown"
                          @blur="handleInputBlur"
                          maxlength="255"
                        />
                          <span v-else v-html="product.productName"></span>
                        </div>
                      </td>

                      <!-- 検索アイコン -->
                      <td class="search-cell">
                        <i
                          class="fa-solid fa-magnifying-glass search-icon text-primary"
                          @click.stop="openSearchModal('product', product)"
                          title="商品選択"
                        ></i>
                      </td>

                      <!-- 呼び径1～3 -->
                      <td v-for="key in ['diameter1','diameter2','diameter3']" :key="key">
                        <div
                          class="editable-text qty-input"
                          :class="{ editing: currentlyEditing?.fieldKey === key && currentlyEditing?.product === product, selectedform: product.selectedFields.has(key) }"
                          @click="handleProductTextClick($event, product, key)"
                        >
                          <input
                            v-if="currentlyEditing?.fieldKey === key && currentlyEditing?.product === product"
                            class="edit-input"
                            maxlength="4"
                            :value="stripTags(product[key])"
                            @keydown="handleKeydown"
                            @blur="handleInputBlur"
                            @input="handleInput"
                          />
                          <span v-else v-html="product[key]"></span>
                        </div>
                      </td>

                      <!-- 数量 -->
                      <td>
                        <div
                          class="editable-text qty-input"
                          :class="{ editing: currentlyEditing?.fieldKey === 'quantity' && currentlyEditing?.product === product, selectedform: product.selectedFields.has('quantity') }"
                          @click="handleProductTextClick($event, product, 'quantity')"
                        >
                          <input
                            v-if="currentlyEditing?.fieldKey === 'quantity' && currentlyEditing?.product === product"
                            class="edit-input"
                            maxlength="10"
                            :value="stripTags(product.quantity)"
                            @keydown="handleKeydown"
                            @blur="handleInputBlur"
                            @input="handleInput"
                          />
                          <span v-else v-html="product.quantity"></span>
                        </div>
                      </td>

                      <!-- 備考 -->
                      <td>
                        <div
                          class="editable-text qty-input"
                          :class="{ editing: currentlyEditing?.fieldKey === 'bikou' && currentlyEditing?.product === product, selectedform: product.selectedFields.has('bikou') }"
                          @click="handleProductTextClick($event, product, 'bikou')"
                        >
                          <input
                            v-if="currentlyEditing?.fieldKey === 'bikou' && currentlyEditing?.product === product"
                            class="edit-input"
                            maxlength="30"
                            :value="stripTags(product.bikou)"
                            @keydown="handleKeydown"
                            @blur="handleInputBlur"
                          />
                          <span v-else v-html="product.bikou"></span>
                        </div>
                      </td>

                      <!-- 操作 -->
                      <td>
                        <div class="pluralBtn-container">
                          <input type="button" value="＋" class="add pluralBtn" @click="addProduct(index)">
                          <input type="button" value="－" class="del pluralBtn" @click="deleteProduct(index)">
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>

          <div class="col-sp-12 btn_center ma_top_a line_up center center_a ma_top_b">
            <div class="button_r back none od_b delete_btn" @click="openBackModal">戻る</div>
            <button 
              type="button" 
              @click.prevent="regist" 
              class="button_r none search od_a to ocr_btn" 
              :class="{ 'bo_btn syo delete_btn disabled': !item || item['OCR受注伝票NO'] !== null }"
              :disabled="isSubmitting || !item || item['OCR受注伝票NO'] !== null"
            >{{ item && item['OCR受注伝票NO'] !== null ? '変換済み' : '受注入力データ変換' }}</button>
          </div>
        </form>
      </div>
    </div>
    <!-- 検索モーダル -->
    <Search
      v-if="modalType"
      :type="modalType"
      :conversionText="currentConvertValue"
      @close="closeSearchModal"
      @selected="handleSearchSelect"
    />


    <!-- 戻るモーダル -->
    <div v-if="showBackModal" class="modal_wrap">
      <div class="modal_inner s return_modal_inner">
        <div class="signup_form">
          <h5>編集データが破棄されますが、<br>戻ってもよろしいでしょうか？</h5>
        </div>
        <div class="btn_space_modal">
          <div class="submit_btn_no close_icon" @click="closeBackModal">いいえ</div>
          <div class="submit_btn_yes id_btn_yes" @click="handleBackConfirm">はい</div>
        </div>
      </div>
    </div>

    <!-- 得意先CD or 出荷先CD or 商品CD重複した場合:選択モーダル -->
    <div class="modal_wrap" v-if="showChoiceModal">
      <div class="modal_inner pop_width">
        <p class="pop_ttl txt_blue" v-if="duplicateType === '商品'">{{ duplicateProductRowIndex + 1 }} 行目）商品名「{{ duplicateName }}」に対して、どの商品CDを使用しますか？</p>
        <p class="pop_ttl txt_blue" v-else>{{ nameKey }}「{{ duplicateName }}」に対して、どの{{ cdKey }}を使用しますか？</p>
        <div class="table-wrapper scrollable-table">
          <table class="table_w pop_table hover-highlight">
            <thead>
              <tr class="head">
                <th>{{ cdKey }}</th>
                <th>{{ nameKey }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="choice in duplicateChoices"
                :key="choice[cdKey]"
                :class="{ selectedform: selectedCD === choice[cdKey] }"
                @click="handleSelect(choice)"
                class="pointer selectable-row"
              >
                <td>{{ (choice[cdKey] || '').trim() }}</td>
                <td>{{ (choice[nameKey] || '').trim() }}</td>              
              </tr>
            </tbody>
          </table>
        </div>
        <div class="btn_space_modal">
          <div class="submit_btn_no close_icon" @click="showChoiceModal = false">キャンセル</div>
          <div class="submit_btn_yes" @click="handleChoiceConfirm">OK</div>
        </div>
      </div>
    </div>


    <!-- ======= 受注入力データ変換完了ポップアップ ======= -->
    <div v-if="showModal" class="modal_wrap">
      <div class="modal_inner pop_width">
        <p class="pop_ttl txt_blue">受注No.{{registeredOrderNo}}にて仮登録されました<br>
          基幹システムにて本登録してください</p>
          <div class="btn_space_modal">
            <div class="submit_btn_no close_icon" @click="handleBackConfirm">一覧に戻る</div>
          </div>                   
      </div>
    </div>  

  </section>
</template>

<style scoped>
.loading-wrap {
  position: fixed;
  width: 100vw;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255, 255, 255, 0.8);
  z-index: 2;
  font-size: 1.5em;
}
.button_r { width: 200px; }

/* 背景はクリック・スクロールOK */
.modal_wrap {
  position: fixed;/* スクロールしても位置固定 */
  inset: 0;/* top:0; right:0; bottom:0; left:0; */
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  pointer-events: none; /* 背景スクロール・クリックを許可したいので */
}
/* 中身（モーダル本体）は操作できるようにする */
.modal_wrap > * {
  pointer-events: auto;
}
/* 全体をスクロールできるように */
.dashboard {
    min-height: 1500px;
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