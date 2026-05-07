<script setup>
// ** ポップアップ表示流れ ***************************************

// 親コンポーネントから open() 呼び出し
//        ↓
// Edit.vue の open() 関数が isVisible = true に変更
//        ↓
// テンプレート内の <div v-if="isVisible"> が true になり表示
//        ↓
// ポップアップが画面に表示される

// ***********************************************************

import { reactive, ref, computed, watch } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';//通知機能



// 配車設定（権限）をLayout.vueから受け取る
const props = defineProps({
  items: {
    type: Array, // items は配列で
    required: true // 絶対に親から渡してもらわないとダメ
  }
})

console.log("Edit.vueのitemsの中身:", props)  // 確認


//配車設定（権限）の取得
const dispatchDisplay = ref(0)
watch(() => props.items, (newItems) => {
  if (newItems && newItems.length > 0) {
    // const setting = newItems[0]["直送配車計画"]
    const setting = newItems[0]["配車可_不可"]
    dispatchDisplay.value = setting ?? 1
    console.log("dispatchDisplay:", dispatchDisplay.value)  // 確認
  }
}, { immediate: true })

const isDisabled = computed(() => dispatchDisplay.value !== 0);




// モーダル表示状態の管理
const isVisible = ref(false); // 初期状態は非表示

const item = reactive({
  D出荷予定_ID: null,
  工場部門CD: null,              // M所属-工場部門CD
  出荷予定日: '',              
  出荷予定数: 0,                // 出荷予定数（重量）
  出荷先CD: '',
  出荷先郵便番号: '',
  出荷先住所1: '',
  出荷先略名: '',
  営業担当者CD: null,           // M担当者-営業担当者CD
  得意先_CD: '',               // M得意先-得意先_CD
  積み合わせ番号: '',
  その他情報: '',               // その他情報(自由記入欄)
  地図リンク: '',
  重量: ''
})


//group初期値
const group = ref(null)

// モーダルを開く処理
const open = (shipmentGroup) => {
  group.value = shipmentGroup
  Object.assign(item, shipmentGroup.items[0])
  isVisible.value = true // ← これでポップアップが「表示」状態になる
}// モーダルを閉じる処理
const close = () => {
  isVisible.value = false;
};


//市町村区だけ抽出
const extractCityWard = (address) => {
  const match = address.match(/(?:都|道|府|県)([^市区町村]+市|[^市区町村]+区|[^市区町村]+町|[^市区町村]+村)/);
  return match ? match[1] : '';
}


//日付表示関数
function formatDate(dateStr) {
  if (!dateStr || dateStr.length !== 8) return dateStr;
  const year = dateStr.slice(0, 4);
  const month = parseInt(dateStr.slice(4, 6), 10);
  const day = parseInt(dateStr.slice(6, 8), 10);
  return `${year}年${month}月${day}日`;
}


//更新
const changeDispatch = () => {
    axios.put('/api/dispatch/edit', {
        D出荷予定_ID: item.D出荷予定_ID, // 識別IDを使って1件特定
        その他情報: item.その他情報,
        地図リンク: item.地図リンク,
        車両: item.車両,
        積み下ろし順: item.積み下ろし順    
    }).then((res) => {
        console.log('dispatch change success');
        ElNotification({
            title: 'Success',
            message: '出荷予定の更新に成功しました',
            type: 'success',
        });
        //ブラウザ更新
        setTimeout(() => {
          window.location.href = '/dispatch';
        }, 1000); 

    }).catch((error) => {
        console.log('error ' + error);
        ElNotification({
            title: 'Error',
            message: '出荷予定の更新に失敗しました',
            type: 'error',
        });
    });
};

// 外部から呼び出せる関数を定義
defineExpose({ open });
</script>



<template>
<!-- isVisible によって表示切り替え -->
  <div class="modal_wrap" id="kengen_modal" v-if="isVisible">
    <div class="modal_inner dis_modal_inner">
      <div class="contents_head dispop_head">
        <h5 class="card-title dis_card-title" v-if="group">
          {{ extractCityWard(item.出荷先住所1) }} &nbsp;
          {{ Math.ceil(group.items.reduce((sum, i) => sum + Number(i.重量), 0)) }}㌔
        </h5>      
        <p class="dispop_date">{{ formatDate(item.出荷予定日) }}</p>
      </div>
        <div class="row space align-center justify-content-between page c space_d dis_pop">    
          <div class="col-md-12 dis_form">
            <label class="col-form-label">積み合わせ番号</label>   
              <div class="dis_form">
                <input type="text" class="form-control normal dis_num" v-model="item.車両" maxlength="3" @input="item.車両 = item.車両.slice(0, 3)" :disabled="isDisabled">  
                <span class="dis_sen">ー</span>   
                <input type="text" class="form-control normal dis_num" v-model="item.積み下ろし順" maxlength="3" @input="item.積み下ろし順 = item.積み下ろし順.slice(0, 3)" :disabled="isDisabled">   
              </div>                  
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">得意先コード</label>   
            <span>{{ item.得意先CD }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">得意先名</label>   
            <span>{{ item.得意先名 }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">出荷先コード</label>   
            <span>{{ item.出荷先CD }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">出荷先名</label>   
            <span>{{ item.出荷先略名 }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">出荷先郵便番号</label>   
            <span>{{ item.出荷先郵便番号 }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">出荷先住所</label>   
            <!-- <span>{{ item.出荷先住所1 }}</span>   -->
             <span>
                <a 
                  :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.出荷先住所1)}`" 
                  target="_blank" 
                  rel="noopener noreferrer"
                >
                  {{ item.出荷先住所1 }}
                </a>
            </span>
          </div> 
          <div class="col-md-6">
          <label class="col-form-label">営業担当コード</label>   
          <span>{{ item.営業担当者CD }}</span>                     
        </div> 
          <div class="col-md-6">
            <label class="col-form-label">営業担当者名</label>   
            <span>{{ item.担当者名 }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">営業所コード</label>   
            <span>00{{ item.管轄部門CD }}</span>                     
          </div> 
          <div class="col-md-6">
            <label class="col-form-label">営業所名</label>   
            <span>{{ item.部門名 }}</span>                     
          </div> 
          <div class="col-md-12">
            <label class="col-form-label">地図リンク先</label>   
            <span>
              <a :href="item.地図リンク" target="_blank">           
                {{ item.地図リンク }}
              </a>            
            </span>                     
          </div> 
          <div class="col-md-12 dis_form">
            <label class="col-form-label">その他情報</label>   
            <span class="dis_form_w">
              <textarea class="form-control normal" style="height: 100px" v-model="item.その他情報" :disabled="isDisabled"></textarea>    
            </span>
          </div>                
          <div class="col-md-12 dis_form">
            <label class="col-form-label">地図リンク追加</label>   
            <span class="dis_form_w">
              <input type="text" class="form-control normal" v-model="item.地図リンク" :disabled="isDisabled">                      
            </span>
          </div> 
          <div class="col-sp-12 btn_center ma_top_a line_up center">
            <div class="button_r back none od_b close_icon disp_btn"  @click="close">戻る</div>
            <div class="button_r none search od_a disp_btn" @click.prevent="changeDispatch" v-if="!isDisabled">保存</div>        
          </div>
        </div>                                     
    </div>
  </div>  

</template>


<style scoped>
/* モーダル固定 */
 .modal_wrap {
  position: fixed;
  overflow-y: auto;
}
/* モーダルスクロール */
.modal_inner {
  max-height: 90vh;
  overflow-y: auto;
}
</style>