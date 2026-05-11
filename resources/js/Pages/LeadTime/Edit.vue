<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'; // ← 追加
const router = useRouter(); // ← ここで router を取得

defineProps({
  authItems: Array
})

const loadingActive = ref(false); // ローディング状態を管理する変数

const goToList = () => {
  router.push({ path: '/leadtime/'})
}
// const goToUpdate = () => {
//   router.push({ path: '/leadtime/update'})
// }

const factoryCd = ref('')
const factoryList = [
  { cd: '1', name: '本社' },
  { cd: '2', name: '九工' },
  { cd: '3', name: '東工' },
]
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
      <li>工程日数マスター</li>
    </ol>

    <button type="submit" class="button_r back none od_b" @click.prevent="goToList()">
      閉じる
    </button>
  </div>


  <div class="col-lg-6">
    <div class="card">
        <div class="contents_head">
          <h5 class="card-title">検索条件</h5>
        </div>

        <div class="row space align-center justify-content-between page group contents space_d">
          <!-- <div class="col-lg-12">
            <label class="col-form-label">工場CD</label>    
            <input type="text" class="form-control normal">                  
          </div> -->
          <div class="col-lg-12">
            <label class="col-form-label">工場CD</label>

            <input
              v-model="factoryCd"
              list="factory-list"
              class="form-control normal search-input"
              placeholder="工場CDを入力または選択"
            >

            <datalist id="factory-list">
              <option
                v-for="item in factoryList"
                :key="item.cd"
                :value="item.cd"
              >
                {{ item.name }}
              </option>
            </datalist>
          </div>
          <div class="col-lg-12 test">
            <label class="col-form-label">商品CD</label>    
            <input type="text" class="form-control normal search-input">                  
          </div>
          <div class="col-lg-12">
            <label class="col-form-label">呼び径1</label>    
            <input type="text" class="form-control normal search-input">                  
          </div>
          <div class="col-lg-12">
            <label class="col-form-label">呼び径2</label>    
            <input type="text" class="form-control normal search-input">                  
          </div>
          <div class="col-lg-12">
            <label class="col-form-label">呼び径3</label>    
            <input type="text" class="form-control normal search-input">                  
          </div>
          <div class="col-sp-12 btn_center ma_top_a line_up flex-end">
              <button type="submit" class="button_r none search od_a to max">表示</button>    
          </div>    
          
          </div>                
        </div>              
    </div>

    <!-- <div class="col-lg-12"> -->
    <div class="col-lg-6">
      <div class="card">
          <div class="row space align-center justify-content-between page group contents space_d">
            <div class="col-lg-6">
              <label class="col-form-label">ロット数量</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">溶射</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">GS造形</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">グリッド溶射フラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">NB造形</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">内外塗装</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">LG造形</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">組付完成検査</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">外注造形</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">素材あり納期</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">GSフラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">素材なし納期</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">NBフラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">移送本社-九工</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">LGフラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">移送本社-東工</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">外注フラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">移送九工-東工</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">ショット＆研掃</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">問合数量上限</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">素管検査</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">ｾﾞﾛ素材納期</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">修正</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">繁忙加算</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">水圧</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">予備項目1</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">加工1</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">予備項目2</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">加工2</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">予備項目3</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6">
              <label class="col-form-label">加工2フラグ</label>    
              <input type="text" class="form-control normal">                  
            </div>
            <div class="col-lg-6"></div>
            <div class="col-lg-6">
              <label class="col-form-label">グリッド原管</label>    
              <input class="form-control normal" disabled>                  
            </div>
            <div class="col-lg-6"></div>
            <div class="radio_f">
              <div class="col-sp-6 btn_center ma_top_a line_up left">
                  <button type="submit" class="button_r none search od_a to max">変更</button>   
              </div>     
              <div class="col-sp-6 btn_center ma_top_a line_up left">
                  <button type="submit" class="button_r back none od_b max">キャンセル</button>    
              </div>     
            </div>

          <!-- <div class="col-sp-12 btn_center ma_top_a line_up max" style="justify-content: flex-start;">
              <button type="submit" class="button_r none search cont max" @click.prevent="goToUpdate()">工程日数一括変更</button>    
          </div>      -->

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
.flex-end {
  justify-content: flex-end;
}
.form-control.normal:disabled {
  background:#D6D6D6;
}
@media (min-width: 1024px) {
  .left {
    margin-left: auto;
  }
}
@media (max-width: 1023px) {
    .button_r.max {
        width: 100%;
    }
    .btn_center {
        flex-direction: unset;
    }
}


@media (min-width: 1000px) {
  .search-input {
    width: 200px !important;
    flex: unset !important;
  }
}
</style>
