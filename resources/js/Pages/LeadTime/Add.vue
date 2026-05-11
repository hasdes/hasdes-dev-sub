<script setup>
import { onMounted, ref } from "vue"
import jspreadsheet from "jspreadsheet-ce"
import "jspreadsheet-ce/dist/jspreadsheet.css"
import axios from "axios"
import { useRouter } from 'vue-router'; // ← 追加
const router = useRouter(); // ← ここで router を取得

defineProps({
  authItems: Array
})

const sheet = ref(null)
let table = null

// DB保存
const save = async () => {
  const data = table[0].getData().filter(r => r[0]) //空行除外

  const res = await axios.post("/api/excel/insert", {
    rows: data
  })
  // エラーセル色付け
  if(res.data.errors){
    res.data.errors.forEach(err=>{
      table[0].setStyle(err.cell,"background-color","#ffcccc")
    })
  }else{
    alert("保存しました")
    table[0].setData([]) //保存後クリア
  }
}

onMounted(() => {
  // テーブル高さ設定
  const getTableHeight = () => {
    if (window.innerWidth <= 768) {
      return "400px" // スマホ
    } else if (window.innerWidth <= 1600) {
      return "550px" // タブレット,ノートpc
    } else {
      return "700px" // PC 大型
    }
  }

  table = jspreadsheet(sheet.value, {
    contextMenu: () => false,  // メニュー非表示
    worksheets: [{
        // minDimensions: [37, 1],
        minDimensions: [17, 1],
        allowInsertColumn: false,
        onbeforepaste: (worksheet, data) => {
          return data.map(row => row.slice(0, 17))
        },
        // freezeColumns: 5,   // 左5列固定
        freezeColumns: 5,   // 左5列固定
        freezeRows: 1,      // 1行目固定
        tableOverflow: true,   // ← これ必須
        tableHeight: getTableHeight(),
        tableWidth: "100%",   // ← これ追加
      
        // columns: [
        //   { type: "numeric", title: "工場CD", width: 100 },
        //   { type: "numeric", title: "商品CD", width: 100 },
        //   { type: "numeric", title: "呼び径1", width: 100 },
        //   { type: "numeric", title: "呼び径2", width: 100 },
        //   { type: "numeric", title: "呼び径3", width: 100 },
        //   { type: "numeric", title: "数量", width: 100 },
        //   // { type: "numeric", title: "GS造型", width: 100 },
        //   // { type: "numeric", title: "NB造型", width: 100 },
        //   // { type: "numeric", title: "LG造型", width: 100 },
        //   // { type: "numeric", title: "外注造型", width: 100 },
        //   { type: "numeric", title: "GSフラグ", width: 100 },
        //   { type: "numeric", title: "NBフラグ", width: 100 },
        //   { type: "numeric", title: "LGフラグ", width: 100 },
        //   { type: "numeric", title: "外注フラグ", width: 100 },
        //   // { type: "numeric", title: "ショット＆研掃", width: 100 },
        //   // { type: "numeric", title: "素管検査", width: 100 },
        //   // { type: "numeric", title: "修正", width: 100 },
        //   // { type: "numeric", title: "水圧", width: 100 },
        //   // { type: "numeric", title: "加工1", width: 100 },
        //   // { type: "numeric", title: "加工2", width: 100 },
        //   { type: "numeric", title: "加工2フラグ", width: 100 },
        //   // { type: "numeric", title: "グリッド原管", width: 100 },
        //   // { type: "numeric", title: "溶射", width: 100 },
        //   { type: "numeric", title: "グリッド容赦フラグ", width: 150 },
        //   // { type: "numeric", title: "内外塗装", width: 100 },
        //   // { type: "numeric", title: "組付完成検査", width: 100 },
        //   { type: "numeric", title: "素材あり納期", width: 120 },
        //   { type: "numeric", title: "素材なし納期", width: 120 },
        //   // { type: "numeric", title: "移送本社-九工", width: 100 },
        //   // { type: "numeric", title: "移送本社-東工", width: 100 },
        //   // { type: "numeric", title: "移送九工-東工", width: 100 },
        //   { type: "numeric", title: "問合数量上限", width: 120 },
        //   // { type: "numeric", title: "ｾﾞﾛ素材納期", width: 100 },
        //   { type: "numeric", title: "繁忙加算", width: 100 },
        //   // { type: "numeric", title: "予備項目1", width: 100 },
        //   // { type: "numeric", title: "予備項目2", width: 100 },
        //   // { type: "numeric", title: "予備項目3", width: 100 },
        //   { type: "text", title: "備考", width: 200 }
        // ],

        columns: [
          { type: "numeric", title: "工場CD", width: 80 },
          { type: "numeric", title: "商品CD", width: 80 },
          { type: "numeric", title: "呼び径1", width: 80 },
          { type: "numeric", title: "呼び径2", width: 80 },
          { type: "numeric", title: "呼び径3", width: 80 },
          { type: "numeric", title: "数量", width: 80 },
          { type: "numeric", title: "GSフラグ", width: 90 },
          { type: "numeric", title: "NBフラグ", width: 90 },
          { type: "numeric", title: "LGフラグ", width: 90 },
          { type: "numeric", title: "外注フラグ", width: 100 },
          { type: "numeric", title: "加工2フラグ", width: 110 },
          { type: "numeric", title: "グリッド容赦フラグ", width: 160 },
          { type: "numeric", title: "素材あり納期", width: 120 },
          { type: "numeric", title: "素材なし納期", width: 120 },
          { type: "numeric", title: "問合数量上限", width: 120 },
          { type: "numeric", title: "繁忙加算", width: 90 },
          { type: "text", title: "備考", width: 200 }
        ],

        // 行自動追加
        onchange:(instance,cell,x,y,value)=>{
            const lastRow = instance.getData().length - 1
            if(y === lastRow){
            instance.insertRow()
            }

        }
    }]
  })

  // window.confirmの上書きより前に元のconfirmを保存
  window.__originalConfirm = window.confirm
  window.confirm = (msg) => {
  const ja = msg
      .replace("Are you sure to delete the selected rows?", "選択した行を削除しますか？")
      .replace("Are you sure?", "よろしいですか？")
  return window.__originalConfirm(ja)
  }

  // Enterで下移動
  document.addEventListener("keydown",(e)=>{
    if(e.key === "Enter"){
      const ws = table[0]
      const sel = ws.getSelected()
      if(!sel) return
      const [x,y] = sel
      ws.setSelection(x,y+1)
      e.preventDefault()
    }
  })
})

const goToList = () => {
  router.push({ path: '/leadtime/'})
}

</script>

<template>
  <section class="section dashboard">
    <!-- ローディング画面 -->
    <div v-if="loadingActive" class="loading-wrap">
      <span>読み込み中...</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
      <ol class="breadcrumb mb-0">
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>情報表示</li>
        <li>
          <router-link to="/leadtime">目安納期</router-link>
        </li>
        <li>商品一括登録</li>
      </ol>
      <button type="submit" class="button_r back none od_b" @click.prevent="goToList()">
        閉じる
      </button>
    </div>
    <div class="col-lg-12" id="add-product-btn">
      <div class="card">
        <div class="contents_head">
          <h5 class="card-title">商品一括登録</h5>
        </div>
        <!-- Excelシート -->
        <div class="sheet-wrapper">
          <div ref="sheet"></div>
        </div>
        <div class="col-sp-12 btn_center line_up center save">
          <button type="submit" class="button_r none search od_a to" @click="save">保存</button>    
        </div>     
      </div>
    </div>
  </section>
</template>


<style scoped>
/* 親のカードからはみ出さないためのラッパー */
.sheet-wrapper {
  margin-top: 20px;
  width: 100%;
  /* 横スクロールを許可する設定 */
  overflow-x: auto;
  /* JSpreadsheetの枠線が切れないように少し余裕を持たせる */
  padding-bottom: 10px;
  padding: 0 10px;
}
/* JSpreadsheet自体のコンテナを親の幅に合わせる */
:deep(.jss_container) {
  max-width: 100%;
}
:deep(.jss_corner) {
  display: none !important;
}
/* 画面横幅が1600px以下の時だけ、少し小さくしてスクロールさせる設定 */
@media (max-width: 1600px) {
  .sheet-wrapper {
    zoom: 0.9; /* transformよりレイアウトが崩れにくい */
    overflow-x: auto;
  }
}
.save {
  margin: 10px 0;
}
</style>