<script setup>
import axios from 'axios'
import { ref, onMounted } from 'vue'

// 親からのプロパティ
const props = defineProps({
  parentLoading: { type: Boolean, default: false },
})
const emit = defineEmits(['search'])

const status = ref(0)
const isLoading = ref(false)
const sections = ref([])
const section = ref('')
const syozokubumonCD = ref(null)/* ログインユーザー所属部門CD */
const savedFilters = JSON.parse(sessionStorage.getItem('orderFilters') || '{}')/** 検索セッション復元 */

/* =====================
 * 部門一覧取得 & 初期営業所決定
 * ===================== */
const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/departments/getSalesOffice')
    sections.value = response.data

    /* ① セッション最優先 */
    if ('sales_office' in savedFilters) {
      section.value = String(savedFilters.sales_office)
      // console.log("初期：セッション反映 →", section.value)
      return
    }

    /* ② ログインユーザー所属部門 */
    if (syozokubumonCD.value != null) {
      const my = String(syozokubumonCD.value)
      const exists = sections.value.some(s => String(s.部門CD) === my)
      if (exists) {
        section.value = my
        // console.log("初期：ログイン所属営業所 →", section.value)
        return
      }
    }

    /* ③ フォールバック */
    if (sections.value.length > 0) {
      section.value = String(sections.value[0].部門CD)
      // console.log("初期：フォールバック先頭 →", section.value)
    }

  } catch (e) {
    console.error("部門取得失敗:", e)
  }
}


/* =====================
 * 検索
 * ===================== */
const searchOrder = () => {
  const params = {
    sales_office: Number(section.value),
    status: Number(status.value)
  }
  sessionStorage.setItem('orderFilters', JSON.stringify(params))
  emit('search', params)
}


/* =====================
 * PDFアップロード&OCR処理
 * ===================== */
const showUploadModal = ref(false)
const uploadFiles = ref([])
const isDragOver = ref(false)
const fileInput = ref(null)
const MAX_PDF_COUNT = 20

const uploadPdf = async () => {
  if (uploadFiles.value.length === 0) {
    alert('PDFを選択してください')
    return
  }

  if (uploadFiles.value.length > MAX_PDF_COUNT) {
    alert(`PDFは最大${MAX_PDF_COUNT}件までアップロードできます`)
    return
  }

  const formData = new FormData()
  formData.append('sales_office', Number(section.value))

  uploadFiles.value.forEach(file => {
    formData.append('pdfs[]', file)
  })

  isLoading.value = true  //★読み込み中

  try {
    const res = await axios.post('/api/orderslip-ocr/upload', formData)

    isLoading.value = false
    showUploadModal.value = false

    if (res.data.errors?.length > 0) {
      alert(
        '以下のPDFでエラーが発生しました。\n\n' +
        res.data.errors.join('\n')
      )
      return
    }
    const processed = res.data.processed ?? 0
    const duplicate = res.data.duplicate_deleted ?? 0

    let message = `${processed}件アップロードしました。`

    if (duplicate > 0) {
      message += `（重複・既に登録済みのファイルが${duplicate}件あったため削除しました）`
    }

    alert(message) //アラート
    window.location.reload() //画面更新

  } catch (e) {
    console.error('アップロードエラー', e)

    // Axios の場合（ほぼこれ）
    if (e.response) {
      console.error('status', e.response.status)
      console.error('data', e.response.data)
      console.error('headers', e.response.headers)

      alert(
        e.response.data?.errors?.join('\n')
        ?? 'アップロードまたはOCR処理に失敗しました'
      )

    } else if (e.request) {
      console.error('request', e.request)
      alert('サーバーから応答がありません')

    } else {
      console.error('message', e.message)
      alert(e.message)
    }
  }
  finally {
    isLoading.value = false
  }
}

/* =====================
 * Drag & Drop
 * ===================== */
const addFiles = (files) => {
  for (const file of files) {
    if (uploadFiles.value.length >= MAX_PDF_COUNT) {
      alert(`PDFは最大${MAX_PDF_COUNT}件までです`)
      break
    }
    if (file.type !== 'application/pdf') {
      alert(`PDF以外は不可: ${file.name}`)
      continue
    }
    if (uploadFiles.value.some(f => f.name === file.name && f.size === file.size)) {
      continue
    }
    uploadFiles.value.push(file)
  }
}
const onDrop = (e) => {
  isDragOver.value = false
  addFiles(e.dataTransfer.files)
}
const onFileChange = (e) => {
  addFiles(e.target.files)
  fileInput.value.value = ''
}
const removeFile = (i) => uploadFiles.value.splice(i, 1)
const onDragOver = (e) => { e.preventDefault(); isDragOver.value = true }
const onDragLeave = (e) => {
  if (!e.currentTarget.contains(e.relatedTarget)) isDragOver.value = false
}


/* =====================
 * 初期処理（1回のみ）
 * ===================== */
onMounted(async () => {

  // 検索セッションの復元
  if ('status' in savedFilters) status.value = savedFilters.status
  // ① まずログインユーザーの所属部門CDをセッションから取得
  try {
    const res = await axios.get('/api/auth/userinfo')
    syozokubumonCD.value = res.data.所属部門CD
    // console.log("Filter 所属部門CD:", syozokubumonCD.value)
  } catch (e) {
    console.error("Filter ユーザー情報取得エラー", e)
  }

  // ② その上で部門一覧を取りに行き、初期選択を決める
  await fetchDepartments()
  // ★ 初期値が全部そろった時点で検索を発火
  // searchOrder()

})




</script>


<template>
  <!-- ローディング表示 -->
<div v-if="isLoading" class="loading-overlay">
  <div class="loading-box">
    <div class="spinner"></div>
    <p>アップロード中です…<br>しばらくお待ちください</p>
  </div>
</div>

  <div class="col-lg-12">
      <div class="card">
        <details class="contents_head" open @toggle="toggleIcon">
          <summary class="send_f">             
            <h5 class="card-title">検索条件</h5>
            <i class="bi bi-arrow-up"></i>
          </summary>
          <form action="">
            <div class="search_send bo_none">
              <div class="row align-center two space_b space_d">                
                <div class="col-lg-3 form_r flex">
                  <label for="inputEmail" class="col-form-label label_w">営業所</label>                
                  <select class="form-select normal" v-model="section">
                    <option v-for="sec in sections" :key="sec.部門CD" :value="String(sec.部門CD)">
                      {{ sec.部門CD }}:{{ sec.部門名 }}
                    </option>
                  </select> 
                </div>  

                <div class="col-lg-3 form_r flex">
                  <label for="inputEmail" class="col-form-label label_wm">チェック状態</label>                
                  <select class="form-select normal" v-model="status">
                    <option style="color:#050B15;" value="0">未確認</option>
                    <option style="color:#050B15;" value="1">確認済</option>
                    <option style="color:#050B15;" value="2">全て</option>
                  </select>              
                </div>             

                <div class="col-lg-3 button-group">
                  <div class="col-lg-3 btn_center ma_btm_a center_a">
                    <button type="button" class="button_r none search" @click="searchOrder" :disabled="isLoading || parentLoading">検索</button>
                  </div>
                  <div class="col-lg-5 btn_center ma_btm_a center_a">
                    <button type="button" class="button_a none" @click.prevent="showUploadModal = true" :disabled="isLoading">アップロード</button>
                  </div>
                </div>          

                </div>          
              </div>
            </form>        
        </details>  
      </div>
  </div>


  <!-- PDFアップロードモーダル -->
  <teleport to="body">
    <div v-if="showUploadModal" class="modal-overlay">
      <div class="pdf-modal">
        <h5>注文書PDFアップロード</h5>
        <div
          class="drop-area"
          :class="{ over: isDragOver }"
          @dragover="onDragOver"
          @dragleave="onDragLeave"
          @drop.prevent="onDrop"
        >
          <p v-if="uploadFiles.length === 0">ここにPDFをドラッグ＆ドロップ（20個まで）<br>または</p>
          <ul v-else class="file-list">
            <li v-for="(file, index) in uploadFiles" :key="index">
              <span class="file-name">📄 {{ file.name }}</span>
              <button type="button" class="remove" @click="removeFile(index)">✕</button>
            </li>
          </ul>
          <input
            type="file"
            accept="application/pdf"
            multiple
            hidden
            ref="fileInput"
            @change="onFileChange"
          >
          <button type="button" class="bo_btn d" @click="fileInput.click()">ファイル選択</button>
        </div>
        <div class="modal-actions">
          <button type="button" class="bo_btn se d" @click="uploadPdf">アップロード</button>
          <button type="button" class="bo_btn gr d" @click="showUploadModal = false">キャンセル</button>
        </div>
      </div>
    </div>
  </teleport>

</template>




<style scoped>
.table_w th, .table_w td {
    padding: 2px 2px 2px 10px;
}
@media (min-width: 992px) {
    .col-lg-3 {
        width: 30%;
    }
}
@media screen and (min-width: 591px) {
    .table_w {
        min-width: 1000px;
    }
}

/* 読み込み中 */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0,0,0,0.4);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.loading-box {
  background: #fff;
  padding: 30px 40px;
  border-radius: 8px;
  text-align: center;
}
.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 15px;
  border: 4px solid #ddd;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
