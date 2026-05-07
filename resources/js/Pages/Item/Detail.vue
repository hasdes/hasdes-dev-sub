<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

defineProps({
  authItems: Array
})

const items = ref([]);
const userId = ref(null);
const key = ref(null);
import { useRouter } from 'vue-router'; // Vue Routerのインポート
const router = useRouter(); 
// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key');
};
const hasContent = ref(false);
// 商品種別の数値を対応する文字列に変換する関数
const PRODUCT_TYPES = {
  1: '1:異形管',
  2: '2:バルブ',
  3: '3:筺類',
  4: '4:接合部分',
  5: '5:その他',
};
const getProductTypeName = (productType) => {
  return PRODUCT_TYPES[productType] || '不明';
};

// 商品種別に応じたクラスを返す関数
const getClassForDiv = (productType, divType) => {
  const typesToGrayClasses = {
    1: ['材質CD', '形式CD', '操作CD', 'セットCD'],
    2: ['セットCD'],
    3: ['材質CD', '形式CD', '操作CD', 'フランジCD'],
    4: ['材質CD', '形式CD', '操作CD', 'セットCD'],
    5: ['材質CD', '形式CD', '操作CD', 'セットCD', 'フランジCD', '都市CD']
  };
  return typesToGrayClasses[productType]?.includes(divType) ? 'gray' : '';
};

// 顧客情報を取得する関数
const reLoadItems = () => {
  key.value = getKeyFromUrl();
  if (key.value) {
    axios.get('/api/item/detail', {
    params: { key: key.value }
  })
  .then((res) => {
    console.log('APIレスポンス:', res.data);
    userId.value = res.data.userId;
    items.value = Array.isArray(res.data.data) ? res.data.data : [res.data.data];
    hasContent.value = res.data.hasContent;
    console.log('hasContent:', hasContent.value);
    console.log('品名CD:', items[0]?.品名CD);
  })
  .catch((error) => {
    console.error(error);
  });

  } else {
    console.error('URLにkeyパラメータがありません。');
  }
};

const goToDetail = (itemCode) => {
  router.push({ path: '/item/ContentsDetail', query: { key: itemCode } });
};

onMounted(() => {
  reLoadItems();
});
</script>

<template>
<section class="section dashboard">
      <div class="row">
        <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
          <li>情報表示</li>
          <li><router-link to="/item">商品表示</router-link></li>
          <li>商品詳細</li>
        </ol>
        <div class="col-lg-12">
          <div class="card ma_btm_bm">
              <div class="contents_head">
                <h5 class="card-title">商品詳細</h5>
              </div>
              <div class="row space align-center justify-content-between page stock space_d">
                <div class="col-md-6">
                  <label class="col-form-label">商品種別</label>   
                  <span>
                    {{ getProductTypeName(items[0]?.商品種別) }}
                  </span>
                </div>    
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '商品CD')">
                  <label class="col-form-label">商品CD</label>   
                  <span>{{ items[0]?.商品CD || 'データがありません' }}</span>
                </div>  
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '商品名')">
                  <label class="col-form-label">商品名</label>   
                  <span>{{ items[0]?.商品名_社内用 || 'データがありません' }}</span>
                </div>  
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '呼び径1')">
                  <label class="col-form-label">呼び径</label>   
                  <span>{{ items[0]?.呼び径1 || 'データがありません' }}</span>
                  <span>{{ items[0]?.呼び径2 || 'データがありません' }}</span>
                  <span>{{ items[0]?.呼び径3 || 'データがありません' }}</span>
                </div>
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '単重')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '単重')">単重</label>
                  <span>{{ items[0]?.単重 || 'データがありません' }}</span>
                </div>  
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '品名CD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '品名CD')">品名CD</label>   
                  <span>{{ items[0]?.品名CD || 'データがありません' }}</span>   
                </div> 
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '材質CD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '材質CD')">材質CD</label>   
                  <span>{{ items[0]?.材質CD || 'データがありません' }}</span>         
                </div> 
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '形式CD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '形式CD')">形式CD</label>   
                  <span>{{ items[0]?.形式CD || 'データがありません' }}</span>           
                </div> 
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '操作CD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '操作CD')">操作CD</label>   
                  <span>{{ items[0]?.操作CD || 'データがありません' }}</span>         
                </div> 
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, '塗装CD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '塗装CD')">塗装CD</label>   
                  <span>{{ items[0]?.塗装CD || 'データがありません' }}</span>        
                </div> 
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, 'フランジCD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, 'フランジCD')">フランジCD</label>   
                  <span>{{ items[0]?.フランジCD || 'データがありません' }}</span>     
                </div>
                <div class="col-md-6":class="getClassForDiv(items[0]?.商品種別, 'セットCD')">
                  <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, 'セットCD')">セットCD</label>   
                  <span>{{ items[0]?.セットCD || 'データがありません' }}</span>          
                </div> 
               
                <div class="col-md-6" :class="getClassForDiv(items[0]?.商品種別, '都市CD')">
                <label class="col-form-label":class="getClassForDiv(items[0]?.商品種別, '都市CD')">都市CD</label>   
                <span>{{ items[0]?.都市CD || 'データがありません' }}</span>           
                </div>

                <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                  <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a>
                  <button :disabled="!hasContent" @click.prevent="goToDetail(items[0]?.品名CD)" type="button" :class="{'button_r none search': hasContent}">
                    コンテンツ
                  </button>
                </div>      
              </div>
          </div>
        </div>
      </div>
    </section>
</template>
