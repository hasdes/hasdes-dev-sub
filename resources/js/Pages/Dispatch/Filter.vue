<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
// import { useRouter } from 'vue-router'
import { useRouter, useRoute } from 'vue-router' // ← useRoute を追加！

defineProps({
  authItems: Array
})

const route = useRoute()
const receivedBumonCd = computed(() => route.query.bumonCd)

const bases = ref([])  // 取得した拠点データを入れる配列
const selectedBase = ref('')

// ローディングとエラー状態の管理(API通信中の状態やエラー表示用。)
const loadingActive = ref(false)


// APIから拠点データを取得する関数
const fetchBases = async () => {

  loadingActive.value = true

  try {
    const response = await axios.get('/api/dispatch/filter')
    bases.value = response.data
  } catch (error) {
    console.error('拠点データの取得に失敗しました:', error)
  } finally {
    loadingActive.value = false
  }

}


//選択ボタン
const router = useRouter()
const handleSelect = async () => {
  if (!selectedBase.value) {
    alert('拠点を選択してください。')
    return
  }

  try {
    // 選択された拠点をセッションに保存
    await axios.post('/api/set-department-cd', {
      department_cd: String(selectedBase.value),
    });
    // List.vue に遷移
    router.push('/dispatch')
  } catch (error) {
    console.log('送信データ:', selectedBase.value)
    console.error('拠点のセッション保存に失敗しました:', error)
  }
}

// コンポーネントがマウントされたらデータ取得
// onMounted(() => { fetchBases() })
onMounted(async () => {
  await fetchBases()
  selectedBase.value = receivedBumonCd.value
})
</script>


<template>
  <section class="section dashboard">

    <!-- ★ 追加：ローディングオーバーレイ -->
    <div v-if="loadingActive" class="loading-wrap">
      <span>読み込み中...</span>
    </div>

      <div class="row">
        <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
           <li>情報表示</li>
          <li><router-link to="/dispatch">直送配車計画</router-link></li>
          <li>拠点</li>
        </ol>
        <div class="col-lg-12">
          <div class="card ma_btm_bm">
              <div class="contents_head">
                <h5 class="card-title">拠点</h5>
              </div>
              <form>
                <div class="row space align-center justify-content-between page group contents space_d">
                  <div class="col-lg-12">
                    <label class="col-form-label">拠点一覧</label>   
                    <div>
                      <fieldset class="radio_btn base_box">
                        <label class="base_radio" v-for="base in bases" :key="base.部門CD">
                          <input type="radio" name="radio_btn" :value="base.部門CD" v-model="selectedBase"/>
                          {{ base.部門名 }}
                        </label>
                      </fieldset>  
                    </div>                   
                   </div>
                  <div class="col-sp-12 btn_center ma_top_a line_up center">
                    <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a>
                    <button type="button" class="button_r none search od_a to" @click="handleSelect">選択</button>        
                  </div>
                </div>              
              </form>           
          </div>
        </div>
      </div>
    </section>
  </template>
  
<style>
.loading-wrap {
  position: fixed;
  top: 50%;
  left: 50%;
  width: 15vw;
  height: 15vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255,255,255,0.7);
  z-index: 2000;           /* 既存要素より上に置く */
  font-size: 1.5rem;
  transform: translate(-50%, -50%);
}
</style>